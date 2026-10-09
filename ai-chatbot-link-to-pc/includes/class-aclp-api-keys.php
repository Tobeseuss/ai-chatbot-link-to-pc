<?php
/**
 * مدیریت کلیدهای API.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_API_Keys {

        /**
         * ساخت کلید جدید. مقدار متنی کلید در دیتابیس هم ذخیره می‌شود تا کاربر
         * بعداً بتواند آن را در پنل ببیند و کپی کند (درخواست مالک پروژه).
         *
         * @param string $name        نام کلید.
         * @param int    $max_clients حداکثر کلاینت (۰ = نامحدود).
         * @param string $notes       یادداشت.
         * @return array{row: object, plain: string}|WP_Error
         */
        public static function create( $name, $max_clients = 0, $notes = '' ) {
                global $wpdb;

                $name = sanitize_text_field( $name );
                if ( '' === $name ) {
                        return new WP_Error( 'aclp_invalid_name', 'نام کلید الزامی است.' );
                }

                $plain = 'aclp_live_' . bin2hex( random_bytes( 24 ) );
                $hash  = hash( 'sha256', $plain );

                $wpdb->insert(
                        $wpdb->prefix . 'aclp_api_keys',
                        array(
                                'name'       => $name,
                                'key_hash'   => $hash,
                                'key_prefix' => substr( $plain, 0, 16 ) . '…',
                                'key_plain'  => $plain,
                                'is_active'  => 1,
                                'max_clients' => max( 0, (int) $max_clients ),
                                'notes'      => sanitize_textarea_field( $notes ),
                                'created_at' => ACLP_Utils::now(),
                        ),
                        array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
                );

                if ( $wpdb->insert_id ) {
                        ACLP_Logger::add( 'key_created', 'کلید جدید ساخته شد: ' . $name, array( 'key_id' => $wpdb->insert_id ) );
                        return array(
                                'row'   => self::get( $wpdb->insert_id ),
                                'plain' => $plain,
                        );
                }
                return new WP_Error( 'aclp_db_error', 'خطا در ذخیره کلید.' );
        }

        /**
         * یافتن کلید بر اساس مقدار متنی (با هش).
         *
         * @param string $plain مقدار متنی کلید.
         * @return object|null
         */
        public static function get_by_plain( $plain ) {
                global $wpdb;
                if ( ! is_string( $plain ) || strlen( $plain ) < 20 ) {
                        return null;
                }
                $hash = hash( 'sha256', trim( $plain ) );
                return $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_api_keys WHERE key_hash = %s LIMIT 1", $hash )
                );
        }

        /**
         * دریافت یک کلید.
         *
         * @param int $id شناسه.
         * @return object|null
         */
        public static function get( $id ) {
                global $wpdb;
                return $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_api_keys WHERE id = %d", (int) $id )
                );
        }

        /**
         * فهرست همه کلیدها.
         *
         * @return array
         */
        public static function all() {
                global $wpdb;
                return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}aclp_api_keys ORDER BY id DESC" );
        }

        /**
         * فعال/غیرفعال کردن کلید.
         *
         * @param int  $id     شناسه.
         * @param bool $active وضعیت.
         * @return bool
         */
        public static function set_active( $id, $active ) {
                global $wpdb;
                return (bool) $wpdb->update(
                        $wpdb->prefix . 'aclp_api_keys',
                        array( 'is_active' => $active ? 1 : 0 ),
                        array( 'id' => (int) $id ),
                        array( '%d' ),
                        array( '%d' )
                );
        }

        /**
         * حذف کلید به همراه کلاینت‌ها و فرمان‌های در انتظار آن.
         *
         * @param int $id شناسه.
         * @return bool
         */
        public static function delete( $id ) {
                global $wpdb;
                $id = (int) $id;

                // حذف فرمان‌های در صف کلاینت‌های این کلید.
                $wpdb->query(
                        $wpdb->prepare(
                                "DELETE c FROM {$wpdb->prefix}aclp_commands c
                                 INNER JOIN {$wpdb->prefix}aclp_clients cl ON cl.id = c.client_id
                                 WHERE cl.key_id = %d AND c.status IN ('pending','sent')",
                                $id
                        )
                );
                $wpdb->delete( $wpdb->prefix . 'aclp_clients', array( 'key_id' => $id ), array( '%d' ) );
                $ok = (bool) $wpdb->delete( $wpdb->prefix . 'aclp_api_keys', array( 'id' => $id ), array( '%d' ) );
                if ( $ok ) {
                        ACLP_Logger::add( 'key_deleted', 'کلید حذف شد', array( 'key_id' => $id ) );
                }
                return $ok;
        }

        /**
         * ثبت زمان آخرین استفاده (حداکثر هر ۶۰ ثانیه یک‌بار برای کاهش نوشتن).
         *
         * @param int $id شناسه کلید.
         */
        public static function touch( $id ) {
                global $wpdb;
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}aclp_api_keys
                                 SET last_used_at = %s
                                 WHERE id = %d AND (last_used_at IS NULL OR last_used_at < DATE_SUB(%s, INTERVAL 60 SECOND))",
                                ACLP_Utils::now(),
                                (int) $id,
                                ACLP_Utils::now()
                        )
                );
        }

        /**
         * آمار استفاده یک کلید: فرمان‌های ارسالی، نتایج دریافتی، فایل‌های هر دو جهت
         * و اینکه آیا استفاده دوطرفه شروع شده یا نه (نمایش در صفحه جزئیات کلید).
         *
         * @param int $key_id شناسه کلید.
         * @return array{commands:int,results:int,files_from_pc:int,files_to_pc:int,bidirectional:string,last_activity:string,sources:array}
         */
        public static function usage_stats( $key_id ) {
                global $wpdb;
                $key_id = (int) $key_id;

                $commands = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aclp_commands WHERE key_id = %d", $key_id )
                );
                $results = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aclp_commands WHERE key_id = %d AND status IN ('completed','failed')", $key_id )
                );
                $files_from_pc = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aclp_files WHERE key_id = %d AND direction = 'from_pc'", $key_id )
                );
                $files_to_pc = (int) $wpdb->get_var(
                        $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}aclp_files WHERE key_id = %d AND direction = 'to_pc'", $key_id )
                );
                $last_activity = (string) $wpdb->get_var(
                        $wpdb->prepare( "SELECT MAX(created_at) FROM {$wpdb->prefix}aclp_commands WHERE key_id = %d", $key_id )
                );
                $sources = $wpdb->get_col(
                        $wpdb->prepare( "SELECT DISTINCT source FROM {$wpdb->prefix}aclp_commands WHERE key_id = %d AND source <> '' LIMIT 20", $key_id )
                );

                // سطح استفاده دوطرفه:
                //   full    = فرمان ارسال شده + نتیجه برگشته + فایل در هر دو جهت رد و بدل شده
                //   partial = فرمان و نتیجه دوطرفه بوده ولی هنوز فایلی رد و بدل نشده
                //   one_way = فقط فرمان ارسال شده
                //   none    = هنوز هیچ استفاده‌ای نشده
                if ( $commands > 0 && $results > 0 && $files_from_pc > 0 && $files_to_pc > 0 ) {
                        $bidirectional = 'full';
                } elseif ( $commands > 0 && $results > 0 && ( $files_from_pc > 0 || $files_to_pc > 0 ) ) {
                        $bidirectional = 'partial';
                } elseif ( $commands > 0 && $results > 0 ) {
                        $bidirectional = 'partial';
                } elseif ( $commands > 0 ) {
                        $bidirectional = 'one_way';
                } else {
                        $bidirectional = 'none';
                }

                return array(
                        'commands'       => $commands,
                        'results'        => $results,
                        'files_from_pc'  => $files_from_pc,
                        'files_to_pc'    => $files_to_pc,
                        'bidirectional'  => $bidirectional,
                        'last_activity'  => $last_activity,
                        'sources'        => (array) $sources,
                );
        }

        /**
         * برچسب فارسی سطح استفاده دوطرفه.
         *
         * @param string $level سطح.
         * @return string
         */
        public static function bidirectional_label( $level ) {
                $map = array(
                        'full'    => 'دوطرفه کامل (فرمان + نتیجه + فایل)',
                        'partial' => 'دوطرفه (فرمان و نتیجه)',
                        'one_way' => 'یک‌طرفه (فقط فرمان ارسالی)',
                        'none'    => 'هنوز استفاده نشده',
                );
                return isset( $map[ $level ] ) ? $map[ $level ] : $level;
        }
}
