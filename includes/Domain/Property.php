<?php
/**
 * Domain wrapper around a `ibb_property` post.
 *
 * Reads from WordPress postmeta on demand and exposes typed accessors. All
 * meta keys live under the `_ibb_*` prefix. Defaults are provided so a freshly
 * published property without configured rates/rules still produces sensible
 * behaviour rather than throwing.
 */

declare( strict_types=1 );

namespace IBB\Rentals\Domain;

use IBB\Rentals\PostTypes\PropertyPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Property {

	public const PAYMENT_FULL    = 'full';
	public const PAYMENT_DEPOSIT = 'deposit';

	public function __construct(
		public readonly int $id,
		public readonly WP_Post $post,
	) {}

	public static function from_id( int $id ): ?self {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || $post->post_type !== PropertyPostType::POST_TYPE ) {
			return null;
		}
		return new self( $id, $post );
	}

	public static function from_post( WP_Post $post ): ?self {
		if ( $post->post_type !== PropertyPostType::POST_TYPE ) {
			return null;
		}
		return new self( $post->ID, $post );
	}

	public function title(): string {
		return get_the_title( $this->post );
	}

	public function meta( string $key, mixed $default = null ): mixed {
		$value = get_post_meta( $this->id, $key, true );
		return $value === '' ? $default : $value;
	}

	public function max_guests(): int        { return (int) $this->meta( '_ibb_max_guests', 2 ); }
	public function bedrooms(): int          { return (int) $this->meta( '_ibb_bedrooms', 1 ); }
	public function bathrooms(): float       { return (float) $this->meta( '_ibb_bathrooms', 1 ); }
	public function beds(): int              { return (int) $this->meta( '_ibb_beds', 1 ); }

	public function short_description(): string {
		// Brief summary shown in cart line items, search cards, etc. Stored
		// in postmeta (not post_excerpt) so we don't fight Gutenberg's
		// sidebar-vs-metabox excerpt-save race.
		return (string) $this->meta( '_ibb_short_description', '' );
	}

	public function description(): string {
		return (string) $this->meta( '_ibb_description', '' );
	}

	public function check_in_time(): string  { return (string) $this->meta( '_ibb_check_in_time', '15:00' ); }
	public function check_out_time(): string { return (string) $this->meta( '_ibb_check_out_time', '11:00' ); }

	public function base_rate(): float        { return (float) $this->meta( '_ibb_base_rate', 0 ); }
	public function weekend_uplift_pct(): float { return (float) $this->meta( '_ibb_weekend_uplift_pct', 0 ); }

	/** @return list<int> ISO-8601 weekdays (1=Mon … 7=Sun). */
	public function weekend_days(): array {
		$raw = (string) $this->meta( '_ibb_weekend_days', '5,6,7' );
		$out = [];
		foreach ( explode( ',', $raw ) as $piece ) {
			$n = (int) trim( $piece );
			if ( $n >= 1 && $n <= 7 ) {
				$out[] = $n;
			}
		}
		return $out;
	}

	public function min_nights(): int           { return max( 1, (int) $this->meta( '_ibb_min_nights', 1 ) ); }
	public function max_nights(): int           { return max( 0, (int) $this->meta( '_ibb_max_nights', 0 ) ); }
	public function advance_booking_days(): int { return max( 0, (int) $this->meta( '_ibb_advance_booking_days', 0 ) ); }
	public function max_advance_days(): int     { return max( 0, (int) $this->meta( '_ibb_max_advance_days', 0 ) ); }

	public function cleaning_fee(): float          { return (float) $this->meta( '_ibb_cleaning_fee', 0 ); }
	public function extra_guest_fee(): float       { return (float) $this->meta( '_ibb_extra_guest_fee', 0 ); }
	public function extra_guest_threshold(): int   { return (int) $this->meta( '_ibb_extra_guest_threshold', $this->max_guests() ); }
	public function security_deposit(): float     { return (float) $this->meta( '_ibb_security_deposit', 0 ); }

	/**
	 * Tax class slug applied to the accommodation portion of the booking
	 * (nights × rate, after LOS discount). `''` = not taxed,
	 * `'standard'` = WC standard rate, anything else = a user-defined slug
	 * registered under WooCommerce → Settings → Tax.
	 */
	public function tax_class(): string {
		return (string) $this->meta( '_ibb_tax_class', '' );
	}

	/**
	 * Tax class slug for the cleaning fee. Empty string = not taxed (default).
	 * Stored separately from the accommodation tax class so a property can be
	 * subject to e.g. hotel/PB1 tax on accommodation while keeping the cleaning
	 * fee at standard VAT (or untaxed altogether).
	 */
	public function cleaning_tax_class(): string {
		return (string) $this->meta( '_ibb_cleaning_tax_class', '' );
	}

	/**
	 * Tax class slug for the extra-guest fee. Defaults to whatever the
	 * accommodation tax class is — extra-guest is logically part of the stay
	 * — but can be overridden when the local tax regime treats them differently.
	 */
	public function extra_guest_tax_class(): string {
		$raw = (string) $this->meta( '_ibb_extra_guest_tax_class', '__inherit__' );
		return $raw === '__inherit__' ? $this->tax_class() : $raw;
	}

	/**
	 * @return list<array{min_nights:int,pct:float}>  sorted descending by min_nights
	 */
	public function los_discounts(): array {
		$raw = $this->meta( '_ibb_los_discounts', '[]' );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		$out = [];
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['min_nights'], $row['pct'] ) ) {
				continue;
			}
			$out[] = [
				'min_nights' => (int) $row['min_nights'],
				'pct'        => (float) $row['pct'],
			];
		}
		usort( $out, static fn( $a, $b ) => $b['min_nights'] <=> $a['min_nights'] );
		return $out;
	}

	/**
	 * @return list<array{start:string,end:string}>
	 */
	public function blackout_ranges(): array {
		$raw = $this->meta( '_ibb_blackout_ranges', '[]' );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		$out = [];
		foreach ( $decoded as $row ) {
			if ( is_array( $row ) && isset( $row['start'], $row['end'] ) ) {
				$out[] = [ 'start' => (string) $row['start'], 'end' => (string) $row['end'] ];
			}
		}
		return $out;
	}

	public function payment_mode(): string {
		$mode = (string) $this->meta( '_ibb_payment_mode', self::PAYMENT_FULL );
		return $mode === self::PAYMENT_DEPOSIT ? self::PAYMENT_DEPOSIT : self::PAYMENT_FULL;
	}

	public function deposit_pct(): float {
		$pct = (float) $this->meta( '_ibb_deposit_pct', 30 );
		return max( 0, min( 100, $pct ) );
	}

	public function balance_due_days_before(): int {
		return max( 0, (int) $this->meta( '_ibb_balance_due_days_before', 14 ) );
	}

	public function linked_product_id(): ?int {
		$id = (int) $this->meta( '_ibb_linked_product_id', 0 );
		return $id > 0 ? $id : null;
	}

	/**
	 * Named photo sub-galleries (e.g. "Bedroom 1", "Pool"). Stored as JSON in
	 * postmeta `_ibb_galleries`; admin manages them via the Photos tab.
	 *
	 * @return list<array{slug:string,label:string,attachments:list<int>}>
	 */
	public function galleries(): array {
		$raw     = $this->meta( '_ibb_galleries', '[]' );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : [] );
		if ( ! is_array( $decoded ) ) {
			return [];
		}
		$out = [];
		foreach ( $decoded as $g ) {
			if ( ! is_array( $g ) || empty( $g['slug'] ) ) {
				continue;
			}
			$slug  = sanitize_key( (string) $g['slug'] );
			$label = isset( $g['label'] ) ? (string) $g['label'] : $slug;
			$ids   = array_values( array_filter( array_map( 'intval', (array) ( $g['attachments'] ?? [] ) ) ) );
			$out[] = [ 'slug' => $slug, 'label' => $label, 'attachments' => $ids ];
		}
		return $out;
	}

	/**
	 * Look up a single named gallery by slug.
	 *
	 * @return array{slug:string,label:string,attachments:list<int>}|null
	 */
	public function gallery( string $slug ): ?array {
		$slug = sanitize_key( $slug );
		foreach ( $this->galleries() as $g ) {
			if ( $g['slug'] === $slug ) {
				return $g;
			}
		}
		return null;
	}

	/**
	 * Flat de-duplicated list of every attachment ID across every gallery,
	 * preserving gallery order then within-gallery order.
	 *
	 * @return list<int>
	 */
	public function all_attachments(): array {
		$seen = [];
		foreach ( $this->galleries() as $g ) {
			foreach ( $g['attachments'] as $id ) {
				if ( ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = true;
				}
			}
		}
		return array_keys( $seen );
	}

	public function ical_export_token(): string {
		$token = (string) $this->meta( '_ibb_ical_export_token', '' );
		if ( $token === '' ) {
			$secret = (string) get_option( 'ibb_rentals_token_secret', '' );
			$token  = hash_hmac( 'sha256', 'ical:' . $this->id, $secret );
			update_post_meta( $this->id, '_ibb_ical_export_token', $token );
		}
		return $token;
	}
}
