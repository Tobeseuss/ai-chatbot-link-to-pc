<?php
/**
 * مدیریت تنظیمات پلاگین.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Settings {

        const OPTION = 'aclp_settings';

        /**
         * مقادیر پیش‌فرض تنظیمات.
         *
         * @return array
         */
        public static function defaults() {
                return array(
                        'poll_interval'             => 5,     // فاصله پیشنهادی polling ایجنت (ثانیه).
                        'online_timeout'            => 90,    // اگر کلاینت در این مدت خبری نداد، آفلاین محسوب شود (ثانیه).
                        'command_timeout'           => 300,   // تایم‌اوت پیش‌فرض اجرای دستور در ایجنت (ثانیه).
                        'history_retention_days'    => 30,    // مدت نگهداری تاریخچه فرمان‌ها و لاگ‌ها (روز).
                        'file_retention_days'       => 7,     // مدت نگهداری فایل‌های منتقل‌شده (روز).
                        'max_log_entries'           => 50000, // حداکثر ردیف‌های جدول لاگ.
                        'max_commands_rows'         => 100000,// حداکثر ردیف‌های جدول فرمان‌ها.
                        'max_file_size_mb'          => 256,   // حداکثر حجم هر فایل قابل آپلود (مگابایت).
                        'rate_limit_per_min'        => 240,   // حداکثر درخواست در دقیقه برای هر کلید.
                        'max_pending_per_client'    => 50,    // حداکثر فرمان در صف هر کلاینت.
                        'delete_data_on_uninstall'  => 0,     // هنگام حذف پلاگین، همه داده‌ها پاک شود؟
                        // --- یکپارچگی گیت‌هاب (برای ایجنت‌های توسعه‌دهنده — اصل ۳ و ۴) ---
                        'github_repo_url'           => 'https://github.com/Tobeseuss/ai-chatbot-link-to-pc', // ریپوی رسمی پروژه.
                        'github_pat'                => '',    // PAT کاربر (باید دسترسی کامل read/write داشته باشد) تا ایجنت‌های هوش مصنوعی بتوانند نسخه جدید منتشر کنند.
                );
        }

        /**
         * کلیدهای متنی تنظیمات (به‌جز کلیدهای عددی).
         *
         * @return array
         */
        public static function text_keys() {
                return array( 'github_repo_url', 'github_pat' );
        }

        /**
         * دریافت همه تنظیمات (با ادغام پیش‌فرض‌ها).
         *
         * @return array
         */
        public static function all() {
                $saved = get_option( self::OPTION, array() );
                if ( ! is_array( $saved ) ) {
                        $saved = array();
                }
                return wp_parse_args( $saved, self::defaults() );
        }

        /**
         * دریافت یک تنظیم خاص.
         *
         * @param string $key کلید.
         * @param mixed  $default مقدار پیش‌فرض جایگزین.
         * @return mixed
         */
        public static function get( $key, $default = null ) {
                $all = self::all();
                return isset( $all[ $key ] ) ? $all[ $key ] : $default;
        }

        /**
         * ذخیره تنظیمات (فقط کلیدهای مجاز).
         *
         * @param array $settings تنظیمات جدید.
         */
        public static function update( array $settings ) {
                $numeric = array_diff( array_keys( self::defaults() ), self::text_keys() );
                $clean   = array();
                foreach ( $numeric as $k ) {
                        if ( array_key_exists( $k, $settings ) ) {
                                $clean[ $k ] = ( 'delete_data_on_uninstall' === $k ) ? (int) ! empty( $settings[ $k ] ) : max( 0, (int) $settings[ $k ] );
                        }
                }
                foreach ( self::text_keys() as $k ) {
                        if ( array_key_exists( $k, $settings ) ) {
                                $clean[ $k ] = trim( (string) $settings[ $k ] );
                        }
                }
                update_option( self::OPTION, array_merge( self::all(), $clean ) );
        }
}
