<?php
/**
 * پیام‌های گفتگوی کاربر با هوش مصنوعی از طریق ایجنت (v1.3.0).
 *
 * کاربر در برنامه ایجنت («python aclp_agent.py chat») پیام/فایل می‌فرستد؛
 * هوش مصنوعیِ متصل به همان کلید با GET /chat/pending آنها را می‌گیرد و با
 * POST /chat/reply پاسخ می‌دهد؛ ایجنت با GET /chat/replies پاسخ‌ها را می‌گیرد.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACLP_Chat {

	/**
	 * ثبت پیام جدید.
	 *
	 * @param array $args {key_id, client_id, direction, body, files(array), source, ip}.
	 * @return int|null شناسه پیام.
	 */
	public static function add( array $args ) {
		global $wpdb;
		$direction = ( 'from_ai' === ( $args['direction'] ?? '' ) ) ? 'from_ai' : 'to_ai';
		$files     = array();
		foreach ( (array) ( $args['files'] ?? array() ) as $f ) {
			if ( is_array( $f ) && ! empty( $f['file_id'] ) ) {
				$files[] = array(
					'file_id'  => (int) $f['file_id'],
					'url'      => isset( $f['url'] ) ? esc_url_raw( (string) $f['url'] ) : '',
					'filename' => isset( $f['filename'] ) ? sanitize_text_field( (string) $f['filename'] ) : '',
					'size'     => isset( $f['size'] ) ? (int) $f['size'] : 0,
				);
			}
		}
		$ok = $wpdb->insert(
			$wpdb->prefix . 'aclp_chat_messages',
			array(
				'key_id'     => (int) ( $args['key_id'] ?? 0 ),
				'client_id'  => (int) ( $args['client_id'] ?? 0 ),
				'direction'  => $direction,
				'body'       => (string) ( $args['body'] ?? '' ),
				'files'      => $files ? wp_json_encode( $files ) : null,
				'source'     => substr( (string) ( $args['source'] ?? '' ), 0, 190 ),
				'status'     => 'new',
				'ip'         => substr( (string) ( $args['ip'] ?? '' ), 0, 45 ),
				'created_at' => ACLP_Utils::now(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : null;
	}

	/**
	 * پیام‌های تحویل‌نشده کاربر (to_ai) برای یک کلید — مصرف هوش مصنوعی.
	 *
	 * @param int $key_id شناسه کلید.
	 * @param int $limit سقف.
	 * @return array
	 */
	public static function pending_for_key( $key_id, $limit = 50 ) {
		global $wpdb;
		$t = $wpdb->prefix . 'aclp_chat_messages';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t} WHERE key_id = %d AND direction = 'to_ai' AND status = 'new' ORDER BY id ASC LIMIT %d",
				(int) $key_id,
				max( 1, (int) $limit )
			)
		);
	}

	/**
	 * علامت‌گذاری پیام‌ها به‌عنوان تحویل‌شده.
	 *
	 * @param array $ids شناسه‌ها.
	 */
	public static function mark_delivered( array $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', $ids );
		if ( ! $ids ) {
			return;
		}
		$t = $wpdb->prefix . 'aclp_chat_messages';
		$in = implode( ',', $ids );
		$wpdb->query(
			"UPDATE {$t} SET status = 'delivered', delivered_at = '" . ACLP_Utils::now() . "' WHERE id IN ({$in})"
		);
	}

	/**
	 * پاسخ‌های هوش مصنوعی (from_ai) برای یک کلاینت/کلید بعد از شناسه مشخص.
	 * client_id = 0 یعنی پیام همگانی برای همه سیستم‌های همان کلید.
	 *
	 * @param int    $key_id    کلید.
	 * @param int    $client_id کلاینت.
	 * @param int    $since     بعد از این شناسه.
	 * @param int    $limit     سقف.
	 * @param string $order     asc|desc.
	 * @return array
	 */
	public static function replies_for_client( $key_id, $client_id, $since = 0, $limit = 50, $order = 'asc' ) {
		global $wpdb;
		$t     = $wpdb->prefix . 'aclp_chat_messages';
		$order = ( 'desc' === strtolower( (string) $order ) ) ? 'DESC' : 'ASC';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t}
				 WHERE key_id = %d AND direction = 'from_ai'
				   AND (client_id = %d OR client_id = 0) AND id > %d
				 ORDER BY id {$order} LIMIT %d",
				(int) $key_id,
				(int) $client_id,
				(int) $since,
				max( 1, min( 200, (int) $limit ) )
			)
		);
	}

	/**
	 * آخرین پیام‌های یک کلید (پنل مدیریت).
	 *
	 * @param int   $key_id کلید (0 = همه).
	 * @param int   $limit  سقف.
	 * @return array
	 */
	public static function latest_for_key( $key_id = 0, $limit = 100 ) {
		global $wpdb;
		$t = $wpdb->prefix . 'aclp_chat_messages';
		if ( $key_id > 0 ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$t} WHERE key_id = %d ORDER BY id DESC LIMIT %d", (int) $key_id, max( 1, (int) $limit ) )
			);
		}
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", max( 1, (int) $limit ) )
		);
	}

	/**
	 * شکل استاندارد پیام برای API.
	 *
	 * @param object $row ردیف پیام.
	 * @return array
	 */
	public static function api_shape( $row ) {
		return array(
			'id'         => (int) $row->id,
			'client_id'  => (int) $row->client_id,
			'direction'  => $row->direction,
			'text'       => (string) $row->body,
			'body'       => (string) $row->body,
			'files'      => (array) json_decode( (string) $row->files, true ),
			'source'     => (string) $row->source,
			'status'     => (string) $row->status,
			'created_at' => $row->created_at,
		);
	}

	/**
	 * آمار گفتگوها برای داشبورد.
	 *
	 * @return array
	 */
	public static function stats() {
		global $wpdb;
		$t = $wpdb->prefix . 'aclp_chat_messages';
		return array(
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ),
			'to_ai'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE direction = 'to_ai'" ),
			'from_ai'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE direction = 'from_ai'" ),
			'pending'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE direction = 'to_ai' AND status = 'new'" ),
		);
	}
}
