<?php
/*
Plugin Name: WooCommerce Variation Editor
Plugin URI:  http://action-a-day.com/
Description: WooCommerce plugin to provide spreadsheet style editing for product variations.
Version:     0.4
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

/**
 * Only setup on admin screens
 */
if (is_admin() && ! class_exists("aad_wcve")) {
	if (! include_once('class-wcve-variation-table.php')) return false;

	class aad_wcve
	{
		/**
		 * Plugin version
		 */
		const PLUGIN_VER = 0.4;
		
		/**
		 * Default number of variations displayed per screen
		 */
		const DEFAULT_PER_PAGE = 20;
		
		/**
		 * Slug for menu page
		 */
		const MENU_SLUG = 'edit-product-variations';
		
		/**
		 * Active Variable Product ID
		 *
		 * @var int
		 */
		private $product_id;
		
		/**
		 * Active Variable Product
		 *
		 * @var Object, class WC_Product_Variable
		 */
		private $product;
		
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
		
		/**
		 * Instantiate class
		 */
		public function __construct()
		{
			$this->product_id = ! empty($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : NULL ;
			$this->admin_notices = array();
			$this->admin_notice_errors = array();
			
			// Filter needed for admin screen options on custom screens to save - filter is executed early in processing.
			add_filter('set-screen-option', array($this, 'filter_set_screen_option'), 10, 3 );

			// Setup admin page for editing variable products
			add_action('admin_menu', array($this, 'action_admin_menu'));

			// Do plugin initialization
			add_action('admin_init', array($this, 'action_admin_init'));
		}
		
		/**
		 * Filter used to save option values for admin screens
		 *
		 * If the option is one that has been defined by plugin, return the value
		 *
		 * @return value for screen option
		 **/
		public function filter_set_screen_option($status, $option, $value)
		{
			if ('wcve_variations_per_page' == $option) {
				$status = (int)$value;
			}

			return $status;
		}
		
		/**
		 * Hook into WP after plugins have loaded, runs during admin_init
		 *
		 * Register scripts, css, add filter to row handling for page/post screens used to display product list.
		 *
		 * @return void
		 */
		public function action_admin_init()
		{
			/**
			 * User must be able to manage WooCommerce to continue  
			 * TODO there might be a more granular privilege to check
			 */
			if (! current_user_can('manage_woocommerce')) return;
			
			/**
			 * Only hook if WooCommerce is available
			 */
			if (class_exists('WooCommerce')) {
				/**
				 * Register admin javascript
				 */
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
				
				/**
				 * Add section for reporting configuration errors and notices
				 */
				add_action('admin_notices', array( $this, 'render_admin_notices'));
				
				/*
				 * Add option for editing product variations to Product Row screen of WooCommerce
				 */
				add_filter('page_row_actions', array($this, 'filter_make_edit_variation_link_row'), 10, 2);				
				add_filter('post_row_actions', array($this, 'filter_make_edit_variation_link_row'), 10, 2);
			}
		}
		
		/**
		 * When rendering product lists, add option to edit variable products
		 *
	     * This filter will only be active if user is able to manage WooCommerce products
		 *
		 * @param $actions, array of actions to be displayed for the Post
		 * @param $post, Current Post object
		 * @return array of actions to be displayed for the Post
		 */
		public function filter_make_edit_variation_link_row($actions, $post) {
			if ($post->post_type != 'product') return $actions; // Only interested in WooCommerce products

			$terms = wp_get_object_terms($post->ID, 'product_type');

			if (count($terms) > 0) {
				$product_type = sanitize_title(current($terms)->name);
			
				/**
				 * For variable products, add the edit variation action 
				 */
			    if ($product_type == 'variable') {
					$url = str_replace('#038;', '&', menu_page_url(self::MENU_SLUG, false));
					$url = add_query_arg('product_id', esc_attr($post->ID), $url);
				
			        $actions['edit_product_variations'] = '<a href="' . esc_url($url) . '" title="'
			            . esc_attr(__("Edit Product Variations", 'aad-wcve'))
			            . '">' .  __('Edit Variations', 'aad-wcve') . '</a>';
			    }
			}

		    return $actions;
		}
		
		/**
		 * Create admin menu item for editing variable products and register supporting actions
		 *
		 * @return void
		 */
		public function action_admin_menu()
		{
			/**
			 * User must be able to manage WooCommerce
			 */
			if (! current_user_can('manage_woocommerce')) return;
			
			$hook_suffix = add_submenu_page('edit.php?post_type=product', 
				__('Edit Product Variations', 'aad-wcve'), 
				__('Edit Variations', 'aad-wcve'), 
				'manage_woocommerce', // If user can manage WooCommerce
				self::MENU_SLUG,  // Slug for this menu
				array($this, 'render_edit_product_variations') // Method to render screen
			);

			/**
			 * Pre-render processing for the variation edit screen
			 */
			add_action('load-' . $hook_suffix, array($this, 'action_load_table'));

			/**
			 * Add style sheet and scripts needed on the variation edit screen
			 */
			add_action('admin_print_scripts-' . $hook_suffix, array($this, 'enqueue_admin_scripts'));
			add_action('admin_print_styles-' . $hook_suffix, array($this, 'enqueue_admin_styles'));
		}
		
		/**
		 * Action called before rendering of screen
		 * Handles plugin actions for table content
		 *
		 * @return void
		 */
		public function action_load_table()
		{
			if (! $this->product_id) return; // Leave if no product defined
			$this->product = wc_get_product($this->product_id);
			if (! $this->product || (! $this->product->is_type('variable'))) {
				$this->product_id = NULL; // If product could not be retrieved or it's not a variable product, reset
				unset($this->product);
				return;
			}

			/**
			 * Setup screen options
			 */
			add_screen_option('per_page', array(
				'label' => 'Product Variations per page',
				'default' => self::DEFAULT_PER_PAGE,
				'option' => 'wcve_variations_per_page'
			));
			
			/**
			 * Setup Table class used to display product list variations
			 */
			$this->variation_table = new wcve_variation_Table($this->product);
			
			/**
			 * Perform actions
			 *
			 * Nonce is generated by WP_List_Table class as bulk-pluralName
			 */
			$doaction = $this->variation_table->current_action();
			
			if ($doaction && !empty($_REQUEST) && check_admin_referer('bulk-wcve_edit_variations', '_wpnonce')) {
				/**
				 * Does user have the needed credentials?
				 */
				if (! current_user_can('manage_woocommerce')) {
					wp_die('You are not allowed to manage WooCommerce Products');
				}
				
				/**
				 * Create redirect URL, strip some form elements for the redirect
				 */
				$sendback = remove_query_arg(array('action', 'action2', '_wpnonce', '_wp_http_referer'), wp_get_referer());
				
				/**
				 * If a filter was active, preserve on redirect
				 */
				foreach ($this->product->get_variation_attributes() as $slug => $attributes) {
					if (! empty($_REQUEST["variation_${slug}"])) {
						$sendback = add_query_arg("variation_${slug}", urlencode($_REQUEST["variation_${slug}"]), $sendback);
					}
				}

				switch($doaction) {
					case 'Mass Edit':
						$metadata = $this->build_mass_edit_metadata();
						$this->save_metadata($metadata);
						break;
					
					case 'Update':
						/**
						 * Save Updated fields for displayed variations
						 */
						$metadata = $this->build_update_metadata();
						$this->save_metadata($metadata);
						break;
						
					case 'Reset':
						/**
						 * Screen refresh - no changes will be applied
						 */
						break;
					
					default:
						wp_die('Invalid Action ' .$doaction. ' for variation editor table');
						// Don't get here
						break;
				}

				// FIXME $sendback = add_query_arg('wcve_message', urlencode($message), $sendback);
				wp_safe_redirect($sendback);
				exit;
				// Don't get here
			}
			
			// Display message from save operation
			if (! empty($_REQUEST['wcve_message'])) {
				$this->log_admin_notice("green", $_REQUEST['wcve_message']);
			}
		}
		
		/**
		 * Build list of associative arrays containing meta fields to be updated
		 *
		 * @return array [variation_id][field] = value
		 */
		private function build_update_metadata()
		{
			$variation_updates = array();
			
			/*
			  Save text/number input field values
			 */
			$variation_fields = array("sku", "thumbnail_id", "weight", "length", "width", "height", "stock", "regular_price", "sale_price");
			
			foreach ($variation_fields as $variation_field) {
				if (empty($_REQUEST[$variation_field])) continue;
				
				foreach($_REQUEST[$variation_field] as $variation_id => $value) {
					if (empty($variation_updates[$variation_id]))
						$variation_updates[$variation_id] = array();
					$variation_updates[$variation_id][$variation_field] = $value;
				}
			}
			
			/*
			  Take care of checkbox fields
			 */
			if (! empty($_REQUEST['orig_manage_stock'])) {
				foreach($_REQUEST['orig_manage_stock'] as $variation_id => $value) {
					$new = ! empty($_REQUEST['manage_stock'][$variation_id]) ? 'yes' : 'no';
					if ($value != $new) {
						if (empty($variation_updates[$variation_id]))
							$variation_updates[$variation_id] = array();
						$variation_updates[$variation_id]['manage_stock'] = $new;
					}
				}
			}
			
			if (! empty($_REQUEST['orig_stock_status'])) {
				foreach($_REQUEST['orig_stock_status'] as $variation_id => $value) {
					$new = ! empty($_REQUEST['stock_status'][$variation_id]) ? 'instock' : 'outofstock';
					if ($value != $new) {
						if (empty($variation_updates[$variation_id]))
							$variation_updates[$variation_id] = array();
						$variation_updates[$variation_id]['stock_status'] = $new;
					}
				}
			}
			
			/*
			  And finally select type fields
			 */
			if (! empty($_REQUEST['orig_backorders'])) {
				foreach($_REQUEST['orig_backorders'] as $variation_id => $value) {
					$new = ! empty($_REQUEST['backorders'][$variation_id]) ? $_REQUEST['backorders'][$variation_id] : "allow";
					if ($value != $new) {
						if (empty($variation_updates[$variation_id]))
							$variation_updates[$variation_id] = array();
						$variation_updates[$variation_id]['backorders'] = $new;
					}
				}
			}
			
			return $variation_updates;		
		}
		
		/**
		 * When Mass Edit is requested, generate individual elements to update each product variation
		 *
		 * @return array [variation_id][field] = value
		 */
		private function build_mass_edit_metadata()
		{
			$variation_updates = array();
			
			/**
			 * Visible variations
			 */
			$ids = ! empty($_REQUEST['all_ids']) ? array_map('intval', explode(',', $_REQUEST['all_ids'])) : array();
			if (count($ids) == 0)
				return array();
			
			/**
			 * Fields that are mass changeable
			 */
			$fields = array('thumbnail_id', 'regular_price', 'sale_price', 'stock', 'weight', 'length', 'width', 'height');
			
			/**
			 * For each field, set value for all visible variations
			 */
			foreach ($fields as $field) {
				if (! empty($_REQUEST['all_' . $field])) {
					$value = $_REQUEST['all_' . $field];
					
					foreach ($ids as $id) {
						if (empty($variation_updates[$id]))
							$variation_updates[$id] = array();
						$variation_updates[$id][$field] = $value;
					}
				}
			}
			
			return $variation_updates;
		}
		
		/**
		 * Save variation updates to DB
		 *
		 * Available Fields:
		 *  - sku
		 *	- thumbnail_id
		 *	- regular_price
		 *	- sale_price
		 *	- stock_status
		 *	- manage_stock
		 *	- stock
		 *	- backorders
		 *	- weight
		 *	- length, width, height
		 *
		 * See woocommerce/includes/admin/meta-boxes/class-wc-meta-box-product-data.php
		 *
		 * @param array [variation_id][field] = value
		 * @return void
		 */
		private function save_metadata($variation_updates)
		{
			if (count($variation_updates) == 0) return;
			$price_changed = false;
			
			foreach ($variation_updates as $variation_id => $fields) {
				/**
				 * SKU
				 */
				if (isset($fields['sku'])) {
					$sku = get_post_meta($variation_id, '_sku', true);
					$new_sku = wc_clean(stripslashes($fields['sku']));
					
					if ('' == $new_sku || empty($new_sku)) {
						update_post_meta($variation_id, '_sku', '');
					} else {
						/**
						 * Update SKU in compliance with unique requirements, if any
						 */
						$unique_sku = wc_product_has_unique_sku( $variation_id, $new_sku );
						if (!$unique_sku) {
							$this->add_error($variation_id, 'sku', 'SKU must be unique');
						} else {
							update_post_meta($variation_id, '_sku', $new_sku);
						}
					}
				}
				
				/**
				 * Thumbnail
				 */
				if (isset($fields['thumbnail_id'])) {
					update_post_meta($variation_id, '_thumbnail_id', absint($fields['thumbnail_id']));
				}
				
				/**
				 * Price fields - If one of the price related fields is set determine new price for variation
				 */
				if (isset($fields['regular_price']) || isset($fields['sale_price']) || 
					isset($fields['date_from']) || isset($fields['date_to'])) {
						/**
						 * Collect new price & sale_price settings either from provided input or DB
						 */
						$regular_price = isset($fields['regular_price']) ? wc_format_decimal($fields['regular_price']) : 
							get_post_meta($variation_id, '_regular_price', true);
						$sale_price = isset($fields['sale_price']) ? wc_format_decimal($fields['sale_price']) :
							get_post_meta($variation_id, '_sale_price', true);
						$date_from = isset($fields['date_from']) ? 
							($fields['date_from'] === '' ? '' : strtotime(wc_clean($fields['date_from']))) 
							:  get_post_meta($variation_id, '_sale_price_dates_from', true);
						$date_to = isset($fields['date_to']) ? 
							($fields['date_to'] === '' ? '' : strtotime(wc_clean($fields['date_to']))) 
							:  get_post_meta($variation_id, '_sale_price_dates_to', true);
						$now = strtotime( 'NOW', current_time( 'timestamp' ));
						
						if ($date_to && !$date_from)
							$date_from = $now;
						
						/**
						 * If no dates specified for sale
						 */
						if ($sale_price && !$date_to && !$date_from) {
							$price = $sale_price;
						} else {
							$price = $regular_price;
						}
						
						/**
						 * If sale has started ...
						 */
						if ($sale_price && $date_from && $date_from < $now) {
							$price = $sale_price;
						}
						
						/**
						 * ... But if we're past the end of the sale
						 */
						if ($date_to && $date_to < $now) {
							$price = $regular_price;
							$date_from = '';
							$date_to = '';
						}
						
						/**
						 * Now that that's all sorted out, save the pricing for this variation
						 */
						update_post_meta($variation_id, '_price', $price);
						update_post_meta($variation_id, '_regular_price', $regular_price);
						update_post_meta($variation_id, '_sale_price', $sale_price);
						update_post_meta($variation_id, '_sale_price_dates_from', $date_from);
						update_post_meta($variation_id, '_sale_price_dates_to', $date_to);
						$price_changed = true;
				} // End Price
				
				/**
				 * Stock
				 */
				if (isset($fields['stock_status'])) {
					wc_update_product_stock_status( $variation_id, $fields['stock_status']);
				}

				$manage_stock = isset($fields['manage_stock']) ? 
					$fields['manage_stock'] : get_post_meta($variation_id, '_manage_stock', true);
				update_post_meta($variation_id, '_manage_stock', $manage_stock);
				
				if ($manage_stock == "yes") {
					if (isset($fields['backorders'])) {
						// FIXME Validate field value
						update_post_meta($variation_id, '_backorders', $fields['backorders']);
					}
					if (isset($fields['stock'])) {
						wc_update_product_stock($variation_id, wc_stock_amount($fields['stock']));
					}
				} else {
					delete_post_meta($variation_id, '_backorders');
					delete_post_meta($variation_id, '_stock');
				}
				
				/**
				 * Weight & Dimensions
				 */
				if (! $this->product->is_virtual()) {
					foreach (array('weight', 'width', 'length', 'height') as $field) {
						if (isset($fields[$field])) {
							update_post_meta($variation_id, '_' . $field ,
								$fields[$field] === '' ? '' : wc_format_decimal($fields[$field]));
						}
					}
				}
			} // End for each product variation
			
			/**
			 * Update variable parent to keep prices in sync
			 */
			if ($price_changed)
				WC_Product_Variable::sync($this->product->id);
		}
				
		/**
		 * Render content of page used to edit product variations
		 *
		 * @return void
		 */		
		function render_edit_product_variations()
		{
			$title = ! empty($this->product) ? $this->product->get_title() : "Undefined";
			
			/**
			 * Build form url
			 */
			$formurl = str_replace('#038;', '&', menu_page_url(self::MENU_SLUG, false));
			$formurl = add_query_arg('product_id', esc_attr($this->product_id), $formurl);
			
			?>
			<div class="wrap">
				<div id="icon-edit" class="icon32 icon32-edit-product-variations">
					<br>
				</div>
				<h2><?php printf(__('Edit Product Variations for %s','aad-wcve'), esc_attr($title)); ?></h2>
				<?php
				if ($this->product_id) {
					?>
					<form action="<?php echo esc_url($formurl); ?>" method="post" accept-charset="utf-8">
						<?php
						// Retrieve product variations for display
						$this->variation_table->prepare_items();
						// $this->variation_table->search_box('search', 'search_id'); // TODO Search Box must follow prepare_items() call
						$this->variation_table->display();
						?>
					</form>
					<?php
				} else {
					$url = add_query_arg('product_type', 'variable', admin_url('edit.php?post_type=product'));
					echo 'Please use the <a href="' . esc_url($url) . '">Product List</a> to select a variable product to edit';
				}
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
			wp_enqueue_media(); // Enqueue the WP media scripts
			
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
