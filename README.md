# Auto Cross Tagging

A simple Wordpress plugin to automatically build a taxonomy of terms based on existing content for cross promotion.

Note! This plugin is dependant on [ACF](https://en-gb.wordpress.org/plugins/advanced-custom-fields/) - you can activate the plugin without it, but nothing will happen!

## About
Auto Cross Tagging is designed to automatically maintain a taxonomy based on the existing content of your site. The idea came from the awesome plugin [CPTonomies](https://en-gb.wordpress.org/plugins/cpt-onomies/), but this, slightly different approach allows you to apply auto tagging to only the content you need (rather than the entire post type). This also means we can leverage some more of Wordpress' native functionality, like page templates, page nesting, native tax queries.

To use it;
1. Enable [ACF](https://en-gb.wordpress.org/plugins/advanced-custom-fields/) and this plugin
2. Assign which post types you want to use from the cross tagging admin page
3. Edit a post from a post type you set as an 'auto taxonomy creator', and tick the box on the lower right corner 'Create AutoTaxonomy?'
4. Edit some other content from a post type you set as an 'auto taxonomy user' and tag the content to the new term

## Other Notes
* There's no risk of URL slug conflicts as all term slugs are prefixed with 'auto_tax_'
* Content assigned to a term can be viewed in the usual way from the admin back end
* Deleting a term from the tax page will clear the origin's ACF fields
* Turning a page's ACF 'Create AutoTaxonomy?' field off won't delete the terms assigned to it - (in case you want to re-enable it) - this term would need deleting from the tax page.

## Usage

```php
$args = array(
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
```
Or, use the ACF function ```get_field('auto_tax_term_id')``` to pull the related term ID from the source page's post meta

```php
$args = array(
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
```