<?php
/**
 * Base class for IBB property-field text dynamic tags.
 *
 * One shared `Property` picker control + resolver. Subclasses just override
 * `get_field_name()` / `get_field_title()` and implement `field_value()` to
 * pull a single string from the resolved `Property`.
 *
 * Why a base class: every text field tag (Title, Address, Bedrooms, Base
 * Rate, Check-in time, …) repeats the same Property control, the same
 * `resolve_property_for_widget()` call, and the same empty-value handling.
 * Concentrating that here keeps each leaf tag to ~10 lines.
 *
 * Tags extending this register under the `text` Elementor dynamic-tag
 * category — bindable to any widget control with `dynamic.active = true`
 * (Heading, Text Editor, native string controls).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Integrations\Elementor\DynamicTags;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Integrations\Elementor\Module as ElementorModule;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\\Elementor\\Core\\DynamicTags\\Tag' ) ) {
	return;
}

abstract class AbstractPropertyFieldTag extends \Elementor\Core\DynamicTags\Tag {

	abstract public function field_value( Property $property ): string;

	public function get_group(): string {
		return 'ibb-rentals';
	}

	public function get_categories(): array {
		if ( defined( '\\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY' ) ) {
			return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
		}
		return [ 'text' ];
	}

	protected function register_controls(): void {
		$this->add_control( 'property_id', [
			'label'   => __( 'Property', 'ibb-rentals' ),
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => ElementorModule::property_options(),
			'default' => 'current',
		] );
	}

	public function render(): void {
		$property = ElementorModule::resolve_property_for_widget(
			(string) $this->get_settings( 'property_id' )
		);
		if ( ! $property ) {
			return;
		}
		echo wp_kses_post( $this->field_value( $property ) );
	}
}
