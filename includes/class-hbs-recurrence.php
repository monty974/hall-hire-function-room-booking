<?php

class HBS_Recurrence {

	/**
	 * Parse RRULE and generate dates
	 * 
	 * @param string $rrule RRULE string (e.g., "FREQ=WEEKLY;BYDAY=MO,WE,FR;UNTIL=2025-12-31")
	 * @param string $start_date Start date in YYYY-MM-DD format
	 * @param int $limit Maximum number of occurrences to generate
	 * @return array Array of dates in YYYY-MM-DD format
	 */
	public static function generate_dates( $rrule, $start_date, $limit = 100 ) {
		$dates = array();
		$components = self::parse_rrule( $rrule );
		
		$current = new DateTime( $start_date );
		$end_date = isset( $components['until'] ) ? new DateTime( $components['until'] ) : null;
		
		$count = 0;
		while ( $count < $limit ) {
			if ( $end_date && $current > $end_date ) {
				break;
			}

			$dates[] = $current->format( 'Y-m-d' );
			$current = self::advance_gmdate( $current, $components );
			$count++;
		}

		return $dates;
	}

	/**
	 * Parse RRULE string into components
	 */
	private static function parse_rrule( $rrule ) {
		$components = array();
		$parts = explode( ';', $rrule );

		foreach ( $parts as $part ) {
			list( $key, $value ) = explode( '=', $part );
			$components[ strtolower( $key ) ] = $value;
		}

		return $components;
	}

	/**
	 * Advance date based on frequency
	 */
	private static function advance_gmdate( DateTime $current, $components ) {
		$freq = isset( $components['freq'] ) ? $components['freq'] : 'WEEKLY';
		$interval = isset( $components['interval'] ) ? intval( $components['interval'] ) : 1;

		$interval_map = array(
			'DAILY'   => 'day',
			'WEEKLY'  => 'week',
			'MONTHLY' => 'month',
			'YEARLY'  => 'year',
		);

		if ( isset( $interval_map[ $freq ] ) ) {
			$current->modify( '+' . $interval . ' ' . $interval_map[ $freq ] );
		}

		// Handle BYDAY for weekly recurrence
		if ( $freq === 'WEEKLY' && isset( $components['byday'] ) ) {
			$days = array_map( 'trim', explode( ',', $components['byday'] ) );
			$day_map = array(
				'MO' => 'Monday',
				'TU' => 'Tuesday',
				'WE' => 'Wednesday',
				'TH' => 'Thursday',
				'FR' => 'Friday',
				'SA' => 'Saturday',
				'SU' => 'Sunday',
			);

			// Move to next matching day
			do {
				$current->modify( '+1 day' );
				$current_day = $current->format( 'l' );
				$found = false;

				foreach ( $days as $day ) {
					if ( isset( $day_map[ $day ] ) && $day_map[ $day ] === $current_day ) {
						$found = true;
						break;
					}
				}
			} while ( ! $found );
		}

		return $current;
	}

	/**
	 * Build RRULE from form data
	 */
	public static function build_rrule( $frequency, $days = array(), $interval = 1, $until = null ) {
		$rrule = 'FREQ=' . strtoupper( $frequency );

		if ( $interval > 1 ) {
			$rrule .= ';INTERVAL=' . intval( $interval );
		}

		if ( ! empty( $days ) ) {
			$rrule .= ';BYDAY=' . implode( ',', $days );
		}

		if ( $until ) {
			$rrule .= ';UNTIL=' . gmgmdate( 'Ymd', strtotime( $until ) );
		}

		return $rrule;
	}

	/**
	 * Check if booking is recurring
	 */
	public static function is_recurring( $booking_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}hbs_recurrence_rules WHERE booking_id = %d LIMIT 1",
			$booking_id
		) );
		return ! empty( $result );
	}

	/**
	 * Get all dates for a recurring booking
	 */
	public static function get_booking_dates( $booking_id ) {
		global $wpdb;
		
		$start_date = get_post_meta( $booking_id, 'start_date', true );
		$recurrence = $wpdb->get_row( $wpdb->prepare(
			"SELECT rrule FROM {$wpdb->prefix}hbs_recurrence_rules WHERE booking_id = %d LIMIT 1",
			$booking_id
		) );

		if ( ! $recurrence ) {
			// Single booking
			return array( $start_date );
		}

		// Generate all dates
		return self::generate_dates( $recurrence->rrule, $start_date, 500 );
	}
}
