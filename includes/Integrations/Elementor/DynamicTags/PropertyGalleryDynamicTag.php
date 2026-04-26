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
 * Lives at PSR-4 path IBB\Rentals\Integrations\Elementor\DynamicTags\… so
 * the autoloader can resolve it after Elementor is loaded; the file is
 * `require_once`d at runtime by the module entry point so its declaration
 * happens AFTER Elementor's parent class exists.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

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
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );

		$this->add_control( 'gallery_slug', [
			'label'       => __( 'Gallery', 'ibb-rentals' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => ElementorModule::gallery_slug_options(),
			'default'     => '',
			'description' => __( 'Pick "All photos" to combine every gallery, or a specific gallery to use just that one. Slugs are shared across properties — e.g. picking "bedroom-1" works on any property that has a Bedroom 1 gallery.', 'ibb-rentals' ),
		] );
	}

	/**
	 * @param array<string,mixed> $options
	 * @return array<int, array{id:int, url:string}>
	 */
	public function get_value( array $options = [] ): array {
		$property = ElementorModule::resolve_property_for_widget(
			(string) $this->get_settings( 'property_id' )
		);
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

		// Match Elementor's idiomatic gallery-dynamic-tag return shape: a
		// flat list of { id: <int> } entries (see how WC's Product Gallery
		// and Elementor Pro's Featured Image Gallery do it). Widgets resolve
		// URLs / sizes themselves via wp_get_attachment_image_src() based on
		// their own "Image Size" control. Including a `url` field here can
		// confuse some widgets (Pro Gallery, Image Carousel) that ignore it
		// but strictly validate the entry shape — keep the return minimal.
		$out = [];
		foreach ( $ids as $aid ) {
			if ( ! wp_attachment_is_image( (int) $aid ) ) {
				continue;
			}
			$out[] = [ 'id' => (int) $aid ];
		}
		return $out;
	}

}
