<?php
/**
 * Frontend shortcodes — booking form, single property, search, calendar.
 *
 * The booking form is the only one that *requires* a property; the others
 * accept an `id` attribute or fall back to the global $post when present.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Frontend;

use IBB\Rentals\Domain\Property;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public function register(): void {
		add_shortcode( 'ibb_booking_form', [ $this, 'render_booking_form' ] );
		add_shortcode( 'ibb_property',     [ $this, 'render_property' ] );
		add_shortcode( 'ibb_search',       [ $this, 'render_search' ] );
		add_shortcode( 'ibb_calendar',     [ $this, 'render_calendar' ] );
		add_shortcode( 'ibb_gallery',      [ $this, 'render_gallery' ] );
	}

	/** @param array<string, string>|string $atts */
	public function render_booking_form( $atts ): string {
		$atts     = shortcode_atts( [ 'id' => 0 ], (array) $atts );
		$id       = (int) $atts['id'] ?: ( get_the_ID() ?: 0 );
		$property = Property::from_id( $id );
		if ( ! $property ) {
			return '';
		}
		$product_id = $property->linked_product_id();
		if ( ! $product_id ) {
			return '<p class="ibb-booking__error">' . esc_html__( 'Booking is not yet enabled for this property.', 'ibb-rentals' ) . '</p>';
		}

		ob_start();
		?>
		<form class="ibb-booking" data-property-id="<?php echo esc_attr( (string) $property->id ); ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
			<h3 class="ibb-booking__title"><?php echo esc_html__( 'Reserve your stay', 'ibb-rentals' ); ?></h3>

			<div class="ibb-booking__field">
				<label for="ibb-dates-<?php echo esc_attr( (string) $property->id ); ?>"><?php esc_html_e( 'Dates', 'ibb-rentals' ); ?></label>
				<input type="text" id="ibb-dates-<?php echo esc_attr( (string) $property->id ); ?>" class="ibb-booking__dates" placeholder="<?php esc_attr_e( 'Check-in → Check-out', 'ibb-rentals' ); ?>" readonly />
			</div>

			<div class="ibb-booking__field">
				<label for="ibb-guests-<?php echo esc_attr( (string) $property->id ); ?>"><?php esc_html_e( 'Guests', 'ibb-rentals' ); ?></label>
				<div class="ibb-booking__stepper" data-min="1" data-max="<?php echo esc_attr( (string) $property->max_guests() ); ?>">
					<button type="button" class="ibb-booking__step ibb-booking__step--down" aria-label="<?php esc_attr_e( 'Decrease guests', 'ibb-rentals' ); ?>">−</button>
					<input
						type="number"
						id="ibb-guests-<?php echo esc_attr( (string) $property->id ); ?>"
						class="ibb-booking__guests"
						value="1"
						min="1"
						max="<?php echo esc_attr( (string) $property->max_guests() ); ?>"
						step="1"
						inputmode="numeric"
					/>
					<button type="button" class="ibb-booking__step ibb-booking__step--up" aria-label="<?php esc_attr_e( 'Increase guests', 'ibb-rentals' ); ?>">+</button>
				</div>
				<small class="ibb-booking__hint"><?php
					printf(
						/* translators: %d: max guests */
						esc_html__( 'Max %d guests', 'ibb-rentals' ),
						$property->max_guests()
					);
				?></small>
			</div>

			<div class="ibb-booking__quote"></div>
			<div class="ibb-booking__error" role="alert"></div>

			<button type="submit" class="ibb-booking__submit" disabled><?php esc_html_e( 'Book now', 'ibb-rentals' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string, string>|string $atts */
	public function render_property( $atts ): string {
		$atts     = shortcode_atts( [ 'id' => 0 ], (array) $atts );
		$id       = (int) $atts['id'] ?: ( get_the_ID() ?: 0 );
		$property = Property::from_id( $id );
		if ( ! $property ) {
			return '';
		}
		ob_start();
		?>
		<div class="ibb-property">
			<h2 class="ibb-property__title"><?php echo esc_html( $property->title() ); ?></h2>
			<?php if ( has_post_thumbnail( $property->id ) ) : ?>
				<div class="ibb-property__image"><?php echo get_the_post_thumbnail( $property->id, 'large' ); ?></div>
			<?php endif; ?>
			<div class="ibb-property__meta">
				<span><?php echo esc_html( sprintf(
					/* translators: 1: max guests, 2: bedrooms, 3: bathrooms */
					_n( '%1$d guest · %2$d bedroom · %3$s bath', '%1$d guests · %2$d bedrooms · %3$s baths', $property->max_guests(), 'ibb-rentals' ),
					$property->max_guests(),
					$property->bedrooms(),
					(string) $property->bathrooms()
				) ); ?></span>
			</div>
			<div class="ibb-property__description"><?php
				// Run the full `the_content` filter chain so shortcodes
				// (including [ibb_gallery]), oEmbeds, autop, etc. are
				// processed inside the property description.
				echo apply_filters( 'the_content', $property->post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?></div>
			<?php echo $this->render_booking_form( [ 'id' => $property->id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function render_search( $atts ): string {
		// Minimal v1 search — the property archive at /properties/ is the primary discovery surface.
		// Future iterations can wire a full filterable AJAX search here.
		$query = new \WP_Query( [
			'post_type'      => \IBB\Rentals\PostTypes\PropertyPostType::POST_TYPE,
			'posts_per_page' => 12,
			'post_status'    => 'publish',
		] );
		ob_start();
		echo '<div class="ibb-search">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$pid = get_the_ID();
			echo '<article class="ibb-search__item">';
			echo '<h3><a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a></h3>';
			if ( has_post_thumbnail( $pid ) ) {
				echo '<a href="' . esc_url( get_permalink( $pid ) ) . '">' . get_the_post_thumbnail( $pid, 'medium' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '<p>' . esc_html( get_the_excerpt( $pid ) ) . '</p>';
			echo '</article>';
		}
		wp_reset_postdata();
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_calendar( $atts ): string {
		// Display-only month grid; rendered server-side. Booking form uses Flatpickr for interaction.
		return '';
	}

	/**
	 * Render an attached photo gallery for a property.
	 *
	 * Usage:
	 *   [ibb_gallery]                                  → all photos for the current property
	 *   [ibb_gallery property="123"]                   → all photos for property 123
	 *   [ibb_gallery gallery="bedroom-1"]              → just the "Bedroom 1" gallery of the current property
	 *   [ibb_gallery property="123" gallery="pool"]    → just the "Pool" gallery of property 123
	 *   [ibb_gallery property="123" size="large" cols="2" link="file"]
	 *
	 * @param array<string, string>|string $atts
	 */
	public function render_gallery( $atts ): string {
		$atts = shortcode_atts( [
			'id'       => 0,         // legacy alias for `property`
			'property' => 0,
			'gallery'  => '',
			'size'     => 'medium_large',
			'cols'     => 3,
			'link'     => 'file',    // file | none
			'class'    => '',
		], (array) $atts );

		$property_id = (int) ( $atts['property'] ?: $atts['id'] );
		if ( ! $property_id ) {
			$property_id = (int) ( get_the_ID() ?: 0 );
		}
		$property = Property::from_id( $property_id );
		if ( ! $property ) {
			return '';
		}

		$slug = sanitize_key( (string) $atts['gallery'] );
		if ( $slug !== '' ) {
			$gallery = $property->gallery( $slug );
			$ids     = $gallery ? $gallery['attachments'] : [];
		} else {
			$ids = $property->all_attachments();
		}

		if ( empty( $ids ) ) {
			return '';
		}

		$size  = (string) $atts['size'];
		$cols  = max( 1, min( 6, (int) $atts['cols'] ) );
		$link  = $atts['link'] === 'none' ? 'none' : 'file';
		$class = trim( 'ibb-gallery-display ibb-gallery-display--cols-' . $cols . ' ' . sanitize_html_class( (string) $atts['class'] ) );

		$out = '<div class="' . esc_attr( $class ) . '" data-property-id="' . esc_attr( (string) $property_id ) . '"' . ( $slug !== '' ? ' data-gallery="' . esc_attr( $slug ) . '"' : '' ) . '>';
		foreach ( $ids as $aid ) {
			$img = wp_get_attachment_image( (int) $aid, $size, false, [
				'loading' => 'lazy',
				'class'   => 'ibb-gallery-display__image',
			] );
			if ( ! $img ) {
				continue;
			}
			if ( $link === 'file' ) {
				$full = wp_get_attachment_image_url( (int) $aid, 'full' );
				$out .= '<a class="ibb-gallery-display__item" href="' . esc_url( $full ?: '#' ) . '" data-property-id="' . esc_attr( (string) $property_id ) . '">' . $img . '</a>';
			} else {
				$out .= '<span class="ibb-gallery-display__item">' . $img . '</span>';
			}
		}
		$out .= '</div>';

		return $out;
	}
}
