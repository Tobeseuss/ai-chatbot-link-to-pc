<?php
/**
 * توابع کمکی عمومی.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACLP_Utils {

	/**
	 * زمان فعلی UTC در فرمت MySQL.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * تولید شناسه یکتا برای فرمان‌ها.
	 *
	 * @return string
	 */
	public static function uid() {
		return wp_generate_uuid4();
	}

	/**
	 * بررسی آنلاین بودن کلاینت بر اساس آخرین دیده‌شدن.
	 *
	 * @param object $client ردیف کلاینت.
	 * @return bool
	 */
	public static function client_is_online( $client ) {
		if ( empty( $client->last_seen_at ) || '0000-00-00 00:00:00' === $client->last_seen_at ) {
			return false;
		}
		$timeout = (int) ACLP_Settings::get( 'online_timeout', 90 );
		return ( time() - strtotime( $client->last_seen_at . ' UTC' ) ) <= $timeout;
	}

	/**
	 * قالب‌بندی حجم فایل به شکل خوانا.
	 *
	 * @param int $bytes بایت.
	 * @return string
	 */
	public static function human_size( $bytes ) {
		$bytes = max( 0, (int) $bytes );
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$i     = 0;
		$v     = $bytes;
		while ( $v >= 1024 && $i < count( $units ) - 1 ) {
			$v /= 1024;
			$i++;
		}
		return round( $v, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * برچسب فارسی وضعیت فرمان.
	 *
	 * @param string $status وضعیت.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'pending'   => 'در صف',
			'sent'      => 'ارسال‌شده',
			'running'   => 'در حال اجرا',
			'completed' => 'تکمیل‌شده',
			'failed'    => 'ناموفق',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	/**
	 * برچسب فارسی نوع فرمان.
	 *
	 * @param string $type نوع.
	 * @return string
	 */
	public static function type_label( $type ) {
		$map = array(
			'shell'         => 'اجرای دستور',
			'file_read'     => 'خواندن فایل',
			'file_write'    => 'نوشتن فایل',
			'file_list'     => 'لیست فایل‌ها',
			'file_delete'   => 'حذف فایل',
			'file_mkdir'    => 'ساخت پوشه',
			'file_move'     => 'انتقال/کپی فایل',
			'file_download' => 'دانلود فایل به سیستم',
			'upload_file'   => 'ارسال فایل از سیستم',
			'open_url'      => 'باز کردن مرورگر',
			'http_request'  => 'درخواست HTTP',
			'screenshot'    => 'اسکرین‌شات',
			'sysinfo'       => 'اطلاعات سیستم',
			'process_list'  => 'لیست پردازه‌ها',
			'kill_process'  => 'بستن پردازه',
			'install'       => 'نصب برنامه',
			'run_python'    => 'اجرای کد پایتون',
			'ping'          => 'تست اتصال',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $type;
	}

	/**
	 * دایرکتوری ذخیره فایل‌های پلاگین.
	 *
	 * @return string مسیر بدون اسلش انتهایی.
	 */
	public static function storage_dir() {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . 'aclp-files';
	}
}
