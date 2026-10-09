<?php
/**
 * نگهداری دوره‌ای: پاک‌سازی تاریخچه، فایل‌ها و وضعیت کلاینت‌ها.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Cron {

        /**
         * غیرفعال‌سازی پلاگین: حذف زمان‌بندی.
         */
        public static function deactivate() {
                wp_clear_scheduled_hook( 'aclp_hourly_maintenance' );
        }

        /**
         * اجرای نگهداری ساعتی.
         */
        public static function run() {
                global $wpdb;

                $retention_days = (int) ACLP_Settings::get( 'history_retention_days', 30 );
                $file_days      = (int) ACLP_Settings::get( 'file_retention_days', 7 );
                $max_logs       = (int) ACLP_Settings::get( 'max_log_entries', 50000 );
                $max_commands   = (int) ACLP_Settings::get( 'max_commands_rows', 100000 );
                $online_timeout = (int) ACLP_Settings::get( 'online_timeout', 90 );

                // ۱) علامت‌گذاری کلاینت‌های خاموش.
                $wpdb->query(
                        $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}aclp_clients
                                 SET status = 'offline'
                                 WHERE status = 'online' AND (last_seen_at IS NULL OR last_seen_at < %s)",
                                gmdate( 'Y-m-d H:i:s', time() - $online_timeout )
                        )
                );

                // ۲) حذف فرمان‌های قدیمی‌تر از مهلت نگهداری.
                if ( $retention_days > 0 ) {
                        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_commands WHERE created_at < %s", $cutoff ) );
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_logs WHERE created_at < %s", $cutoff ) );
                        // پیام‌های گفتگو هم مشمول همان مهلت نگهداری هستند (v1.3.0).
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_chat_messages WHERE created_at < %s", $cutoff ) );
                }

                // ۳) حذف فایل‌های منقضی (از دیسک و دیتابیس).
                if ( $file_days > 0 ) {
                        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $file_days * DAY_IN_SECONDS );
                        $old    = $wpdb->get_results( $wpdb->prepare( "SELECT id, stored_path FROM {$wpdb->prefix}aclp_files WHERE created_at < %s LIMIT 500", $cutoff ) );
                        foreach ( (array) $old as $f ) {
                                if ( ! empty( $f->stored_path ) && file_exists( $f->stored_path ) ) {
                                        @unlink( $f->stored_path );
                                }
                                $wpdb->delete( $wpdb->prefix . 'aclp_files', array( 'id' => (int) $f->id ), array( '%d' ) );
                        }
                }

                // ۴) اعمال سقف ردیف‌ها.
                if ( $max_logs > 0 ) {
                        $wpdb->query(
                                "DELETE l FROM {$wpdb->prefix}aclp_logs l
                                 LEFT JOIN (SELECT id FROM {$wpdb->prefix}aclp_logs ORDER BY id DESC LIMIT {$max_logs}) keep ON keep.id = l.id
                                 WHERE keep.id IS NULL"
                        );
                }
                if ( $max_commands > 0 ) {
                        $wpdb->query(
                                "DELETE c FROM {$wpdb->prefix}aclp_commands c
                                 LEFT JOIN (SELECT id FROM {$wpdb->prefix}aclp_commands ORDER BY id DESC LIMIT {$max_commands}) keep ON keep.id = c.id
                                 WHERE keep.id IS NULL"
                        );
                }
                $max_chat = (int) ACLP_Settings::get( 'max_chat_rows', 50000 );
                if ( $max_chat > 0 ) {
                        $wpdb->query(
                                "DELETE m FROM {$wpdb->prefix}aclp_chat_messages m
                                 LEFT JOIN (SELECT id FROM {$wpdb->prefix}aclp_chat_messages ORDER BY id DESC LIMIT {$max_chat}) keep ON keep.id = m.id
                                 WHERE keep.id IS NULL"
                        );
                }

                ACLP_Logger::add( 'maintenance', 'نگهداری دوره‌ای اجرا شد' );
        }
}
