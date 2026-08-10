<?php

declare(strict_types=1);

/*
Plugin Name: NTS Site
Description: Custom functionality for Notes site.
Version: 1.0.0
Requires PHP: 8.5
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/core/cleanup.php';
require_once 'includes/core/branding.php';
require_once 'includes/acf/acf.php';
