<?php
/**
 * منوها، اکشن‌های پنل مدیریت و اعلان‌ها.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

require_once ACLP_DIR . 'admin/class-aclp-admin-pages.php';

class ACLP_Admin {

        /**
         * راه‌اندازی هوک‌های مدیریت.
         */
        public static function boot() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

                add_action( 'admin_post_aclp_add_key', array( __CLASS__, 'handle_add_key' ) );
                add_action( 'admin_post_aclp_key_action', array( __CLASS__, 'handle_key_action' ) );
                add_action( 'admin_post_aclp_client_delete', array( __CLASS__, 'handle_client_delete' ) );
                add_action( 'admin_post_aclp_save_settings', array( __CLASS__, 'handle_save_settings' ) );
                add_action( 'admin_post_aclp_purge_history', array( __CLASS__, 'handle_purge_history' ) );
                add_action( 'admin_post_aclp_download_file', array( __CLASS__, 'handle_download_file' ) );
        }

        /**
         * ساخت منوی مدیریت.
         */
        public static function menu() {
                add_menu_page( 'AI-PC Link', 'AI-PC Link', 'manage_options', 'aclp', array( 'ACLP_Admin_Pages', 'render_dashboard' ), 'dashicons-admin-links', 58 );
                add_submenu_page( 'aclp', 'داشبورد', 'داشبورد', 'manage_options', 'aclp', array( 'ACLP_Admin_Pages', 'render_dashboard' ) );
                add_submenu_page( 'aclp', 'کلیدهای API', 'کلیدهای API', 'manage_options', 'aclp-keys', array( 'ACLP_Admin_Pages', 'render_keys' ) );
                add_submenu_page( 'aclp', 'سیستم‌های متصل', 'سیستم‌های متصل', 'manage_options', 'aclp-clients', array( 'ACLP_Admin_Pages', 'render_clients' ) );
                add_submenu_page( 'aclp', 'تاریخچه تعاملات', 'تاریخچه تعاملات', 'manage_options', 'aclp-history', array( 'ACLP_Admin_Pages', 'render_history' ) );
                add_submenu_page( 'aclp', 'تنظیمات', 'تنظیمات', 'manage_options', 'aclp-settings', array( 'ACLP_Admin_Pages', 'render_settings' ) );
        }

        /**
         * بارگذاری استایل و اسکریپت فقط در صفحات پلاگین.
         *
         * @param string $hook صفحه فعلی.
         */
        public static function assets( $hook ) {
                if ( false === strpos( (string) $hook, 'aclp' ) ) {
                        return;
                }
                wp_enqueue_style( 'aclp-admin', ACLP_URL . 'admin/css/aclp-admin.css', array(), ACLP_VERSION );
                wp_enqueue_script( 'aclp-admin', ACLP_URL . 'admin/js/aclp-admin.js', array(), ACLP_VERSION, true );
        }

        /* ---------------------------------------------------------------------
         * هندلرهای اکشن
         * ------------------------------------------------------------------- */

        /**
         * بازگشت به صفحه مبدا با اعلان.
         *
         * @param string $page  اسلاگ صفحه.
         * @param string $notice نوع اعلان.
         * @param string $error  پیام خطا.
         */
        private static function redirect( $page, $notice = '', $error = '' ) {
                $url = admin_url( 'admin.php?page=' . $page );
                if ( $notice ) {
                        $url = add_query_arg( 'aclp_notice', $notice, $url );
                }
                if ( $error ) {
                        $url = add_query_arg( 'aclp_error', rawurlencode( $error ), $url );
                }
                wp_safe_redirect( $url );
                exit;
        }

        /**
         * ساخت کلید جدید.
         */
        public static function handle_add_key() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_action', '_aclp_nonce' );

                $name        = isset( $_POST['key_name'] ) ? wp_unslash( $_POST['key_name'] ) : '';
                $max_clients = isset( $_POST['max_clients'] ) ? (int) $_POST['max_clients'] : 0;
                $notes       = isset( $_POST['key_notes'] ) ? wp_unslash( $_POST['key_notes'] ) : '';

                $result = ACLP_API_Keys::create( $name, $max_clients, $notes );
                if ( is_wp_error( $result ) ) {
                        self::redirect( 'aclp-keys', '', $result->get_error_message() );
                }

                // نمایش کلید فقط یک‌بار.
                set_transient( 'aclp_new_key_' . get_current_user_id(), $result['plain'], 300 );
                self::redirect( 'aclp-keys', 'key_created' );
        }

        /**
         * فعال/غیرفعال/حذف کلید.
         */
        public static function handle_key_action() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_action', '_aclp_nonce' );

                $id   = isset( $_POST['key_id'] ) ? (int) $_POST['key_id'] : 0;
                $do   = isset( $_POST['subaction'] ) ? sanitize_key( $_POST['subaction'] ) : '';
                $page = 'aclp-keys';

                if ( 'activate' === $do ) {
                        ACLP_API_Keys::set_active( $id, true );
                        self::redirect( $page, 'key_activated' );
                }
                if ( 'deactivate' === $do ) {
                        ACLP_API_Keys::set_active( $id, false );
                        self::redirect( $page, 'key_deactivated' );
                }
                if ( 'delete' === $do ) {
                        ACLP_API_Keys::delete( $id );
                        self::redirect( $page, 'key_deleted' );
                }
                self::redirect( $page );
        }

        /**
         * حذف کلاینت.
         */
        public static function handle_client_delete() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_action', '_aclp_nonce' );
                ACLP_Clients::delete( isset( $_POST['client_id'] ) ? (int) $_POST['client_id'] : 0 );
                self::redirect( 'aclp-clients', 'client_deleted' );
        }

        /**
         * ذخیره تنظیمات.
         */
        public static function handle_save_settings() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_action', '_aclp_nonce' );

                ACLP_Settings::update( array(
                        'poll_interval'             => isset( $_POST['poll_interval'] ) ? $_POST['poll_interval'] : 5,
                        'online_timeout'            => isset( $_POST['online_timeout'] ) ? $_POST['online_timeout'] : 90,
                        'command_timeout'           => isset( $_POST['command_timeout'] ) ? $_POST['command_timeout'] : 300,
                        'history_retention_days'    => isset( $_POST['history_retention_days'] ) ? $_POST['history_retention_days'] : 30,
                        'file_retention_days'       => isset( $_POST['file_retention_days'] ) ? $_POST['file_retention_days'] : 7,
                        'max_log_entries'           => isset( $_POST['max_log_entries'] ) ? $_POST['max_log_entries'] : 50000,
                        'max_commands_rows'         => isset( $_POST['max_commands_rows'] ) ? $_POST['max_commands_rows'] : 100000,
                        'max_file_size_mb'          => isset( $_POST['max_file_size_mb'] ) ? $_POST['max_file_size_mb'] : 256,
                        'rate_limit_per_min'        => isset( $_POST['rate_limit_per_min'] ) ? $_POST['rate_limit_per_min'] : 240,
                        'max_pending_per_client'    => isset( $_POST['max_pending_per_client'] ) ? $_POST['max_pending_per_client'] : 50,
                        'delete_data_on_uninstall'  => isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
                        'github_repo_url'           => isset( $_POST['github_repo_url'] ) ? wp_unslash( $_POST['github_repo_url'] ) : '',
                        'github_pat'                => isset( $_POST['github_pat'] ) ? wp_unslash( $_POST['github_pat'] ) : '',
                ) );

                self::redirect( 'aclp-settings', 'settings_saved' );
        }

        /**
         * پاک‌سازی دستی تاریخچه.
         */
        public static function handle_purge_history() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_action', '_aclp_nonce' );

                global $wpdb;
                $mode = isset( $_POST['purge_mode'] ) ? sanitize_key( $_POST['purge_mode'] ) : 'old';
                $days = isset( $_POST['purge_days'] ) ? max( 0, (int) $_POST['purge_days'] ) : 0;

                if ( 'all' === $mode ) {
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}aclp_commands" );
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}aclp_logs" );
                } elseif ( 'files' === $mode ) {
                        foreach ( (array) $wpdb->get_results( "SELECT id, stored_path FROM {$wpdb->prefix}aclp_files LIMIT 2000" ) as $f ) {
                                if ( ! empty( $f->stored_path ) && file_exists( $f->stored_path ) ) {
                                        @unlink( $f->stored_path );
                                }
                                $wpdb->delete( $wpdb->prefix . 'aclp_files', array( 'id' => (int) $f->id ), array( '%d' ) );
                        }
                } elseif ( $days > 0 ) {
                        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_commands WHERE created_at < %s", $cutoff ) );
                        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}aclp_logs WHERE created_at < %s", $cutoff ) );
                }

                self::redirect( 'aclp-settings', 'purged' );
        }

        /**
         * دانلود فایل از پنل مدیریت.
         */
        public static function handle_download_file() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی ندارید.' );
                }
                check_admin_referer( 'aclp_download', '_aclp_nonce' );

                $file = ACLP_Files::get( isset( $_GET['file_id'] ) ? (int) $_GET['file_id'] : 0 );
                if ( ! $file || empty( $file->stored_path ) || ! file_exists( $file->stored_path ) ) {
                        wp_die( 'فایل یافت نشد یا منقضی شده است.' );
                }

                nocache_headers();
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Disposition: attachment; filename="' . rawurlencode( $file->original_name ) . '"' );
                header( 'Content-Length: ' . filesize( $file->stored_path ) );
                readfile( $file->stored_path );
                exit;
        }
}

/**
 * رندر اعلان‌های مدیریت.
 */
class ACLP_Admin_Notices {

        /**
         * نمایش اعلان‌ها بر اساس پارامترهای URL.
         */
        public static function render() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                if ( isset( $_GET['aclp_error'] ) ) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['aclp_error'] ) ) ) . '</p></div>';
                }

                $notice = isset( $_GET['aclp_notice'] ) ? sanitize_key( $_GET['aclp_notice'] ) : '';
                $map    = array(
                        'key_activated'   => 'کلید API فعال شد.',
                        'key_deactivated' => 'کلید API غیرفعال شد.',
                        'key_deleted'     => 'کلید API حذف شد.',
                        'client_deleted'  => 'سیستم متصل حذف شد.',
                        'settings_saved'  => 'تنظیمات ذخیره شد.',
                        'purged'          => 'پاک‌سازی انجام شد.',
                );
                if ( $notice && isset( $map[ $notice ] ) ) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
                }

                // نمایش یک‌باره کلید تازه ساخته‌شده.
                if ( 'key_created' === $notice ) {
                        $plain = get_transient( 'aclp_new_key_' . get_current_user_id() );
                        if ( $plain ) {
                                delete_transient( 'aclp_new_key_' . get_current_user_id() );
                                echo '<div class="notice notice-success aclp-key-notice"><p><strong>کلید API ساخته شد!</strong> این مقدار فقط همین یک‌بار نمایش داده می‌شود — همین حالا آن را کپی و در جای امن ذخیره کنید:</p>';
                                echo '<p><code class="aclp-key-plain" id="aclp-new-key">' . esc_html( $plain ) . '</code> <button type="button" class="button aclp-copy-key" data-target="aclp-new-key">کپی</button></p>';
                                echo '<p class="description">⚠️ هرکس این کلید را داشته باشد، کنترل کامل سیستم متصل به آن را در اختیار می‌گیرد. هرگز آن را عمومی نکنید.</p></div>';
                        }
                }
        }
}

ACLP_Admin::boot();
