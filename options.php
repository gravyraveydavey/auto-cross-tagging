<?php
// Check whether the button has been pressed AND also check the nonce
if (isset($_POST['auto_taxonomies_options']) && check_admin_referer('auto_taxonomies_check_submission_nonce')) {
	// the button has been pressed AND we've passed the security check
	// store stuff!
	update_option('auto_taxonomies_options', $_POST['auto_taxonomies_options']);

	if (array_key_exists('at_cpts_batch_assign', $_POST['auto_taxonomies_options']) ){
		$this->batch_assign_terms($_POST['auto_taxonomies_options']['at_cpts_batch_assign']);
	}
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
									$this->_log($this->options);
										$default_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
										$custom_cpts = get_post_types( array( 'public' => true, '_builtin' => true ), 'objects' );
										$cpts = array_merge($default_cpts, $custom_cpts);

										if (!empty($cpts)){
											$html = '<select multiple size="5" name="auto_taxonomies_options[at_cpts_creators][]" id="auto_taxonomies_options_at_cpts_creators" class="regular-text all-options">';
											foreach( $cpts as $cpt ) {
												$html .= sprintf( '<option value="%s" %s>%s</option>', $cpt->name, in_array( $cpt->name, $this->creators ) ? 'selected="selected"' : '', $cpt->labels->singular_name );
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
							<?php

							if ($this->creators){
								foreach($this->creators as $creators_cpt){
									?>
									<tr>
										<td><label for="auto_taxonomies_options_at_cpts_users_<?php echo $creators_cpt; ?>">Allow the following post types to <strong>use</strong> the <strong><?php echo $creators_cpt; ?></strong> auto tax</label></td>
										<td>
											<?php
											$cpt_users = array();
											if(array_key_exists($creators_cpt, $this->users)){
												$cpt_users = (array) $this->users[$creators_cpt];
											}
											//$this->_log($cpts);
												if (!empty($cpts)){
													$html = '<select multiple size="5" name="auto_taxonomies_options[at_cpts_users]['.$creators_cpt.'][]" id="auto_taxonomies_options_at_cpts_users_'.$creators_cpt.'" class="regular-text all-options">';
													foreach( $cpts as $cpt ) {
														if ($creators_cpt !== $cpt->name){
															$html .= sprintf( '<option value="%s" %s>%s</option>', $cpt->name, in_array( $cpt->name, $cpt_users ) ? 'selected' : '', $cpt->labels->singular_name );
														}
													}
													$html .= '</select>';
												} else {
													$html = '<p class="description">No custom post types found</p>';
													$html .= '<input type="hidden" name="auto_taxonomies_options[at_cpts_users][]" id="auto_taxonomies_options_at_cpts_users_'.$cpt.'" />';
												}
												echo $html;
												?>
										</td>
									</tr>
									<?php
								}
							}
							?>
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

					<?php
					if ($this->creators){
					?>
					<table class="widefat">
						<thead>
							<tr>
								<th colspan="2"><strong>Enable default term creation</strong></td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><label for="auto_taxonomies_options_at_cpts_enabled_by_default">Enable default term creation the following post types (to make post type work like CPTonomies):</label></td>
								<td>
									<?php
									$enabled_by_default = array();
									if (array_key_exists('enabled_by_default', $this->options)){
										$enabled_by_default = $this->options['enabled_by_default'];
									}
									if (!empty($cpts)){
										$html = '<select multiple size="5" name="auto_taxonomies_options[enabled_by_default][]" id="auto_taxonomies_options_at_cpts_enabled_by_default" class="regular-text all-options">';
										foreach( $cpts as $cpt ) {
											if (in_array( $cpt->name, $this->creators )){
												$html .= sprintf( '<option value="%s" %s>%s</option>', $cpt->name, in_array( $cpt->name, $enabled_by_default ) ? 'selected' : '', $cpt->labels->singular_name );
											}
										}
										$html .= '</select>';
									} else {
										$html = '<p class="description">No custom post types found</p>';
										$html .= '<input type="hidden" name="auto_taxonomies_options[enabled_by_default][]" id="auto_taxonomies_options_at_cpts_enabled_by_default" />';
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
					<?php
					}
					?>
					<table class="widefat">
						<thead>
							<tr>
								<th colspan="2"><strong>Batch assign</strong></td>
							</tr>
						</thead>
						<tbody>
							<?php
							if (empty($this->creators)){
								?>
								<tr>
									<td>Please choose an auto tax creator above in order to batch assign</td>
								</tr>
								<?php
							} else {

							}
							?>
							<tr>
								<td><label for="auto_taxonomies_options_at_cpts_batch_assign">Batch update a post type to add all it's content as new tax terms</label></td>
								<td>
									<?php
										$html = '<select multiple size="5" name="auto_taxonomies_options[at_cpts_batch_assign][]" id="auto_taxonomies_options_at_cpts_batch_assign" class="regular-text all-options">';
										foreach( $cpts as $cpt ) {
											if(in_array($cpt->name, $this->creators)){
												$html .= sprintf( '<option value="%s" %s>%s</option>', $cpt->name, '', $cpt->labels->singular_name );
											}
										}
										$html .= '</select>';
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
