<?php
/**
 * فعال‌سازی پلاگین: ساخت جداول دیتابیس و زمان‌بندی‌ها.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class ACLP_Activator {

        /**
         * اجرای فرآیند فعال‌سازی.
         */
        public static function activate() {
                self::create_tables();

                // ذخیره تنظیمات پیش‌فرض اگر وجود ندارد.
                if ( false === get_option( ACLP_Settings::OPTION, false ) ) {
                        update_option( ACLP_Settings::OPTION, ACLP_Settings::defaults() );
                }

                // زمان‌بندی نگهداری ساعتی.
                if ( ! wp_next_scheduled( 'aclp_hourly_maintenance' ) ) {
                        wp_schedule_event( time() + 3600, 'hourly', 'aclp_hourly_maintenance' );
                }

                update_option( 'aclp_version', ACLP_VERSION );
                update_option( 'aclp_activated_at', ACLP_Utils::now() );
        }

        /**
         * ارتقای ساختار دیتابیس پس از بروزرسانی پلاگین (بدون نیاز به غیرفعال/فعال).
         */
        public static function maybe_upgrade() {
                $installed = get_option( 'aclp_version', '' );
                if ( $installed === ACLP_VERSION ) {
                        return;
                }
                self::create_tables();
                self::add_missing_columns();
                update_option( 'aclp_version', ACLP_VERSION );
        }

        /**
         * افزودن ستون‌های جدید به جداول نسخه‌های قبلی (مطمئن‌تر از dbDelta).
         */
        public static function add_missing_columns() {
                global $wpdb;

                // ستون key_plain: نمایش دوباره کلید API در پنل (درخواست مالک — کلیدها مخفی نباشند).
                $columns_keys = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}aclp_api_keys", 0 );
                if ( $columns_keys && ! in_array( 'key_plain', $columns_keys, true ) ) {
                        $wpdb->query( "ALTER TABLE {$wpdb->prefix}aclp_api_keys ADD COLUMN key_plain VARCHAR(255) NOT NULL DEFAULT '' AFTER key_prefix" );
                }

                // ستون ai_model: مشخصات هوش مصنوعی/چت‌بات کنترل‌کننده هر سیستم.
                $columns_clients = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}aclp_clients", 0 );
                if ( $columns_clients && ! in_array( 'ai_model', $columns_clients, true ) ) {
                        $wpdb->query( "ALTER TABLE {$wpdb->prefix}aclp_clients ADD COLUMN ai_model VARCHAR(190) NOT NULL DEFAULT '' AFTER agent_version" );
                }

                // BUGFIX v1.3.0: کلاینت‌های نسخه‌های قبلی با client_uid خالی ثبت شده بودند
                // (باگ ذخیره‌سازی) — این ردیف‌ها بی‌فایده‌اند و احراز هویت را می‌شکنند؛ پاک می‌شوند.
                if ( $columns_clients ) {
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}aclp_clients WHERE client_uid = '' OR client_uid IS NULL" );
                }
        }

        /**
         * ساخت جداول اختصاصی پلاگین.
         */
        public static function create_tables() {
                global $wpdb;
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $charset = $wpdb->get_charset_collate();
                $p       = $wpdb->prefix;
                $now_sql = "DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00'";

                $sql = array();

                // کلیدهای API.
                $sql[] = "CREATE TABLE {$p}aclp_api_keys (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        name VARCHAR(190) NOT NULL DEFAULT '',
                        key_hash CHAR(64) NOT NULL DEFAULT '',
                        key_prefix VARCHAR(24) NOT NULL DEFAULT '',
                        key_plain VARCHAR(255) NOT NULL DEFAULT '',
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        max_clients INT UNSIGNED NOT NULL DEFAULT 0,
                        notes TEXT NULL,
                        created_at {$now_sql},
                        last_used_at DATETIME NULL DEFAULT NULL,
                        PRIMARY KEY  (id),
                        UNIQUE KEY key_hash (key_hash),
                        KEY key_prefix (key_prefix)
                ) $charset;";

                // کلاینت‌ها (سیستم‌های متصل).
                $sql[] = "CREATE TABLE {$p}aclp_clients (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        client_uid VARCHAR(64) NOT NULL DEFAULT '',
                        name VARCHAR(190) NOT NULL DEFAULT '',
                        os VARCHAR(50) NOT NULL DEFAULT '',
                        os_version VARCHAR(120) NOT NULL DEFAULT '',
                        hostname VARCHAR(190) NOT NULL DEFAULT '',
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        python_version VARCHAR(50) NOT NULL DEFAULT '',
                        agent_version VARCHAR(20) NOT NULL DEFAULT '',
                        ai_model VARCHAR(190) NOT NULL DEFAULT '',
                        capabilities TEXT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'offline',
                        commands_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        last_seen_at DATETIME NULL DEFAULT NULL,
                        registered_at {$now_sql},
                        PRIMARY KEY  (id),
                        UNIQUE KEY client_uid (client_uid),
                        KEY key_id (key_id),
                        KEY status (status)
                ) $charset;";

                // فرمان‌ها (تاریخچه تعاملات).
                $sql[] = "CREATE TABLE {$p}aclp_commands (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        command_uid VARCHAR(64) NOT NULL DEFAULT '',
                        group_uid VARCHAR(64) NOT NULL DEFAULT '',
                        key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        type VARCHAR(50) NOT NULL DEFAULT '',
                        payload LONGTEXT NULL,
                        source VARCHAR(190) NOT NULL DEFAULT '',
                        status VARCHAR(20) NOT NULL DEFAULT 'pending',
                        result LONGTEXT NULL,
                        error TEXT NULL,
                        duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        created_at {$now_sql},
                        sent_at DATETIME NULL DEFAULT NULL,
                        started_at DATETIME NULL DEFAULT NULL,
                        completed_at DATETIME NULL DEFAULT NULL,
                        PRIMARY KEY  (id),
                        UNIQUE KEY command_uid (command_uid),
                        KEY client_status (client_id, status),
                        KEY key_id (key_id),
                        KEY created_at (created_at)
                ) $charset;";

                // فایل‌های منتقل‌شده.
                $sql[] = "CREATE TABLE {$p}aclp_files (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        command_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        direction VARCHAR(10) NOT NULL DEFAULT 'from_pc',
                        original_name VARCHAR(255) NOT NULL DEFAULT '',
                        stored_path TEXT NULL,
                        size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        mime VARCHAR(100) NOT NULL DEFAULT '',
                        created_at {$now_sql},
                        PRIMARY KEY  (id),
                        KEY command_id (command_id),
                        KEY key_id (key_id),
                        KEY created_at (created_at)
                ) $charset;";

                // لاگ رویدادها.
                $sql[] = "CREATE TABLE {$p}aclp_logs (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        command_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        event VARCHAR(50) NOT NULL DEFAULT '',
                        message TEXT NULL,
                        data LONGTEXT NULL,
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        created_at {$now_sql},
                        PRIMARY KEY  (id),
                        KEY event (event),
                        KEY created_at (created_at),
                        KEY key_id (key_id)
                ) $charset;";

                // پیام‌های گفتگوی کاربر با هوش مصنوعی (v1.3.0).
                $sql[] = "CREATE TABLE {$p}aclp_chat_messages (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        key_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        direction VARCHAR(10) NOT NULL DEFAULT 'to_ai',
                        body TEXT NULL,
                        files LONGTEXT NULL,
                        source VARCHAR(190) NOT NULL DEFAULT '',
                        status VARCHAR(20) NOT NULL DEFAULT 'new',
                        ip VARCHAR(45) NOT NULL DEFAULT '',
                        created_at {$now_sql},
                        delivered_at DATETIME NULL DEFAULT NULL,
                        PRIMARY KEY  (id),
                        KEY key_id (key_id),
                        KEY client_id (client_id),
                        KEY direction (direction),
                        KEY status (status),
                        KEY created_at (created_at)
                ) $charset;";

                foreach ( $sql as $query ) {
                        dbDelta( $query );
                }
        }
}
