<?php
/*
Plugin Name: WooCommerce Variation Editor
Plugin URI:  http://action-a-day.com/
Description: WooCommerce plugin to provide spreadsheet style editing for product variations.
Version:     0.1
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
*/

defined( 'ABSPATH' ) or die( 'I\'m Sorry Dave, I can\'t do that!' );

if (! class_exists("aad_wcve")) {
	class aad_wcve
	{
		function __construct()
		{
			add_action('init', array($this, 'action_init'));
		}
		
		/**
		 * Hook into WP after plugins have loaded
		 *
		 * @return void
		 */
		function action_init()
		{
			// Only hook if WooCommerce is available
			if (class_exists('WooCommerce')) {
				add_filter('page_row_actions', array($this, 'make_edit_variation_link_row'), 10, 2);				
				add_filter('post_row_actions', array($this, 'make_edit_variation_link_row'), 10, 2);				
			}
		}
		
		/**
		 * For variation products, add option to edit the variation
		 */
		function make_edit_variation_link_row($actions, $post) {
			if ($post->post_type != 'product') return $actions; // Only interested in WooCommerce products

			$terms = wp_get_object_terms( $post->ID, 'product_type' );
			$product_type = sanitize_title( current( $terms )->name );
			
		    if ($product_type == 'variable' && current_user_can('edit_posts')) {
		        $actions['edit_product_variations'] = '<a href="FIXME-'.$post->ID.'" title="' //FIXME
		            . esc_attr(__("Edit Product Variations", 'aad-wcve'))
		            . '">' .  __('Edit Variations', 'aad-wcve') . '</a>';
		    }
		    return $actions;
		}
	}
}

global $aad_wcve;
$aad_wcve = new aad_wcve();
