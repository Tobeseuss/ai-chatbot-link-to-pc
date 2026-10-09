<?php
/**
 * Plugin Name:       AI Chatbot Link to PC
 * Plugin URI:        https://github.com/Tobeseuss/ai-chatbot-link-to-pc
 * Description:       پل ارتباطی بین چت‌بات‌های هوش مصنوعی و سیستم‌عامل کاربر (ویندوز/لینوکس). کلیدهای API نامحدود، پشتیبانی چند سیستم همزمان، اجرای دستور، انتقال فایل، تاریخچه کامل تعاملات.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Tobeseuss
 * Author URI:        https://github.com/Tobeseuss
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-chatbot-link-to-pc
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit; // دسترسی مستقیم ممنوع.
}

define( 'ACLP_VERSION', '1.2.0' );
define( 'ACLP_FILE', __FILE__ );
define( 'ACLP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACLP_URL', plugin_dir_url( __FILE__ ) );

require_once ACLP_DIR . 'includes/class-aclp-settings.php';
require_once ACLP_DIR . 'includes/class-aclp-utils.php';
require_once ACLP_DIR . 'includes/class-aclp-activator.php';
require_once ACLP_DIR . 'includes/class-aclp-api-keys.php';
require_once ACLP_DIR . 'includes/class-aclp-clients.php';
require_once ACLP_DIR . 'includes/class-aclp-commands.php';
require_once ACLP_DIR . 'includes/class-aclp-files.php';
require_once ACLP_DIR . 'includes/class-aclp-logs.php';
require_once ACLP_DIR . 'includes/class-aclp-cron.php';
require_once ACLP_DIR . 'includes/class-aclp-auth.php';
require_once ACLP_DIR . 'includes/class-aclp-rest.php';

if ( is_admin() ) {
        require_once ACLP_DIR . 'admin/class-aclp-admin.php';
}

register_activation_hook( __FILE__, array( 'ACLP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACLP_Cron', 'deactivate' ) );

/**
 * مقداردهی اولیه پلاگین.
 */
function aclp_init() {
        // ارتقای ساختار دیتابیس پس از بروزرسانی پلاگین.
        add_action( 'admin_init', array( 'ACLP_Activator', 'maybe_upgrade' ) );

        // زمان‌بندی نگهداری دوره‌ای (پاک‌سازی تاریخچه و فایل‌ها).
        add_action( 'aclp_hourly_maintenance', array( 'ACLP_Cron', 'run' ) );

        // ثبت مسیرهای REST API.
        add_action( 'rest_api_init', array( 'ACLP_REST', 'register_routes' ) );

        // اعلان‌های مدیر.
        add_action( 'admin_notices', array( 'ACLP_Admin_Notices', 'render' ) );
}
add_action( 'plugins_loaded', 'aclp_init' );

/**
 * لینک تنظیمات در صفحه افزونه‌ها.
 *
 * @param array $links لینک‌های فعلی.
 * @return array
 */
function aclp_action_links( $links ) {
        $url = admin_url( 'admin.php?page=aclp' );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">داشبورد</a>' );
        return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aclp_action_links' );
