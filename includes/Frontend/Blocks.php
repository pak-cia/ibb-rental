<?php
/**
 * Gutenberg block wrappers around the IBB shortcodes.
 *
 * Blocks: `ibb/booking-form`, `ibb/gallery`, `ibb/property-details`,
 * `ibb/calendar`, `ibb/property-description`.
 * All are server-rendered via PHP `render_callback`; the edit-time preview
 * uses WP's `ServerSideRender` component so the editor matches the front-end.
 *
 * No build step required: the editor JS is registered as an inline script
 * against a no-source handle.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Frontend;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	private const HANDLE = 'ibb-rentals-blocks';

	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	public function register_blocks(): void {
		register_block_type( 'ibb/booking-form', [
			'api_version'     => 3,
			'title'           => __( 'IBB · Booking form', 'ibb-rentals' ),
			'category'        => 'ibb-rentals',
			'icon'            => 'calendar-alt',
			'description'     => __( 'Date-picker + quote + add-to-cart for one property.', 'ibb-rentals' ),
			'keywords'        => [ 'booking', 'rental', 'reserve' ],
			'supports'        => [ 'html' => false, 'align' => [ 'wide', 'full' ] ],
			'attributes'      => [
				'propertyId' => [ 'type' => 'integer', 'default' => 0 ],
				'align'      => [ 'type' => 'string' ],
			],
			'render_callback' => [ $this, 'render_booking_form_block' ],
		] );

		register_block_type( 'ibb/gallery', [
			'api_version'     => 3,
			'title'           => __( 'IBB · Property gallery', 'ibb-rentals' ),
			'category'        => 'ibb-rentals',
			'icon'            => 'format-gallery',
			'description'     => __( 'Photo grid for a property — full set or one named sub-gallery.', 'ibb-rentals' ),
			'keywords'        => [ 'gallery', 'photos', 'images', 'rental' ],
			'supports'        => [ 'html' => false, 'align' => [ 'wide', 'full' ] ],
			'attributes'      => [
				'propertyId'   => [ 'type' => 'integer', 'default' => 0 ],
				'gallerySlug'  => [ 'type' => 'string',  'default' => '' ],
				'size'         => [ 'type' => 'string',  'default' => 'medium_large' ],
				'cols'         => [ 'type' => 'integer', 'default' => 3 ],
				'link'         => [ 'type' => 'string',  'default' => 'file' ],
				'align'        => [ 'type' => 'string' ],
			],
			'render_callback' => [ $this, 'render_gallery_block' ],
		] );

		register_block_type( 'ibb/calendar', [
			'api_version'     => 3,
			'title'           => __( 'IBB · Availability calendar', 'ibb-rentals' ),
			'category'        => 'ibb-rentals',
			'icon'            => 'calendar',
			'description'     => __( 'Read-only inline calendar showing available and blocked dates for a property.', 'ibb-rentals' ),
			'keywords'        => [ 'calendar', 'availability', 'dates', 'rental' ],
			'supports'        => [ 'html' => false ],
			'attributes'      => [
				'propertyId' => [ 'type' => 'integer', 'default' => 0 ],
				'months'     => [ 'type' => 'integer', 'default' => 2 ],
				'legend'     => [ 'type' => 'boolean', 'default' => true ],
			],
			'render_callback' => [ $this, 'render_calendar_block' ],
		] );

		register_block_type( 'ibb/property-details', [
			'api_version'     => 3,
			'title'           => __( 'IBB · Property details', 'ibb-rentals' ),
			'category'        => 'ibb-rentals',
			'icon'            => 'info-outline',
			'description'     => __( 'Property metadata — guests, bedrooms, location, amenities, etc.', 'ibb-rentals' ),
			'keywords'        => [ 'property', 'details', 'specs', 'rental' ],
			'supports'        => [ 'html' => false, 'align' => [ 'wide', 'full' ] ],
			'attributes'      => [
				'propertyId' => [ 'type' => 'integer', 'default' => 0 ],
				'fields'     => [
					'type'    => 'array',
					'items'   => [ 'type' => 'string' ],
					'default' => [ 'guests', 'bedrooms', 'bathrooms', 'beds' ],
				],
				'layout'     => [ 'type' => 'string', 'default' => 'grid' ],
				'align'      => [ 'type' => 'string' ],
			],
			'render_callback' => [ $this, 'render_property_details_block' ],
		] );

		register_block_type( 'ibb/property-description', [
			'api_version'     => 3,
			'title'           => __( 'IBB · Property description', 'ibb-rentals' ),
			'category'        => 'ibb-rentals',
			'icon'            => 'text-page',
			'description'     => __( 'The property\'s main writeup — renders post_content through the_content filters.', 'ibb-rentals' ),
			'keywords'        => [ 'description', 'content', 'about', 'rental' ],
			'supports'        => [ 'html' => false, 'align' => [ 'wide', 'full' ] ],
			'attributes'      => [
				'propertyId' => [ 'type' => 'integer', 'default' => 0 ],
				'align'      => [ 'type' => 'string' ],
			],
			'render_callback' => [ $this, 'render_property_description_block' ],
		] );

		add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 1 );
	}

	/**
	 * @param array<int, array<string, mixed>> $cats
	 * @return array<int, array<string, mixed>>
	 */
	public function register_category( array $cats ): array {
		foreach ( $cats as $cat ) {
			if ( ( $cat['slug'] ?? '' ) === 'ibb-rentals' ) {
				return $cats;
			}
		}
		array_unshift( $cats, [
			'slug'  => 'ibb-rentals',
			'title' => __( 'IBB Rentals', 'ibb-rentals' ),
			'icon'  => null,
		] );
		return $cats;
	}

	/** @param array<string, mixed> $attrs */
	public function render_booking_form_block( array $attrs ): string {
		$shortcodes = new Shortcodes();
		$out = $shortcodes->render_booking_form( [
			'id' => (int) ( $attrs['propertyId'] ?? 0 ),
		] );
		return $this->wrap_with_align( $out, $attrs );
	}

	/** @param array<string, mixed> $attrs */
	public function render_calendar_block( array $attrs ): string {
		$shortcodes = new Shortcodes();
		return $shortcodes->render_calendar( [
			'id'     => (int) ( $attrs['propertyId'] ?? 0 ),
			'months' => (int) ( $attrs['months'] ?? 2 ),
			'legend' => ( $attrs['legend'] ?? true ) ? 'yes' : 'no',
		] );
	}

	/** @param array<string, mixed> $attrs */
	public function render_gallery_block( array $attrs ): string {
		$shortcodes = new Shortcodes();
		$out = $shortcodes->render_gallery( [
			'property' => (int) ( $attrs['propertyId'] ?? 0 ),
			'gallery'  => (string) ( $attrs['gallerySlug'] ?? '' ),
			'size'     => (string) ( $attrs['size'] ?? 'medium_large' ),
			'cols'     => (int) ( $attrs['cols'] ?? 3 ),
			'link'     => (string) ( $attrs['link'] ?? 'file' ),
		] );
		return $this->wrap_with_align( $out, $attrs );
	}

	/** @param array<string, mixed> $attrs */
	public function render_property_details_block( array $attrs ): string {
		$shortcodes = new Shortcodes();
		$fields = $attrs['fields'] ?? [];
		if ( ! is_array( $fields ) ) {
			$fields = [];
		}
		$out = $shortcodes->render_property_details( [
			'id'     => (int) ( $attrs['propertyId'] ?? 0 ),
			'fields' => implode( ',', array_map( 'sanitize_key', $fields ) ),
			'layout' => (string) ( $attrs['layout'] ?? 'grid' ),
		] );
		return $this->wrap_with_align( $out, $attrs );
	}

	/** @param array<string, mixed> $attrs */
	public function render_property_description_block( array $attrs ): string {
		$property_id = (int) ( $attrs['propertyId'] ?? 0 );
		if ( $property_id <= 0 ) {
			$property_id = (int) get_the_ID();
		}
		if ( $property_id <= 0 ) {
			return '';
		}
		$property = Property::from_id( $property_id );
		if ( ! $property ) {
			return '';
		}
		$content = $property->description();
		if ( $content === '' ) {
			return '';
		}
		$html = '<div class="ibb-property-description">'
			. wpautop( esc_html( $content ) )
			. '</div>';
		return $this->wrap_with_align( $html, $attrs );
	}

	/** @param array<string, mixed> $attrs */
	private function wrap_with_align( string $html, array $attrs ): string {
		$align = (string) ( $attrs['align'] ?? '' );
		if ( $align === '' || $html === '' ) {
			return $html;
		}
		return '<div class="align' . esc_attr( $align ) . '">' . $html . '</div>';
	}

	public function enqueue_editor_assets(): void {
		wp_register_script(
			self::HANDLE,
			'',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
			IBB_RENTALS_VERSION,
			true
		);
		wp_register_style( self::HANDLE, '', [ 'wp-edit-blocks' ], IBB_RENTALS_VERSION );

		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );

		wp_add_inline_script(
			self::HANDLE,
			'window.IBBRentalsBlocks = ' . wp_json_encode( $this->editor_data() ) . ';',
			'before'
		);
		wp_add_inline_script( self::HANDLE, $this->editor_js() );
		wp_add_inline_style( self::HANDLE, $this->editor_css() );
	}

	/**
	 * Data fed to the editor JS: a list of properties + their galleries so
	 * the inspector controls can populate dropdowns without an extra REST call.
	 *
	 * @return array<string, mixed>
	 */
	private function editor_data(): array {
		$posts = get_posts( [
			'post_type'        => PropertyPostType::POST_TYPE,
			'post_status'      => [ 'publish', 'private', 'draft' ],
			'numberposts'      => 200,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		] );

		$properties = [];
		$galleries  = [];
		foreach ( $posts as $p ) {
			$properties[] = [
				'id'    => (int) $p->ID,
				'label' => $p->post_title !== '' ? $p->post_title : ( '#' . $p->ID ),
			];
			$prop = Property::from_post( $p );
			if ( $prop ) {
				$gs = [];
				foreach ( $prop->galleries() as $g ) {
					$gs[] = [ 'slug' => $g['slug'], 'label' => $g['label'] ];
				}
				$galleries[ (string) $p->ID ] = $gs;
			}
		}

		return [
			'properties' => $properties,
			'galleries'  => $galleries,
			'sizes'      => [
				[ 'value' => 'thumbnail',     'label' => __( 'Thumbnail', 'ibb-rentals' ) ],
				[ 'value' => 'medium',        'label' => __( 'Medium', 'ibb-rentals' ) ],
				[ 'value' => 'medium_large',  'label' => __( 'Medium-large', 'ibb-rentals' ) ],
				[ 'value' => 'large',         'label' => __( 'Large', 'ibb-rentals' ) ],
				[ 'value' => 'full',          'label' => __( 'Full', 'ibb-rentals' ) ],
			],
			'detailFields' => [
				[ 'value' => 'guests',         'label' => __( 'Guests', 'ibb-rentals' ) ],
				[ 'value' => 'max_guests',     'label' => __( 'Max guests', 'ibb-rentals' ) ],
				[ 'value' => 'bedrooms',       'label' => __( 'Bedrooms', 'ibb-rentals' ) ],
				[ 'value' => 'bathrooms',      'label' => __( 'Bathrooms', 'ibb-rentals' ) ],
				[ 'value' => 'beds',           'label' => __( 'Beds', 'ibb-rentals' ) ],
				[ 'value' => 'check_in_time',  'label' => __( 'Check-in time', 'ibb-rentals' ) ],
				[ 'value' => 'check_out_time', 'label' => __( 'Check-out time', 'ibb-rentals' ) ],
				[ 'value' => 'address',        'label' => __( 'Address', 'ibb-rentals' ) ],
				[ 'value' => 'amenities',      'label' => __( 'Amenities', 'ibb-rentals' ) ],
				[ 'value' => 'location',       'label' => __( 'Location', 'ibb-rentals' ) ],
				[ 'value' => 'property_type',  'label' => __( 'Property type', 'ibb-rentals' ) ],
			],
		];
	}

	private function editor_js(): string {
		return <<<'JS'
( function( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.element ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = ( wp.blockEditor && wp.blockEditor.InspectorControls ) || ( wp.editor && wp.editor.InspectorControls );
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var CheckboxControl = wp.components.CheckboxControl;
	var Placeholder = wp.components.Placeholder;
	var ServerSideRender = ( wp.serverSideRender ) || ( wp.editor && wp.editor.ServerSideRender );
	var __ = wp.i18n.__;
	var data = window.IBBRentalsBlocks || { properties: [], galleries: {}, sizes: [], detailFields: [] };

	function propertyOptions() {
		var opts = [ { value: 0, label: __( '— Current page (if it is a property)', 'ibb-rentals' ) } ];
		( data.properties || [] ).forEach( function( p ) {
			opts.push( { value: p.id, label: p.label } );
		} );
		return opts;
	}

	function galleryOptions( propertyId ) {
		var pid = parseInt( propertyId, 10 ) || 0;
		var list = ( data.galleries && data.galleries[ String( pid ) ] ) || [];
		var opts = [ { value: '', label: __( '— All photos', 'ibb-rentals' ) } ];
		list.forEach( function( g ) {
			opts.push( { value: g.slug, label: g.label + '  (' + g.slug + ')' } );
		} );
		return opts;
	}

	function previewOrPlaceholder( blockName, attributes, instructions ) {
		if ( ! ServerSideRender ) {
			return el( Placeholder, { label: blockName, instructions: instructions } );
		}
		return el( ServerSideRender, {
			block: blockName,
			attributes: attributes,
			EmptyResponsePlaceholder: function() {
				return el( Placeholder, {
					label: blockName,
					instructions: __( 'Pick a property in the sidebar — or publish a property if none exist yet.', 'ibb-rentals' )
				} );
			}
		} );
	}

	// ─── ibb/booking-form ────────────────────────────────────────────────
	registerBlockType( 'ibb/booking-form', {
		edit: function( props ) {
			var atts = props.attributes;
			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Property', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Property', 'ibb-rentals' ),
							value: atts.propertyId,
							options: propertyOptions(),
							onChange: function( v ) { props.setAttributes( { propertyId: parseInt( v, 10 ) || 0 } ); }
						} )
					)
				),
				el( 'div', { className: 'ibb-block-preview ibb-block-preview--booking' },
					previewOrPlaceholder( 'ibb/booking-form', atts )
				)
			);
		},
		save: function() { return null; }
	} );

	// ─── ibb/gallery ─────────────────────────────────────────────────────
	registerBlockType( 'ibb/gallery', {
		edit: function( props ) {
			var atts = props.attributes;
			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Source', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Property', 'ibb-rentals' ),
							value: atts.propertyId,
							options: propertyOptions(),
							onChange: function( v ) {
								props.setAttributes( { propertyId: parseInt( v, 10 ) || 0, gallerySlug: '' } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Gallery', 'ibb-rentals' ),
							value: atts.gallerySlug,
							options: galleryOptions( atts.propertyId ),
							onChange: function( v ) { props.setAttributes( { gallerySlug: v } ); }
						} )
					),
					el( PanelBody, { title: __( 'Layout', 'ibb-rentals' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Columns', 'ibb-rentals' ),
							value: atts.cols,
							min: 1, max: 6,
							onChange: function( v ) { props.setAttributes( { cols: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Image size', 'ibb-rentals' ),
							value: atts.size,
							options: data.sizes,
							onChange: function( v ) { props.setAttributes( { size: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'On click', 'ibb-rentals' ),
							value: atts.link,
							options: [
								{ value: 'file', label: __( 'Open lightbox', 'ibb-rentals' ) },
								{ value: 'none', label: __( 'No link', 'ibb-rentals' ) }
							],
							onChange: function( v ) { props.setAttributes( { link: v } ); }
						} )
					)
				),
				el( 'div', { className: 'ibb-block-preview ibb-block-preview--gallery' },
					previewOrPlaceholder( 'ibb/gallery', atts )
				)
			);
		},
		save: function() { return null; }
	} );

	// ─── ibb/calendar ───────────────────────────────────────────────────
	registerBlockType( 'ibb/calendar', {
		edit: function( props ) {
			var atts = props.attributes;
			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Property', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Property', 'ibb-rentals' ),
							value: atts.propertyId,
							options: propertyOptions(),
							onChange: function( v ) { props.setAttributes( { propertyId: parseInt( v, 10 ) || 0 } ); }
						} )
					),
					el( PanelBody, { title: __( 'Display', 'ibb-rentals' ), initialOpen: true },
						el( wp.components.RangeControl, {
							label: __( 'Months to show', 'ibb-rentals' ),
							value: atts.months,
							min: 1, max: 3,
							onChange: function( v ) { props.setAttributes( { months: v } ); }
						} ),
						el( wp.components.ToggleControl, {
							label: __( 'Show legend', 'ibb-rentals' ),
							checked: atts.legend,
							onChange: function( v ) { props.setAttributes( { legend: v } ); }
						} )
					)
				),
				el( 'div', { className: 'ibb-block-preview ibb-block-preview--calendar' },
					previewOrPlaceholder( 'ibb/calendar', atts )
				)
			);
		},
		save: function() { return null; }
	} );

	// ─── ibb/property-details ────────────────────────────────────────────
	registerBlockType( 'ibb/property-details', {
		edit: function( props ) {
			var atts = props.attributes;
			var fieldsArr = Array.isArray( atts.fields ) ? atts.fields : [];

			function toggleField( key, checked ) {
				var next = fieldsArr.slice();
				if ( checked ) {
					if ( next.indexOf( key ) === -1 ) next.push( key );
				} else {
					next = next.filter( function( k ) { return k !== key; } );
				}
				// Preserve declared order
				var order = ( data.detailFields || [] ).map( function( f ) { return f.value; } );
				next.sort( function( a, b ) { return order.indexOf( a ) - order.indexOf( b ); } );
				props.setAttributes( { fields: next } );
			}

			var fieldCheckboxes = ( data.detailFields || [] ).map( function( f ) {
				return el( CheckboxControl, {
					key: f.value,
					label: f.label,
					checked: fieldsArr.indexOf( f.value ) !== -1,
					onChange: function( checked ) { toggleField( f.value, checked ); }
				} );
			} );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Property', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Property', 'ibb-rentals' ),
							value: atts.propertyId,
							options: propertyOptions(),
							onChange: function( v ) { props.setAttributes( { propertyId: parseInt( v, 10 ) || 0 } ); }
						} )
					),
					el( PanelBody, { title: __( 'Fields', 'ibb-rentals' ), initialOpen: true }, fieldCheckboxes ),
					el( PanelBody, { title: __( 'Layout', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout', 'ibb-rentals' ),
							value: atts.layout,
							options: [
								{ value: 'grid', label: __( 'Grid (large value + label)', 'ibb-rentals' ) },
								{ value: 'compact', label: __( 'Compact (one line)', 'ibb-rentals' ) },
								{ value: 'list', label: __( 'List (key/value pairs)', 'ibb-rentals' ) }
							],
							onChange: function( v ) { props.setAttributes( { layout: v } ); }
						} )
					)
				),
				el( 'div', { className: 'ibb-block-preview ibb-block-preview--details' },
					previewOrPlaceholder( 'ibb/property-details', atts )
				)
			);
		},
		save: function() { return null; }
	} );

	// ─── ibb/property-description ───────────────────────────────────────
	registerBlockType( 'ibb/property-description', {
		edit: function( props ) {
			var atts = props.attributes;
			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Property', 'ibb-rentals' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Property', 'ibb-rentals' ),
							value: atts.propertyId,
							options: propertyOptions(),
							onChange: function( v ) { props.setAttributes( { propertyId: parseInt( v, 10 ) || 0 } ); }
						} )
					)
				),
				el( 'div', { className: 'ibb-block-preview ibb-block-preview--description' },
					previewOrPlaceholder( 'ibb/property-description', atts )
				)
			);
		},
		save: function() { return null; }
	} );

} )( window.wp );
JS;
	}

	private function editor_css(): string {
		return <<<CSS
.ibb-block-preview { padding: 4px; }
.ibb-block-preview--booking .ibb-booking { max-width: 380px; }
.ibb-block-preview--calendar .ibb-calendar { pointer-events: none; }
.ibb-block-preview--gallery .ibb-gallery-display { pointer-events: none; }
.ibb-block-preview--details .ibb-details { pointer-events: none; }
.editor-styles-wrapper .ibb-details--grid .ibb-details__item { background: #fff; }
.ibb-block-preview--description .ibb-property-description { pointer-events: none; }
CSS;
	}
}
