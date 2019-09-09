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
		$users,
		$creators,
		$tax_prefix,
		$acf_bool_field_id,
		$acf_tax_id_field_id,
		$debug;

	public function __construct(){

		register_activation_hook(__FILE__, array($this, 'activation') );
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));

		if (class_exists('acf') ){
			add_action('plugins_loaded', array($this, 'init'), 20 );
		}

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
		//$this->tax_prefix = 'auto_tax_';
		$this->tax_prefix = '';
		$this->acf_bool_field_id = 'field_591c213c8a3ac';
		$this->acf_tax_id_field_id = 'field_591c6d710a65b';

		$this->populate_options();
		//$this->_log('saved options');
		//$this->_log($this->options);

		// admin stuff
		add_action('admin_menu', array($this,'menu_page'), 10);

		add_action( 'registered_post_type', array($this,'registration' ));
		add_action( 'admin_init', array($this,'register_fields' ));

		add_action( 'admin_enqueue_scripts', array($this,'auto_tax_admin_css'), 11 );

		// run before ACF saves the $_POST['fields'] data (so we can add in the returned term ID)
		add_action('acf/save_post', array($this,'save_hook'), 5);
		add_action('acf/prepare_field/key='.$this->acf_bool_field_id, array($this,'prepare_acf_field') );

		add_action('pre_delete_term', array($this,'delete_auto_term_hook'), 10, 2);
		add_action( 'before_delete_post', array($this,'delete_post_hook'), 10, 2);

		foreach($this->creators as $tax){
			$tax = $this->tax_prefix.$tax;
			//$this->_log('running filter on '.$tax);
			add_filter("manage_edit-".$tax."_columns", array($this,'admin_columns'));
			add_filter("manage_".$tax."_custom_column", array($this,'custom_column'), 10, 3);
		}

		if ($this->tax_prefix !== ''){
			add_action('pre_get_posts', array($this, 'auto_prefix_tax_queries'), 999);
		} else {
			add_action('pre_get_posts', array($this, 'auto_prefix_tax_queries_admin_only'), 10);
		}
		add_filter( 'query_vars', array($this, 'add_admin_query_vars') );
		//$this->autosave_override();		// this needs rethinking - have to write to config file

		add_filter( 'parse_query', array($this, 'handle_auto_tax_admin_queries'), 0 );
		add_action( 'restrict_manage_posts', array($this, 'add_filter_to_admin_listing') );
	}

	public function handle_auto_tax_admin_queries( $query ){
			global $pagenow;
			$type = 'post';
			if (isset($_GET['post_type'])) {
					$type = $_GET['post_type'];
			}
			//$this->_log('parse admin query');
			foreach($this->users as $auto_tax => $assignments){
				//$this->_log('looping ' . $auto_tax .' assignments: ');
				foreach($assignments as $post_type){
					//$this->_log('checking cpt: '. $post_type);
					if ( $post_type == $type && is_admin() && $pagenow == 'edit.php') {
							//$this->_log('looking for auto_tax_'.$auto_tax . ' in get');
							if (isset($_GET['auto_tax_'.$auto_tax])){
								//$this->_log('match - add query var from get, val: ' . $_GET['auto_tax_'.$auto_tax]);
								$query->query_vars['auto_tax_'.$auto_tax] = $_GET['auto_tax_'.$auto_tax];
								//$this->_log($query);
								//add_admin_query_vars('auto_tax_solution', $_GET['solution']);
							}
					}
				}
			}
	}

	public function add_filter_to_admin_listing(){
		global $pagenow;
		$type = 'post';
		if (isset($_GET['post_type'])) {
				$type = $_GET['post_type'];
		}
		//$this->_log('add filter dropdown to admin listing page');
		foreach($this->users as $auto_tax => $assignments){
			//$this->_log('looping ' . $auto_tax .' assignments: ');
			foreach($assignments as $post_type){
				//$this->_log('checking cpt: '. $post_type);
				if ( $post_type == $type && is_admin() && $pagenow == 'edit.php') {
					?>
					<select name="auto_tax_<?php echo $auto_tax;?>">
						<option value="">All <?php echo $auto_tax;?>s</option>
						<?php
							$auto_tax_terms = get_terms( array(
								'taxonomy' => $auto_tax,
								'hide_empty' => true,
							));
							$current = get_query_var('auto_tax_'.$auto_tax, '');
							//$this->_log('checking query vars for auto_tax_'.$auto_tax.' : '.$current);
							foreach ($auto_tax_terms as $term) {
								$selected = ($term->slug == $current) ? ' selected="selected"':'';
								printf(
									'<option value="%s"%s>%s</option>',
									$term->slug,
									$selected,
									$term->name
								);
							}
						?>
					</select>
					<?php
				}
			}
		}
	}

	public function activation() {
		//$this->_log('activation hook!');
		$this->populate_options();
		//$this->_log($this->options);
	}

	public function populate_options(){
		if (!isset($this->options)) $this->options = get_option('auto_taxonomies_options', array());

		$this->users = array();
		if ( array_key_exists('at_cpts_users', $this->options)) {
			$this->users = (array) $this->options['at_cpts_users'];
		}
		$this->creators = array();
		if ( array_key_exists('at_cpts_creators', $this->options)) {
			$this->creators = (array) $this->options['at_cpts_creators'];
		}
	}

	public function deactivation() {
		//$this->_log('deactivation hook!');
	}

	public function auto_tax_admin_css() {
		// used to hide the edit / add interface elements on the term page
		// and the meta box that stores the term id on the source page
		wp_enqueue_style('auto_tax_admin_css', plugins_url('css/wp-admin.css', __FILE__));
		add_action('admin_head', array($this, 'add_tax_page_css'));
	}


	public function delete_auto_term_hook($term_id, $taxonomy){
		// bail early if not an auto_tax term
		$check_tax = str_replace($this->tax_prefix, '', $taxonomy);
		if( !in_array($check_tax, $this->creators)) {
				return;
		}
		$term = get_term($term_id);
		if ($term){
			// update postmeta for source page to remove auto tax
			update_field($this->acf_bool_field_id, 0, $term->description);
			update_field($this->acf_tax_id_field_id, '', $term->description);
		}
	}

	public function delete_post_hook( $postid ){

		global $post_type;
		// bail on action if not an auto tax creator
		if ( !in_array($post_type, $this->creators ))  return;

		//$this->_log('post id: '.$postid);
		$term_id = get_field($this->acf_tax_id_field_id, $postid);
		//$this->_log('deleting post from an auto tax creator - delete term also');
		//$this->_log('term: '.$term_id);
		if ($term_id){
			//$this->_log('attempting to delete term');
			$taxonomy = $this->tax_prefix.$post_type;
			$result = wp_delete_term( $term_id, $taxonomy );
			//$this->_log($result);
		}

	}

	public function admin_columns($columns) {
		// removes the description column
		//$this->_log('running custom admin column filter');
		//$this->_log($columns);
		$type = 'post';
		if (isset($_GET['post_type'])) {
				$type = $_GET['post_type'];
		}

		if ( !in_array($type, $this->creators) ){
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'name' => __('Name'),
				'origin_header' => __('Origin ID'),
				'slug' => __('Slug'),
				'posts' => __('Posts')
			);
		}
		return $columns;
	}


	public function custom_column($string, $column_name, $term_id) {
		// turns description colummn into a link to original source of term
		$type = 'post';
		if (isset($_GET['post_type'])) {
				$type = $_GET['post_type'];
		}

		if ( !in_array($type, $this->creators) ){
			global $taxonomy;

			$term = get_term($term_id, $taxonomy);
			if (!is_wp_error($term)){
				switch ($column_name) {
						case 'origin_header':
								// get header image url
								$string .= "<a href=".get_edit_post_link($term->description).">".$term->description."</a>";
								break;
						default:
								break;
				}
			}
		}
		return $string;
	}


	public function save_hook( $post_id ){

		//$this->_log('save post hook');
		//$this->_log($_POST);
		// bail early if no ACF data
		if( empty($_POST['acf']) ) {
				return;
		}

		if (in_array($_POST['post_type'], $this->creators) && $_POST['acf'][$this->acf_bool_field_id]){
			$taxonomy = $this->tax_prefix.$_POST['post_type'];
			$term_id = $this->add_auto_tax_term( $post_id, $_POST['acf'][$this->acf_bool_field_id], $_POST['acf'][$this->acf_tax_id_field_id], $taxonomy );
			//$this->_log('created term - id: '.$term_id);
			if ($term_id) $_POST['acf'][$this->acf_tax_id_field_id] = $term_id;
		}

	}

	public function add_auto_tax_term( $post_id, $bool_field, $term_id_field, $tax ){
		//$this->_log('adding term to '.$tax);
		$term_id = false;
		//$this->_log('some acf data found...');
		if ($bool_field){

			$post = get_post($post_id);
			$term_name = $post->post_title;
			$term_slug = $post->post_name;

			if ($term_id_field){
				// existing ID found in postmeta, use this as lookup
				$term_id = intval($term_id_field);
				//$this->_log('looking for term id '.$term_id);
				$term = term_exists( $term_id, $tax );

			} else {
				// attempt to find term by post title
				//$this->_log('looking for term '.$term_name);
				$term = term_exists( $term_name, $tax );
			}

			if ($term){

				$term_id = $term['term_id'];
				//$this->_log('existing term found for '.$term_name);
				//$this->_log($term);
				wp_update_term( $term_id, $tax, array('name' => $term_name, 'slug' => $term_slug, 'description' => $post_id) );

			} else {

				//$this->_log('create term for '.$term_name);
				$term = wp_insert_term( $term_name, $tax, array('slug' => $term_slug, 'description' => $post_id) );
				if ( !is_wp_error( $term ) ) {
					$term_id = $term['term_id'];
				}

			}
			//$this->_log($term);

		} else {
			// maybe initiate an auto clean up here to kill off the term
		}

		return $term_id;
	}

	public function registration(){

		// open a filter for customizing the CPTs

		$default_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
		$custom_cpts = get_post_types( array( 'public' => true, '_builtin' => true ), 'objects' );
		$cpts = array_merge($default_cpts, $custom_cpts);

		foreach($this->creators as $creator){
			if(array_key_exists($creator, $this->users)){

				//$this->_log('registering auto tax, assigning to following cpts');
				//$this->_log($attachable_post_types);
				//$this->_log( 'registering tax: ' . $cpts[ $creator ]->name );

				$labels = array(
					'name'                       => _x( $cpts[ $creator ]->labels->name.' (Auto Tax)', 'Taxonomy General Name', 'text_domain' ),
					'singular_name'              => _x( $cpts[ $creator ]->labels->name.' (Auto Tax)', 'Taxonomy Singular Name', 'text_domain' ),
					'menu_name'                  => __( $cpts[ $creator ]->labels->name, 'text_domain' ),
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
					'public'                     => false,
					'show_ui'                    => true,
					'show_admin_column'          => true,
					'query_var'                  => 'auto_tax_'.$cpts[ $creator ]->name,
					'show_in_nav_menus'          => true,
					'show_tagcloud'              => false,
					'rewrite'                    => false,
				);
				//$this->_log($this->users[$creator]);
				register_taxonomy( $this->tax_prefix.$cpts[ $creator ]->name, $this->users[$creator], $args );
			}
		}

	}

	public function register_fields(){

		if( function_exists('acf_add_local_field_group') ){

			$location_array = array();
			$cpts_option = apply_filters( 'auto_taxonomies_attach_to_types', $this->creators );

			if ($cpts_option){
				foreach( $cpts_option as $custom_post_type ) {
					$location_array[] =  array(
						array(
							'param' => 'post_type',
							'operator' => '==',
							'value' => $custom_post_type,
						),
					);
				}
			}

			$auto_tax_field = array(
				'key' => 'group_591c211ca2c29',
				'title' => 'Content Cross Tagging',
				'fields' => array (
					array (
						'key' => $this->acf_bool_field_id,
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
						'key' => $this->acf_tax_id_field_id,
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


	public function auto_prefix_tax_queries( $query ){
		//$this->_log('PLUGIN pre get posts');

		//$this->_log('running pre get posts');
		$query->tax_query = $query->tax_query;
		if ($query->tax_query){

			//$this->_log('tax query was present!');
			//$this->_log($query);
			$rebuild = array();
			$modify = false;
			$tax_query_var = get_query_var( 'tax_query', false );
			if ($tax_query_var){
				//$this->_log('found tax in query var, manually adding to queries object');
				//$this->_log($tax_query_var);
				$query->tax_query->queries = array_merge($query->tax_query->queries, $tax_query_var);
			}
			//$this->_log($query->tax_query->queries);
			foreach($query->tax_query->queries as $i => $tax_query){
				if (is_array($tax_query)){
					//$this->_log($tax_query);
					if (array_key_exists('taxonomy', $tax_query)){
						//$this->_log('checking: '.$tax_query['taxonomy']);
						if ( in_array($tax_query['taxonomy'], $this->creators) ){
							//found a query that matches the post type of one of our auto tax CPTs DIRECTLY (it's not yet been prefixed). So prefix it!
							//$this->_log('adding prefix to '.$tax_query['taxonomy']);
							$query->tax_query->queries[$i]['taxonomy'] = $this->tax_prefix.$tax_query['taxonomy'];
						}
					}
				}
			}
			$query->set('tax_query', $query->tax_query->queries);
			//$this->_log('final modified query:');
			//$this->_log($query);
		}

	}

	function add_admin_query_vars( $vars ){
		foreach($this->creators as $cpt){
			$vars[] = 'auto_tax_'.$cpt;
		}
		//$this->_log($vars);
		flush_rewrite_rules(true);
		return $vars;
	}

	public function auto_prefix_tax_queries_admin_only( $query ){
		// @todo - this is adding everything as it should be to handle AUTO prefixing, but not actually impacting query - debug
		// funciton still needed to assign the prefixed version to the corresponding args
		if (is_admin()){
			//$this->_log('PLUGIN ADMIN pre get posts');
			//$this->_log($query);
			$tax_queries = array();
			foreach($this->creators as $cpt){
				$tax_query_var = 'auto_tax_'.$cpt;
				$auto_tax = get_query_var($tax_query_var);
				//$this->_log('found auto tax query var: ' . $cpt.' term: '. $auto_tax);

				if ($auto_tax){
					$tax_queries[] = array(
						'taxonomy' => $cpt,
						'field' => 'slug',
						'terms' => $auto_tax
					);
				}
			}
			if (!empty($tax_queries)){
				//$this->_log('setting tax queries');
				$query->set( 'tax_query', $tax_queries);
				//$query->query[$tax_query_var] = $auto_tax;
			}
			//$this->_log('updated query');
			//$this->_log($query);
		}

	}

	public function auto_prefix_term_queries($query){
		//$this->_log('running pre get terms query');
		//$this->_log($query);
	}

	public function admin_page() {

		// General check for user permissions.
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.')    );
		}
		include_once(__DIR__ . '/options.php');

	}

	public function batch_assign_terms($cpts){
		//$this->_log('FOUND BATCH ASSIGNMENT REQUEST, DO STUFF');
		//$this->_log($cpts);
		if ($cpts){
			foreach($cpts as $cpt){

				// query CPT for all published content
				$args = array(
					'post_type' => $cpt,
					'fields' => 'ids',
					'post_status' => array('private', 'publish', 'draft', 'pending', 'future'),
					'posts_per_page' => -1,
				);
				$query = new WP_Query( $args );

				if (array_key_exists($cpt, $this->users)){

					if($query->post_count){
						//$solution->term_id
						//update_post_meta( $solution->term_id, '', 1 );

						//$this->_log('ids to add terms to:');
						//$this->_log($query->posts);
						//$this->_log($this->creators);
						foreach ($query->posts as $post_id){
							// keeping variable name contextual, because the CPT slug is being used as the tax term
							$tax = $this->tax_prefix.$cpt;
							//$this->_log('looping '.$tax);
							//$this->_log('update meta for post: ' . $post_id);
							$success = update_field($this->acf_bool_field_id, 1, $post_id);
							$this->add_auto_tax_term($post_id, 1, '', $tax);
							//$this->_log($success);

						}
					}

				}
			}
		}
	}

	public function prepare_acf_field( $field ){
		//$this->_log($field);

		$enabled_by_default = array();
		if (array_key_exists('enabled_by_default', $this->options)){
			$enabled_by_default = $this->options['enabled_by_default'];
		}
		// change default
		if ( in_array( get_post_type(), $enabled_by_default )){
			$field['value'] = 1;
		}

		return $field;
	}

	public function add_tax_page_css(){

		if (!empty($this->creators)){
			?>
			<style>
				<?php
				foreach($this->creators as $tax){
					$tax = $this->tax_prefix.$tax;
					?>
					.taxonomy-<?php echo $tax; ?> #col-left{
						display: none;
					}
					.taxonomy-<?php echo $tax; ?> #col-right{
						float: none;
						width: auto;
					}
					.taxonomy-<?php echo $tax; ?> .row-actions .edit,
					.taxonomy-<?php echo $tax; ?> .row-actions .inline{
						display: none;
					}
					<?php
				}
				?>
			</style>
			<?php
		}
	}
}
