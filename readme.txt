=== Hall hire and function room booking system ===
Contributors: nicklagalle
Tags: booking, reservations, calendar, hall rental, function room, scheduling
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2026.1.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A flexible booking system for managing hall and function room rentals with approval workflow, recurring bookings, and email notifications.

== Description ==

Hall Booking System is a powerful WordPress plugin for managing bookings for halls, meeting rooms, or any rental space.

**Features:**

* Single and recurring bookings (weekly, fortnightly, monthly)
* Public booking form - no login required
* Beautiful two-column layout with availability calendar
* Admin approval workflow with email notifications
* Support for multiple areas/spaces
* Configurable operating hours per day
* Internal time blocks for maintenance/blocking
* Professional, responsive, modern design
* Complete admin management interface
* Email notifications to admins and renters
* Month picker for easy date selection
* Multi-month admin view
* Fully responsive on all devices

== Installation ==

1. Upload the plugin files via WordPress Plugins → Add New
2. Activate the plugin through the Plugins menu
3. Go to Hall Bookings → Settings to configure
4. Create a WordPress page with [hbs_booking_form] shortcode
5. Done! The booking form will appear on your page

== Frequently Asked Questions ==

= Can customers book without logging in? =
Yes! The booking form is public and doesn't require WordPress login.

= Can I have multiple booking areas? =
Yes! Create multiple areas in admin settings (e.g., "Main Hall", "Meeting Room A", "Meeting Room B").

= Do bookings need admin approval? =
Yes, customer bookings are pending by default. Admins approve or reject in the admin panel. You can also create bookings directly in admin that auto-approve.

= Can I allow recurring bookings? =
Yes! Customers can select weekly, fortnightly, or monthly recurring bookings with optional end date.

= Does this plugin handle payments? =
No, this version manages bookings and approvals only. Payment processing would need to be integrated separately.

= Can I set different hours for different days? =
Yes! In Settings → Booking Hours, set specific hours for each day of the week.

= What if a customer tries to book outside operating hours? =
A warning appears. If they try to book during closed times, a message explains the hall is closed.

= Can I block specific time slots? =
Yes! Use "Create Internal Block" to block time for maintenance, staff events, or other reasons.

= Do customers get email confirmations? =
Yes! Renters receive approval or rejection emails. Admins get notified of all new bookings.

== Screenshots ==

1. Public booking form with calendar and time selection
2. Admin bookings management with filtering
3. Availability calendar showing status (green/yellow/red)
4. Admin settings for areas and hours

== Changelog ==

= 2026.1.1 =
* Updated branding: Hall hire and function room booking system
* Updated author: Nick La Galle
* Production release ready for WordPress.org

= 2026.1.0 =
* Production release
* Complete security audit - all domains passed
* Fixed XSS vulnerabilities in email output
* Modern two-column layout design
* Month/year dropdown picker (24 months)
* Multi-month admin view (1/3/6 months)
* Professional card-based styling
* Fully responsive on mobile/tablet
* Comprehensive documentation included

= 2026.0.33 =
* Modern design overhaul with two-column layout
* Professional card styling with shadows and transitions
* Improved typography and color scheme
* Better form controls and interactive states

= 2026.0.32 =
* Added month/year dropdown picker
* Extended navigation range to 24 months
* Prev/Next buttons for quick month navigation

= 2026.0.31 =
* Fixed booking page calendar layout
* Proper 7-column grid alignment
* Days of week header properly positioned

= 2026.0.30 =
* Multi-month admin view (current/3/6/custom months)
* Month/year dropdown selector in calendar

== Support ==

For support, feature requests, or bug reports, please visit the WordPress.org plugin support forums.

== Security ==

This plugin has been thoroughly security audited and follows all WordPress security best practices:

* CSRF protection with nonces
* XSS prevention with proper escaping
* SQL injection prevention with prepared statements
* Input sanitization on all user data
* Proper authentication and authorization checks

== License ==

This plugin is licensed under the GPL v2 or later license.
See the LICENSE file for full details.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
