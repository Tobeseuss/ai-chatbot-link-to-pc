<?php
/**
 * حذف افزونه: پاک‌سازی کامل داده‌ها فقط اگر کاربر در تنظیمات انتخاب کرده باشد.
 *
 * @package ACLP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'aclp_settings', array() );
if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
	return; // داده‌ها حفظ می‌شوند.
}

global $wpdb;

// حذف جداول.
$tables = array(
	$wpdb->prefix . 'aclp_logs',
	$wpdb->prefix . 'aclp_files',
	$wpdb->prefix . 'aclp_commands',
	$wpdb->prefix . 'aclp_clients',
	$wpdb->prefix . 'aclp_api_keys',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// حذف گزینه‌ها و زمان‌بندی.
delete_option( 'aclp_settings' );
delete_option( 'aclp_version' );
delete_option( 'aclp_activated_at' );
wp_clear_scheduled_hook( 'aclp_hourly_maintenance' );

// حذف فایل‌های منتقل‌شده.
$upload = wp_upload_dir();
$dir    = trailingslashit( $upload['basedir'] ) . 'aclp-files';
if ( is_dir( $dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getPathname() );
		} else {
			@unlink( $item->getPathname() );
		}
	}
	@rmdir( $dir );
}
