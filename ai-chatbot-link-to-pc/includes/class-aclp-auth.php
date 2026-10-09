<?php
/**
 * احراز هویت کلید API و محدودیت نرخ درخواست.
 *
 * @package ACLP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACLP_Auth {

	/**
	 * استخراج کلید از درخواست (هدر X-ACLP-Key یا Authorization Bearer یا پارامتر api_key).
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return string
	 */
	public static function key_from_request( $request ) {
		$raw = $request->get_header( 'X-ACLP-Key' );
		if ( ! $raw ) {
			$auth = $request->get_header( 'Authorization' );
			if ( $auth && preg_match( '/Bearer\s+(.+)/i', $auth, $m ) ) {
				$raw = trim( $m[1] );
			}
		}
		if ( ! $raw ) {
			$raw = $request->get_param( 'api_key' );
		}
		return is_string( $raw ) ? trim( $raw ) : '';
	}

	/**
	 * احراز هویت کامل درخواست.
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @return object|WP_Error ردیف کلید یا خطا.
	 */
	public static function authenticate( $request ) {
		$plain = self::key_from_request( $request );
		if ( '' === $plain ) {
			return new WP_Error( 'aclp_missing_key', 'کلید API ارسال نشده است. هدر X-ACLP-Key را تنظیم کنید.', array( 'status' => 401 ) );
		}

		$key = ACLP_API_Keys::get_by_plain( $plain );
		if ( ! $key ) {
			ACLP_Logger::add( 'auth_failed', 'تلاش احراز هویت با کلید نامعتبر' );
			return new WP_Error( 'aclp_invalid_key', 'کلید API نامعتبر است.', array( 'status' => 401 ) );
		}
		if ( ! $key->is_active ) {
			return new WP_Error( 'aclp_key_inactive', 'این کلید API غیرفعال شده است.', array( 'status' => 403 ) );
		}

		// محدودیت نرخ درخواست.
		$limit  = max( 10, (int) ACLP_Settings::get( 'rate_limit_per_min', 240 ) );
		$bucket = (int) floor( time() / 60 );
		$tkey   = 'aclp_rl_' . (int) $key->id . '_' . $bucket;
		$count  = (int) get_transient( $tkey );
		if ( $count >= $limit ) {
			return new WP_Error( 'aclp_rate_limited', 'محدودیت نرخ درخواست. کمی بعد تلاش کنید.', array( 'status' => 429 ) );
		}
		set_transient( $tkey, $count + 1, 65 );

		ACLP_API_Keys::touch( $key->id );
		return $key;
	}

	/**
	 * احراز هویت کلاینت (کلید + شناسه سیستم).
	 *
	 * @param WP_REST_Request $request درخواست.
	 * @param object          $key     کلید احرازشده.
	 * @return object|WP_Error ردیف کلاینت یا خطا.
	 */
	public static function client_from_request( $request, $key ) {
		$uid = $request->get_header( 'X-ACLP-Client-UID' );
		if ( ! $uid ) {
			$uid = $request->get_param( 'client_uid' );
		}
		if ( empty( $uid ) || ! is_string( $uid ) ) {
			return new WP_Error( 'aclp_missing_client', 'شناسه سیستم (client_uid) ارسال نشده است.', array( 'status' => 401 ) );
		}

		$client = ACLP_Clients::get_by_uid( $uid );
		if ( ! $client || (int) $client->key_id !== (int) $key->id ) {
			return new WP_Error( 'aclp_unknown_client', 'این سیستم برای کلید API فعلی ثبت نشده است. ابتدا /agent/register را صدا بزنید.', array( 'status' => 403 ) );
		}
		ACLP_Clients::touch_online( $client->id );
		return $client;
	}
}
