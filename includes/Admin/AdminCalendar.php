<?php
/**
 * Admin availability calendar page.
 *
 * Renders a FullCalendar 6 month/week view PLUS a custom multi-property
 * timeline view (one row per property, one column per day) showing all
 * blocks/bookings. View is switched via toolbar buttons (Month / Week /
 * Timeline). The timeline uses the same wp_ajax_ibb_rentals_calendar_events
 * endpoint as FullCalendar — no additional AJAX handlers needed.
 *
 * Data flow:
 *   - Page load    → inline JSON of properties (id→title) for the selector.
 *   - FC events    → wp_ajax_ibb_rentals_calendar_events (GET, nonce-protected).
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

	/** Colours keyed by block source — matched to the user's OTA sales spreadsheet. */
	private const SOURCE_COLOURS = [
		'web'     => '#7c3aed', // purple       — Website / plugin checkout
		'direct'  => '#0d9488', // teal         — Walk-in / phone
		'airbnb'  => '#dc2626', // red          — Airbnb
		'booking' => '#003580', // blue         — Booking.com
		'agoda'   => '#ea580c', // orange       — Agoda
		'vrbo'    => '#0066cc', // VRBO brand blue
		'expedia' => '#d4a81a', // golden cream — Expedia
		'manual'  => '#6b7280', // grey         — Manual block
		'hold'    => '#9ca3af', // light grey   — Cart hold
	];

	private const SOURCE_COLOUR_DEFAULT = '#475569'; // slate — unknown source

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
					<option value="web"><?php esc_html_e( 'Website bookings', 'ibb-rentals' ); ?></option>
					<option value="direct"><?php esc_html_e( 'Walk-in / phone', 'ibb-rentals' ); ?></option>
					<option value="manual"><?php esc_html_e( 'Manual blocks', 'ibb-rentals' ); ?></option>
					<option value="airbnb">Airbnb</option>
					<option value="booking">Booking.com</option>
					<option value="agoda">Agoda</option>
					<option value="vrbo">VRBO</option>
					<option value="expedia">Expedia</option>
				</select>

				<div style="margin-left:auto;display:flex;gap:4px;">
					<button id="ibb-view-month" class="button button-primary"><?php esc_html_e( 'Month', 'ibb-rentals' ); ?></button>
					<button id="ibb-view-week" class="button"><?php esc_html_e( 'Week', 'ibb-rentals' ); ?></button>
					<button id="ibb-view-timeline" class="button"><?php esc_html_e( 'Timeline', 'ibb-rentals' ); ?></button>
				</div>
			</div>

			<?php
			/* FullCalendar month / week event spacing.
			 * Default FC dayGrid stacks events with no vertical gap, so adjacent
			 * same-color bars (e.g. two airbnb bookings on consecutive rows) blend
			 * together visually. A 2px bottom margin + a faint outline makes each
			 * event clearly distinct without touching FC's layout math.
			 */
			?>
			<style>
				#ibb-admin-calendar .fc-daygrid-event,
				#ibb-admin-calendar .fc-timegrid-event { margin: 1px 1px 2px !important; }
				#ibb-admin-calendar .fc-event { box-shadow: 0 0 0 1px rgba(255,255,255,0.35) inset; }
			</style>
			<?php /* FullCalendar month / week views */ ?>
			<div id="ibb-admin-calendar" style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:4px;"></div>

			<?php /* Multi-property timeline view */ ?>
			<div id="ibb-timeline" style="display:none;background:#fff;padding:16px;border:1px solid #ddd;border-radius:4px;">
				<div style="display:flex;align-items:center;gap:6px;margin-bottom:12px;flex-wrap:wrap;">
					<button id="ibb-tl-prev" class="button">&#8249; <?php esc_html_e( 'Prev', 'ibb-rentals' ); ?></button>
					<button id="ibb-tl-today" class="button"><?php esc_html_e( 'Today', 'ibb-rentals' ); ?></button>
					<button id="ibb-tl-next" class="button"><?php esc_html_e( 'Next', 'ibb-rentals' ); ?> &#8250;</button>
					<strong id="ibb-tl-title" style="font-size:1.1em;margin-left:4px;min-width:140px;display:inline-block;"></strong>
					<button id="ibb-tl-add-block" class="button" style="margin-left:auto;">+ <?php esc_html_e( 'Block dates', 'ibb-rentals' ); ?></button>
				</div>
				<div id="ibb-tl-wrap" style="overflow-x:auto;">
					<div id="ibb-tl-inner"></div>
				</div>
			</div>

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

			<?php /* Event-detail modal */ ?>
			<div id="ibb-cal-detail" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center;">
				<div style="background:#fff;border-radius:6px;padding:24px;min-width:300px;box-shadow:0 4px 24px rgba(0,0,0,.2);">
					<h2 style="margin-top:0;" id="ibb-detail-title"></h2>
					<p id="ibb-detail-body" style="white-space:pre-line;color:#333;"></p>
					<p style="margin-bottom:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
						<a id="ibb-detail-booking-link" href="#" target="_blank" class="button" style="display:none;">
							<?php esc_html_e( 'View booking →', 'ibb-rentals' ); ?>
						</a>
						<a id="ibb-detail-clickup-link" href="#" target="_blank" class="button" style="display:none;">
							<?php esc_html_e( 'View ClickUp task →', 'ibb-rentals' ); ?>
						</a>
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
			var ajaxUrl    = ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
			var properties   = <?php echo wp_json_encode( $properties ); ?>;
			var sourceColours = <?php echo wp_json_encode( self::SOURCE_COLOURS ); ?>;
			var sourceColourDefault = <?php echo wp_json_encode( self::SOURCE_COLOUR_DEFAULT ); ?>;

			var currentView  = 'month'; // 'month' | 'week' | 'timeline'

			var calEl        = document.getElementById('ibb-admin-calendar');
			var filterProp   = document.getElementById('ibb-cal-property-filter');
			var filterSource = document.getElementById('ibb-cal-source-filter');

			// ── FullCalendar init ─────────────────────────────────────────
			var calendar = new FullCalendar.Calendar( calEl, {
				headerToolbar: {
					left:   'prev,next today',
					center: 'title',
					right:  '', // view switching handled by our custom toolbar buttons
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

			// ── Filter listeners (shared between FC and timeline) ─────────
			filterProp.addEventListener( 'change', function(){
				if ( currentView === 'timeline' ) fetchAndRenderTimeline();
				else calendar.refetchEvents();
			} );
			filterSource.addEventListener( 'change', function(){
				if ( currentView === 'timeline' ) fetchAndRenderTimeline();
				else calendar.refetchEvents();
			} );

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
							if ( currentView === 'timeline' ) fetchAndRenderTimeline();
							else calendar.refetchEvents();
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
			var detail            = document.getElementById('ibb-cal-detail');
			var detailTitle       = document.getElementById('ibb-detail-title');
			var detailBody        = document.getElementById('ibb-detail-body');
			var detailDelete      = document.getElementById('ibb-detail-delete');
			var detailClose       = document.getElementById('ibb-detail-close');
			var detailBookingLink = document.getElementById('ibb-detail-booking-link');
			var detailClickupLink = document.getElementById('ibb-detail-clickup-link');
			var activeEvent       = null;

			// Accepts either a real FullCalendar event or a plain object
			// {title, startStr, endStr, extendedProps} from the timeline click handler.
			function openDetailModal( event ) {
				activeEvent = event;
				var p    = event.extendedProps;
				var src  = p.source || '';
				var isDeletable = ( src === 'manual' || src === 'hold' );
				detailTitle.textContent = event.title;

				var lines = [
					<?php echo wp_json_encode( __( 'Check-in:', 'ibb-rentals' ) ); ?>  + ' ' + ( event.startStr || '' ),
					<?php echo wp_json_encode( __( 'Check-out:', 'ibb-rentals' ) ); ?> + ' ' + ( event.endStr   || '' ),
					<?php echo wp_json_encode( __( 'Source:', 'ibb-rentals' ) ); ?>    + ' ' + src,
				];
				if ( p.guest_name  ) lines.push( <?php echo wp_json_encode( __( 'Guest:', 'ibb-rentals' ) ); ?>  + ' ' + p.guest_name );
				if ( p.guest_email ) lines.push( <?php echo wp_json_encode( __( 'Email:', 'ibb-rentals' ) ); ?>  + ' ' + p.guest_email );
				if ( p.order_id    ) lines.push( <?php echo wp_json_encode( __( 'Order:', 'ibb-rentals' ) ); ?>  + ' #' + p.order_id );
				if ( p.summary     ) lines.push( p.summary );
				detailBody.textContent = lines.join('\n');

				detailDelete.style.display = isDeletable ? '' : 'none';

				if ( p.booking_url ) {
					detailBookingLink.href         = p.booking_url;
					detailBookingLink.style.display = '';
				} else {
					detailBookingLink.style.display = 'none';
				}

				if ( p.clickup_url ) {
					detailClickupLink.href         = p.clickup_url;
					detailClickupLink.style.display = '';
				} else {
					detailClickupLink.style.display = 'none';
				}

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
							if ( currentView === 'timeline' ) fetchAndRenderTimeline();
							else calendar.refetchEvents();
						} else {
							alert( res.data || <?php echo wp_json_encode( __( 'Error deleting block.', 'ibb-rentals' ) ); ?> );
						}
					} );
			} );

			// ── View switcher buttons ─────────────────────────────────────
			var btnMonth    = document.getElementById('ibb-view-month');
			var btnWeek     = document.getElementById('ibb-view-week');
			var btnTimeline = document.getElementById('ibb-view-timeline');
			var timelineEl  = document.getElementById('ibb-timeline');

			function switchView( view ) {
				currentView = view;
				btnMonth.classList.toggle(    'button-primary', view === 'month' );
				btnWeek.classList.toggle(     'button-primary', view === 'week' );
				btnTimeline.classList.toggle( 'button-primary', view === 'timeline' );

				if ( view === 'timeline' ) {
					calEl.style.display      = 'none';
					timelineEl.style.display = 'block';
					fetchAndRenderTimeline();
				} else {
					calEl.style.display      = '';
					timelineEl.style.display = 'none';
					calendar.changeView( view === 'week' ? 'dayGridWeek' : 'dayGridMonth' );
				}
			}

			btnMonth.addEventListener(    'click', function(){ switchView('month');    } );
			btnWeek.addEventListener(     'click', function(){ switchView('week');     } );
			btnTimeline.addEventListener( 'click', function(){ switchView('timeline'); } );

			// ── Timeline: state + helpers ─────────────────────────────────
			var tlToday  = new Date();
			var tlYear   = tlToday.getFullYear();
			var tlMonth  = tlToday.getMonth() + 1; // 1-based
			var tlEvents = [];

			var tlTitle = document.getElementById('ibb-tl-title');
			var tlInner = document.getElementById('ibb-tl-inner');

			var MONTH_NAMES = [
				'January','February','March','April','May','June',
				'July','August','September','October','November','December',
			];
			var DAY_W   = 32;  // px per day column
			var ROW_H   = 36;  // px per property row
			var LABEL_W = 165; // px for property name column

			function tlFormatDate( d ) {
				var y   = d.getFullYear();
				var m   = String( d.getMonth() + 1 ).padStart( 2, '0' );
				var day = String( d.getDate() ).padStart( 2, '0' );
				return y + '-' + m + '-' + day;
			}

			function tlEsc( s ) {
				return String( s )
					.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
			}

			function tlUcfirst( s ) {
				return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
			}

			// ── Timeline: fetch + render ──────────────────────────────────
			function fetchAndRenderTimeline() {
				var monthStart = new Date( tlYear, tlMonth - 1, 1 );
				var monthEnd   = new Date( tlYear, tlMonth,     1 );
				var params = new URLSearchParams({
					action: 'ibb_rentals_calendar_events',
					nonce:  nonce,
					start:  tlFormatDate( monthStart ),
					end:    tlFormatDate( monthEnd ),
				});
				if ( filterProp.value )   params.set( 'property_id', filterProp.value );
				if ( filterSource.value ) params.set( 'source',      filterSource.value );
				tlInner.innerHTML = '<p style="padding:8px;color:#888;"><?php echo esc_js( __( 'Loading…', 'ibb-rentals' ) ); ?></p>';
				fetch( ajaxUrl + '?' + params.toString() )
					.then(function(r){ return r.json(); })
					.then(function(data){
						tlEvents = data;
						renderTimeline();
					})
					.catch(function(){
						tlInner.innerHTML = '<p style="padding:8px;color:#c00;"><?php echo esc_js( __( 'Error loading events.', 'ibb-rentals' ) ); ?></p>';
					});
			}

			function renderTimeline() {
				var daysInMonth = new Date( tlYear, tlMonth, 0 ).getDate();
				var monthStart  = new Date( tlYear, tlMonth - 1, 1 );
				var todayStr    = tlFormatDate( tlToday );

				tlTitle.textContent = MONTH_NAMES[ tlMonth - 1 ] + ' ' + tlYear;

				// Property list — respect the property filter if set.
				var propList = [];
				if ( filterProp.value ) {
					var fid = parseInt( filterProp.value );
					propList.push([ fid, properties[ fid ] || ( 'Property #' + fid ) ]);
				} else {
					Object.keys( properties ).forEach(function(id){
						propList.push([ parseInt(id), properties[id] ]);
					});
				}

				var totalWidth = LABEL_W + daysInMonth * DAY_W;
				var html = '<div style="display:inline-block;min-width:' + totalWidth + 'px;width:100%;">';

				// ── Day header ────────────────────────────────────────────
				html += '<div style="display:flex;border-bottom:2px solid #ccc;background:#f0f0f0;">';
				html += '<div style="min-width:' + LABEL_W + 'px;width:' + LABEL_W + 'px;'
					+  'padding:5px 8px;font-weight:600;border-right:1px solid #ccc;font-size:12px;'
					+  'position:sticky;left:0;background:#f0f0f0;z-index:3;">'
					+  '<?php echo esc_js( __( 'Property', 'ibb-rentals' ) ); ?></div>';

				for ( var d = 1; d <= daysInMonth; d++ ) {
					var dt     = new Date( tlYear, tlMonth - 1, d );
					var dtStr  = tlFormatDate( dt );
					var isToday   = ( dtStr === todayStr );
					var isWeekend = ( dt.getDay() === 0 || dt.getDay() === 6 );
					var hBg    = isToday ? '#3b82f6' : ( isWeekend ? '#e0e0e0' : '#f0f0f0' );
					var hColor = isToday ? '#fff'    : '#444';
					html += '<div style="width:' + DAY_W + 'px;min-width:' + DAY_W + 'px;'
						+  'text-align:center;padding:4px 0;font-size:10px;font-weight:600;'
						+  'border-right:1px solid #ddd;background:' + hBg + ';color:' + hColor + ';line-height:1.2;">'
						+  '<span>' + dt.toLocaleDateString( 'en', { weekday: 'narrow' } ) + '</span><br>'
						+  '<span>' + d + '</span>'
						+  '</div>';
				}
				html += '</div>';

				// ── Property rows ─────────────────────────────────────────
				if ( propList.length === 0 ) {
					html += '<p style="padding:16px;color:#666;"><?php echo esc_js( __( 'No properties found.', 'ibb-rentals' ) ); ?></p>';
				}

				propList.forEach(function( entry ) {
					var propId   = entry[0];
					var propName = entry[1];
					var propEvs  = tlEvents.filter(function(ev){
						return ev.extendedProps && ev.extendedProps.property_id == propId;
					});

					html += '<div style="display:flex;border-bottom:1px solid #eee;height:' + ROW_H + 'px;position:relative;">';

					// Sticky property label
					html += '<div style="min-width:' + LABEL_W + 'px;width:' + LABEL_W + 'px;'
						+  'padding:0 8px;display:flex;align-items:center;font-size:12px;'
						+  'border-right:1px solid #ddd;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;'
						+  'position:sticky;left:0;background:#fff;z-index:1;">'
						+  tlEsc( propName ) + '</div>';

					// Day cells + absolute block bars.
					// Explicit width = sum of cells (no flex:1) so a narrow viewport doesn't
					// squeeze cellContainer below the cell-sum and clip the trailing days/bars.
					// Horizontal scrolling kicks in on the outer #ibb-tl-wrap when needed.
					var cellsWidth = daysInMonth * DAY_W;
					html += '<div style="position:relative;width:' + cellsWidth + 'px;flex-shrink:0;display:flex;overflow:hidden;">';

					for ( var dd = 1; dd <= daysInMonth; dd++ ) {
						var ddt      = new Date( tlYear, tlMonth - 1, dd );
						var ddStr    = tlFormatDate( ddt );
						var isTd     = ( ddStr === todayStr );
						var isWknd   = ( ddt.getDay() === 0 || ddt.getDay() === 6 );
						var cellBg   = isTd ? 'rgba(59,130,246,0.09)' : ( isWknd ? 'rgba(0,0,0,0.03)' : 'transparent' );
						html += '<div style="width:' + DAY_W + 'px;min-width:' + DAY_W + 'px;'
							+  'height:100%;border-right:1px solid #f0f0f0;flex-shrink:0;background:' + cellBg + ';"></div>';
					}

					// Block bars (absolutely positioned over the cells)
					propEvs.forEach(function( ev ) {
						if ( !ev.start || !ev.end ) return;

						// Midnight local-time objects for date arithmetic
						var evStart  = new Date( ev.start + 'T00:00:00' );
						var evEnd    = new Date( ev.end   + 'T00:00:00' );
						var mEnd     = new Date( tlYear, tlMonth, 1 );

						var visStart = evStart < monthStart ? monthStart : evStart;
						var visEnd   = evEnd   > mEnd       ? mEnd       : evEnd;
						if ( visStart >= visEnd ) return;

						// Days from month start (Math.round handles DST ±1h edge)
						var startOff = Math.round( ( visStart - monthStart ) / 86400000 );
						var durDays  = Math.round( ( visEnd   - visStart   ) / 86400000 );
						var barLeft  = startOff * DAY_W + 1;
						var barW     = durDays  * DAY_W - 2;
						if ( barW < 2 ) return;

						var src       = ev.extendedProps ? ( ev.extendedProps.source    || '' ) : '';
						var guestName = ev.extendedProps ? ( ev.extendedProps.guest_name || '' ) : '';
						var label     = guestName || tlUcfirst( src );

						var barColour = ev.color || ( sourceColours[ src ] || sourceColourDefault );
						html += '<div data-block-id="' + ( ev.extendedProps ? ev.extendedProps.block_id : '' ) + '" '
							+  'title="' + tlEsc( ev.title ) + '" '
							+  'style="position:absolute;left:' + barLeft + 'px;top:4px;'
							+  'width:' + barW + 'px;height:' + ( ROW_H - 8 ) + 'px;'
							+  'background:' + barColour + ';border-radius:3px;cursor:pointer;'
							+  'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'
							+  'padding:0 5px;font-size:11px;color:#fff;'
							+  'line-height:' + ( ROW_H - 8 ) + 'px;z-index:2;opacity:0.92;">'
							+  tlEsc( label ) + '</div>';
					});

					html += '</div>'; // cells container
					html += '</div>'; // row
				});

				html += '</div>'; // grid wrapper
				tlInner.innerHTML = html;

				// Attach click → detail modal for each block bar
				tlInner.querySelectorAll('[data-block-id]').forEach(function( el ) {
					el.addEventListener('click', function() {
						var blockId = parseInt( el.getAttribute('data-block-id') );
						var ev = tlEvents.find(function(e){
							return e.extendedProps && e.extendedProps.block_id === blockId;
						});
						if ( ev ) {
							openDetailModal({
								title:         ev.title,
								startStr:      ev.start,
								endStr:        ev.end,
								extendedProps: ev.extendedProps,
							});
						}
					});
				});
			}

			// ── Timeline navigation ───────────────────────────────────────
			document.getElementById('ibb-tl-prev').addEventListener('click', function(){
				tlMonth--;
				if ( tlMonth < 1 ) { tlMonth = 12; tlYear--; }
				fetchAndRenderTimeline();
			});
			document.getElementById('ibb-tl-next').addEventListener('click', function(){
				tlMonth++;
				if ( tlMonth > 12 ) { tlMonth = 1; tlYear++; }
				fetchAndRenderTimeline();
			});
			document.getElementById('ibb-tl-today').addEventListener('click', function(){
				tlYear  = tlToday.getFullYear();
				tlMonth = tlToday.getMonth() + 1;
				fetchAndRenderTimeline();
			});
			document.getElementById('ibb-tl-add-block').addEventListener('click', function(){
				openCreateModal('', '');
			});

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
			$blocks = array_values( array_filter( $blocks, fn( Block $b ) => $b->effective_source() === $source ) );
		}

		$properties = $this->get_properties_map();

		// Batch-load booking rows for all direct blocks so we can surface guest names
		// and link back to the WC order without N+1 queries.
		$direct_block_ids = array_values( array_filter(
			array_map( fn( Block $b ) => $b->source === Block::SOURCE_DIRECT ? $b->id : null, $blocks )
		) );

		$bookings_by_block = [];
		if ( ! empty( $direct_block_ids ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $direct_block_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT block_id, guest_name, guest_email, order_id FROM {$wpdb->prefix}ibb_bookings WHERE block_id IN ($placeholders)",
					...$direct_block_ids
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$bookings_by_block[ (int) $row['block_id'] ] = $row;
			}
		}

		$events = [];
		foreach ( $blocks as $block ) {
			// effective_source: ClickUp's source_override wins over the iCal-import source
			// for color/label, since iCal can mis-attribute manual-blackout direct/Agoda
			// bookings as `airbnb`.
			$effective    = $block->effective_source();
			$colour       = self::SOURCE_COLOURS[ $effective ] ?? self::SOURCE_COLOUR_DEFAULT;
			$prop_name    = $properties[ $block->property_id ] ?? 'Property #' . $block->property_id;
			$source_label = ucfirst( $effective );

			$booking    = $bookings_by_block[ $block->id ] ?? null;
			// Direct bookings: name from wp_ibb_bookings. OTA blocks: name written by ClickUp sync.
			$guest_name = $booking ? ( $booking['guest_name'] ?? '' ) : $block->guest_name;

			// WC order edit URL — wc_get_order()->get_edit_order_url() is HPOS-safe.
			$booking_url = '';
			if ( $block->order_id ) {
				$order = wc_get_order( $block->order_id );
				if ( $order ) {
					$booking_url = $order->get_edit_order_url();
				}
			}

			$clickup_url = $block->clickup_task_id !== ''
				? 'https://app.clickup.com/t/' . rawurlencode( $block->clickup_task_id )
				: '';

			$events[] = [
				'id'    => 'block_' . $block->id,
				'title' => $prop_name . ' — ' . ( $guest_name ?: $source_label ),
				'start' => $block->range->checkin_string(),
				'end'   => $block->range->checkout_string(),
				'color' => $colour,
				'extendedProps' => [
					'block_id'    => $block->id,
					'property_id' => $block->property_id,
					'source'      => $effective,
					'raw_source'  => $block->source,
					'order_id'    => $block->order_id,
					'summary'     => $block->summary,
					'guest_name'  => $guest_name,
					'guest_email' => $booking['guest_email'] ?? '',
					'booking_url' => $booking_url,
					'clickup_url' => $clickup_url,
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
