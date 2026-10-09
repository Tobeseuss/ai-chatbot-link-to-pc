<?php
/**
 * لاگر رویدادها (تاریخچه کامل تعاملات).
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACLP_Logger {

	/**
	 * ثبت رویداد.
	 *
	 * @param string $event      نوع رویداد (command_created, command_completed, ...).
	 * @param string $message    پیام.
	 * @param mixed  $data       داده اضافی.
	 * @param int    $key_id     شناسه کلید.
	 * @param int    $client_id  شناسه کلاینت.
	 * @param int    $command_id شناسه فرمان.
	 */
	public static function add( $event, $message = '', $data = array(), $key_id = 0, $client_id = 0, $command_id = 0 ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'aclp_logs',
			array(
				'key_id'     => (int) $key_id,
				'client_id'  => (int) $client_id,
				'command_id' => (int) $command_id,
				'event'      => substr( sanitize_key( $event ), 0, 50 ),
				'message'    => sanitize_textarea_field( substr( (string) $message, 0, 2000 ) ),
				'data'       => wp_json_encode( $data ),
				'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
				'created_at' => ACLP_Utils::now(),
			)
		);
	}

	/**
	 * کوئری لاگ‌ها برای پنل مدیریت.
	 *
	 * @param array $args فیلترها.
	 * @return array
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$t      = $wpdb->prefix . 'aclp_logs';
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'l.event = %s';
			$params[] = sanitize_key( $args['event'] );
		}
		if ( ! empty( $args['key_id'] ) ) {
			$where[]  = 'l.key_id = %d';
			$params[] = (int) $args['key_id'];
		}
		if ( ! empty( $args['client_id'] ) ) {
			$where[]  = 'l.client_id = %d';
			$params[] = (int) $args['client_id'];
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = ! empty( $args['per_page'] ) ? max( 5, min( 100, (int) $args['per_page'] ) ) : 30;
		$paged     = ! empty( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset    = ( $paged - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} l WHERE {$where_sql}", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT l.* FROM {$t} l WHERE {$where_sql} ORDER BY l.id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) )
		);

		return array( 'rows' => $rows, 'total' => $total, 'per_page' => $per_page, 'paged' => $paged );
	}
}
