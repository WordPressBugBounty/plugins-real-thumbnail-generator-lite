<?php
/**
 * Main file for WordPress.
 *
 * @wordpress-plugin
 * Plugin Name:      Real Thumbnail Generator (Free)
 * Plugin URI:      https://devowl.io/
 * Description:     Single or mass image regeneration for your WordPress media thumbnails. Create a custom thumbnail file structure for all your images.
 * Author:          devowl.io
 * Author URI:      https://devowl.io
 * Version:                                                             2.6.90
 * Text Domain:     real-thumbnail-generator
 * Domain Path:     /languages
 */

defined('ABSPATH') or die('No script kiddies please!'); // Avoid direct file request

/**
 * Plugin constants. This file is procedural coding style for initialization of
 * the plugin core and definition of plugin configuration.
 */
if (defined('RTG_PATH')) {
    require_once __DIR__ . '/inc/base/others/fallback-already.php';
    return;
}
define('RTG_FILE', __FILE__);
define('RTG_PATH', dirname(RTG_FILE));
define('RTG_ROOT_SLUG', 'devowl-wp');
define('RTG_SLUG', basename(RTG_PATH));
define('RTG_INC', RTG_PATH . '/inc/');
define('RTG_MIN_PHP', '7.4.0');
define('RTG_MIN_WP', '5.9.0');
define('RTG_NS', 'DevOwl\\RealThumbnailGenerator');
define('RTG_DB_PREFIX', 'rtg'); // The table name prefix wp_{prefix}
define('RTG_OPT_PREFIX', 'rtg'); // The option name prefix in wp_options
define('RTG_SLUG_CAMELCASE', lcfirst(str_replace('-', '', ucwords(RTG_SLUG, '-'))));
//define('RTG_TD', ''); This constant is defined in the core class. Use this constant in all your __() methods
//define('RTG_VERSION', ''); This constant is defined in the core class
//define('RTG_DEBUG', true); This constant should be defined in wp-config.php to enable the Base#debug() method

define('RTG_SLUG_LITE', 'real-thumbnail-generator-lite');
define('RTG_SLUG_PRO', 'real-thumbnail-generator');
// define('RTG_PRO_VERSION', 'https://devowl.io/go/real-thumbnail-generator?source=rtg-lite'); // This constant is defined in the core class

// Check PHP Version and print notice if minimum not reached, otherwise start the plugin core
require_once RTG_INC .
    'base/others/' .
    (version_compare(phpversion(), RTG_MIN_PHP, '>=') ? 'start.php' : 'fallback-php-version.php');
