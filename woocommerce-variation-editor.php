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

// Only setup on admin screens
if (is_admin() && ! class_exists("aad_wcve")) {
	if (! include_once('class-wcve-variation-table.php')) return false;

	class aad_wcve
	{
		/**
		 * Active Variable Product ID
		 *
		 * @var int
		 */
		private $product_id;
		
		/**
		 * Table used for generating list of product varations
		 *
		 * @var string
		 */
		private $variation_table;
		
		function __construct()
		{
			$this->product_id = isset($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : NULL ;

			// Setup admin page for editing variable products
			add_action('admin_menu', array($this, 'action_admin_menu'));

			// Do plugin initialization
			add_action('admin_init', array($this, 'action_init'));
		}
		
		/**
		 * Hook into WP after plugins have loaded
		 *
		 * @return void
		 */
		function action_init()
		{
			// User must be able to manage WooCommerce
			if (! current_user_can('manage_woocommerce')) return;
			
			// Only hook if WooCommerce is available
			if (class_exists('WooCommerce')) {
				// Setup class used for display of variations
				$this->variation_table = new wcve_variation_Table($this->product_id);
				
				// Add option for editing product variations to product rows
				add_filter('page_row_actions', array($this, 'filter_make_edit_variation_link_row'), 10, 2);				
				add_filter('post_row_actions', array($this, 'filter_make_edit_variation_link_row'), 10, 2);
			}
		}
		
		/**
		 * For variation products, add option to edit the variation
		 */
		function filter_make_edit_variation_link_row($actions, $post) {
			if ($post->post_type != 'product') return $actions; // Only interested in WooCommerce products

			$terms = wp_get_object_terms( $post->ID, 'product_type' );
			$product_type = sanitize_title( current( $terms )->name );
			
			// We checked up front if the user can edit posts, if we get here, they can.
		    if ($product_type == 'variable') {
		        $actions['edit_product_variations'] = '<a href="edit.php?post_type=product&page=edit-product-variations&product_id='.$post->ID.'" title="' //FIXME
		            . esc_attr(__("Edit Product Variations", 'aad-wcve'))
		            . '">' .  __('Edit Variations', 'aad-wcve') . '</a>';
		    }
		    return $actions;
		}
		
		/**
		 * Create admin menu item for editing variable products
		 *
		 * @return void
		 */
		function action_admin_menu()
		{
			// User must be able to manage WooCommerce
			if (! current_user_can('manage_woocommerce')) return;
			
			$slug = add_submenu_page('edit.php?post_type=product', 
				__('Edit Product Variations', 'aad-wcve'), 
				__('Edit Variations', 'aad-wcve'), 
				'manage_woocommerce', // If user can manage WooCommerce
				'edit-product-variations',  // Slug for this menu
				array($this, 'render_edit_product_variations') // Method to create page
			);
		}
		
		/**
		 * Render content of page used to edit product variations
		 *
		 * @return void
		 */
		function render_edit_product_variations()
		{
			$plugin_page = 1;
			?>
			<div class="wrap">
				<div id="icon-edit" class="icon32 icon32-edit-product-variations">
					<br>
				</div>
				<h2>Edit Product Variations</h2>
				<?php
				if ($this->product_id) {
					?>
					<form action method="get" accept-charset="utf-8">
						<input type="hidden" name="post_type" value="product">
						<input type="hidden" name="page" value="<?php echo $plugin_page ?>">
						<?php
						global $wpdb;
						
						// $wpdb->query('SET SESSION group_concat_max_len = 10000'); // necessary to get more than 1024 characters in the GROUP_CONCAT columns below

						// $query = "
						//     SELECT p.*,
						//     GROUP_CONCAT(pm.meta_key ORDER BY pm.meta_key DESC SEPARATOR '||') as meta_keys,
						//     GROUP_CONCAT(pm.meta_value ORDER BY pm.meta_key DESC SEPARATOR '||') as meta_values
						//     FROM $wpdb->posts p
						//     LEFT JOIN $wpdb->postmeta pm on pm.post_id = p.ID
						//     WHERE p.post_type = 'product_variation' and p.post_status = 'publish' and p.post_parent = $this->product_id
						//     GROUP BY p.ID
						// ";
						//
						// $products = $wpdb->get_results($query);
						// $products = array_map(array($this,'massage_meta'),$products);
						
						$this->variation_table->prepare_items();

						$this->variation_table->search_box('search', 'search_id'); // Must follow prepare_items() call
						$this->variation_table->display();
						?>
					</form>
					<?php
				} else
					echo 'Please use the <a href="edit.php?post_status=all&post_type=product&product_type=variable">Product List</a> to select a variable product to edit';
				?>
			</div>
			<?php
		}
		
		/**
		 * Massage products to have a member -> meta with unserialized data as expected
		 *
		 * @return void
		 */
		function massage_meta($a)
		{
		    $a->meta = array_combine(explode('||',$a->meta_keys),array_map('maybe_unserialize',explode('||',$a->meta_values)));
		    unset($a->meta_keys);
		    unset($a->meta_values);
			return $a;
		}
	}

	global $aad_wcve;
	$aad_wcve = new aad_wcve();
}
