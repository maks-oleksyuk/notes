<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Lock these General Settings fields in code instead of their DB-backed Settings > General fields.
add_filter( 'pre_option_blogname', fn() => 'Notes' );
add_filter( 'pre_option_date_format', fn() => 'd.m.Y' );
add_filter( 'pre_option_time_format', fn() => 'H:i' );
add_filter( 'pre_option_start_of_week', fn() => '1' );

// Use the plugin's SVG logo as the site icon/favicon instead of a Media Library attachment.
// has_site_icon() just checks get_site_icon_url() for truthiness, so returning a URL here is enough.
add_filter( 'get_site_icon_url', fn() => plugin_dir_url( __FILE__ ) . '../../assets/img/logo.svg' );
