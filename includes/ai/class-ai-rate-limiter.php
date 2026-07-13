<?php
/**
 * Transient-based rate limiting for AI endpoints.
 *
 * Two independent buckets:
 *  - per user (or IP when anonymous) per hour  -> abuse guard
 *  - per site per day                          -> cost guard on the owner's BYOK key
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Rate_Limiter {

	/**
	 * Check both buckets and increment them on success.
	 *
	 * @return true|array true when allowed; ['retry_after' => seconds] when limited.
	 */
	public static function check_and_increment() {
		$user_key = self::user_bucket_key();
		$site_key = 'houzi_ai_rl_site_' . gmdate( 'Ymd' );

		$user_count = intval( get_transient( $user_key ) );
		$site_count = intval( get_transient( $site_key ) );

		if ( $user_count >= Houzi_AI_Settings::rate_per_user_hour() ) {
			return array( 'retry_after' => self::seconds_to_next_hour() );
		}
		if ( $site_count >= Houzi_AI_Settings::rate_per_site_day() ) {
			return array( 'retry_after' => self::seconds_to_next_day() );
		}

		// Expirations are anchored to the bucket window, not the first hit;
		// good enough for a cost guard and keeps the transients self-cleaning.
		set_transient( $user_key, $user_count + 1, self::seconds_to_next_hour() );
		set_transient( $site_key, $site_count + 1, self::seconds_to_next_day() );

		return true;
	}

	private static function user_bucket_key() {
		if ( is_user_logged_in() ) {
			$who = 'u' . get_current_user_id();
		} else {
			$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
			$who = 'ip' . md5( $ip );
		}
		return 'houzi_ai_rl_' . $who . '_' . gmdate( 'YmdH' );
	}

	private static function seconds_to_next_hour() {
		return max( 60, HOUR_IN_SECONDS - ( time() % HOUR_IN_SECONDS ) );
	}

	private static function seconds_to_next_day() {
		return max( 60, DAY_IN_SECONDS - ( time() % DAY_IN_SECONDS ) );
	}
}
