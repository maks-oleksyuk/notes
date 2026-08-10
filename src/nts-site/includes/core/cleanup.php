<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hide the Bedrock "search engine indexing discouraged" admin notice.
add_filter( 'roots/bedrock/disallow_indexing_admin_notice', '__return_false' );

// Remove the "Help" tab/dropdown from the top of every admin screen.
add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		$screen?->remove_help_tabs();
	}
);

// Updates are managed via Composer, so the disabled "Background updates" Site Health test is expected, not a problem.
// The site-health.js widget fetches this test over REST (not the synchronous `site_status_test_result`
// filter path), so it has to be patched on the REST response itself.
add_filter(
	'rest_request_after_callbacks',
	function ( $response, $handler, $request ) {
		if ( '/wp-site-health/v1/tests/background-updates' !== $request->get_route() || is_wp_error( $response ) ) {
			return $response;
		}

		$data           = $response->get_data();
		$data['label']  = __( 'Background updates are disabled (managed via Composer)', 'nts-site' );
		$data['status'] = 'good';
		$response->set_data( $data );

		return $response;
	},
	10,
	3
);
