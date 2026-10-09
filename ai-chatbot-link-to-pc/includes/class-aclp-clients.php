<?php
/**
 * مدیریت کلاینت‌ها (سیستم‌های متصل به یک کلید).
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Clients {

        /**
         * ثبت/به‌روزرسانی کلاینت.
         *
         * @param int   $key_id شناسه کلید.
         * @param array $data   داده‌های کلاینت (client_uid, name, os, ...).
         * @return array{client: object, created: bool}|WP_Error
         */
        public static function register( $key_id, array $data ) {
                global $wpdb;
                $table = $wpdb->prefix . 'aclp_clients';

                $uid = isset( $data['client_uid'] ) ? substr( preg_replace( '/[^A-Za-z0-9\-_]/', '', (string) $data['client_uid'] ), 0, 64 ) : '';
                if ( '' === $uid ) {
                        return new WP_Error( 'aclp_invalid_client_uid', 'client_uid نامعتبر است.' );
                }

                $key = ACLP_API_Keys::get( $key_id );
                if ( ! $key || ! $key->is_active ) {
                        return new WP_Error( 'aclp_key_inactive', 'کلید API فعال نیست.' );
                }

                $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE client_uid = %s", $uid ) );

                if ( $existing && (int) $existing->key_id !== (int) $key_id ) {
                        return new WP_Error( 'aclp_uid_conflict', 'این client_uid از قبل برای کلید دیگری ثبت شده است.' );
                }

                $fields = array(
                        'key_id'         => (int) $key_id,
                        'name'           => isset( $data['name'] ) ? sanitize_text_field( substr( $data['name'], 0, 190 ) ) : '',
                        'os'             => isset( $data['os'] ) ? sanitize_text_field( substr( $data['os'], 0, 50 ) ) : '',
                        'os_version'     => isset( $data['os_version'] ) ? sanitize_text_field( substr( $data['os_version'], 0, 120 ) ) : '',
                        'hostname'       => isset( $data['hostname'] ) ? sanitize_text_field( substr( $data['hostname'], 0, 190 ) ) : '',
                        'ip'             => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
                        'python_version' => isset( $data['python_version'] ) ? sanitize_text_field( substr( $data['python_version'], 0, 50 ) ) : '',
                        'agent_version'  => isset( $data['agent_version'] ) ? sanitize_text_field( substr( $data['agent_version'], 0, 20 ) ) : '',
                        'ai_model'       => isset( $data['ai_model'] ) ? sanitize_text_field( substr( $data['ai_model'], 0, 190 ) ) : '',
                        'capabilities'   => wp_json_encode( isset( $data['capabilities'] ) && is_array( $data['capabilities'] ) ? array_slice( array_map( 'sanitize_text_field', $data['capabilities'] ), 0, 100 ) : array() ),
                        'status'         => 'online',
                        'last_seen_at'   => ACLP_Utils::now(),
                );

                if ( $existing ) {
                        $wpdb->update( $table, $fields, array( 'id' => $existing->id ) );
                        $client = self::get( $existing->id );
                        ACLP_Logger::add( 'client_updated', 'کلاینت بروزرسانی شد: ' . $client->name, array( 'client_id' => $client->id ), (int) $key_id, $client->id );
                        return array( 'client' => $client, 'created' => false );
                }

                // بررسی سقف تعداد کلاینت‌های مجاز برای این کلید.
                $max = (int) $key->max_clients;
                if ( $max > 0 ) {
                        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE key_id = %d", $key_id ) );
                        if ( $count >= $max ) {
                                return new WP_Error( 'aclp_max_clients', sprintf( 'سقف تعداد سیستم‌های این کلید (%d) پر شده است.', $max ) );
                        }
                }

                $fields['registered_at'] = ACLP_Utils::now();
                $wpdb->insert( $table, $fields );
                $client = self::get( $wpdb->insert_id );
                ACLP_Logger::add( 'client_registered', 'سیستم جدید متصل شد: ' . $client->name, array( 'client_id' => $client->id, 'os' => $client->os ), (int) $key_id, $client->id );
                return array( 'client' => $client, 'created' => true );
        }

        /**
         * دریافت کلاینت با شناسه.
         *
         * @param int $id شناسه.
         * @return object|null
         */
        public static function get( $id ) {
                global $wpdb;
                return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_clients WHERE id = %d", (int) $id ) );
        }

        /**
         * دریافت کلاینت با UID.
         *
         * @param string $uid شناسه یکتای کلاینت.
         * @return object|null
         */
        public static function get_by_uid( $uid ) {
                global $wpdb;
                return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_clients WHERE client_uid = %s", substr( (string) $uid, 0, 64 ) ) );
        }

        /**
         * کلاینت‌های یک کلید.
         *
         * @param int $key_id شناسه کلید.
         * @return array
         */
        public static function for_key( $key_id ) {
                global $wpdb;
                return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_clients WHERE key_id = %d ORDER BY last_seen_at DESC", (int) $key_id ) );
        }

        /**
         * همه کلاینت‌ها (برای پنل مدیریت).
         *
         * @return array
         */
        public static function all() {
                global $wpdb;
                return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}aclp_clients ORDER BY last_seen_at DESC" );
        }

        /**
         * ثبت حضور کلاینت (آنلاین شدن).
         *
         * @param int $id شناسه کلاینت.
         */
        public static function touch_online( $id ) {
                global $wpdb;
                $wpdb->update(
                        $wpdb->prefix . 'aclp_clients',
                        array( 'status' => 'online', 'last_seen_at' => ACLP_Utils::now() ),
                        array( 'id' => (int) $id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                );
        }

        /**
         * حذف کلاینت و فرمان‌های در انتظارش.
         *
         * @param int $id شناسه.
         * @return bool
         */
        public static function delete( $id ) {
                global $wpdb;
                $id = (int) $id;
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_commands WHERE client_id = %d AND status IN ('pending','sent')", $id ) );
                $ok = (bool) $wpdb->delete( $wpdb->prefix . 'aclp_clients', array( 'id' => $id ), array( '%d' ) );
                if ( $ok ) {
                        ACLP_Logger::add( 'client_deleted', 'کلاینت حذف شد', array( 'client_id' => $id ) );
                }
                return $ok;
        }
}
