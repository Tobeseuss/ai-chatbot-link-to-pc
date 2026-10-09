<?php
/**
 * مدیریت فایل‌های منتقل‌شده بین چت‌بات و سیستم کاربر.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACLP_Files {

	/**
	 * ذخیره فایل آپلودشده.
	 *
	 * @param array $file        آرایه $_FILES معتبر (name, tmp_name, size).
	 * @param array $args        متادیتا (command_id, key_id, client_id, direction).
	 * @return array{file: object}|WP_Error
	 */
	public static function store_upload( array $file, array $args = array() ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'aclp_no_file', 'فایلی برای آپلود دریافت نشد.' );
		}

		$max_mb = (int) ACLP_Settings::get( 'max_file_size_mb', 256 );
		if ( (int) $file['size'] > $max_mb * 1024 * 1024 ) {
			return new WP_Error( 'aclp_file_too_large', sprintf( 'حجم فایل بیش از حد مجاز (%d مگابایت) است.', $max_mb ) );
		}

		$dir = ACLP_Utils::storage_dir() . '/' . gmdate( 'Y/m' );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'aclp_storage_error', 'امکان ساخت پوشه ذخیره‌سازی وجود ندارد.' );
		}

		$original = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'file.bin';
		$stored   = uniqid( 'aclp_' ) . '-' . $original;
		$dest     = $dir . '/' . $stored;

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) {
			// جایگزین برای محیط‌های غیرمعمول.
			if ( ! @rename( $file['tmp_name'], $dest ) && ! @copy( $file['tmp_name'], $dest ) ) {
				return new WP_Error( 'aclp_move_failed', 'ذخیره فایل روی سرور ناموفق بود.' );
			}
		}
		@chmod( $dest, 0644 );

		global $wpdb;
		$size = (int) ( file_exists( $dest ) ? filesize( $dest ) : $file['size'] );
		$wpdb->insert(
			$wpdb->prefix . 'aclp_files',
			array(
				'command_id'    => ! empty( $args['command_id'] ) ? (int) $args['command_id'] : 0,
				'key_id'        => ! empty( $args['key_id'] ) ? (int) $args['key_id'] : 0,
				'client_id'     => ! empty( $args['client_id'] ) ? (int) $args['client_id'] : 0,
				'direction'     => ( 'to_pc' === ( $args['direction'] ?? '' ) ) ? 'to_pc' : 'from_pc',
				'original_name' => substr( $original, 0, 255 ),
				'stored_path'   => $dest,
				'size'          => $size,
				'mime'          => isset( $file['type'] ) ? sanitize_text_field( substr( (string) $file['type'], 0, 100 ) ) : 'application/octet-stream',
				'created_at'    => ACLP_Utils::now(),
			)
		);

		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'aclp_db_error', 'ثبت فایل در دیتابیس ناموفق بود.' );
		}

		$row = self::get( $wpdb->insert_id );
		ACLP_Logger::add(
			'file_uploaded',
			'فایل آپلود شد: ' . $original,
			array( 'size' => $size, 'direction' => $row->direction ),
			(int) $row->key_id,
			(int) $row->client_id,
			(int) $row->command_id
		);
		return array( 'file' => $row );
	}

	/**
	 * دریافت فایل با شناسه.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_files WHERE id = %d", (int) $id ) );
	}

	/**
	 * فایل‌های یک فرمان.
	 *
	 * @param int $command_id شناسه فرمان.
	 * @return array
	 */
	public static function for_command( $command_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aclp_files WHERE command_id = %d ORDER BY id ASC", (int) $command_id ) );
	}

	/**
	 * ساخت پاسخ استاندارد فایل برای API.
	 *
	 * @param object $row ردیف فایل.
	 * @return array
	 */
	public static function api_shape( $row ) {
		return array(
			'file_id'  => (int) $row->id,
			'filename' => $row->original_name,
			'size'     => (int) $row->size,
			'url'      => rest_url( 'aclp/v1/files/' . (int) $row->id ),
		);
	}

	/**
	 * حذف فایل (از دیسک و دیتابیس).
	 *
	 * @param int $id شناسه.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$row = self::get( $id );
		if ( ! $row ) {
			return false;
		}
		if ( ! empty( $row->stored_path ) && file_exists( $row->stored_path ) ) {
			@unlink( $row->stored_path );
		}
		return (bool) $wpdb->delete( $wpdb->prefix . 'aclp_files', array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * آمار فایل‌ها برای داشبورد.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$t = $wpdb->prefix . 'aclp_files';
		return array(
			'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ),
			'size'  => (int) $wpdb->get_var( "SELECT COALESCE(SUM(size),0) FROM {$t}" ),
		);
	}
}
