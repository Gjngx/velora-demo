<?php
/**
 * Route local WP-Cron requests through the Docker network.
 */

if ( 'localhost' === wp_parse_url( site_url(), PHP_URL_HOST ) ) {
	add_filter(
		'cron_request',
		static function ( $request ) {
			$request['url'] = str_replace( site_url( '/' ), 'http://nginx/', $request['url'] );
			return $request;
		}
	);

	// The regular WP-Cron queue runner can reach Nginx; async loopbacks cannot.
	add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
}
