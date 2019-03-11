<?php
/*
Plugin Name: Auto Cross Tagging
Description: An automatic taxonomy builder for cross promoting content
Version: 1.0
Author: Dave Welch
Author URI: https://github.com/gravyraveydavey
*/

if (class_exists('acf') ){
	auto_taxonomies_init();
}

function auto_taxonomies_init(){

	add_action('admin_menu', 'auto_taxonomies_menu_page', 10);

	add_action( 'admin_init', 'auto_taxonomies_registration' );
	add_action( 'admin_init', 'auto_taxonomies_register_fields' );

	add_action( 'admin_enqueue_scripts', 'auto_tax_admin_css', 11 );

	// run before ACF saves the $_POST['fields'] data (so we can add in the returned term ID)
	add_action('acf/save_post', 'auto_taxonomies_save_hook', 5);

	add_action('pre_delete_term', 'delete_auto_term_hook', 10, 2);

	add_filter("manage_edit-auto_taxonomies_columns", 'auto_taxonomies_admin_columns');

	add_filter("manage_auto_taxonomies_custom_column", 'custom_auto_taxonomies_column', 10, 3);

}

function auto_tax_admin_css() {
	// used to hide the edit / add interface elements on the term page
	// and the meta box that stores the term id on the source page
	wp_enqueue_style('auto_tax_admin_css', plugins_url('css/wp-admin.css', __FILE__));
}

function delete_auto_term_hook($term_id, $taxonomy){
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


function auto_taxonomies_admin_columns($columns) {
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


function custom_auto_taxonomies_column($string, $column_name, $term_id) {
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


function auto_taxonomies_save_hook( $post_id ){

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

function auto_taxonomies_registration(){

	// open a filter for customizing the CPTs
	$stored_options = get_option('auto_taxonomies_options');
	$users = (array)$stored_options['at_cpts_users'];

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

function auto_taxonomies_register_fields(){

	if( function_exists('acf_add_local_field_group') ){

		$location_array = array();
		$stored_options = get_option('auto_taxonomies_options');
		$creators = (array)$stored_options['at_cpts_creators'];
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





function auto_taxonomies_menu_page(){

	$page_title        = 'Auto Cross Tagging';
	$menu_title        = 'Auto Cross Tagging';
	$capability        = 'manage_options';
	$menu_slug         = 'auto-taxonomies';
	$callback_function = 'auto_taxonomies_admin_page';

	add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback_function);

}

function auto_taxonomies_admin_page() {

	// General check for user permissions.
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient pilchards to access this page.')    );
	}

	// Check whether the button has been pressed AND also check the nonce
	if (isset($_POST['auto_taxonomies_options']) && check_admin_referer('auto_taxonomies_check_submission_nonce')) {
		// the button has been pressed AND we've passed the security check
		// store stuff!
		update_option('auto_taxonomies_options', $_POST['auto_taxonomies_options']);
	}
	?>
	<div class="wrap">
		<form method="post" action="options-general.php?page=auto-taxonomies">

			<h1>Auto Cross Tagging Settings</h1>

			<div id="poststuff">

				<div id="post-body" class="metabox-holder columns-2">

					<!-- main content -->
					<div id="post-body-content">

						<table class="widefat">
							<thead>
								<tr>
									<th colspan="2"><strong>Location Settings</strong></td>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><label for="auto_taxonomies_options_at_cpts_creators">Allow the following post types to <strong>create</strong> auto tags</label></td>
									<td>
										<?php
											$stored_options = get_option('auto_taxonomies_options');
											$cpts = get_post_types( array( 'public' => true, '_builtin' => true ), 'objects' );
											$options = (array)$stored_options['at_cpts_creators'];

											if (!empty($cpts)){
												$html = '<select multiple size="5" name="auto_taxonomies_options[at_cpts_creators][]" id="auto_taxonomies_options_at_cpts_creators" class="regular-text all-options">';
												foreach( $cpts as $id => $cpt ) {
													$html .= sprintf( '<option value="%s" %s>%s</option>', $id, in_array( $id, $options ) ? 'selected' : '', $cpt->labels->singular_name );
												}
												$html .= '</select>';
											} else {
												$html = '<p class="description">No custom post types found</p>';
												$html .= '<input type="hidden" name="auto_taxonomies_options[at_cpts_creators][]" id="auto_taxonomies_options_at_cpts_creators" />';
											}
											echo $html;
											?>
									</td>
								</tr>
								<tr>
									<td><label for="auto_taxonomies_options_at_cpts_users">Allow the following post types to <strong>use</strong> auto tags</label></td>
									<td>
										<?php
											$options = (array)$stored_options['at_cpts_users'];
											if (!empty($cpts)){
												$html = '<select multiple size="5" name="auto_taxonomies_options[at_cpts_users][]" id="auto_taxonomies_options_at_cpts_users" class="regular-text all-options">';
												foreach( $cpts as $id => $cpt ) {
													$html .= sprintf( '<option value="%s" %s>%s</option>', $id, in_array( $id, $options ) ? 'selected' : '', $cpt->labels->singular_name );
												}
												$html .= '</select>';
											} else {
												$html = '<p class="description">No custom post types found</p>';
												$html .= '<input type="hidden" name="auto_taxonomies_options[at_cpts_users][]" id="auto_taxonomies_options_at_cpts_users" />';
											}
											echo $html;
											?>
									</td>
								</tr>
							</tbody>
							<tfoot>
								<tr>
									<td colspan="2" align="right">
										<button class="button-primary" type="submit" name="submit"><?php esc_attr_e( 'Save changes' ); ?></button>
									</td>
								</tr>
							</tfoot>
						</table>
						<br />
						<div class="meta-box-sortables">
							<div class="postbox">
								<div class="handlediv" title="Click to toggle"><br></div>
								<h2 class="hndle"><span><?php esc_attr_e('Usage', 'wp_admin_style'); ?></span></h2>

								<div class="inside">
									<p>To perform an auto tax query, the code will look exactly like a normal tax query, but as all the auto generated tax terms are prefixed with 'auto_tax_' (to prevent URL slug conflicts), just prefix your page slug with this string before making your query.</p>
<p><pre><code class="code-block">$args = array(
	'post_type' => 'page',
	'tax_query' => array(
		array(
			'taxonomy' => 'auto_taxonomies',
			'field'    => 'slug',
			'terms'    => 'auto_tax_'.$post->post_name,
		),
	),
);
$query = new WP_Query( $args );
</code></pre></p>
<p>Or, use the ACF function <code>get_field('auto_tax_term_id')</code> to pull the related term ID from the source page's post meta</p>
<p><pre><code class="code-block">$args = array(
	'post_type' => 'page',
	'tax_query' => array(
		array(
			'taxonomy' => 'auto_taxonomies',
			'field'    => 'term_id',
			'terms'    => array( (get_field('auto_tax_term_id') ),
		),
	),
);
$query = new WP_Query( $args );
</code></pre></p>
								</div>
							</div>
						</div>
					</div>
					<!-- post-body-content -->

					<!-- sidebar -->
					<div id="postbox-container-1" class="postbox-container">

						<div class="meta-box-sortables">
							<div class="postbox">
								<div class="handlediv" title="Click to toggle"><br></div>
								<h2 class="hndle"><span><?php esc_attr_e('About', 'wp_admin_style'); ?></span></h2>

								<div class="inside">
									<p>Auto Cross Tagging is designed to automatically maintain a taxonomy based on the existing content of your site. The idea came from the awesome plugin CPTonomies, but this, slightly different approach allows you to apply auto tagging to only the content you need (rather than the entire post type). This also means we can leverage some more of wordpress' native functionality, like page templates, page nesting, native tax queries.</p>
								</div>
							</div>
						</div>

					</div>
					<!-- #postbox-container-1 .postbox-container -->

				</div>
				<!-- #post-body .metabox-holder .columns-2 -->
				<br class="clear">
			</div>
			<?php wp_nonce_field('auto_taxonomies_check_submission_nonce');	?>
		</form>
		<!-- #poststuff -->
	</div> <!-- .wrap -->
	<?php
}


