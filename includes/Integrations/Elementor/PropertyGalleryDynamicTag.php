<?php
/**
 * Elementor dynamic tag — IBB > Property Gallery.
 *
 * Plugs into Elementor Pro's Gallery widget (and any third-party widget that
 * accepts the `gallery` dynamic-tag category). Returns an array of
 * `{id, url}` pairs — Elementor's expected shape for gallery values.
 *
 * Two controls:
 *   - Property: a SELECT2 of all properties, with a "Current page" default
 *     for use on the single-property template.
 *   - Gallery slug: optional. Empty → all photos for the property; otherwise
 *     the named sub-gallery (e.g. "bedroom-1", "pool").
 *
 * Lives at PSR-4 path IBB\Rentals\Integrations\Elementor\… so the autoloader
 * can resolve it after Elementor is loaded; the file is `require_once`d at
 * runtime so its declaration happens AFTER Elementor's parent class exists.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Integrations\Elementor as ElementorIntegration;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Data_Tag' ) ) {
	return;
}

class PropertyGalleryDynamicTag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name(): string {
		return 'ibb-property-gallery';
	}

	public function get_title(): string {
		return __( 'Property Gallery', 'ibb-rentals' );
	}

	public function get_group(): string {
		return 'ibb-rentals';
	}

	public function get_categories(): array {
		if ( defined( '\\Elementor\\Modules\\DynamicTags\\Module::GALLERY_CATEGORY' ) ) {
			return [ \Elementor\Modules\DynamicTags\Module::GALLERY_CATEGORY ];
		}
		return [ 'gallery' ];
	}

	protected function register_controls(): void {
		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorIntegration::property_options(),
			'default' => 'current',
		] );

		$this->add_control( 'gallery_slug', [
			'label'       => __( 'Gallery slug', 'ibb-rentals' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => 'bedroom-1',
			'description' => __( 'Leave empty to include every photo across all galleries. Or enter a gallery slug (lowercase, hyphens) to use just that gallery — e.g. <code>bedroom-1</code> or <code>pool</code>.', 'ibb-rentals' ),
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array<int, array{id:int, url:string}>
	 */
	public function get_value( array $options = [] ): array {
		$property_id = (string) $this->get_settings( 'property_id' );
		if ( $property_id === 'current' || $property_id === '' ) {
			$property_id = (string) get_the_ID();
		}
		$property = Property::from_id( (int) $property_id );
		if ( ! $property ) {
			return [];
		}

		$slug = sanitize_key( (string) $this->get_settings( 'gallery_slug' ) );
		if ( $slug !== '' ) {
			$gallery = $property->gallery( $slug );
			$ids     = $gallery ? $gallery['attachments'] : [];
		} else {
			$ids = $property->all_attachments();
		}

		$out = [];
		foreach ( $ids as $aid ) {
			$url = wp_get_attachment_image_url( (int) $aid, 'full' );
			if ( ! $url ) {
				continue;
			}
			$out[] = [ 'id' => (int) $aid, 'url' => (string) $url ];
		}
		return $out;
	}
}
