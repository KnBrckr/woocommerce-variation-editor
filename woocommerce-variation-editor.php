<?php
/*
Plugin Name: WooCommerce Variation Editor
Plugin URI:  http://action-a-day.com/
Description: WooCommerce plugin to provide spreadsheet style editing for product variations.
Version:     0.5
Author:      Kenneth J. Brucker
Author URI:  http://action-a-day.com
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: aad-wcve

    Copyright 2015 Kenneth J. Brucker  (email : ken.brucker@action-a-day.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Important files from woocommerce related to variable products:
 - woocommerce/includes/class-wc-product-variable.php : Defines Variable product class
 - woocommerce/includes/class-wc-product-variation.php : Defines product class for a single variation of a variable product
 - woocommerce/admin/meta-boxes/class-wc-meta-box-product-data.php : Creates meta box for editing products on admin screen
*/

defined( 'ABSPATH' ) or die( 'I\'m Sorry Dave, I can\'t do that!' );

use AAD\WCVE\Plugin;
use AAD\WCVE\VariationScreen;

/**
 * Define Class Autoloader
 */
spl_autoload_register( 'AAD_autoloader' );
function AAD_autoloader( $class_name ) {
  if ( false !== strpos( $class_name, 'AAD' ) ) {
    $classes_dir = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;
    $class_file = str_replace( '\\', DIRECTORY_SEPARATOR, $class_name ) . '.php';
    require_once $classes_dir . $class_file;
  }
}

/**
 * Hook plugin loaded to execute setup
 */
add_action( 'plugins_loaded', 'AAD_WCVE_init' );

/**
 * Setup Plugin
 */

function AAD_WCVE_init()
{	
	/**
	 * Only setup on admin screens
	 */
	if ( !is_admin() ) {
		return;
	}
	
	$plugin = new Plugin();
	
	$plugin[ 'version' ]		  = '0.5';
	$plugin[ 'path' ]			  = realpath( plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR;
	$plugin[ 'url' ]			  = plugin_dir_url( __FILE__ );
	
	$plugin[ 'VariationScreen' ] = function ($p) {
		$varScreen = new VariationScreen( $p['version'], $p['url'] );
		return $varScreen;
	};
	
	$varScreen = $plugin[ 'VariationScreen' ];
	
	$plugin->run();
}
