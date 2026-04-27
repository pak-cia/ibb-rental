<?php
/**
 * Admin availability calendar page.
 *
 * Renders a FullCalendar 6 month-view showing all blocks/bookings across every
 * property. Supports creating manual blocks by selecting a date range and
 * deleting any block by clicking the event. Assets (FullCalendar CDN) are
 * enqueued only on this page.
 *
 * Data flow:
 *   - Page load   → inline JSON of properties (id→title) for the selector.
 *   - FC events   → wp_ajax_ibb_rentals_calendar_events (GET, nonce-protected).
 *   - Create block → wp_ajax_ibb_rentals_create_block (POST, nonce-protected).
 *   - Delete block → wp_ajax_ibb_rentals_delete_block (POST, nonce-protected).
 */

declare( strict_types=1 );

namespace IBB\Rentals\Admin;

use IBB\Rentals\Domain\Block;
use IBB\Rentals\Domain\DateRange;
use IBB\Rentals\Plugin;
use IBB\Rentals\PostTypes\PropertyPostType;

defined( 'ABSPATH' ) || exit;

final class AdminCalendar {

	public const PAGE_SLUG = 'ibb-rentals-calendar';

	private const NONCE_ACTION = 'ibb_admin_calendar';

	/** Colour palette cycled by property index. */
	private const COLOURS = [
		'#3b82f6', '#10b981', '#f59e0b', '#ef4444',
		'#8b5cf6', '#ec4899', '#06b6d4', '#84cc16',
		'#f97316', '#6366f1',
	];

	public function register(): void {
		add_action( 'admin_enqueue_scripts',                     [ $this, 'enqueue' ] );
		add_action( 'wp_ajax_ibb_rentals_calendar_events',       [ $this, 'ajax_events' ] );
		add_action( 'wp_ajax_ibb_rentals_create_block',          [ $this, 'ajax_create_block' ] );
		add_action( 'wp_ajax_ibb_rentals_delete_block',          [ $this, 'ajax_delete_block' ] );
	}

	public function enqueue( string $hook ): void {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		// FullCalendar 6 — global build bundles all standard plugins.
		// Load in <head> so FullCalendar is defined before the inline init
		// script that runs inside the page-body render() output.
		wp_enqueue_script(
			'fullcalendar',
			'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
			[],
			'6.1.15',
			false
		);
	}

	public function render(): void {
		$properties = $this->get_properties_map();
		$nonce      = wp_create_nonce( self::NONCE_ACTION );
		$ajax_url   = admin_url( 'admin-ajax.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Availability Calendar', 'ibb-rentals' ); ?></h1>

			<div id="ibb-cal-toolbar" style="margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
				<label for="ibb-cal-property-filter" style="font-weight:600;">
					<?php esc_html_e( 'Property:', 'ibb-rentals' ); ?>
				</label>
				<select id="ibb-cal-property-filter">
					<option value=""><?php esc_html_e( '— All properties —', 'ibb-rentals' ); ?></option>
					<?php foreach ( $properties as $id => $title ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>">
							<?php echo esc_html( $title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="ibb-cal-source-filter" style="font-weight:600;margin-left:8px;">
					<?php esc_html_e( 'Source:', 'ibb-rentals' ); ?>
				</label>
				<select id="ibb-cal-source-filter">
					<option value=""><?php esc_html_e( '— All sources —', 'ibb-rentals' ); ?></option>
					<option value="direct"><?php esc_html_e( 'Direct bookings', 'ibb-rentals' ); ?></option>
					<option value="manual"><?php esc_html_e( 'Manual blocks', 'ibb-rentals' ); ?></option>
					<option value="airbnb">Airbnb</option>
					<option value="booking">Booking.com</option>
					<option value="agoda">Agoda</option>
					<option value="vrbo">VRBO</option>
				</select>
			</div>

			<div id="ibb-admin-calendar" style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:4px;"></div>

			<?php /* Create-block modal */ ?>
			<div id="ibb-cal-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
				<div style="background:#fff;border-radius:6px;padding:24px;min-width:340px;box-shadow:0 4px 24px rgba(0,0,0,.2);">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Create manual block', 'ibb-rentals' ); ?></h2>
					<table class="form-table" style="margin:0;">
						<tr>
							<th><?php esc_html_e( 'Property', 'ibb-rentals' ); ?></th>
							<td>
								<select id="ibb-modal-property" style="min-width:220px;">
									<?php foreach ( $properties as $id => $title ) : ?>
										<option value="<?php echo esc_attr( (string) $id ); ?>">
											<?php echo esc_html( $title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Check-in', 'ibb-rentals' ); ?></th>
							<td><input type="date" id="ibb-modal-start" style="width:180px;" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Check-out', 'ibb-rentals' ); ?></th>
							<td><input type="date" id="ibb-modal-end" style="width:180px;" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Note', 'ibb-rentals' ); ?></th>
							<td><input type="text" id="ibb-modal-note" placeholder="<?php esc_attr_e( 'Optional label…', 'ibb-rentals' ); ?>" style="width:220px;" /></td>
						</tr>
					</table>
					<p id="ibb-modal-error" style="color:#c00;display:none;margin-bottom:0;"></p>
					<p style="margin-bottom:0;margin-top:16px;display:flex;gap:8px;">
						<button id="ibb-modal-save" class="button button-primary">
							<?php esc_html_e( 'Block dates', 'ibb-rentals' ); ?>
						</button>
						<button id="ibb-modal-cancel" class="button">
							<?php esc_html_e( 'Cancel', 'ibb-rentals' ); ?>
						</button>
					</p>
				</div>
			</div>

			<?php /* Event-detail popover (reuse same modal) */ ?>
			<div id="ibb-cal-detail" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
				<div style="background:#fff;border-radius:6px;padding:24px;min-width:300px;box-shadow:0 4px 24px rgba(0,0,0,.2);">
					<h2 style="margin-top:0;" id="ibb-detail-title"></h2>
					<p id="ibb-detail-body" style="white-space:pre-line;color:#333;"></p>
					<p style="margin-bottom:0;display:flex;gap:8px;">
						<button id="ibb-detail-delete" class="button button-link-delete">
							<?php esc_html_e( 'Delete block', 'ibb-rentals' ); ?>
						</button>
						<button id="ibb-detail-close" class="button">
							<?php esc_html_e( 'Close', 'ibb-rentals' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>

		<script>
		(function(){
			// Use WP's pre-defined ajaxurl (includes port) rather than admin_url() output.
		var ajaxUrl    = ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
			var properties = <?php echo wp_json_encode( $properties ); ?>;
			var colours    = <?php echo wp_json_encode( array_values( self::COLOURS ) ); ?>;
			var propIds    = Object.keys( properties );
			var colourMap  = {};
			propIds.forEach(function(id, i){ colourMap[id] = colours[ i % colours.length ]; });

			var calEl        = document.getElementById('ibb-admin-calendar');
			var filterProp   = document.getElementById('ibb-cal-property-filter');
			var filterSource = document.getElementById('ibb-cal-source-filter');

			// ── FullCalendar init ─────────────────────────────────────────
			var calendar = new FullCalendar.Calendar( calEl, {
				headerToolbar: {
					left:   'prev,next today',
					center: 'title',
					right:  'dayGridMonth,dayGridWeek'
				},
				initialView: 'dayGridMonth',
				height: 'auto',
				selectable: true,
				selectMirror: true,
				events: function( info, successCb, failureCb ) {
					var params = new URLSearchParams({
						action: 'ibb_rentals_calendar_events',
						nonce:  nonce,
						start:  info.startStr,
						end:    info.endStr,
					});
					if ( filterProp.value )   params.set( 'property_id', filterProp.value );
					if ( filterSource.value ) params.set( 'source', filterSource.value );
					fetch( ajaxUrl + '?' + params.toString() )
						.then(function(r){ return r.json(); })
						.then(successCb)
						.catch(failureCb);
				},
				eventContent: function( arg ) {
					return { html: '<div class="fc-event-main-wrap" style="padding:2px 4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + arg.event.title.replace(/"/g,'&quot;') + '">' + arg.event.title + '</div>' };
				},
				select: function( info ) {
					openCreateModal( info.startStr, info.endStr );
				},
				eventClick: function( info ) {
					openDetailModal( info.event );
				},
			} );
			calendar.render();

			// Re-fetch when filters change.
			filterProp.addEventListener( 'change', function(){ calendar.refetchEvents(); } );
			filterSource.addEventListener( 'change', function(){ calendar.refetchEvents(); } );

			// ── Create block modal ────────────────────────────────────────
			var modal        = document.getElementById('ibb-cal-modal');
			var modalProp    = document.getElementById('ibb-modal-property');
			var modalStart   = document.getElementById('ibb-modal-start');
			var modalEnd     = document.getElementById('ibb-modal-end');
			var modalNote    = document.getElementById('ibb-modal-note');
			var modalError   = document.getElementById('ibb-modal-error');
			var modalSave    = document.getElementById('ibb-modal-save');
			var modalCancel  = document.getElementById('ibb-modal-cancel');

			function openCreateModal( start, end ) {
				modalStart.value = start;
				modalEnd.value   = end;
				modalNote.value  = '';
				modalError.style.display = 'none';
				// Pre-select active filter property if one is set.
				if ( filterProp.value ) modalProp.value = filterProp.value;
				modal.style.display = 'flex';
				modalProp.focus();
			}

			modalCancel.addEventListener( 'click', function(){ modal.style.display = 'none'; calendar.unselect(); } );
			modal.addEventListener( 'click', function(e){ if ( e.target === modal ) { modal.style.display = 'none'; calendar.unselect(); } } );

			modalSave.addEventListener( 'click', function() {
				modalError.style.display = 'none';
				if ( ! modalProp.value || ! modalStart.value || ! modalEnd.value ) {
					modalError.textContent = <?php echo wp_json_encode( __( 'Please fill in all required fields.', 'ibb-rentals' ) ); ?>;
					modalError.style.display = 'block';
					return;
				}
				if ( modalEnd.value <= modalStart.value ) {
					modalError.textContent = <?php echo wp_json_encode( __( 'Check-out must be after check-in.', 'ibb-rentals' ) ); ?>;
					modalError.style.display = 'block';
					return;
				}
				modalSave.disabled = true;
				var body = new URLSearchParams({
					action:      'ibb_rentals_create_block',
					nonce:       nonce,
					property_id: modalProp.value,
					start:       modalStart.value,
					end:         modalEnd.value,
					note:        modalNote.value,
				});
				fetch( ajaxUrl, { method: 'POST', body: body } )
					.then(function(r){ return r.json(); })
					.then(function(res){
						modalSave.disabled = false;
						if ( res.success ) {
							modal.style.display = 'none';
							calendar.refetchEvents();
						} else {
							modalError.textContent = res.data || <?php echo wp_json_encode( __( 'Error creating block.', 'ibb-rentals' ) ); ?>;
							modalError.style.display = 'block';
						}
					} )
					.catch(function(){
						modalSave.disabled = false;
						modalError.textContent = <?php echo wp_json_encode( __( 'Server error.', 'ibb-rentals' ) ); ?>;
						modalError.style.display = 'block';
					} );
			} );

			// ── Event detail modal ────────────────────────────────────────
			var detail       = document.getElementById('ibb-cal-detail');
			var detailTitle  = document.getElementById('ibb-detail-title');
			var detailBody   = document.getElementById('ibb-detail-body');
			var detailDelete = document.getElementById('ibb-detail-delete');
			var detailClose  = document.getElementById('ibb-detail-close');
			var activeEvent  = null;

			function openDetailModal( event ) {
				activeEvent = event;
				var p    = event.extendedProps;
				var src  = p.source || '';
				var isDeletable = ( src === 'manual' || src === 'hold' );
				detailTitle.textContent  = event.title;
				var lines = [
					<?php echo wp_json_encode( __( 'Check-in:', 'ibb-rentals' ) ); ?> + ' ' + ( event.startStr || '' ),
					<?php echo wp_json_encode( __( 'Check-out:', 'ibb-rentals' ) ); ?> + ' ' + ( event.endStr   || '' ),
					<?php echo wp_json_encode( __( 'Source:', 'ibb-rentals' ) ); ?>   + ' ' + src,
				];
				if ( p.order_id ) lines.push( <?php echo wp_json_encode( __( 'Order:', 'ibb-rentals' ) ); ?> + ' #' + p.order_id );
				if ( p.summary  ) lines.push( p.summary );
				detailBody.textContent   = lines.join('\n');
				detailDelete.style.display = isDeletable ? '' : 'none';
				detail.style.display = 'flex';
			}

			detailClose.addEventListener(  'click', function(){ detail.style.display = 'none'; } );
			detail.addEventListener( 'click', function(e){ if ( e.target === detail ) detail.style.display = 'none'; } );

			detailDelete.addEventListener( 'click', function() {
				if ( ! activeEvent ) return;
				if ( ! confirm( <?php echo wp_json_encode( __( 'Delete this block? This cannot be undone.', 'ibb-rentals' ) ); ?> ) ) return;
				var body = new URLSearchParams({
					action:   'ibb_rentals_delete_block',
					nonce:    nonce,
					block_id: activeEvent.extendedProps.block_id,
				});
				fetch( ajaxUrl, { method: 'POST', body: body } )
					.then(function(r){ return r.json(); })
					.then(function(res){
						if ( res.success ) {
							detail.style.display = 'none';
							calendar.refetchEvents();
						} else {
							alert( res.data || <?php echo wp_json_encode( __( 'Error deleting block.', 'ibb-rentals' ) ); ?> );
						}
					} );
			} );

		})();
		</script>
		<?php
	}

	// ── AJAX: return FullCalendar-compatible events ───────────────────────────

	public function ajax_events(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		// FullCalendar sends ISO 8601 strings like '2026-03-29T00:00:00+08:00'.
		// DateRange::from_strings() expects 'Y-m-d' only — strip to first 10 chars.
		$start_str = substr( sanitize_text_field( (string) wp_unslash( $_GET['start'] ?? '' ) ), 0, 10 );
		$end_str   = substr( sanitize_text_field( (string) wp_unslash( $_GET['end']   ?? '' ) ), 0, 10 );
		$prop_id   = isset( $_GET['property_id'] ) ? (int) $_GET['property_id'] : null;
		$source    = sanitize_text_field( (string) wp_unslash( $_GET['source'] ?? '' ) );

		try {
			$range  = DateRange::from_strings( $start_str, $end_str );
		} catch ( \Throwable ) {
			wp_send_json_error( 'Invalid date range', 400 );
		}

		$prop_ids = $prop_id ? [ $prop_id ] : null;
		$blocks   = Plugin::instance()->availability_repo()->find_all_in_window( $range, $prop_ids );

		if ( $source !== '' ) {
			$blocks = array_values( array_filter( $blocks, fn( Block $b ) => $b->source === $source ) );
		}

		$properties = $this->get_properties_map();
		$colours    = self::COLOURS;
		$prop_keys  = array_keys( $properties );

		$events = [];
		foreach ( $blocks as $block ) {
			$prop_index = (int) array_search( $block->property_id, $prop_keys, true );
			$colour     = $colours[ $prop_index % count( $colours ) ];
			$prop_name  = $properties[ $block->property_id ] ?? 'Property #' . $block->property_id;
			$source_label = ucfirst( $block->source );

			$events[] = [
				'id'    => 'block_' . $block->id,
				'title' => $prop_name . ' — ' . $source_label,
				'start' => $block->range->checkin_string(),
				'end'   => $block->range->checkout_string(),
				'color' => $colour,
				'extendedProps' => [
					'block_id'    => $block->id,
					'property_id' => $block->property_id,
					'source'      => $block->source,
					'order_id'    => $block->order_id,
					'summary'     => $block->summary,
				],
			];
		}

		wp_send_json( $events );
	}

	// ── AJAX: create manual block ─────────────────────────────────────────────

	public function ajax_create_block(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$property_id = (int) ( $_POST['property_id'] ?? 0 );
		$start_str   = sanitize_text_field( (string) wp_unslash( $_POST['start'] ?? '' ) );
		$end_str     = sanitize_text_field( (string) wp_unslash( $_POST['end']   ?? '' ) );
		$note        = sanitize_text_field( (string) wp_unslash( $_POST['note']  ?? '' ) );

		if ( $property_id <= 0 || $start_str === '' || $end_str === '' ) {
			wp_send_json_error( __( 'Missing required fields.', 'ibb-rentals' ) );
		}

		try {
			$range = DateRange::from_strings( $start_str, $end_str );
		} catch ( \Throwable ) {
			wp_send_json_error( __( 'Invalid dates.', 'ibb-rentals' ) );
		}

		if ( ! get_post( $property_id ) ) {
			wp_send_json_error( __( 'Property not found.', 'ibb-rentals' ) );
		}

		$repo  = Plugin::instance()->availability_repo();
		$block = new Block(
			id:           null,
			property_id:  $property_id,
			range:        $range,
			source:       Block::SOURCE_MANUAL,
			external_uid: 'manual-' . wp_generate_uuid4(),
			status:       Block::STATUS_CONFIRMED,
			order_id:     null,
			summary:      $note !== '' ? $note : 'Blocked',
		);

		$id = $repo->insert( $block );
		wp_send_json_success( [ 'block_id' => $id ] );
	}

	// ── AJAX: delete block ────────────────────────────────────────────────────

	public function ajax_delete_block(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$block_id = (int) ( $_POST['block_id'] ?? 0 );
		if ( $block_id <= 0 ) {
			wp_send_json_error( __( 'Invalid block ID.', 'ibb-rentals' ) );
		}

		$repo  = Plugin::instance()->availability_repo();
		$block = $repo->find_by_id( $block_id );

		if ( ! $block ) {
			wp_send_json_error( __( 'Block not found.', 'ibb-rentals' ) );
		}

		// Only manual blocks and holds can be deleted from the calendar UI.
		if ( ! in_array( $block->source, [ Block::SOURCE_MANUAL, 'hold' ], true ) ) {
			wp_send_json_error( __( 'Only manual blocks can be deleted here. Cancel the WooCommerce order to remove a direct booking.', 'ibb-rentals' ) );
		}

		$repo->delete_by_id( $block_id );
		wp_send_json_success();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/** @return array<int, string> property_id → title */
	private function get_properties_map(): array {
		$posts = get_posts( [
			'post_type'      => PropertyPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		] );
		$map = [];
		foreach ( (array) $posts as $id ) {
			$map[ (int) $id ] = (string) get_the_title( (int) $id );
		}
		return $map;
	}
}
