<?php
/*
Plugin Name: WooCommerce Variation Editor
Plugin URI:  http://action-a-day.com/
Description: WooCommerce plugin to provide spreadsheet style editing for product variations.
Version:     0.2
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
		 * Plugin version
		 */
		const PLUGIN_VER = 0.2;
		
		/**
		 * Active Variable Product ID
		 *
		 * @var int
		 */
		private $product_id;
		
		/**
		 * Table used for generating list of product variations
		 *
		 * @var string
		 */
		private $variation_table;
		
		/**
		 * Errors and warnings to display on admin screens
		 *
		 * @var array 
		 **/
		protected $admin_notices;
		
		function __construct()
		{
			$this->product_id = isset($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : NULL ;
			$this->admin_notices = array();
			$this->admin_notice_errors = array();

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
				// Register admin javascript
				wp_register_script(
					'wcve-admin', 									// Handle
					plugins_url('wcve-admin.js', __FILE__),			// URL to .js file, relative to this directory
				    array('jquery-core'),							// Dependencies
					self::PLUGIN_VER, 								// Script version
					true 											// Place in footer to allow localization if needed
				);
							   
				/**
				 * Register admin CSS file
				 */
				wp_register_style(
					'aad-csve-css',		 							// Handle
					plugins_url('woocommerce-variation-editor.css', __FILE__), 	// URL to CSS file, relative to this directory
					false,	 										// No Dependencies
					self::PLUGIN_VER,								// CSS Version
					'all'											// Use for all media types
				);			   
				
				// Add section for reporting configuration errors and notices
				add_action('admin_notices', array( $this, 'render_admin_notices'));
				
				/*
				  Add option for editing product variations to product rows
				 */
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

			// Pre-render processing for the edit screen
			add_action('load-' . $slug, array($this, 'action_load_table'));

			// Add style sheet and scripts needed in the options page
			add_action('admin_print_scripts-' . $slug, array($this, 'enqueue_admin_scripts'));
			add_action('admin_print_styles-' . $slug, array($this, 'enqueue_admin_styles'));
			// FIXME $this->slug_edit_variations_table_page = $slug; // Save identifier for later
		}
		
		/**
		 * Action called before rendering of page
		 * Handles plugin actions for table content
		 *
		 * @return void
		 */
		function action_load_table()
		{
			if (! $this->product_id) return; // Leave if no product defined

			// Setup class used for display of variations
			$this->variation_table = new wcve_variation_Table($this->product_id);
			
			/*
			  Get details on defined variations
			 */
			$attributes = maybe_unserialize(get_post_meta($this->product_id, '_product_attributes', true));
			$variations = array();
			foreach ($attributes as $attribute) {
				// Only need to handle variations
				if (! $attribute['is_variation']) continue;
				
				// Get terms for attribute taxonomy or value if it's a custom attribute
				if ($attribute['is_taxonomy']) {
					$post_terms = wp_get_post_terms($this->product_id, $attribute['name']);
					$variation = array();
					foreach ($post_terms as $term) {
						$variation[] = array('slug' => $term->slug, 'name' => $term->name);
					}
				} else {
					$options = array_map('trim', explode(WC_DELIMITER, $attribute['value']));
					foreach ($options as $option) {
						$variation[] = array('slug' => $option, 'name' => $option);
					}
				}
				$variations[$attribute['name']] = $variation;
			}
			$this->variation_table->set_variations($variations);

			/**
			 * Perform actions
			 *
			 * Nonce is generated by WP_List_Table class as bulk-pluralName
			 */
			$doaction = $this->variation_table->current_action();
			
			if ($doaction && !empty($_REQUEST) && check_admin_referer('bulk-wve_edit_variations', '_wpnonce')) {
				// Does user have the needed credentials?
				if (! current_user_can('manage_woocommerce')) {
					wp_die('You are not allowed to manage WooCommerce Products');
				}
				
				$sendback = remove_query_arg(array('action', 'action2', '_wpnonce', '_wp_http_referer'), wp_get_referer());
					
				switch($doaction) {
					case 'Update':
						// Save updated variation data
						$message = $this->save_variation_edits();
						break;
					
					default:
						wp_die('Invalid Action ' .$doaction. ' for variation editor table');
						// Don't get here
						break;
				}

				$sendback = add_query_arg('wcve_message', urlencode($message), $sendback);
				wp_redirect($sendback);
				exit;
				// Don't get here
			}
			
			// Display message from save operation
			if (isset($_REQUEST['wcve_message'])) {
				$this->log_admin_notice("green", $_REQUEST['wcve_message']);
			}
		}
		
		/**
		 * Save variation data updates provided in $_REQUEST
		 *
		 * @return string, Completion message to display on back end
		 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
		 */
		private function save_variation_edits()
		{
			/*
			  Save text/number input field values
			 */

			// List of variation fields that might get updated - checkboxes handled separate
			$variation_fields = array("sku", "thumbnail_id", "weight", "length", "width", "height", "stock", "regular_price", "sale_price");
			
			foreach ($variation_fields as $variation_field) {
				if (! isset($_REQUEST[$variation_field])) continue;
				
				foreach($_REQUEST[$variation_field] as $variation_id => $value) {
					$result = update_post_meta($variation_id, '_' . $variation_field, $value);
					if (! $result) goto db_error;
				}
			}
			
			// FIXME - Might need to adjust _price and min/max in parent
			// FIXME - Stock Qty & In Stock might need to be connected
			
			/*
			  Take care of checkbox fields
			 */
			
			if (isset($_REQUEST['orig_manage_stock'])) {
				foreach($_REQUEST['orig_manage_stock'] as $variation_id => $value) {
					$new = isset($_REQUEST['manage_stock'][$variation_id]) ? "yes" : "no";
					if ($value != $new) {
						$result = update_post_meta($variation_id, '_manage_stock', $value);
						if (! $result) goto db_error;
					}
				}
			}
			
			if (isset($_REQUEST['orig_stock_status'])) {
				foreach($_REQUEST['orig_stock_status'] as $variation_id => $value) {
					$new = isset($_REQUEST['stock_status'][$variation_id]) ? "instock" : "outofstock";
					if ($value != $new) {
						$result = update_post_meta($variation_id, '_stock_status', $value);
						if (! $result) goto db_error;
					}
				}
			}
			
			/*
			  And finally select type fields
			 */

			if (isset($_REQUEST['orig_backorders'])) {
				foreach($_REQUEST['orig_backorders'] as $variation_id => $value) {
					$new = isset($_REQUEST['backorders'][$variation_id]) ? $_REQUEST['backorders'][$variation_id] : "allow";
					if ($value != $new) {
						$result = update_post_meta($variation_id, '_backorders', $value);
						if (! $result) goto db_error;
					}
				}
			}
			
			// backorders
			
			return "Updates Saved";
			
			db_error : {
				// TODO Enhance logged error
				error_log(var_dump($result, true));
				return "Update returned $result";
			}
		}
		
		/**
		 * Render content of page used to edit product variations
		 *
		 * @return void
		 */		
		function render_edit_product_variations()
		{
			global $plugin_page;
			?>
			<div class="wrap">
				<div id="icon-edit" class="icon32 icon32-edit-product-variations">
					<br>
				</div>
				<h2><?php _e('Edit Product Variations','aad-wcve'); ?></h2>
				<?php
				if ($this->product_id) {
					// FIXME Remove $_REQUEST['wcve_message'] from form input
					?>
					<form action method="post" accept-charset="utf-8">
						<input type="hidden" name="post_type" value="product">
						<input type="hidden" name="page" value="<?php echo $plugin_page ?>">
						<input type="hidden" name="product_id" value="<?php echo esc_attr($this->product_id) ?>">
						<?php
						// Retrieve product variations for display
						$this->variation_table->prepare_items();
						// $this->variation_table->search_box('search', 'search_id'); // TODO Must follow prepare_items() call
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
		 * Enqueues admin scripts required for page handling
		 *
		 * @return void
		 */
		function enqueue_admin_scripts()
		{
			wp_enqueue_script('wcve-admin');
		}
		
		/**
		 * Enqueues admin style sheets required for page handling
		 *
		 * @return void
		 */
		function enqueue_admin_styles()
		{
			wp_enqueue_style('aad-csve-css');
		}
		
		/**
		 * Add a message to notice messages
		 * 
		 * @param $class, string "red", "yellow", "green".  Selects log message type
		 * @param $msg, string or HTML content to display.  User input should be scrubbed by caller
		 * @return void
		 **/
		function log_admin_notice($class, $msg)
		{
			$this->admin_notices[] = array($class, $msg);
		}
		
		/**
		 * Display Notice messages at head of admin screen
		 *
		 * @return void
		 **/
		function render_admin_notices()
		{
			/*
				WP defines the following classes for display:
					- error  (Red)
					- updated  (Green)
					- update-nag  (Yellow)
			*/

			static $notice_class = array(
				'red' => 'error',
				'yellow' => 'update-nag',
				'green' => 'updated'
			);
		
			if (count($this->admin_notices)) {
				foreach ($this->admin_notices as $notice) {
					// TODO Handle undefined notice class
					echo '<div class="'. $notice_class[$notice[0]] . '">';
					echo '<p>' . wp_kses($notice[1], array(), array()) . '</p>';
					echo '</div>';			
				}
			}
		}
	}

	global $aad_wcve;
	$aad_wcve = new aad_wcve();
}
