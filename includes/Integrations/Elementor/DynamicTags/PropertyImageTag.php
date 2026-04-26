<?php
/**
 * Elementor dynamic tag — IBB > Property Image.
 *
 * Returns the property's primary image (first attachment from the first
 * named gallery, falling back to the WP featured image). Bindable to any
 * Image control — Image widget, Background image, Loop Item template image
 * placeholder, etc.
 *
 * Returns Elementor's expected image-tag shape: `[ 'id' => int, 'url' => string ]`.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Data_Tag' ) ) {
	return;
}

class PropertyImageTag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name(): string  { return 'ibb-property-image'; }
	public function get_title(): string { return __( 'Property Image', 'ibb-rentals' ); }
	public function get_group(): string { return 'ibb-rentals'; }

	public function get_categories(): array {
		if ( defined( '\\Elementor\\Modules\\DynamicTags\\Module::IMAGE_CATEGORY' ) ) {
			return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
		}
		return [ 'image' ];
	}

	protected function register_controls(): void {
		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );

		$this->add_control( 'gallery_slug', [
			'label'       => __( 'Gallery', 'ibb-rentals' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => ElementorModule::gallery_slug_options(),
			'default'     => '',
			'description' => __( 'Pick a specific gallery to source from, or "All photos" to use the first image across every gallery. Falls back to the property\'s featured image if no galleries have any images.', 'ibb-rentals' ),
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array{id:int, url:string}
	 */
	public function get_value( array $options = [] ): array {
		$property = ElementorModule::resolve_property_for_widget(
			(string) $this->get_settings( 'property_id' )
		);
		if ( ! $property ) {
			return [ 'id' => 0, 'url' => '' ];
		}

		$slug = sanitize_key( (string) $this->get_settings( 'gallery_slug' ) );
		$ids  = [];
		if ( $slug !== '' ) {
			$gallery = $property->gallery( $slug );
			$ids     = $gallery ? $gallery['attachments'] : [];
		} else {
			$ids = $property->all_attachments();
		}

		$first = 0;
		foreach ( $ids as $aid ) {
			if ( wp_attachment_is_image( (int) $aid ) ) {
				$first = (int) $aid;
				break;
			}
		}

		// Fall back to WP's native featured image if galleries are empty.
		if ( $first === 0 ) {
			$first = (int) get_post_thumbnail_id( $property->id );
		}

		if ( $first === 0 ) {
			return [ 'id' => 0, 'url' => '' ];
		}

		$url = (string) wp_get_attachment_image_url( $first, 'full' );
		return [ 'id' => $first, 'url' => $url ];
	}
}
