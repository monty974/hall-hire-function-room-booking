<?php

class HBS_Post_Types {

	public function register_post_types() {
		// Hall post type
		register_post_type( 'hbs_hall', array(
			'label'               => __( 'Halls', 'venuehub-hall-bookings' ),
			'singular_name'       => __( 'Hall', 'venuehub-hall-bookings' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'           => 'dashicons-building',
			'menu_position'       => 5,
		) );

		// Bookable area post type
		register_post_type( 'hbs_area', array(
			'label'               => __( 'Bookable Areas', 'venuehub-hall-bookings' ),
			'singular_name'       => __( 'Bookable Area', 'venuehub-hall-bookings' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=hbs_hall',
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'           => 'dashicons-layout',
		) );

		// Booking request post type
		register_post_type( 'hbs_booking', array(
			'label'               => __( 'Booking Requests', 'venuehub-hall-bookings' ),
			'singular_name'       => __( 'Booking Request', 'venuehub-hall-bookings' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=hbs_hall',
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'           => 'dashicons-calendar-alt',
		) );

		// Blocked period post type
		register_post_type( 'hbs_blocked', array(
			'label'               => __( 'Blocked Periods', 'venuehub-hall-bookings' ),
			'singular_name'       => __( 'Blocked Period', 'venuehub-hall-bookings' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=hbs_hall',
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'custom-fields' ),
			'menu_icon'           => 'dashicons-lock',
		) );
	}

	public function register_taxonomies() {
		// Booking status taxonomy
		register_taxonomy( 'hbs_booking_status', 'hbs_booking', array(
			'label'             => __( 'Booking Status', 'venuehub-hall-bookings' ),
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => false,
			'show_in_quick_edit' => true,
		) );

		// Add default statuses
		wp_insert_term( 'pending', 'hbs_booking_status', array( 'description' => 'Awaiting approval' ) );
		wp_insert_term( 'approved', 'hbs_booking_status', array( 'description' => 'Approved and confirmed' ) );
		wp_insert_term( 'rejected', 'hbs_booking_status', array( 'description' => 'Rejected' ) );
	}
}
