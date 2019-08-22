<?php
/*
Plugin Name: Auto Cross Tagging
Description: An automatic taxonomy builder for cross promoting content
Version: 1.0
Author: Dave Welch
Author URI: https://github.com/gravyraveydavey
*/

$void = new auto_cross_tagging_plugin();

class auto_cross_tagging_plugin {

	private
		$plugin_base,
		$plugin_base_path,
		$options,
		$debug;

	public function __construct(){

		register_activation_hook(__FILE__, array($this, 'activation') );
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));

		if (class_exists('acf') ){
			add_action('plugins_loaded', array($this, 'init'), 20 );
		}

		//add_action('plugins_loaded', array($this, 'setup_cpt_dump') );
		// $hooks = array(
		// 'registered_post_type',
		// 'wp_loaded',
		// 'wp_loaded',
		// 'adminmenu',
		// 'shutdown'
		// );
		// $this->_log('registering action for: ' . $hook);
		add_action('after_setup_theme', function(){
			$this->cpt_dump('after_setup_theme');
		});
		add_action('wp_loaded', function(){
			$this->cpt_dump('wp_loaded');
		});
	}

	public function _log($message){
		if ( defined('LOCAL_DEV_ENVIRONMENT') ){
			if (LOCAL_DEV_ENVIRONMENT === true){
				error_log( print_r($message, true));
			}
		}
	}

	public function init(){
		add_action( 'init', array($this, 'setup'), 0 );
	}
	public function setup() {

		$this->plugin_base = plugin_dir_url(__FILE__);
		$this->plugin_base_path = plugin_dir_path(__FILE__);
		$this->debug = false;

		$this->populate_options();
		//$this->_log('saved options');
		//$this->_log($this->options);

		// admin stuff
		add_action('admin_menu', array($this,'menu_page'), 10);

		add_action( 'admin_init', array($this,'registration' ));
		add_action( 'admin_init', array($this,'register_fields' ));

		add_action( 'admin_enqueue_scripts', array($this,'auto_tax_admin_css'), 11 );

		// run before ACF saves the $_POST['fields'] data (so we can add in the returned term ID)
		add_action('acf/save_post', array($this,'save_hook'), 5);

		add_action('pre_delete_term', array($this,'delete_auto_term_hook'), 10, 2);

		add_filter("manage_edit-columns", array($this,'admin_columns'));

		add_filter("manage_custom_column", array($this,'custom_column'), 10, 3);


		//$this->autosave_override();		// this needs rethinking - have to write to config file
	}

	public function activation() {
		//$this->_log('activation hook!');
		$this->populate_options();
		//$this->_log($this->options);
	}

	public function populate_options(){
		if (!isset($this->options)) $this->options = get_option('auto_taxonomies_options');
	}

	public function deactivation() {
		//$this->_log('deactivation hook!');
	}

	public function auto_tax_admin_css() {
		// used to hide the edit / add interface elements on the term page
		// and the meta box that stores the term id on the source page
		wp_enqueue_style('auto_tax_admin_css', plugins_url('css/wp-admin.css', __FILE__));
	}

	public function delete_auto_term_hook($term_id, $taxonomy){
			// bail early if not an auto_tax term
			if( $taxonomy !== 'auto_taxonomies') {
					return;
			}
			$term = get_term($term_id);
			if ($term){
				// update postmeta for source page to remove auto tax
				update_field('field_591c213c8a3ac', 0, $term->description);
				update_field('field_591c6d710a65b', '', $term->description);
			}
	}


	public function admin_columns($columns) {
		// removes the description column
			$new_columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'origin_header' => __('Origin ID'),
				'slug' => __('Slug'),
				'posts' => __('Posts')
			);
			return $new_columns;
	}


	public function custom_column($string, $column_name, $term_id) {
		// turns description colummn into a link to original source of term
			$term = get_term($term_id, 'auto_taxonomies');
			switch ($column_name) {
					case 'origin_header':
							// get header image url
							$string .= "<a href=".get_edit_post_link($term->description).">".$term->description."</a>";
							break;
					default:
							break;
			}
			return $string;
	}


	public function save_hook( $post_id ){

			// bail early if no ACF data
			if( empty($_POST['acf']) ) {
					return;
			}

		//_log('some acf data found...');
			if ($_POST['acf']['field_591c213c8a3ac']){

				$post = get_post($post_id);
			$term_name = $post->post_title;
			$term_slug = 'auto-tax-'.$post->post_name;

				if ($_POST['acf']['field_591c6d710a65b']){
					// existing ID found in postmeta, use this as lookup
				$term_id = intval($_POST['acf']['field_591c6d710a65b']);
				//_log('looking for term id '.$term_id);
					$term = term_exists( $term_id, 'auto_taxonomies' );

				} else {
					// attempt to find term by post title
				//_log('looking for term '.$term_name);
				$term = term_exists( $term_name, 'auto_taxonomies' );
				}

			if ($term){

				$term_id = $term['term_id'];
				//_log('existing term found for '.$term_name);
				//_log($term);
				wp_update_term( $term_id, 'auto_taxonomies', array('name' => $term_name, 'slug' => $term_slug, 'description' => $post_id) );

			} else {

				//_log('create term for '.$term_name);
				$term = wp_insert_term( $term_name, 'auto_taxonomies', array('slug' => $term_slug, 'description' => $post_id) );
				$term_id = $term['term_id'];

			}
			//_log($term);
			$_POST['acf']['field_591c6d710a65b'] = $term_id;

			} else {
				// maybe initiate an auto clean up here to kill off the term
			}

	}

	public function registration(){

		// open a filter for customizing the CPTs
		$users = (array) $this->options['at_cpts_users'];

		$attachable_post_types = apply_filters( 'auto_taxonomies_attach_to_types', $users );

		$labels = array(
			'name'                       => _x( 'Content Cross Tagging', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'              => _x( 'Cross Tag', 'Taxonomy Singular Name', 'text_domain' ),
			'menu_name'                  => __( 'Cross Tagging', 'text_domain' ),
			'all_items'                  => __( 'All Items', 'text_domain' ),
			'parent_item'                => __( 'Parent Item', 'text_domain' ),
			'parent_item_colon'          => __( 'Parent Item:', 'text_domain' ),
			'new_item_name'              => __( 'New Item Name', 'text_domain' ),
			'add_new_item'               => __( 'Add New Item', 'text_domain' ),
			'edit_item'                  => __( 'Edit Item', 'text_domain' ),
			'update_item'                => __( 'Update Item', 'text_domain' ),
			'view_item'                  => __( 'View Item', 'text_domain' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'text_domain' ),
			'add_or_remove_items'        => __( 'Add or remove items', 'text_domain' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
			'popular_items'              => __( 'Popular Items', 'text_domain' ),
			'search_items'               => __( 'Search Items', 'text_domain' ),
			'not_found'                  => __( 'Not Found', 'text_domain' ),
			'no_terms'                   => __( 'No items', 'text_domain' ),
			'items_list'                 => __( 'Items list', 'text_domain' ),
			'items_list_navigation'      => __( 'Items list navigation', 'text_domain' ),
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => false,
			'show_tagcloud'              => false,
		);

		register_taxonomy( 'auto_taxonomies', $attachable_post_types, $args );

	}

	public function register_fields(){

		if( function_exists('acf_add_local_field_group') ){

			$location_array = array();
			$creators = (array) $this->options['at_cpts_creators'];
			$cpts_option = apply_filters( 'auto_taxonomies_attach_to_types', $creators );

			foreach( $cpts_option as $custom_post_type ) {
				$location_array[] =  array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => $custom_post_type,
					),
				);
			}

			$auto_tax_field = array(
				'key' => 'group_591c211ca2c29',
				'title' => 'Content Cross Tagging',
				'fields' => array (
					array (
						'key' => 'field_591c213c8a3ac',
						'label' => 'Create AutoTaxonomy?',
						'name' => 'create_autotaxonomy_from_page',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 0,
						'ui' => 0,
						'ui_on_text' => '',
						'ui_off_text' => '',
					),
					array (
						'key' => 'field_591c6d710a65b',
						'label' => 'auto_tax_term_id',
						'name' => 'auto_tax_term_id',
						'type' => 'number',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array (
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'min' => '',
						'max' => '',
						'step' => '',
					),
				),
				'location' => $location_array,
				'menu_order' => 0,
				'position' => 'side',
				'style' => 'default',
				'label_placement' => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen' => '',
				'active' => 1,
				'description' => '',
			);

			acf_add_local_field_group( $auto_tax_field );

		}

	}

	public function menu_page(){

		$page_title        = 'Auto Cross Tagging';
		$menu_title        = 'Auto Cross Tagging';
		$capability        = 'manage_options';
		$menu_slug         = 'auto-taxonomies';
		$callback_function = array($this, 'admin_page');

		add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback_function);

	}

	public function admin_page() {

		// General check for user permissions.
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.')    );
		}
		include_once(__DIR__ . '/options.php');
	}

	public function cpt_dump($hook){
		$default_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		$custom_cpts = get_post_types( array( 'public' => true, '_builtin' => true ), 'names' );
		$cpts = array_merge($default_cpts, $custom_cpts);
		$this->_log('CPTS FROM HOOK: ' . $hook);
		$this->_log($cpts);
	}
}


/////
