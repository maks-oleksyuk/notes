<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register custom save/load paths for each ACF type.
function nts_site_register_acf_json_paths(): void {
	$acf_paths = array(
		'acf-field-group' => 'field_group',
		'acf-post-type'   => 'post_types',
		'acf-taxonomy'    => 'taxonomy',
		'ui-options-page' => 'ui_options_page',
	);

	$base_path = plugin_dir_path( __FILE__ ) . '../../import/acf/';

	foreach ( $acf_paths as $type => $dir ) {
		add_filter( "acf/settings/save_json/type=$type", fn() => $base_path . $dir );
	}

	add_filter( 'acf/settings/load_json', fn() => array_map( fn( $dir ) => $base_path . $dir, $acf_paths ) );
}
nts_site_register_acf_json_paths();

// Custom JSON filename logic.
function nts_site_custom_acf_json_filename( $filename, $post ): string {
	// Use post-type or taxonomy key as filename if available.
	$key = $post['post_type'] ?? $post['taxonomy'] ?? null;
	if ( $key ) {
		return "$key.json";
	}

	return strtolower( str_replace( array( ' ', '_' ), '-', $post['title'] ?? 'untitled' ) ) . '.json';
}
add_filter( 'acf/json/save_file_name', 'nts_site_custom_acf_json_filename', 10, 3 );
