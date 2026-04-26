<?php
/**
 * Default single-property template. Themes can override at:
 *   - {theme}/ibb-rentals/single-ibb_property.php
 *   - {theme}/single-ibb_property.php
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main ibb-single">
	<div class="ibb-single__inner">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php echo do_shortcode( '[ibb_property id="' . esc_attr( (string) get_the_ID() ) . '"]' ); ?>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
