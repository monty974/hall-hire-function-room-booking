<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HBS_Frontend {

	public function enqueue_frontend_scripts() {
		// Enqueue scripts only on relevant pages
		if ( is_page( 'booking' ) || has_shortcode( get_the_content(), 'hbs_booking_form' ) ) {
			wp_enqueue_script( 'hbs-frontend', HBS_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), HBS_VERSION );
			wp_enqueue_style( 'hbs-frontend', HBS_PLUGIN_URL . 'assets/css/frontend.css', array(), HBS_VERSION );

			// Localize script
			wp_localize_script( 'hbs-frontend', 'hbsData', array(
				'nonce'     => wp_create_nonce( 'hbs_nonce' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			) );

			// Enqueue calendar library
			
			
		}
	}

	/**
	 * Shortcode: [hbs_booking_form]
	 */
	public function register_shortcodes() {
		add_shortcode( 'hbs_booking_form', array( $this, 'render_booking_form' ) );
		add_shortcode( 'hbs_calendar', array( $this, 'render_calendar' ) );
	}

	/**
	 * Render public booking form
	 */
	public function render_booking_form( $atts ) {
		$areas = get_posts( array(
			'post_type'      => 'hbs_area',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		ob_start();
		?>
		<div class="hbs-booking-form-wrapper">
			<form id="hbs-booking-form" class="hbs-booking-form">
				<h2>Book Your Event</h2>

				<div class="hbs-form-group">
					<label for="hbs-area">Select Area:</label>
					<select id="hbs-area" name="area_id" required>
						<option value="">-- Select an area --</option>
						<?php foreach ( $areas as $area ) : ?>
							<option value="<?php echo esc_attr( $area->ID ); ?>">
								<?php echo esc_html( $area->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-start-date">Start Date:</label>
					<input type="date" id="hbs-start-date" name="start_date" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-end-date">End Date:</label>
					<input type="date" id="hbs-end-date" name="end_date" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-duration">Duration:</label>
					<select id="hbs-duration" name="duration_type" required>
						<option value="two_hours">2 Hours</option>
						<option value="half_day">Half Day</option>
						<option value="full_day" selected>Full Day</option>
					</select>
				</div>

				<div class="hbs-form-group">
					<label>
						<input type="checkbox" name="is_recurring" id="hbs-is-recurring">
						This is a recurring booking
					</label>
				</div>

				<div id="hbs-recurring-fields" style="display: none;">
					<div class="hbs-form-group">
						<label for="hbs-recurrence-type">Frequency:</label>
						<select id="hbs-recurrence-type" name="recurrence_type">
							<option value="weekly">Weekly</option>
							<option value="fortnightly">Fortnightly</option>
							<option value="monthly">Monthly</option>
						</select>
					</div>

					<div class="hbs-form-group">
						<label for="hbs-recurrence-until">Recurrence Until:</label>
						<input type="date" id="hbs-recurrence-until" name="recurrence_until">
					</div>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-renter-name">Your Name:</label>
					<input type="text" id="hbs-renter-name" name="renter_name" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-renter-email">Email:</label>
					<input type="email" id="hbs-renter-email" name="renter_email" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-renter-phone">Phone:</label>
					<input type="tel" id="hbs-renter-phone" name="renter_phone" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-event-type">Event Type:</label>
					<input type="text" id="hbs-event-type" name="event_type" placeholder="e.g., Wedding, Birthday, Corporate Event" required>
				</div>

				<div class="hbs-form-group">
					<label for="hbs-event-details">Event Details:</label>
					<textarea id="hbs-event-details" name="event_details" rows="4" placeholder="Tell us more about your event..."></textarea>
				</div>

				<button type="submit" class="hbs-btn hbs-btn-primary">Submit Booking Request</button>
				<div id="hbs-form-message" class="hbs-message" style="display: none;"></div>
			</form>
		</div>

		<script>
			jQuery(function($) {
				$('#hbs-is-recurring').on('change', function() {
					$('#hbs-recurring-fields').toggle(this.checked);
				});

				$('#hbs-booking-form').on('submit', function(e) {
					e.preventDefault();
					var $form = $(this);
					var $message = $('#hbs-form-message');

					$.ajax({
						url: hbsData.ajaxUrl,
						type: 'POST',
						data: $form.serialize() + '&action=hbs_submit_booking_request&nonce=' + hbsData.nonce,
						success: function(response) {
							if (response.success) {
								$message.html('Thank you! Your booking request has been submitted. You will receive a confirmation email shortly.').addClass('success').show();
								$form[0].reset();
								$('#hbs-recurring-fields').hide();
							} else {
								$message.html('Error: ' + response.data).addClass('error').show();
							}
						}
					});
				});
			});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render public availability calendar
	 */
	public function render_calendar( $atts ) {
		ob_start();
		?>
		<div id="hbs-calendar" class="hbs-calendar-wrapper">
			<!-- FullCalendar will render here -->
		</div>

		<script>
			jQuery(function($) {
				document.addEventListener('DOMContentLoaded', function() {
					var calendarEl = document.getElementById('hbs-calendar');
					var calendar = new FullCalendar.Calendar(calendarEl, {
						initialView: 'dayGridMonth',
						headerToolbar: {
							left: 'prev,next today',
							center: 'title',
							right: 'dayGridMonth,listMonth'
						},
						events: function(info, successCallback, failureCallback) {
							// Fetch events from AJAX
							$.ajax({
								url: hbsData.ajaxUrl,
								type: 'POST',
								data: {
									action: 'hbs_get_availability',
									nonce: hbsData.nonce,
									start_date: info.startStr,
									end_date: info.endStr
								},
								success: function(response) {
									if (response.success) {
										var events = [];
										$.each(response.data, function(date, availability) {
											if (!availability.available) {
												events.push({
													start: date,
													title: 'Booked',
													rendering: 'background',
													backgroundColor: '#dc3545',
													borderColor: '#c82333'
												});
											}
										});
										successCallback(events);
									}
								}
							});
						}
					});
					calendar.render();
				});
			});
		</script>
		<?php
		return ob_get_clean();
	}
}

// Initialize shortcodes on init
add_action( 'init', function() {
	$frontend = new HBS_Frontend();
	$frontend->register_shortcodes();
} );
