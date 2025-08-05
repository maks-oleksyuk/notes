<?php

declare(strict_types=1);

$nts_site_acf_paths = array(
	'acf-field-group' => 'field_group',
	'acf-post-type'   => 'post_types',
	'acf-taxonomy'    => 'taxonomy',
	'ui-options-page' => 'ui_options_page',
);

$nts_site_base_path = plugin_dir_path( __FILE__ ) . '../../import/acf/';

// Register custom save paths for each ACF type.
foreach ( $nts_site_acf_paths as $nts_site_type => $nts_site_dir ) {
	add_filter( "acf/settings/save_json/type=$nts_site_type", fn() => $nts_site_base_path . $nts_site_dir );
}

// Register all custom load paths.
add_filter( 'acf/settings/load_json', fn() => array_map( fn( $dir ) => $nts_site_base_path . $dir, $nts_site_acf_paths ) );

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
