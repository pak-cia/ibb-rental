<?php
/**
 * Tabbed metabox for the property edit screen.
 *
 * Each tab maps to one logical configuration area: details, rates, rules,
 * availability, iCal. The metabox owns its own tiny CSS/JS — bundled inline
 * so the build pipeline doesn't have to ship anything for this to work.
 *
 * Save handlers nonce-check, sanitize, then write postmeta. Rate rows go to
 * the custom `ibb_rates` table via RateRepository, NOT postmeta.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Admin;

use IBB\Rentals\Domain\Property;
use IBB\Rentals\Ical\Exporter;
use IBB\Rentals\PostTypes\PropertyPostType;
use IBB\Rentals\Repositories\FeedRepository;
use IBB\Rentals\Repositories\RateRepository;
use IBB\Rentals\Woo\GatewayCapabilities;

defined( 'ABSPATH' ) || exit;

final class PropertyMetaboxes {

	private const NONCE = 'ibb_property_meta';

	public function __construct(
		private RateRepository $rates,
		private FeedRepository $feeds,
		private Exporter $ical_exporter,
		private GatewayCapabilities $gateways,
	) {}

	public function register(): void {
		add_action( 'add_meta_boxes_' . PropertyPostType::POST_TYPE, [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . PropertyPostType::POST_TYPE, [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'print_footer_js' ], 99 );
	}

	public function print_footer_js(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== PropertyPostType::POST_TYPE ) {
			return;
		}
		if ( ! in_array( $screen->base, [ 'post' ], true ) ) {
			return;
		}
		echo '<script>' . $this->galleries_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>' . $this->los_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>' . $this->blackout_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script>' . $this->seasonal_rates_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function enqueue( string $hook ): void {
		global $post;
		if ( ! $post instanceof \WP_Post || $post->post_type !== PropertyPostType::POST_TYPE ) {
			return;
		}
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_register_style( 'ibb-rentals-admin', false, [], IBB_RENTALS_VERSION );
		wp_enqueue_style( 'ibb-rentals-admin' );
		wp_add_inline_style( 'ibb-rentals-admin', $this->css() );
	}

	public function add_metabox(): void {
		add_meta_box(
			'ibb-property-config',
			__( 'Rental configuration', 'ibb-rentals' ),
			[ $this, 'render' ],
			PropertyPostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		$property = Property::from_post( $post );
		if ( ! $property ) {
			return;
		}
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$tabs = [
			'details'      => __( 'Details', 'ibb-rentals' ),
			'photos'       => __( 'Photos', 'ibb-rentals' ),
			'rates'        => __( 'Rates', 'ibb-rentals' ),
			'rules'        => __( 'Booking rules', 'ibb-rentals' ),
			'availability' => __( 'Availability', 'ibb-rentals' ),
			'ical'         => __( 'iCal', 'ibb-rentals' ),
		];

		echo '<div class="ibb-tabs">';
		echo '<ul class="ibb-tabs__nav">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<li><a href="#ibb-tab-%1$s" data-tab="%1$s">%2$s</a></li>',
				esc_attr( $key ),
				esc_html( $label )
			);
		}
		echo '</ul>';

		echo '<div class="ibb-tabs__panels">';
		$this->render_details( $property );
		$this->render_photos( $property );
		$this->render_rates( $property );
		$this->render_rules( $property );
		$this->render_availability( $property );
		$this->render_ical( $property );
		echo '</div></div>';

		echo '<script>(function(){var tabs=document.querySelector(".ibb-tabs");if(!tabs)return;var links=tabs.querySelectorAll(".ibb-tabs__nav a");function activate(name){links.forEach(function(a){a.classList.toggle("is-active",a.dataset.tab===name)});tabs.querySelectorAll(".ibb-tab").forEach(function(p){p.classList.toggle("is-active",p.id==="ibb-tab-"+name)});}links.forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();activate(a.dataset.tab);});});activate(links[0].dataset.tab);})();</script>';
	}

	private function render_details( Property $p ): void {
		echo '<div class="ibb-tab" id="ibb-tab-details"><table class="form-table"><tbody>';

		// Short description — surfaces in cart line items + search cards.
		// Backed by `_ibb_short_description` postmeta (not WP's post_excerpt)
		// to avoid the Gutenberg-sidebar / metabox-form save race.
		echo '<tr><th><label for="_ibb_short_description">' . esc_html__( 'Short description', 'ibb-rentals' ) . '</label></th><td>';
		printf(
			'<textarea id="_ibb_short_description" name="_ibb_short_description" rows="2" class="large-text" placeholder="%s">%s</textarea>',
			esc_attr__( 'e.g. Beachfront villa with private pool, sleeps 6.', 'ibb-rentals' ),
			esc_textarea( $p->short_description() )
		);
		echo '<p class="description">' . esc_html__( 'A brief one-or-two-sentence summary. Shown under the property name in cart, checkout, and search results.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th><label for="_ibb_description">' . esc_html__( 'Description', 'ibb-rentals' ) . '</label></th><td>';
		printf(
			'<textarea id="_ibb_description" name="_ibb_description" rows="7" class="large-text" placeholder="%s">%s</textarea>',
			esc_attr__( 'Full property writeup — amenities, atmosphere, surroundings, house rules, etc.', 'ibb-rentals' ),
			esc_textarea( $p->description() )
		);
		echo '<p class="description">' . esc_html__( 'Displayed via the Property Description block or Elementor dynamic tag.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';

		$this->row( __( 'Max guests', 'ibb-rentals' ),     $this->number( '_ibb_max_guests', $p->max_guests(), 1 ) );
		$this->row( __( 'Bedrooms', 'ibb-rentals' ),       $this->number( '_ibb_bedrooms', $p->bedrooms(), 0 ) );
		$this->row( __( 'Bathrooms', 'ibb-rentals' ),      $this->number( '_ibb_bathrooms', $p->bathrooms(), 0, 0.5 ) );
		$this->row( __( 'Beds', 'ibb-rentals' ),           $this->number( '_ibb_beds', $p->beds(), 0 ) );
		$this->row( __( 'Address', 'ibb-rentals' ),        $this->text(   '_ibb_address', (string) $p->meta( '_ibb_address', '' ) ) );
		$this->row( __( 'Latitude', 'ibb-rentals' ),       $this->text(   '_ibb_lat', (string) $p->meta( '_ibb_lat', '' ) ) );
		$this->row( __( 'Longitude', 'ibb-rentals' ),      $this->text(   '_ibb_lng', (string) $p->meta( '_ibb_lng', '' ) ) );
		$this->row( __( 'Check-in time', 'ibb-rentals' ),  $this->time(   '_ibb_check_in_time', $p->check_in_time() ) );
		$this->row( __( 'Check-out time', 'ibb-rentals' ), $this->time(   '_ibb_check_out_time', $p->check_out_time() ) );
		echo '</tbody></table></div>';
	}

	private function render_photos( Property $p ): void {
		$galleries = $p->galleries();

		// Hydrate each gallery's attachments with a thumbnail URL so the JS
		// can render existing items immediately on page load (without a
		// secondary REST round-trip).
		$hydrated = [];
		foreach ( $galleries as $g ) {
			$items = [];
			foreach ( $g['attachments'] as $aid ) {
				$src = wp_get_attachment_image_src( (int) $aid, 'thumbnail' );
				$items[] = [
					'id'    => (int) $aid,
					'thumb' => $src ? (string) $src[0] : '',
					'alt'   => (string) get_post_meta( (int) $aid, '_wp_attachment_image_alt', true ),
				];
			}
			$hydrated[] = [
				'slug'        => $g['slug'],
				'label'       => $g['label'],
				'attachments' => $items,
			];
		}

		echo '<div class="ibb-tab" id="ibb-tab-photos">';
		echo '<p class="description">' . esc_html__( 'Organise property photos into named galleries (e.g. "Main", "Bedroom 1", "Pool"). Use the [ibb_gallery] shortcode or the IBB dynamic tag in Elementor to reference them on the front-end.', 'ibb-rentals' ) . '</p>';

		echo '<div class="ibb-galleries" id="ibb-galleries">';
		printf(
			'<textarea name="_ibb_galleries" id="ibb-galleries-data" hidden>%s</textarea>',
			esc_textarea( wp_json_encode( $galleries ) ?: '[]' )
		);

		// Initial state passed to JS so the first paint shows existing thumbnails
		// without needing to fetch them.
		printf(
			'<script type="application/json" id="ibb-galleries-initial">%s</script>',
			wp_json_encode( $hydrated ) ?: '[]'
		);

		echo '<div class="ibb-galleries__add">';
		echo '<input type="text" id="ibb-new-gallery-label" placeholder="' . esc_attr__( 'New gallery name (e.g. Bedroom 1)', 'ibb-rentals' ) . '" />';
		echo '<button type="button" class="button button-secondary" id="ibb-add-gallery">+ ' . esc_html__( 'Add gallery', 'ibb-rentals' ) . '</button>';
		echo '</div>';

		echo '<div class="ibb-galleries__list" id="ibb-galleries-list"></div>';

		echo '</div></div>';
	}

	private function render_rates( Property $p ): void {
		$rate_rows = $this->rates->find_for_property( $p->id );
		echo '<div class="ibb-tab" id="ibb-tab-rates"><table class="form-table"><tbody>';
		$this->row( __( 'Base nightly rate', 'ibb-rentals' ),    $this->number( '_ibb_base_rate', $p->base_rate(), 0, 0.01 ) );
		$this->row( __( 'Weekend uplift (%)', 'ibb-rentals' ),   $this->number( '_ibb_weekend_uplift_pct', $p->weekend_uplift_pct(), 0, 0.1 ) );
		$this->row( __( 'Weekend days (ISO 1=Mon … 7=Sun)', 'ibb-rentals' ), $this->text( '_ibb_weekend_days', (string) $p->meta( '_ibb_weekend_days', '5,6,7' ) ) );
		echo '</tbody></table>';

		$this->render_los_editor( $p );

		$this->render_seasonal_rates_editor( $rate_rows );
		echo '</div>';
	}

	/** @param list<array<string, mixed>> $rate_rows */
	private function render_seasonal_rates_editor( array $rate_rows ): void {
		echo '<h4>' . esc_html__( 'Seasonal rates', 'ibb-rentals' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Override the base nightly rate for specific date ranges. Both From and To are inclusive — the rate applies on every night from From through To. On overlap, higher priority wins.', 'ibb-rentals' ) . '</p>';

		echo '<div class="ibb-srates-wrap"><table class="ibb-srates" id="ibb-srates"><thead><tr>';
		echo '<th>' . esc_html__( 'From (inclusive)', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'To (inclusive)', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Rate/night', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Label', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Priority', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Wknd uplift', 'ibb-rentals' ) . '</th>';
		echo '<th>' . esc_html__( 'Min stay', 'ibb-rentals' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody id="ibb-srates-rows">';

		if ( ! $rate_rows ) {
			$this->render_seasonal_rate_row( 0, [], true );
		} else {
			foreach ( $rate_rows as $i => $row ) {
				$this->render_seasonal_rate_row( $i, $row, false );
			}
		}

		echo '</tbody></table></div>';

		echo '<template id="ibb-srates-row-template">';
		$this->render_seasonal_rate_row( '__INDEX__', [], true );
		echo '</template>';

		echo '<p><button type="button" class="button button-secondary" id="ibb-srates-add">+ ' . esc_html__( 'Add seasonal rate', 'ibb-rentals' ) . '</button></p>';
	}

	/** @param int|string $index @param array<string, mixed> $row */
	private function render_seasonal_rate_row( int|string $index, array $row, bool $is_blank ): void {
		$n        = '_ibb_seasonal_rate_rows[' . $index . ']';
		$from     = $is_blank ? '' : esc_attr( (string) $row['date_from'] );
		$to       = $is_blank ? '' : esc_attr( (string) $row['date_to'] );
		$rate     = $is_blank ? '' : esc_attr( (string) $row['nightly_rate'] );
		$label    = $is_blank ? '' : esc_attr( (string) $row['label'] );
		$priority = $is_blank ? '10' : esc_attr( (string) $row['priority'] );
		$uplift   = $is_blank ? '' : esc_attr( (string) ( $row['weekend_uplift'] ?? '' ) );
		$utype    = $is_blank ? 'pct' : (string) ( $row['uplift_type'] ?? 'pct' );
		$minstay  = $is_blank ? '' : esc_attr( (string) ( $row['min_stay'] ?? '' ) );

		echo '<tr class="ibb-srates__row">';
		printf( '<td><input type="date" name="%s[date_from]" value="%s" required /></td>', esc_attr( $n ), $from );
		printf( '<td><input type="date" name="%s[date_to]" value="%s" required /></td>', esc_attr( $n ), $to );
		printf( '<td><input type="number" name="%s[nightly_rate]" value="%s" min="0" step="0.01" style="width:140px" required /></td>', esc_attr( $n ), $rate );
		printf( '<td><input type="text" name="%s[label]" value="%s" placeholder="%s" style="width:120px" /></td>', esc_attr( $n ), $label, esc_attr__( 'e.g. High season', 'ibb-rentals' ) );
		printf( '<td><input type="number" name="%s[priority]" value="%s" min="0" max="999" style="width:55px" /></td>', esc_attr( $n ), $priority );
		echo '<td>';
		printf( '<input type="number" name="%s[weekend_uplift]" value="%s" min="0" step="0.01" style="width:110px" placeholder="—" />', esc_attr( $n ), $uplift );
		echo ' <select name="' . esc_attr( $n ) . '[uplift_type]" style="width:52px">';
		printf( '<option value="pct" %s>%%</option>', selected( $utype, 'pct', false ) );
		printf( '<option value="abs" %s>abs</option>', selected( $utype, 'abs', false ) );
		echo '</select></td>';
		printf( '<td><input type="number" name="%s[min_stay]" value="%s" min="1" style="width:55px" placeholder="—" /></td>', esc_attr( $n ), $minstay );
		echo '<td><button type="button" class="button-link ibb-srates__remove" aria-label="' . esc_attr__( 'Remove', 'ibb-rentals' ) . '">×</button></td>';
		echo '</tr>';
	}

	private function render_rules( Property $p ): void {
		echo '<div class="ibb-tab" id="ibb-tab-rules"><table class="form-table"><tbody>';
		$this->row( __( 'Min nights', 'ibb-rentals' ),               $this->number( '_ibb_min_nights', $p->min_nights(), 1 ) );
		$this->row( __( 'Max nights (0 = no limit)', 'ibb-rentals' ), $this->number( '_ibb_max_nights', $p->max_nights(), 0 ) );
		$this->row( __( 'Min advance days', 'ibb-rentals' ),         $this->number( '_ibb_advance_booking_days', $p->advance_booking_days(), 0 ) );
		$this->row( __( 'Max advance days (0 = no limit)', 'ibb-rentals' ), $this->number( '_ibb_max_advance_days', $p->max_advance_days(), 0 ) );
		$this->row( __( 'Cleaning fee', 'ibb-rentals' ),             $this->number( '_ibb_cleaning_fee', $p->cleaning_fee(), 0, 0.01 ) );
		$this->row( __( 'Extra-guest fee (per guest, per night)', 'ibb-rentals' ), $this->number( '_ibb_extra_guest_fee', $p->extra_guest_fee(), 0, 0.01 ) );
		$this->row( __( 'Extra-guest threshold', 'ibb-rentals' ),    $this->number( '_ibb_extra_guest_threshold', $p->extra_guest_threshold(), 0 ) );
		$this->row( __( 'Security deposit (informational)', 'ibb-rentals' ), $this->number( '_ibb_security_deposit', $p->security_deposit(), 0, 0.01 ) );

		// ── Tax classes ───────────────────────────────────────────────────
		// Three independent selectors so admins can charge e.g. PB1 hotel tax
		// on the stay but keep cleaning at standard VAT (or untaxed). The
		// accommodation class also mirrors to the linked WC product's
		// tax_class + tax_status (see ProductSync). Cleaning and extra-guest
		// tax classes are applied at cart time via WC_Cart::add_fee() so each
		// fee carries its own tax class through to checkout — see
		// Woo/CartHandler.php.
		$tax_options = $this->tax_class_options();

		echo '<tr><th><label>' . esc_html__( 'Tax class — accommodation', 'ibb-rentals' ) . '</label></th><td>';
		$this->render_tax_class_select( '_ibb_tax_class', (string) $p->meta( '_ibb_tax_class', '' ), $tax_options );
		echo '<p class="description">' . esc_html__( 'Applied to nights × rate (after any length-of-stay discount). Mirrored to the linked WC product on save. Configure rates under WooCommerce → Settings → Tax.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th><label>' . esc_html__( 'Tax class — cleaning fee', 'ibb-rentals' ) . '</label></th><td>';
		$this->render_tax_class_select( '_ibb_cleaning_tax_class', (string) $p->meta( '_ibb_cleaning_tax_class', '' ), $tax_options );
		echo '<p class="description">' . esc_html__( 'Defaults to "Not taxed". Some jurisdictions exempt cleaning from accommodation tax — set this independently of the stay class.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th><label>' . esc_html__( 'Tax class — extra-guest fee', 'ibb-rentals' ) . '</label></th><td>';
		$current_eg = (string) $p->meta( '_ibb_extra_guest_tax_class', '__inherit__' );
		$eg_options = [ '__inherit__' => __( 'Same as accommodation', 'ibb-rentals' ) ] + $tax_options;
		$this->render_tax_class_select( '_ibb_extra_guest_tax_class', $current_eg, $eg_options );
		echo '<p class="description">' . esc_html__( 'Extra-guest fees usually follow the accommodation tax. Override here if your tax regime treats them differently.', 'ibb-rentals' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th></th><td><p class="description"><em>' . esc_html__( 'Security deposit is informational only and never charged today, so no tax class applies to it.', 'ibb-rentals' ) . '</em></p></td></tr>';

		$mode = $p->payment_mode();
		echo '<tr><th><label>' . esc_html__( 'Payment mode', 'ibb-rentals' ) . '</label></th><td>';
		echo '<select name="_ibb_payment_mode">';
		printf( '<option value="full" %s>%s</option>', selected( $mode, 'full', false ), esc_html__( 'Full payment at booking', 'ibb-rentals' ) );
		printf( '<option value="deposit" %s>%s</option>', selected( $mode, 'deposit', false ), esc_html__( 'Deposit + balance later', 'ibb-rentals' ) );
		echo '</select></td></tr>';
		$this->row( __( 'Deposit %', 'ibb-rentals' ),                 $this->number( '_ibb_deposit_pct', $p->deposit_pct(), 0, 1 ) );
		$this->row( __( 'Balance due (days before check-in)', 'ibb-rentals' ), $this->number( '_ibb_balance_due_days_before', $p->balance_due_days_before(), 0 ) );
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Gateway capabilities', 'ibb-rentals' ) . '</h4>';
		$summary = $this->gateways->active_gateway_summary();
		if ( $summary ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Gateway', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'Balance path', 'ibb-rentals' ) . '</th></tr></thead><tbody>';
			foreach ( $summary as $g ) {
				printf(
					'<tr><td>%s</td><td><code>%s</code></td></tr>',
					esc_html( $g['title'] ),
					esc_html( $g['path'] )
				);
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'auto_charge = stored card off-session. payment_link = guest receives a pay-for-order email before check-in.', 'ibb-rentals' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No active WooCommerce gateways detected.', 'ibb-rentals' ) . '</p>';
		}
		echo '</div>';
	}

	private function render_availability( Property $p ): void {
		echo '<div class="ibb-tab" id="ibb-tab-availability">';
		$this->render_blackout_editor( $p );
		echo '<p class="description">' . esc_html__( 'Manual block-outs (via drag-to-block on the calendar) live in the bookings system and respect the audit log. Blackout ranges above refuse booking attempts at quote time — useful for maintenance periods or owner-held dates that recur regularly.', 'ibb-rentals' ) . '</p>';
		echo '</div>';
	}

	private function render_blackout_editor( Property $p ): void {
		$rows = $p->blackout_ranges();
		// Sort ascending by start date in the editor.
		usort( $rows, static fn( $a, $b ) => strcmp( (string) $a['start'], (string) $b['start'] ) );

		echo '<h4>' . esc_html__( 'Blackout ranges', 'ibb-rentals' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Date ranges where new bookings are refused. Both From and To are inclusive — entering May 1 → May 7 means the property is unavailable on every night from May 1 through May 7. Guests see these dates greyed out in the date picker.', 'ibb-rentals' ) . '</p>';

		echo '<table class="ibb-blackout" id="ibb-blackout"><thead><tr>';
		echo '<th class="ibb-blackout__col-start">' . esc_html__( 'From (inclusive)', 'ibb-rentals' ) . '</th>';
		echo '<th class="ibb-blackout__col-end">' . esc_html__( 'To (inclusive)', 'ibb-rentals' ) . '</th>';
		echo '<th class="ibb-blackout__col-actions"></th>';
		echo '</tr></thead><tbody id="ibb-blackout-rows">';

		if ( ! $rows ) {
			$this->render_blackout_row( 0, [ 'start' => '', 'end' => '' ], true );
		} else {
			foreach ( $rows as $i => $row ) {
				$this->render_blackout_row( $i, $row, false );
			}
		}

		echo '</tbody></table>';

		echo '<template id="ibb-blackout-row-template">';
		$this->render_blackout_row( '__INDEX__', [ 'start' => '', 'end' => '' ], true );
		echo '</template>';

		echo '<p><button type="button" class="button button-secondary" id="ibb-blackout-add">+ ' . esc_html__( 'Add blackout range', 'ibb-rentals' ) . '</button></p>';
	}

	/**
	 * @param int|string $index
	 *
	 * Blackout ranges (and seasonal rate ranges) use INCLUSIVE end-date
	 * semantics — different from booking ranges (`wp_ibb_blocks`, iCal
	 * VEVENTs) which are half-open `[checkin, checkout)` so turnover days
	 * work. For an admin-defined blackout, "May 1 → May 7" means every
	 * night from May 1 through May 7 is unavailable.
	 */
	private function render_blackout_row( int|string $index, array $row, bool $is_blank ): void {
		$start_name = '_ibb_blackout_rows[' . $index . '][start]';
		$end_name   = '_ibb_blackout_rows[' . $index . '][end]';
		echo '<tr class="ibb-blackout__row">';
		printf(
			'<td class="ibb-blackout__col-start"><input type="date" name="%s" value="%s" /></td>',
			esc_attr( $start_name ),
			esc_attr( (string) ( $is_blank ? '' : $row['start'] ) )
		);
		printf(
			'<td class="ibb-blackout__col-end"><input type="date" name="%s" value="%s" /></td>',
			esc_attr( $end_name ),
			esc_attr( (string) ( $is_blank ? '' : $row['end'] ) )
		);
		echo '<td class="ibb-blackout__col-actions"><button type="button" class="button-link ibb-blackout__remove" aria-label="' . esc_attr__( 'Remove range', 'ibb-rentals' ) . '">×</button></td>';
		echo '</tr>';
	}

	private function render_ical( Property $p ): void {
		echo '<div class="ibb-tab" id="ibb-tab-ical">';

		$urls   = $this->ical_exporter->feed_urls( $p->id );
		$labels = [
			'airbnb'  => 'Airbnb',
			'booking' => 'Booking.com',
			'agoda'   => 'Agoda',
			'vrbo'    => 'VRBO',
			'expedia' => 'Expedia',
		];

		echo '<h4>' . esc_html__( 'Export feeds — one URL per OTA', 'ibb-rentals' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Paste each URL into the matching OTA as its inbound calendar feed. The plugin acts as the central availability hub: every booking on every OTA (plus website + walk-in bookings) flows out to the others, with a per-OTA loop guard so an OTA never re-imports its own bookings.', 'ibb-rentals' ) . '</p>';
		echo '<table class="widefat striped" style="margin-bottom:16px;"><thead><tr><th style="width:140px;">' . esc_html__( 'OTA', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'Feed URL', 'ibb-rentals' ) . '</th></tr></thead><tbody>';
		foreach ( $urls as $ota => $url ) {
			printf(
				'<tr><td><strong>%s</strong></td><td><input type="text" readonly value="%s" class="large-text code" onclick="this.select()" /></td></tr>',
				esc_html( $labels[ $ota ] ?? ucfirst( $ota ) ),
				esc_attr( $url )
			);
		}
		echo '</tbody></table>';

		echo '<h4>' . esc_html__( 'Import feeds from OTAs', 'ibb-rentals' ) . '</h4>';
		$rows = $this->feeds->find_for_property( $p->id );
		if ( $rows ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Label', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'Source', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'URL', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'Last sync', 'ibb-rentals' ) . '</th><th>' . esc_html__( 'Status', 'ibb-rentals' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
					esc_html( (string) $r['label'] ),
					esc_html( (string) $r['source'] ),
					esc_html( (string) $r['url'] ),
					esc_html( (string) ( $r['last_synced_at'] ?: '—' ) ),
					esc_html( (string) ( $r['last_status'] ?: '—' ) )
				);
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No import feeds configured. Add one via the Rentals → Feeds admin page.', 'ibb-rentals' ) . '</p>';
		}
		echo '</div>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$numeric_keys = [
			'_ibb_max_guests', '_ibb_bedrooms', '_ibb_bathrooms', '_ibb_beds',
			'_ibb_base_rate', '_ibb_weekend_uplift_pct',
			'_ibb_min_nights', '_ibb_max_nights',
			'_ibb_advance_booking_days', '_ibb_max_advance_days',
			'_ibb_cleaning_fee', '_ibb_extra_guest_fee', '_ibb_extra_guest_threshold',
			'_ibb_security_deposit', '_ibb_deposit_pct', '_ibb_balance_due_days_before',
		];
		foreach ( $numeric_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, (float) wp_unslash( $_POST[ $key ] ) );
			}
		}

		$text_keys = [ '_ibb_address', '_ibb_lat', '_ibb_lng', '_ibb_weekend_days', '_ibb_check_in_time', '_ibb_check_out_time' ];
		foreach ( $text_keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( isset( $_POST['_ibb_short_description'] ) ) {
			update_post_meta(
				$post_id,
				'_ibb_short_description',
				sanitize_textarea_field( (string) wp_unslash( $_POST['_ibb_short_description'] ) )
			);
		}

		if ( isset( $_POST['_ibb_description'] ) ) {
			update_post_meta(
				$post_id,
				'_ibb_description',
				sanitize_textarea_field( (string) wp_unslash( $_POST['_ibb_description'] ) )
			);
		}

		if ( isset( $_POST['_ibb_payment_mode'] ) ) {
			$mode = (string) wp_unslash( $_POST['_ibb_payment_mode'] );
			update_post_meta( $post_id, '_ibb_payment_mode', $mode === 'deposit' ? 'deposit' : 'full' );
		}

		// Tax-class fields. Allowed values: '', 'standard', plus every slug from
		// WC → Settings → Tax. The extra-guest field also accepts the sentinel
		// '__inherit__' meaning "follow the accommodation tax class".
		$allowed_tax = [ '', 'standard' ];
		if ( class_exists( '\\WC_Tax' ) ) {
			foreach ( \WC_Tax::get_tax_classes() as $class_label ) {
				$allowed_tax[] = sanitize_title( $class_label );
			}
		}
		foreach ( [ '_ibb_tax_class', '_ibb_cleaning_tax_class' ] as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$raw = sanitize_title( (string) wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, $key, in_array( $raw, $allowed_tax, true ) ? $raw : '' );
			}
		}
		if ( isset( $_POST['_ibb_extra_guest_tax_class'] ) ) {
			$raw = (string) wp_unslash( $_POST['_ibb_extra_guest_tax_class'] );
			$raw = $raw === '__inherit__' ? '__inherit__' : sanitize_title( $raw );
			$allowed_eg = array_merge( $allowed_tax, [ '__inherit__' ] );
			update_post_meta( $post_id, '_ibb_extra_guest_tax_class', in_array( $raw, $allowed_eg, true ) ? $raw : '__inherit__' );
		}

		// LOS discounts: row-based UI now (was JSON textarea). Each row is
		// `_ibb_los_discount_rows[N][min_nights]` + `[pct]`. Pairs with both
		// values populated are kept; rows where either is missing/blank are
		// dropped. Stored in the canonical `_ibb_los_discounts` JSON shape.
		if ( isset( $_POST['_ibb_los_discount_rows'] ) && is_array( $_POST['_ibb_los_discount_rows'] ) ) {
			$out  = [];
			$seen = [];
			foreach ( wp_unslash( $_POST['_ibb_los_discount_rows'] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$min = isset( $row['min_nights'] ) ? (int) $row['min_nights'] : 0;
				$pct = isset( $row['pct'] ) ? (float) $row['pct'] : 0.0;
				if ( $min < 1 || $pct <= 0 || $pct > 100 ) {
					continue;
				}
				if ( isset( $seen[ $min ] ) ) {
					continue; // duplicate min_nights — keep the first.
				}
				$seen[ $min ] = true;
				$out[] = [ 'min_nights' => $min, 'pct' => round( $pct, 2 ) ];
			}
			usort( $out, static fn( $a, $b ) => $b['min_nights'] <=> $a['min_nights'] );
			update_post_meta( $post_id, '_ibb_los_discounts', wp_json_encode( $out ) ?: '[]' );
		}

		if ( isset( $_POST['_ibb_blackout_rows'] ) && is_array( $_POST['_ibb_blackout_rows'] ) ) {
			$out = [];
			foreach ( (array) $_POST['_ibb_blackout_rows'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$start = sanitize_text_field( (string) wp_unslash( $row['start'] ?? '' ) );
				$end   = sanitize_text_field( (string) wp_unslash( $row['end'] ?? '' ) );
				if ( $start === '' || $end === '' ) {
					continue;
				}
				// Validate date format and logical order. Both ends are
				// inclusive — admin enters "May 1 → May 7" to block 7
				// nights. Same-day blackout (start == end) blocks one
				// night. Reject only when end is BEFORE start.
				$s = \DateTimeImmutable::createFromFormat( '!Y-m-d', $start );
				$e = \DateTimeImmutable::createFromFormat( '!Y-m-d', $end );
				if ( ! $s || ! $e || $e < $s ) {
					continue;
				}
				$out[] = [ 'start' => $start, 'end' => $end ];
			}
			usort( $out, static fn( $a, $b ) => strcmp( $a['start'], $b['start'] ) );
			update_post_meta( $post_id, '_ibb_blackout_ranges', wp_json_encode( $out ) ?: '[]' );
		}

		// Seasonal rates: delete-and-reinsert on every save.
		if ( isset( $_POST['_ibb_seasonal_rate_rows'] ) && is_array( $_POST['_ibb_seasonal_rate_rows'] ) ) {
			$this->rates->delete_for_property( $post_id );
			foreach ( (array) wp_unslash( $_POST['_ibb_seasonal_rate_rows'] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$from = sanitize_text_field( (string) ( $row['date_from'] ?? '' ) );
				$to   = sanitize_text_field( (string) ( $row['date_to']   ?? '' ) );
				$rate = (float) ( $row['nightly_rate'] ?? 0 );
				if ( $from === '' || $to === '' || $rate <= 0 ) {
					continue;
				}
				$df = \DateTimeImmutable::createFromFormat( '!Y-m-d', $from );
				$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $to );
				// Both ends inclusive — same-day rate (single-night
				// override, e.g. Christmas) is valid; reject only when
				// `to` is BEFORE `from`.
				if ( ! $df || ! $dt || $dt < $df ) {
					continue;
				}
				$uplift_raw = trim( (string) ( $row['weekend_uplift'] ?? '' ) );
				$minstay_raw = trim( (string) ( $row['min_stay'] ?? '' ) );
				$this->rates->insert( [
					'property_id'    => $post_id,
					'date_from'      => $from,
					'date_to'        => $to,
					'nightly_rate'   => round( $rate, 2 ),
					'label'          => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
					'priority'       => max( 0, (int) ( $row['priority'] ?? 10 ) ),
					'weekend_uplift' => $uplift_raw !== '' ? round( (float) $uplift_raw, 2 ) : null,
					'uplift_type'    => in_array( (string) ( $row['uplift_type'] ?? 'pct' ), [ 'pct', 'abs' ], true ) ? $row['uplift_type'] : 'pct',
					'min_stay'       => $minstay_raw !== '' ? max( 1, (int) $minstay_raw ) : null,
				] );
			}
		}

		if ( isset( $_POST['_ibb_galleries'] ) ) {
			$raw     = (string) wp_unslash( $_POST['_ibb_galleries'] );
			$decoded = json_decode( $raw, true );
			$out     = [];
			$seen    = [];
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $g ) {
					if ( ! is_array( $g ) ) {
						continue;
					}
					$label = isset( $g['label'] ) ? sanitize_text_field( (string) $g['label'] ) : '';
					if ( $label === '' ) {
						continue;
					}
					$slug = isset( $g['slug'] ) && $g['slug'] !== ''
						? sanitize_key( (string) $g['slug'] )
						: sanitize_title( $label );
					$slug = $slug !== '' ? $slug : 'gallery';

					// Enforce slug uniqueness within this property.
					$base = $slug;
					$i    = 2;
					while ( isset( $seen[ $slug ] ) ) {
						$slug = $base . '-' . $i;
						$i++;
					}
					$seen[ $slug ] = true;

					$ids = array_values( array_filter( array_map( 'intval', (array) ( $g['attachments'] ?? [] ) ) ) );
					$out[] = [ 'slug' => $slug, 'label' => $label, 'attachments' => $ids ];
				}
			}
			update_post_meta( $post_id, '_ibb_galleries', wp_json_encode( $out ) ?: '[]' );
		}
	}

	private function row( string $label, string $field ): void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $field . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Friendly editor for length-of-stay discounts.
	 *
	 * Replaces the original `_ibb_los_discounts` JSON textarea with a
	 * table of (min nights, % off) rows. Each row is a pair of named
	 * inputs `_ibb_los_discount_rows[N][min_nights]` and `[pct]` —
	 * native form arrays, so saves work even with JS disabled. JS
	 * adds row indexing and the +/- buttons.
	 *
	 * The legacy `_ibb_los_discounts` postmeta key is still the
	 * canonical storage (a JSON-encoded array sorted desc by
	 * min_nights). The save handler reads the new row inputs and
	 * writes the canonical key — no migration needed for existing
	 * properties since the rows are seeded from `los_discounts()`.
	 */
	private function render_los_editor( Property $p ): void {
		$rows = $p->los_discounts();
		// Sort ascending in the editor so the smallest stay shows first
		// (matches how a user would think about it: "after 7 nights they
		// get X off, after 14 nights they get Y off…"). Storage stays
		// descending — sorted at save time.
		usort( $rows, static fn( $a, $b ) => $a['min_nights'] <=> $b['min_nights'] );

		echo '<h4>' . esc_html__( 'Length-of-stay discounts', 'ibb-rentals' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Discount % off the nightly subtotal once the stay reaches the minimum number of nights. The longest matching stay wins, so a guest booking 21 nights with both 7- and 14-night rules gets the 14-night discount.', 'ibb-rentals' ) . '</p>';

		echo '<table class="ibb-los" id="ibb-los"><thead><tr>';
		echo '<th class="ibb-los__col-nights">' . esc_html__( 'Min nights', 'ibb-rentals' ) . '</th>';
		echo '<th class="ibb-los__col-pct">' . esc_html__( '% off', 'ibb-rentals' ) . '</th>';
		echo '<th class="ibb-los__col-actions"></th>';
		echo '</tr></thead><tbody id="ibb-los-rows">';

		if ( ! $rows ) {
			$this->render_los_row( 0, [ 'min_nights' => 7, 'pct' => 0.0 ] );
		} else {
			foreach ( $rows as $i => $row ) {
				$this->render_los_row( $i, $row );
			}
		}

		echo '</tbody></table>';

		// Hidden template row used by the JS to clone new rows.
		echo '<template id="ibb-los-row-template">';
		$this->render_los_row( '__INDEX__', [ 'min_nights' => '', 'pct' => '' ] );
		echo '</template>';

		echo '<p><button type="button" class="button button-secondary" id="ibb-los-add">+ ' . esc_html__( 'Add discount tier', 'ibb-rentals' ) . '</button></p>';
	}

	/** @param int|string $index  Numeric for real rows; "__INDEX__" for the JS template. */
	private function render_los_row( int|string $index, array $row ): void {
		$nights_name = '_ibb_los_discount_rows[' . $index . '][min_nights]';
		$pct_name    = '_ibb_los_discount_rows[' . $index . '][pct]';
		echo '<tr class="ibb-los__row">';
		printf(
			'<td class="ibb-los__col-nights"><input type="number" name="%s" value="%s" min="1" step="1" class="small-text" /></td>',
			esc_attr( $nights_name ),
			esc_attr( (string) $row['min_nights'] )
		);
		printf(
			'<td class="ibb-los__col-pct"><input type="number" name="%s" value="%s" min="0" max="100" step="0.1" class="small-text" /> %%</td>',
			esc_attr( $pct_name ),
			esc_attr( (string) $row['pct'] )
		);
		echo '<td class="ibb-los__col-actions"><button type="button" class="button-link ibb-los__remove" aria-label="' . esc_attr__( 'Remove tier', 'ibb-rentals' ) . '">×</button></td>';
		echo '</tr>';
	}

	private function number( string $name, int|float $value, int|float $min = 0, int|float $step = 1 ): string {
		return sprintf(
			'<input type="number" name="%s" value="%s" min="%s" step="%s" class="small-text" />',
			esc_attr( $name ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $step )
		);
	}

	private function text( string $name, string $value ): string {
		return sprintf( '<input type="text" name="%s" value="%s" class="regular-text" />', esc_attr( $name ), esc_attr( $value ) );
	}

	private function time( string $name, string $value ): string {
		return sprintf( '<input type="time" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
	}

	/**
	 * Build the canonical IBB tax-class option list:
	 *   ''         => Not taxed
	 *   'standard' => Standard rate
	 *   <slug>     => each user-defined class from WC → Settings → Tax
	 *
	 * @return array<string,string>
	 */
	private function tax_class_options(): array {
		$opts = [
			''         => __( 'Not taxed', 'ibb-rentals' ),
			'standard' => __( 'Standard rate', 'ibb-rentals' ),
		];
		if ( class_exists( '\\WC_Tax' ) ) {
			foreach ( \WC_Tax::get_tax_classes() as $class_label ) {
				$slug = sanitize_title( $class_label );
				if ( $slug !== '' && $slug !== 'standard' ) {
					$opts[ $slug ] = $class_label;
				}
			}
		}
		return $opts;
	}

	/** @param array<string,string> $options */
	private function render_tax_class_select( string $name, string $current, array $options ): void {
		printf( '<select name="%s">', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $value ),
				selected( $current, (string) $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	private function css(): string {
		return <<<CSS
.ibb-tabs__nav { display:flex; gap:4px; margin:0 0 12px; border-bottom:1px solid #ccd0d4; padding:0; }
.ibb-tabs__nav li { margin:0; }
.ibb-tabs__nav a { display:block; padding:8px 14px; text-decoration:none; color:#1d2327; border:1px solid transparent; border-bottom:0; border-radius:4px 4px 0 0; }
.ibb-tabs__nav a.is-active { background:#fff; border-color:#ccd0d4; margin-bottom:-1px; font-weight:600; }
.ibb-tab { display:none; padding:8px 0; }
.ibb-tab.is-active { display:block; }
.ibb-tab h4 { margin-top:18px; }

/* Currency-style inputs need room for IDR-scale values (e.g. 2,200,000 = 7 digits + commas).
   Bumps every <input type="number" class="small-text"> on the Rates and Booking-rules tabs
   from WP's default ~75px to ~140px. */
#ibb-tab-rates input.small-text[type="number"],
#ibb-tab-rules input.small-text[type="number"] { width:140px; }

.ibb-galleries__add { display:flex; gap:8px; margin:12px 0 18px; }
.ibb-galleries__add input { flex:1; max-width:340px; }
.ibb-gallery { background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:14px 16px; margin-bottom:14px; }
.ibb-gallery__header { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
.ibb-gallery__label { flex:1; min-width:180px; max-width:340px; font-size:1em; font-weight:600; padding:6px 8px; }
.ibb-gallery__slug { color:#646970; font-family:Consolas, Menlo, monospace; font-size:.85em; padding:2px 8px; background:#f0f0f1; border-radius:3px; }
.ibb-gallery__shortcode { color:#646970; font-family:Consolas, Menlo, monospace; font-size:.78em; user-select:all; cursor:text; }
.ibb-gallery__delete { color:#b32d2e; }
.ibb-gallery__images { display:grid; grid-template-columns:repeat(auto-fill, minmax(96px, 1fr)); gap:8px; margin-bottom:10px; min-height:8px; }
.ibb-gallery__images:empty::before { content:attr(data-empty); color:#8c8f94; font-size:.85em; padding:14px; grid-column:1/-1; text-align:center; border:1px dashed #c3c4c7; border-radius:4px; }
.ibb-image { position:relative; aspect-ratio:1; border-radius:4px; overflow:hidden; border:1px solid #dcdcde; background:#f0f0f1; }
.ibb-image img { width:100%; height:100%; object-fit:cover; display:block; }
.ibb-image__remove { position:absolute; top:4px; right:4px; width:22px; height:22px; padding:0; border:0; border-radius:50%; background:rgba(0,0,0,.65); color:#fff; font-size:14px; line-height:1; cursor:pointer; }
.ibb-image__remove:hover { background:#b32d2e; }

.ibb-los { border-collapse:collapse; margin:8px 0 4px; max-width:520px; width:100%; }
.ibb-los th { text-align:left; font-size:.8em; color:#646970; font-weight:600; padding:6px 8px 4px; border-bottom:1px solid #dcdcde; }
.ibb-los td { padding:6px 8px; vertical-align:middle; }
.ibb-los__col-nights { width:140px; }
.ibb-los__col-pct { width:160px; }
.ibb-los__col-actions { width:32px; text-align:right; }
.ibb-los__row + .ibb-los__row td { border-top:1px solid #f0f0f1; }
.ibb-los__remove { color:#b32d2e !important; font-size:18px; line-height:1; padding:2px 6px; text-decoration:none; }
.ibb-los__remove:hover { color:#fff !important; background:#b32d2e; border-radius:3px; }
.ibb-los__col-pct input { margin-right:4px; }

.ibb-blackout { border-collapse:collapse; margin:8px 0 4px; max-width:520px; width:100%; }
.ibb-blackout th { text-align:left; font-size:.8em; color:#646970; font-weight:600; padding:6px 8px 4px; border-bottom:1px solid #dcdcde; }
.ibb-blackout td { padding:6px 8px; vertical-align:middle; }
.ibb-blackout__col-start, .ibb-blackout__col-end { width:180px; }
.ibb-blackout__col-actions { width:32px; text-align:right; }
.ibb-blackout__row + .ibb-blackout__row td { border-top:1px solid #f0f0f1; }
.ibb-blackout__remove { color:#b32d2e !important; font-size:18px; line-height:1; padding:2px 6px; text-decoration:none; }
.ibb-blackout__remove:hover { color:#fff !important; background:#b32d2e; border-radius:3px; }
.ibb-srates-wrap { overflow-x:auto; margin:8px 0 4px; }
.ibb-srates { border-collapse:collapse; width:100%; min-width:640px; }
.ibb-srates th { text-align:left; font-size:.8em; color:#646970; font-weight:600; padding:6px 8px 4px; border-bottom:1px solid #dcdcde; white-space:nowrap; }
.ibb-srates td { padding:5px 6px; vertical-align:middle; }
.ibb-srates__row + .ibb-srates__row td { border-top:1px solid #f0f0f1; }
.ibb-srates__remove { color:#b32d2e !important; font-size:18px; line-height:1; padding:2px 6px; text-decoration:none; }
.ibb-srates__remove:hover { color:#fff !important; background:#b32d2e; border-radius:3px; }
CSS;
	}

	private function galleries_js(): string {
		return <<<'JS'
(function(){
  function init(retries) {
    var root = document.getElementById('ibb-galleries');
    if (!root) {
      if (retries > 0) { setTimeout(function(){ init(retries - 1); }, 200); }
      return;
    }
    if (root.dataset.ibbInit === '1') return; // idempotent
    root.dataset.ibbInit = '1';
    if (!window.wp || !window.wp.media) {
      console.warn('[ibb-rentals] wp.media not available; gallery picker disabled.');
      return;
    }
    boot(root);
  }

  function boot(root) {
  var hidden = document.getElementById('ibb-galleries-data');
  var listEl = document.getElementById('ibb-galleries-list');
  var addBtn = document.getElementById('ibb-add-gallery');
  var addInput = document.getElementById('ibb-new-gallery-label');
  var initialEl = document.getElementById('ibb-galleries-initial');

  var state = [];
  try { state = JSON.parse((initialEl && initialEl.textContent) || '[]'); } catch (e) { state = []; }

  function slugify(label) {
    return String(label).toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .substr(0, 60) || 'gallery';
  }

  function uniqueSlug(base) {
    var taken = state.map(function(g){ return g.slug; });
    var slug = base, n = 2;
    while (taken.indexOf(slug) !== -1) { slug = base + '-' + n++; }
    return slug;
  }

  function persist() {
    // Strip thumbnails before serializing — we only store IDs server-side.
    var stripped = state.map(function(g){
      return {
        slug: g.slug,
        label: g.label,
        attachments: (g.attachments || []).map(function(a){ return a.id; })
      };
    });
    hidden.value = JSON.stringify(stripped);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];
    });
  }

  function render() {
    listEl.innerHTML = '';
    if (state.length === 0) {
      listEl.innerHTML = '<p class="description"><em>No galleries yet — add one above.</em></p>';
      persist();
      return;
    }
    state.forEach(function(g, gi){
      var card = document.createElement('div');
      card.className = 'ibb-gallery';
      card.dataset.idx = gi;

      var imagesHtml = (g.attachments || []).map(function(a, ai){
        var thumb = a.thumb || '';
        return '<div class="ibb-image" data-idx="' + ai + '">' +
          (thumb ? '<img src="' + escapeHtml(thumb) + '" alt="' + escapeHtml(a.alt || '') + '">' : '') +
          '<button type="button" class="ibb-image__remove" aria-label="Remove">×</button>' +
          '</div>';
      }).join('');

      var shortcode = '[ibb_gallery gallery="' + escapeHtml(g.slug) + '"]';

      card.innerHTML =
        '<div class="ibb-gallery__header">' +
          '<input type="text" class="ibb-gallery__label" value="' + escapeHtml(g.label) + '">' +
          '<code class="ibb-gallery__slug">' + escapeHtml(g.slug) + '</code>' +
          '<code class="ibb-gallery__shortcode" title="Click to copy">' + escapeHtml(shortcode) + '</code>' +
          '<button type="button" class="button button-link-delete ibb-gallery__delete">Remove gallery</button>' +
        '</div>' +
        '<div class="ibb-gallery__images" data-empty="No images yet — click ‘Add images’ to choose from the media library.">' + imagesHtml + '</div>' +
        '<button type="button" class="button button-secondary ibb-gallery__add-images">+ Add images</button>';

      listEl.appendChild(card);
    });
    persist();
  }

  // Add new gallery
  addBtn.addEventListener('click', function(){
    var label = (addInput.value || '').trim();
    if (label === '') { addInput.focus(); return; }
    var slug = uniqueSlug(slugify(label));
    state.push({ slug: slug, label: label, attachments: [] });
    addInput.value = '';
    render();
  });
  addInput.addEventListener('keydown', function(e){
    if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); }
  });

  // Per-gallery interactions
  listEl.addEventListener('click', function(e){
    var card = e.target.closest('.ibb-gallery');
    if (!card) return;
    var gi = parseInt(card.dataset.idx, 10);
    var gallery = state[gi];
    if (!gallery) return;

    if (e.target.classList.contains('ibb-gallery__delete')) {
      if (!confirm('Remove gallery "' + gallery.label + '"? Images stay in your media library.')) return;
      state.splice(gi, 1);
      render();
      return;
    }

    if (e.target.classList.contains('ibb-image__remove')) {
      var imgWrap = e.target.closest('.ibb-image');
      var ai = parseInt(imgWrap.dataset.idx, 10);
      gallery.attachments.splice(ai, 1);
      render();
      return;
    }

    if (e.target.classList.contains('ibb-gallery__add-images')) {
      var frame = wp.media({
        title: 'Add images to "' + gallery.label + '"',
        multiple: 'add',
        library: { type: 'image' },
        button: { text: 'Add to gallery' }
      });
      // Force the modal into Bulk-Select mode on open so each thumbnail click
      // toggles selection without needing Ctrl/Cmd. Default WP behaviour requires
      // Ctrl-click for multi-select, which is an accessibility blocker for users
      // with limited modifier-key access. The "Bulk select" toggle button on the
      // toolbar normally engages this; we click it programmatically.
      frame.on('open', function () {
        // Wait a tick for the toolbar to render, then click the bulk-select button
        // if it isn't already engaged. Re-runs are no-op idempotent (we check the
        // pressed state before clicking).
        setTimeout(function () {
          var btn = document.querySelector('.media-modal .select-mode-toggle-button');
          if (btn && btn.getAttribute('aria-pressed') !== 'true') {
            btn.click();
          }
        }, 50);
      });
      frame.on('select', function(){
        var sel = frame.state().get('selection').toJSON();
        sel.forEach(function(item){
          if (gallery.attachments.some(function(a){ return a.id === item.id; })) return;
          var thumb = (item.sizes && item.sizes.thumbnail && item.sizes.thumbnail.url) ||
                      (item.sizes && item.sizes.medium && item.sizes.medium.url) ||
                      item.url || '';
          gallery.attachments.push({ id: item.id, thumb: thumb, alt: item.alt || '' });
        });
        render();
      });
      frame.open();
      return;
    }
  });

  // Live label edits → re-derive slug if not already manually unique-collided
  listEl.addEventListener('input', function(e){
    if (!e.target.classList.contains('ibb-gallery__label')) return;
    var card = e.target.closest('.ibb-gallery');
    var gi = parseInt(card.dataset.idx, 10);
    var gallery = state[gi];
    if (!gallery) return;
    gallery.label = e.target.value;
    persist();
  });

  render();
  } // end boot()

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ init(60); });
  } else {
    init(60);
  }
})();
JS;
	}

	/**
	 * Tiny enhancer for the LOS table:
	 *   - "Add discount tier" clones the <template> row and appends it.
	 *   - Clicking the × on a row removes it (or empties the last row,
	 *     since the save handler already drops invalid rows).
	 *
	 * Indices in the cloned name attributes are rewritten with a
	 * monotonic counter so duplicate `[N]` keys don't collide. The PHP
	 * save handler doesn't actually care about the index — it iterates
	 * the array values — but unique indices keep the DOM tidy.
	 */
	private function los_js(): string {
		return <<<'JS'
(function(){
  function init() {
    var root = document.getElementById('ibb-los');
    if (!root) return;
    if (root.dataset.ibbInit === '1') return;
    root.dataset.ibbInit = '1';

    var rowsEl = document.getElementById('ibb-los-rows');
    var addBtn = document.getElementById('ibb-los-add');
    var template = document.getElementById('ibb-los-row-template');
    if (!rowsEl || !addBtn || !template) return;

    var counter = rowsEl.children.length;

    addBtn.addEventListener('click', function(){
      var html = template.innerHTML.replace(/__INDEX__/g, String(counter++));
      var holder = document.createElement('tbody');
      holder.innerHTML = html;
      var row = holder.querySelector('tr');
      if (row) {
        rowsEl.appendChild(row);
        var firstInput = row.querySelector('input');
        if (firstInput) firstInput.focus();
      }
    });

    rowsEl.addEventListener('click', function(e){
      if (!e.target.classList.contains('ibb-los__remove')) return;
      var row = e.target.closest('tr');
      if (!row) return;
      // If this is the last row, just clear it instead of removing —
      // keeps the table from collapsing to a confusing empty state.
      if (rowsEl.children.length <= 1) {
        row.querySelectorAll('input').forEach(function(input){ input.value = ''; });
        return;
      }
      row.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JS;
	}

	private function seasonal_rates_js(): string {
		return <<<'JS'
(function(){
  function init() {
    var root = document.getElementById('ibb-srates');
    if (!root || root.dataset.ibbInit === '1') return;
    root.dataset.ibbInit = '1';

    var rowsEl   = document.getElementById('ibb-srates-rows');
    var addBtn   = document.getElementById('ibb-srates-add');
    var template = document.getElementById('ibb-srates-row-template');
    if (!rowsEl || !addBtn || !template) return;

    var counter = rowsEl.children.length;

    addBtn.addEventListener('click', function(){
      var html   = template.innerHTML.replace(/__INDEX__/g, String(counter++));
      var holder = document.createElement('tbody');
      holder.innerHTML = html;
      var row = holder.querySelector('tr');
      if (row) {
        rowsEl.appendChild(row);
        var firstInput = row.querySelector('input');
        if (firstInput) firstInput.focus();
      }
    });

    rowsEl.addEventListener('click', function(e){
      if (!e.target.classList.contains('ibb-srates__remove')) return;
      var row = e.target.closest('tr');
      if (!row) return;
      if (rowsEl.children.length <= 1) {
        row.querySelectorAll('input').forEach(function(i){ i.value = ''; });
        return;
      }
      row.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JS;
	}

	private function blackout_js(): string {
		return <<<'JS'
(function(){
  function init() {
    var root = document.getElementById('ibb-blackout');
    if (!root) return;
    if (root.dataset.ibbInit === '1') return;
    root.dataset.ibbInit = '1';

    var rowsEl = document.getElementById('ibb-blackout-rows');
    var addBtn = document.getElementById('ibb-blackout-add');
    var template = document.getElementById('ibb-blackout-row-template');
    if (!rowsEl || !addBtn || !template) return;

    var counter = rowsEl.children.length;

    addBtn.addEventListener('click', function(){
      var html = template.innerHTML.replace(/__INDEX__/g, String(counter++));
      var holder = document.createElement('tbody');
      holder.innerHTML = html;
      var row = holder.querySelector('tr');
      if (row) {
        rowsEl.appendChild(row);
        var firstInput = row.querySelector('input');
        if (firstInput) firstInput.focus();
      }
    });

    rowsEl.addEventListener('click', function(e){
      if (!e.target.classList.contains('ibb-blackout__remove')) return;
      var row = e.target.closest('tr');
      if (!row) return;
      if (rowsEl.children.length <= 1) {
        row.querySelectorAll('input').forEach(function(input){ input.value = ''; });
        return;
      }
      row.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JS;
	}
}
