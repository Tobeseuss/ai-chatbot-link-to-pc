<?php
/**
 * مدیریت فرمان‌ها و تاریخچه تعاملات.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Commands {

        /**
         * ساخت فرمان جدید در صف.
         *
         * @param int    $key_id    شناسه کلید.
         * @param int    $client_id شناسه کلاینت.
         * @param string $type      نوع فرمان.
         * @param mixed  $payload   بار داده (آرایه/شیء).
         * @param string $source    منبع (نام چت‌بات).
         * @param string $group_uid شناسه گروه (برای broadcast).
         * @return object|null ردیف فرمان.
         */
        public static function create( $key_id, $client_id, $type, $payload, $source = '', $group_uid = '' ) {
                global $wpdb;
                $uid = ACLP_Utils::uid();
                $wpdb->insert(
                        $wpdb->prefix . 'aclp_commands',
                        array(
                                'command_uid' => $uid,
                                'group_uid'   => $group_uid,
                                'key_id'      => (int) $key_id,
                                'client_id'   => (int) $client_id,
                                'type'        => substr( sanitize_key( $type ), 0, 50 ),
                                'payload'     => wp_json_encode( $payload ),
                                'source'      => sanitize_text_field( substr( (string) $source, 0, 190 ) ),
                                'status'      => 'pending',
                                'created_at'  => ACLP_Utils::now(),
                        )
                );
                return $wpdb->insert_id ? self::get( $wpdb->insert_id ) : null;
        }

        /**
         * دریافت فرمان با شناسه عددی.
         *
         * @param int $id شناسه.
         * @return object|null
         */
        public static function get( $id ) {
                global $wpdb;
                return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_commands WHERE id = %d", (int) $id ) );
        }

        /**
         * دریافت فرمان با UID.
         *
         * @param string $uid شناسه یکتا.
         * @return object|null
         */
        public static function get_by_uid( $uid ) {
                global $wpdb;
                return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_commands WHERE command_uid = %s", substr( (string) $uid, 0, 64 ) ) );
        }

        /**
         * فرمان‌های در انتظار یک کلاینت.
         *
         * @param int $client_id شناسه کلاینت.
         * @param int $limit     حداکثر تعداد.
         * @return array
         */
        public static function pending_for_client( $client_id, $limit = 10 ) {
                global $wpdb;
                $limit = max( 1, min( 20, (int) $limit ) );
                return $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}aclp_commands
                                 WHERE client_id = %d AND status = 'pending'
                                 ORDER BY id ASC LIMIT %d",
                                (int) $client_id,
                                $limit
                        )
                );
        }

        /**
         * تعداد فرمان‌های در انتظار کلاینت.
         *
         * @param int $client_id شناسه کلاینت.
         * @return int
         */
        public static function pending_count( $client_id ) {
                global $wpdb;
                return (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aclp_commands WHERE client_id = %d AND status = 'pending'", (int) $client_id )
                );
        }

        /**
         * علامت‌گذاری فرمان به عنوان ارسال‌شده.
         *
         * @param object $row ردیف فرمان.
         */
        public static function mark_sent( $row ) {
                global $wpdb;
                $wpdb->update(
                        $wpdb->prefix . 'aclp_commands',
                        array( 'status' => 'sent', 'sent_at' => ACLP_Utils::now() ),
                        array( 'id' => (int) $row->id ),
                        array( '%s', '%s' ),
                        array( '%d' )
                );
        }

        /**
         * ثبت وضعیت «در حال اجرا».
         *
         * @param string $uid شناسه یکتای فرمان.
         * @param int    $client_id شناسه کلاینت (برای کنترل مالکیت).
         * @return bool
         */
        public static function mark_running( $uid, $client_id ) {
                global $wpdb;
                return (bool) $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}aclp_commands
                                 SET status = 'running', started_at = %s
                                 WHERE command_uid = %s AND client_id = %d AND status IN ('pending','sent')",
                                ACLP_Utils::now(),
                                substr( (string) $uid, 0, 64 ),
                                (int) $client_id
                        )
                );
        }

        /**
         * ثبت نتیجه فرمان.
         *
         * @param string $uid        شناسه یکتا.
         * @param int    $client_id  شناسه کلاینت.
         * @param string $status     وضعیت نهایی (completed/failed).
         * @param mixed  $result     نتیجه.
         * @param string $error      پیام خطا.
         * @param int    $duration_ms مدت اجرا (میلی‌ثانیه).
         * @return bool
         */
        public static function set_result( $uid, $client_id, $status, $result, $error = '', $duration_ms = 0 ) {
                global $wpdb;
                $status = in_array( $status, array( 'completed', 'failed' ), true ) ? $status : 'completed';

                $ok = (bool) $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}aclp_commands
                                 SET status = %s, result = %s, error = %s, duration_ms = %d, completed_at = %s
                                 WHERE command_uid = %s AND client_id = %d AND status IN ('pending','sent','running')",
                                $status,
                                wp_json_encode( $result ),
                                sanitize_textarea_field( substr( (string) $error, 0, 5000 ) ),
                                max( 0, (int) $duration_ms ),
                                ACLP_Utils::now(),
                                substr( (string) $uid, 0, 64 ),
                                (int) $client_id
                        )
                );

                if ( $ok ) {
                        $cmd = self::get_by_uid( $uid );
                        if ( $cmd ) {
                                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}aclp_clients SET commands_total = commands_total + 1 WHERE id = %d", (int) $cmd->client_id ) );
                                ACLP_Logger::add(
                                        'command_' . $status,
                                        'نتیجه فرمان ثبت شد: ' . $cmd->type,
                                        array( 'status' => $status, 'duration_ms' => (int) $duration_ms ),
                                        (int) $cmd->key_id,
                                        (int) $cmd->client_id,
                                        (int) $cmd->id
                                );
                        }
                }
                return $ok;
        }

        /**
         * کوئری پیشرفته برای پنل مدیریت.
         *
         * @param array $args فیلترها (key_id, client_id, status, type, search, paged, per_page).
         * @return array{rows: array, total: int}
         */
        public static function query( array $args = array() ) {
                global $wpdb;
                $t    = $wpdb->prefix . 'aclp_commands';
                $where = array( '1=1' );
                $params = array();

                if ( ! empty( $args['key_id'] ) ) {
                        $where[]  = 'c.key_id = %d';
                        $params[] = (int) $args['key_id'];
                }
                if ( ! empty( $args['client_id'] ) ) {
                        $where[]  = 'c.client_id = %d';
                        $params[] = (int) $args['client_id'];
                }
                if ( ! empty( $args['status'] ) ) {
                        $where[]  = 'c.status = %s';
                        $params[] = sanitize_key( $args['status'] );
                }
                if ( ! empty( $args['type'] ) ) {
                        $where[]  = 'c.type = %s';
                        $params[] = sanitize_key( $args['type'] );
                }
                if ( ! empty( $args['search'] ) ) {
                        $s        = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
                        $where[]  = '(c.command_uid LIKE %s OR c.payload LIKE %s OR c.result LIKE %s OR c.source LIKE %s)';
                        array_push( $params, $s, $s, $s, $s );
                }

                $where_sql = implode( ' AND ', $where );
                $total     = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$t} c WHERE {$where_sql}", $params )
                );

                $per_page = ! empty( $args['per_page'] ) ? max( 5, min( 100, (int) $args['per_page'] ) ) : 25;
                $paged    = ! empty( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
                $offset   = ( $paged - 1 ) * $per_page;

                $rows = $wpdb->get_results(
                        $wpdb->prepare(
                                "SELECT c.*, cl.name AS client_name, cl.hostname AS client_hostname, k.name AS key_name
                                 FROM {$t} c
                                 LEFT JOIN {$wpdb->prefix}aclp_clients cl ON cl.id = c.client_id
                                 LEFT JOIN {$wpdb->prefix}aclp_api_keys k ON k.id = c.key_id
                                 WHERE {$where_sql}
                                 ORDER BY c.id DESC LIMIT %d OFFSET %d",
                                array_merge( $params, array( $per_page, $offset ) )
                        )
                );

                return array( 'rows' => $rows, 'total' => $total, 'per_page' => $per_page, 'paged' => $paged );
        }

        /**
         * آمار داشبورد.
         *
         * @return array
         */
        public static function stats() {
                global $wpdb;
                $t   = $wpdb->prefix . 'aclp_commands';
                $day = gmdate( 'Y-m-d 00:00:00' );
                return array(
                        'today'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $day ) ),
                        'pending'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'pending'" ),
                        'running'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status IN ('sent','running')" ),
                        'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'completed'" ),
                        'failed'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'failed'" ),
                        'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ),
                );
        }
}
