<?php
/**
 * class wcve_varation_Table
 *
 * WP_List_Table Class extension for managing product variation lists in admin menu
 *
 * Uses concepts implemented in sample plugin: http://wordpress.org/plugins/custom-list-table-example/
 *
 * Instance should be created during load-* action for the page in question to have screen/column settings correctly initialized.
 *
 * @package WooCommerce Variation Editor
 * @author Kenneth J. Brucker <ken@action-a-day.com>
 * @copyright 2015 Kenneth J. Brucker (email: ken@action-a-day.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 **/

namespace AAD\WCVE;

/*
 *  Protect from direct execution
 */
if ( !defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

class VariationTable extends \WP_List_Table {
	/**
	 * Variable Product
	 *
	 * @var object WC_Product_Variable
	 */
	private $product = NULL;
	
	/**
	 * Product Variation Attributes
	 *
	 * @var associative array of attribute name => array of attributes
	 */
	private $variations;
	
	/**
	 * Array of available backorder options for building select statement
	 *
	 * @var array
	 */
	private $backorder_options;
	
	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	 function __construct($product) {
		 parent::__construct( array(
		'singular'=> 'wcve_edit_variation', //Singular label
		'plural' => 'wcve_edit_variations', //plural label, also this will be one of the table css class
		'ajax'	=> false //We won't support Ajax for this table
		) );
		
		$this->backorder_options = array(
			'no'     => __( 'No', 'aad-wcve' ),
			'notify' => __( 'Notify', 'aad-wcve' ),
			'yes'    => __( 'Yes', 'aad-wcve' )
		);
		
		$this->product = $product;
		$this->variations = $product->get_variation_attributes();
	 }
	 
 	/**
 	 * Provides extra navigation before and after variation table
 	 *
 	 * @return void
 	 **/
	function extra_tablenav( $which )
	{
		if ( 'top' == $which ) {
			/**
			 * Display button to Update values
			 */
			echo '<div class="alignleft actions">';
			submit_button('Update', 'primary', 'action', false);
			echo '</div><div class="alignleft actions">';
			submit_button('Reset', 'button', 'action', false);
			echo '</div>';
			
			/**
			 * Display Filtering drop-downs for each variation property
			 */
			echo '<div class="alignleft actions">';
			$this->render_variation_dropdowns();
			submit_button( __( 'Filter' ), 'button', 'filter_action', false, array( 'id' => 'aad-wcve-query-submit' ) );
			echo '</div>';
		} elseif ( 'bottom' == $which ) {
			/**
			 * Display button to Update values
			 */
			echo '<div class="alignleft actions">';
			submit_button('Update', 'primary', 'action2', false);
			echo '</div><div class="alignleft actions">';
			submit_button('Reset', 'button', 'action2', false);
			echo '</div>';
		}
	}
	
	/**
	 * Render drop-down selectors for each variation attribute of the variable product
	 *
	 * @return void
	 */
	private	function render_variation_dropdowns()
	{
		/**
		 * WC_Product_Variable::get_variation_attributes() will return only those attributes where a variation
		 * is enabled. If all product variations are disabled, the list will be empty.  As a result, the list of
		 * defined attributes is retrieved from DB to build the filter list.
		 */
		foreach ($this->variations as $title => $attributes) {
			$variation_slug = sanitize_title($title);
			/**
			 * If filtering on an attribute was selected for this screen, keep it selected
			 */
			$selected = ! empty($_REQUEST["variation_${variation_slug}"]) ? 
				esc_attr($_REQUEST["variation_${variation_slug}"]) : "all";
			?>
			<label for="filter_by_<?php echo $variation_slug ?>" class="screen-reader-text"><?php echo wc_attribute_label($variation_slug); ?></label>
			<select name="variation_<?php echo $variation_slug; ?>" id="filter_by_<?php echo $variation_slug; ?>">
				<option value="all">Any <?php echo wc_attribute_label($title); ?>...</option>
				<?php
				// TODO Display terms in correct order
				foreach ($attributes as $term) {
					printf("<option value='%s' %s>%s</option>\n",
						esc_attr($term),
						selected($selected, esc_attr($term), false),
						esc_attr($term)
					);
				}
				?>
			</select>
			<?php
		}
	}
	
	/**
	 * Define columns that are used in the table
	 *
	 * Array in form 'column_name' => 'column_title'
	 *
	 * The values's provided for bulk actions are defined in $this->column_cb()
	 *
	 * @return array $columns, array of columns
	 **/
	public function get_columns()
	{
		$columns = array(
            // 'cb'      => '<input type="checkbox" />', //Render a checkbox instead of text
			'var_id' => 'ID',
			'thumbnail_id'   => 'Thumbnail'
		);
		
		// Insert columns for the product variations
		foreach ($this->variations as $title=>$attributes) {
			$slug = sanitize_title($title);
			$columns['attribute_' . $slug] = wc_attribute_label($title);
		}
		
		if (wc_product_sku_enabled())
			$columns['sku'] = __('SKU', 'woocommerce');
		
		$columns = array_merge($columns, array(
			'regular_price' => 'Price (' . get_woocommerce_currency_symbol() . ')',
			'sale_price' => 'Sale Price (' . get_woocommerce_currency_symbol() . ')'
		));
		
		if ( 'yes' == get_option( 'woocommerce_manage_stock' ) ) {
			$columns = array_merge($columns, array(
				'stock_status' => __( 'In stock', 'woocommerce' ), // Checkbox
				'manage_stock' => __('Manage stock?', 'woocommerce'), // Checkbox
				'stock' => 'Stock Qty',
				'backorders' => __( 'Allow Backorders?', 'woocommerce' ) // Dropdown
			));
		}

		/**
		 * Weight and Dimensions only for shippable products
		 */
		if (! $this->product->is_virtual()) {
			if (wc_product_weight_enabled())
				$columns['weight'] =  __( 'Weight', 'woocommerce' ) . ' (' . esc_html( get_option( 'woocommerce_weight_unit' ) ) . ')';
		
			if (wc_product_dimensions_enabled())
				$columns['dimensions'] =  __( 'Dimensions (L&times;W&times;H)', 'woocommerce' ) . ' (' . esc_html( get_option( 'woocommerce_dimension_unit' ) ) . ')';
		}

		return $columns;
	}
	
	/**
	 * Define columns that are sortable
	 *
	 * Array in form 'column_name' => 'database_field_name'
	 *
	 * @return array $sortable, array of columns that can be sorted
	 **/
	public function get_sortable_columns()
	{
		$sortable = array(
			'stock' => array('stock', false),
			'price' => array('price', false),
			'sale_price' => array('sale_price', false),
			'regular_price' => array('regular_price', false)
		);
		
		// Allow sorting on the defined product variations
		foreach ($this->variations as $title=>$attributes) {
			$slug = sanitize_title($title);
			$sortable['attribute_'.$slug] = array('attribute_'.$slug, false);
		}
		
		return $sortable;
	}
	
	/**
	 * Define bulk actions that will work on table
	 *
	 * The actions are dealt with where this class is instantiated.  $this->current_action defines the action requested
	 *
	 * @return array Associative array of bulk actions in form 'slug' => 'visible title'
	 **/
	public function get_bulk_actions()
	{
		// $actions = array(
		// 	'delete' => __('Delete')
		// );

		$actions = array();		
		return $actions;
	}
	
	/**
	 * Prepare table items for display
	 *
	 * Must setup $this->items as array of items for base class to work
	 *
	 * @return void
	 **/
	public function prepare_items()
	{
		global $wpdb;
		
		if (! $this->product) {
			$this->items = array();
			$this->set_pagination_args( array(
				"total_items" => 0,
				"total_pages" => 0,
				"per_page" => 0,
			) );
			return;
		}
		
		// Default sort ordering by variation id
	    $orderby = !empty($_REQUEST["orderby"]) ? $_REQUEST["orderby"] : 'var_id';
	    $order = !empty($_REQUEST["order"]) ? (strtoupper($_REQUEST["order"]) == 'ASC' ? 'ASC' : 'DESC') : 'ASC';
		
		$orderby_clause =  'ORDER BY ' . sanitize_sql_orderby("`$orderby` $order");
	    		
		/**
		 * Add requested Filters
		 */
		$filter = "TRUE";
		foreach ($this->variations as $title => $attributes) {
			$slug = sanitize_title($title);
			if (! empty($_REQUEST["variation_${slug}"])) {
				$value = esc_sql($_REQUEST["variation_${slug}"]);
				if ('all' == $value) continue;
				$filter .= " and `attribute_" . $slug . "` = '$value'";
			}
		}

		/**
		 * Get the table items
		 *
		 * Query is based on information from:
		 *  http://www.dbforums.com/showthread.php?1683775-WordPress-Pivot-Query
		 *  http://stackoverflow.com/questions/11654632/turning-a-wordpress-post-meta-table-into-an-easier-to-use-view
		 *  http://wordpress.stackexchange.com/questions/38530/most-efficient-way-to-get-posts-with-postmeta
		 */
		
		// FIXME Use woocommerce classes to access variation data? Can it be done with speed?
		
		$query = "
			SELECT dt.* FROM (
			SELECT  p.ID as var_id,";
			
		/**
		 * Add the variation attributes to selection
		 */
		foreach ($this->variations as $title => $variation) {
			$slug = sanitize_title($title);
			$query .= "MAX(CASE WHEN pm.meta_key = 'attribute_" . $slug . 
				"' then pm.meta_value ELSE NULL END) as `attribute_" . $slug . "`,";
		}
		
		/**
		 * Add other meta items and complete the query
		 */
		$query .= " MAX(CASE WHEN pm.meta_key = '_sku' then pm.meta_value ELSE NULL END) as sku,
			        MAX(CASE WHEN pm.meta_key = '_thumbnail_id' then pm.meta_value ELSE NULL END) as thumbnail_id,
			        MAX(CASE WHEN pm.meta_key = '_weight' then pm.meta_value ELSE NULL END) as weight,
			        MAX(CASE WHEN pm.meta_key = '_length' then pm.meta_value ELSE NULL END) as length,
			        MAX(CASE WHEN pm.meta_key = '_width' then pm.meta_value ELSE NULL END) as width,
			        MAX(CASE WHEN pm.meta_key = '_height' then pm.meta_value ELSE NULL END) as height,
			        MAX(CASE WHEN pm.meta_key = '_manage_stock' then pm.meta_value ELSE NULL END) as manage_stock,
			        MAX(CASE WHEN pm.meta_key = '_stock_status' then pm.meta_value ELSE NULL END) as stock_status,
			        MAX(CASE WHEN pm.meta_key = '_backorders' then pm.meta_value ELSE NULL END) as backorders,
			        MAX(CASE WHEN pm.meta_key = '_stock' then pm.meta_value ELSE NULL END) as stock,
			        MAX(CASE WHEN pm.meta_key = '_regular_price' then pm.meta_value ELSE NULL END) as regular_price,
			        MAX(CASE WHEN pm.meta_key = '_sale_price' then pm.meta_value ELSE NULL END) as sale_price
			FROM    `$wpdb->posts` p 
			LEFT JOIN `$wpdb->postmeta` pm ON ( pm.post_id = p.ID)
			WHERE
			   p.post_type = 'product_variation' and 
			   p.post_status = 'publish' and 
			   p.post_parent = '" . esc_sql( $this->product->get_id() ) . "'
			GROUP BY
			   p.ID,p.post_title
			) as dt where $filter
			$orderby_clause
		";

		/**
		 * Pagination of table elements
		 */
        $perpage = $this->get_items_per_page('wcve_variations_per_page');
        $totalitems = $wpdb->query($query); 		// Returns the total number of selected rows
        $totalpages = ceil($totalitems/$perpage); 	// How many pages in total?
		$this->set_pagination_args( array(
			"total_items" => $totalitems,
			"total_pages" => $totalpages,
			"per_page" => $perpage,
		) );
		
		/**
		 * Update query to include per-page limit
		 */
		$current_page = absint($this->get_pagenum()); // Which page is this?
		$offset = ($current_page - 1) * $perpage;
		$query .= ' LIMIT ' . $offset . ',' . $perpage;

		/**
		 * Run the query
		 */
		$this->items = $wpdb->get_results($query);
	}
	
	/**
	 * Extend the default display_rows() method to include a special header row for mass edit of all rows
	 *
	 * @return void
	 */
	function display_rows()
	{
		list($columns, $hidden, $sortable) = $this->get_column_info();

		/**
		 * Display row used for mass edit
		 */
		echo '<tr class="wcve-edit-all">';
		foreach ($columns as $column_key => $display_name) {
			switch ($column_key) {
			case 'var_id':
				$cell = "Set All:";
				break;
			
			case 'thumbnail_id':
				$image = wc_placeholder_img(array(40,40)); // Use placeholder in mass-edit row
				$cell = "<div class='wcve-image-selector wcve-edit-all-item'>";
				$cell .= "<input type='hidden' name='all_thumbnail_id' class='input-thumbnail_id' value='' />";
				$cell .= "$image</div>";
				break;
		
			case 'regular_price':
			case 'sale_price':
			case 'weight':
				$cell = "<input type='text' name='all_${column_key}' value id='all_${column_key}' size='5' class='wcve-edit-all-item input-${column_key}' />";
				break;
				
			case 'stock':
				$cell = "<input type='number' size='5' name='all_stock' step='any' class='wcve-edit-all-item input-${column_key}' />";
				break;
				
			case 'manage_stock':
			case 'stock_status':
				$cell = "<input type='checkbox' name='all_$column_key' value='yes' id='all_$column_key' class='wcve-edit-all-item input-${column_key}'/>";
				break;
			
			case 'backorders':
				$cell = "<select name='all_backorders' class='wcve-edit-all-item select-${column_key}'>";
				$cell .= "<option value=''></option>";
					foreach ( $this->backorder_options as $key => $value ) {
						$cell .= sprintf('<option value="%s">%s</option>', esc_attr($key), esc_html($value));
					}
				$cell .= '</select>';
				break;
			
			case 'dimensions':
				$cell = ""; // TODO Dimensions
				break;
		
			default:
				$cell = ""; // Don't create input cell for other fields
				break;
			}
			
			/**
			 * Hide/show column data
			 */
			$style = "";
			if ( in_array( $column_key, $hidden ) )
				$style = ' style="display:none;"';
			echo "<td class='all-$column_key column-$column_key'$style>$cell</td>";
		}
		echo '</tr>';
		
		/**
		 * Now let parent have a turn and display the rest of the table
		 */
		parent::display_rows();
	}
	
	/**
	 * Default formatting for column data
	 *
	 * @param array $item, row of data for presentation
	 * @param string, $column_name
	 * @return string, formatted column data
	 */
	function column_default($item, $column_name)
	{
		/**
		 * If handling an attribute column, value is read-only
		 */
		if (substr($column_name, 0, 10) == 'attribute_') {
			$terms = get_term_by('slug', esc_attr($item->$column_name), substr($column_name, 10));
			return is_object($terms) ? $terms->name : $item->$column_name;
		}
		else
			return "<input type='text' size='5' name='${column_name}[$item->var_id]' value='" . 
				esc_attr($item->$column_name) . "' class='wcve-cell input-${column_name}' />";
	}
		
	/**
	 * Method to provide checkbox column in table
	 *
	 * Provides the REQUEST variable that will contain the selected values
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML to be placed in table cell
	 **/
	function column_cb($item)
	{
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  // Let's simply repurpose the table's singular label
            /*$2%s*/ $item->var_id              // The value of the checkbox should be the record's id
        );
	}
	
	/**
	 * Method to provide HTML for variation ID
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_var_id($item)
	{
		return esc_attr($item->var_id);
	}
	
	/**
	 * Method to provide HTML for thumbnail image
	 *
	 * Manipulated by Javascript to manage media uploader
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_thumbnail_id($item)
	{
		/*
			TODO If thumbnail defined, provide method to remove
		*/
		$remove = $item->thumbnail_id > 0 ? 'remove' : '';
		
		/*
			Get image attachement, use default place holder if none found
		*/
		$image = wp_get_attachment_image($item->thumbnail_id, array(40,40));
		if (empty($image)) $image = wc_placeholder_img(array(40,40)); // Use placeholder if no thumbnail available
		
		return "<div class='wcve-image-selector'><input type='hidden' name='thumbnail_id[$item->var_id]' class='wcve-cell' value='" .esc_attr( $item->thumbnail_id) . "' />$image</div>";
	}
	
	/**
	 * Method to provide HTML for SKU
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_sku($item)
	{
		return "<input type='text' size='5' name='sku[$item->var_id]' value='" . esc_attr($item->sku) . 
			"' placeholder='" . esc_attr($this->product->get_sku()) . "' class='input-sku wcve-cell' />";
	}
	
	/**
	 * Method to provide HTML for regular price
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_regular_price($item)
	{
		$price = esc_attr($item->regular_price);
		$placeholder = __( 'Required', 'aad-wcve' );
		return "<input type='text' size='5' name='regular_price[$item->var_id]' value='$price' class='input-regular_price wc_input_price wcve-cell' placeholder='$placeholder' />";
	}
	
	/**
	 * Method to provide HTML for sale price
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_sale_price($item)
	{
		$price = esc_attr($item->sale_price);
		return "<input type='text' size='5' name='sale_price[$item->var_id]' value='$price' class='input-sale_price wc_input_price wcve-cell' />";
	}

	/**
	 * Method to provide HTML for manage_stock checkbox
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_stock_status($item)
	{
		$id = $item->var_id;
		
		$html = "<input type='hidden' name='orig_stock_status[$id]' value='$item->stock_status'>";
		$html .= "<input type='checkbox' name='stock_status[$id]' value='instock' class='input-stock_status wcve-checkbox'" .
			checked($item->stock_status, "instock", false) . "/>";
		return $html;
	}
	
	/**
	 * Method to provide HTML for manage_stock checkbox
	 *
	 * Use id attribute on input to locate related stock fields via class selector to show/hide managed fields
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_manage_stock($item)
	{
		$id = $item->var_id;
		
		$html = "<input type='hidden' name='orig_manage_stock[$id]' value='$item->manage_stock'>";
        $html .= "<input type='checkbox' id='manage_stock_$id' name='manage_stock[$id]' value='yes' " .
			checked($item->manage_stock, "yes", false) . " class='input-manage_stock wcve-checkbox' />";
		return $html;
	}
	
	/**
	 * Method to provide HTML for stock
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_stock($item)
	{
		$id = $item->var_id;
		$stock = esc_attr($item->stock);
		
		/**
		 * If stock is not being managed, hide this field
		 */
		$hide = "";
		if ($item->manage_stock == NULL || $item->manage_stock == "no")
			$hide = "hidden";
		
		return "<input type='number' size='5' name='stock[$id]' value='$stock' step='any' class='input-stock wcve-cell manage_stock_$id $hide' />";
	}
	
	/**
	 * Method to provide HTML for allow backorders
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_backorders($item)
	{
		$id = $item->var_id;
		
		/**
		 * If stock is not being managed, hide this field
		 */
		$hide = "";
		if ($item->manage_stock == NULL || $item->manage_stock == "no")
			$hide = "hidden";
		
		$html = "<input type='hidden' name='orig_backorders[$id]' value='$item->backorders'>";
		$html .= "<select name='backorders[$id]' class='select-backorders manage_stock_$id $hide wcve-select'>";
				foreach ( $this->backorder_options as $key => $value ) {
					$html .= '<option value="' . esc_attr( $key ) . '" ' . 
						selected( $key === $item->backorders, true, false ) . '>' . esc_html( $value ) . '</option>';
				}
		$html .= '</select>';
		return $html;
	}
	
	
	/**
	 * Method to provide HTML for Dimensions
	 *
     * @see WP_List_Table::::single_row_columns()
	 * @param $item array of row data for presentation
	 * @return string Text or HTML
	 **/
	function column_dimensions($item)
	{
		static $abbrev = array('length'=>'L', 'width'=>'W', 'height'=>'H');
		$html = "";
		$id = $item->var_id;
		
		$dims = array();
		$dims['height'] = $this->product->get_height();
		$dims['width'] = $this->product->get_width();
		$dims['length'] = $this->product->get_length();
		
		foreach (array('length', 'width', 'height') as $dim) {
			$html .= "<div>";
			$html .= "<label for='product_${dim}_${id}'>" . $abbrev[$dim] . ":</label>";
			$html .= "<input type='text' name='${dim}[${id}]' value='" . $item->$dim . "' id='product_${dim}_${id}' placeholder='" . esc_attr( $dims[$dim] ) . "' class='input-${dim} input-text wc_input_decimal wcve-cell' size='6'>";
			$html .= "</div>";
			
		}
		return $html;
	}
	
	/**
	 * Echo text when no items are found in the table
	 *
	 * @return void
	 **/
	function no_items()
	{
		_e('No variations found.', 'aad-wcve');
	}
}		

?>