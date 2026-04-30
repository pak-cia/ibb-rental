<?php
/**
 * Adds support for `'type' => 'wp_editor'` form fields to WC_Email subclasses.
 *
 * WC's `WC_Settings_API` dispatches form-field rendering to `generate_<type>_html()`
 * and validation to `validate_<type>_field()` on the settings class. By implementing
 * those methods here, any WC_Email subclass that uses this trait can declare an
 * `'additional_content' => [ 'type' => 'wp_editor', ... ]` field and get a full
 * TinyMCE rich-text editor (with media library button) in the WC admin email screen.
 *
 * Trait so both `BookingConfirmationEmail` and `BookingReminderEmail` share the
 * implementation without forcing a non-WC parent class between them and `WC_Email`.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Emails;

defined( 'ABSPATH' ) || exit;

trait WpEditorFieldTrait {

	/**
	 * @param string                $key
	 * @param array<string, mixed>  $data
	 */
	public function generate_wp_editor_html( string $key, array $data ): string {
		$field_key = $this->get_field_key( $key );
		$defaults  = [
			'title'       => '',
			'description' => '',
			'default'     => '',
			'desc_tip'    => false,
			'editor_args' => [],
		];
		$data      = wp_parse_args( $data, $defaults );
		$value     = (string) $this->get_option( $key, (string) $data['default'] );

		$editor_args = array_merge(
			[
				'textarea_name' => $field_key,
				'textarea_rows' => 8,
				'media_buttons' => true,
				'teeny'         => false,
				'tinymce'       => [
					'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,fullscreen,wp_help',
					'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
				],
			],
			(array) $data['editor_args']
		);

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( (string) $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</th>
			<td class="forminp">
				<fieldset>
					<?php wp_editor( $value, $field_key, $editor_args ); ?>
					<?php if ( ! empty( $data['description'] ) && empty( $data['desc_tip'] ) ) : ?>
						<p class="description"><?php echo wp_kses_post( (string) $data['description'] ); ?></p>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Sanitize on save. wp_kses_post allows the same tags as WP post content,
	 * which lines up with what the editor produces.
	 *
	 * @param string      $key
	 * @param string|null $value
	 */
	public function validate_wp_editor_field( string $key, $value ): string {
		return wp_kses_post( (string) $value );
	}
}
