<?php
/**
 * Plugin Name: Hall hire and function room booking system
 * Description: A flexible booking system for managing hall and function room rentals with approval workflow, recurring bookings, and email notifications
 * Version: 2026.1.2
 * Author: Nick La Galle
 * License: GPL v2 or later
 * Text Domain: hall-hire-and-function-room-booking-system
 * Domain Path: /languages
 * 
 * BUILD VERIFICATION: 2026.0.12-FINAL
 * Changes in this build:
 * ✓ Edit Booking Modal HTML added to admin page
 * ✓ Recurring checkbox: JS only sends is_recurring if checked
 * ✓ Date parsing: Improved with error handling
 * ✓ Frontend.js: Fixed is_recurring field sending
 * ✓ Admin.js: Fixed openEditBookingModal function
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'HBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HBS_VERSION', '2026.1.2' );

// Include required files
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-utilities.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-database.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-bookings.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-email.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-admin.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-public-form.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-calendar.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-rest-api.php';
require_once HBS_PLUGIN_DIR . 'includes/class-hbs-setup-wizard.php';

/**
 * Main plugin initialization
 */
class Hall_Booking_System {
	
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Register activation/deactivation hooks
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		
		// Defer class initialization to plugins_loaded hook for safety
		add_action( 'plugins_loaded', [ $this, 'init_classes' ], 10 );
	}

	public function init_classes() {
		// Initialize classes with error handling
		try {
			HBS_Database::get_instance();
			HBS_Bookings::get_instance();
			HBS_Email::get_instance();
			HBS_Admin::get_instance();
			HBS_Public_Form::get_instance();
			HBS_Calendar::get_instance();
			HBS_REST_API::get_instance();
			HBS_Setup_Wizard::get_instance();
		} catch ( Exception $e ) {
			// Log the error but don't crash
		}
	}

	public function activate() {
		// Ensure database class is available
		if (!class_exists('HBS_Database')) {
			wp_die('HBS_Database class not found. Please contact support.');
		}
		
		// Create database tables
		try {
			$db = HBS_Database::get_instance();
			if (method_exists($db, 'create_tables')) {
				$db->create_tables();
			}
		} catch ( Exception $e ) {
			error_log('HBS Activation Error: ' . $e->getMessage());
			wp_die( esc_html( 'Database setup failed. Please check error logs.' ) );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

// Initialize plugin
Hall_Booking_System::get_instance();
