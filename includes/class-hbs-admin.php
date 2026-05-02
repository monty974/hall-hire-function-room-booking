<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Hall Booking System - Admin Interface
 */

class HBS_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu - defaults to bookings page
        add_menu_page(
            'Hall Bookings',
            'Hall Bookings',
            'manage_options',
            'hbs-bookings',
            [$this, 'render_bookings_page'],
            'dashicons-calendar-alt',
            30
        );
        
        // Submenu: Setup Wizard
        add_submenu_page(
            'hbs-bookings',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'hbs-setup-wizard',
            [$this, 'render_setup_wizard_page']
        );
        
        // Submenu: Settings
        add_submenu_page(
            'hbs-bookings',
            'Hall Settings',
            'Hall Settings',
            'manage_options',
            'hbs-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Render setup wizard page
     */
    public function render_setup_wizard_page() {
        // Load the setup wizard
        $wizard = HBS_Setup_Wizard::get_instance();
        $wizard->render_wizard();
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if (strpos($hook_suffix, 'hbs-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        
        
        wp_enqueue_style('hbs-admin', HBS_PLUGIN_URL . 'assets/css/admin.css', [], HBS_VERSION);
        wp_enqueue_script('hbs-admin', HBS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], HBS_VERSION, true);
        
        wp_localize_script('hbs-admin', 'hbsAdminData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hbs_admin_nonce'),
        ]);
    }
    
    /**
     * Render bookings management page
     */
    public function render_bookings_page() {
        ?>
        <div class="wrap">
            <h1>Hall Bookings Management</h1>
            
            <div class="hbs-admin-wrapper">
                
                <div class="hbs-filters">
                    <div class="filter-group">
                        <label for="admin-area-filter">Area:</label>
                        <select id="admin-area-filter" class="hbs-filter-select">
                            <option value="">All Areas</option>
                            <?php $this->render_area_filter_options(); ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="admin-status-filter">Status:</label>
                        <select id="admin-status-filter" class="hbs-filter-select">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="admin-month-range">Show:</label>
                        <select id="admin-month-range" class="hbs-filter-input">
                            <option value="current">Current Month</option>
                            <option value="next3" selected>Next 3 Months</option>
                            <option value="next6">Next 6 Months</option>
                            <option value="custom">Custom Month</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" id="custom-month-group" style="display:none;">
                        <label for="admin-month-filter">Select Month:</label>
                        <input type="month" id="admin-month-filter" class="hbs-filter-input" value="<?php echo esc_attr(gmdate('Y-m')); ?>">
                    </div>
                    
                    <button id="hbs-refresh-btn" class="button button-primary">Refresh</button>
                </div>
                
                <div class="hbs-actions">
                    <button id="hbs-create-booking-btn" class="button button-primary" style="margin-right: 10px;">+ Create Booking</button>
                    <button id="hbs-create-block-btn" class="button button-secondary">Create Internal Block</button>
                </div>
                
                <table id="hbs-bookings-table" class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Renter</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Area</th>
                            <th>Event Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hbs-bookings-tbody">
                        <tr><td colspan="9" style="text-align:center;">Loading...</td></tr>
                    </tbody>
                </table>
                
            </div>
        </div>
        
        <!-- Create Internal Block Modal -->
        <div id="hbs-block-modal" class="hbs-modal" style="display:none;">
            <div class="hbs-modal-content">
                <span class="hbs-close-modal">&times;</span>
                <h2>Create Internal Block</h2>
                
                <form id="hbs-block-form">
                    <div class="form-group">
                        <label for="block-area">Area:</label>
                        <select id="block-area" name="area_id" required>
                            <?php $this->render_area_filter_options(); ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="block-date">Date:</label>
                        <input type="date" id="block-date" name="block_date" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="block-start">Start Time:</label>
                            <input type="time" id="block-start" name="start_time" value="00:00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="block-end">End Time:</label>
                            <input type="time" id="block-end" name="end_time" value="23:59" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="block-reason">Reason:</label>
                        <input type="text" id="block-reason" name="reason" placeholder="e.g., Maintenance, Staff Event" required>
                    </div>
                    
                    <button type="submit" class="button button-primary">Create Block</button>
                </form>
            </div>
        </div>
        
        <!-- Create Booking Modal -->
        <div id="hbs-booking-modal" class="hbs-modal" style="display:none;">
            <div class="hbs-modal-content" style="max-width: 600px;">
                <span class="hbs-close-modal" onclick="document.getElementById('hbs-booking-modal').style.display='none';">&times;</span>
                <h2>Create Booking</h2>
                
                <form id="hbs-admin-booking-form">
                    <div class="form-group">
                        <label for="booking-area">Area: <span style="color:red;">*</span></label>
                        <select id="booking-area" name="area_id" required>
                            <option value="">-- Select Area --</option>
                            <?php $this->render_area_filter_options(); ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-date">Date: <span style="color:red;">*</span></label>
                        <input type="date" id="booking-date" name="booking_date" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking-start-time">Start Time: <span style="color:red;">*</span></label>
                            <input type="time" id="booking-start-time" name="start_time" value="09:00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking-duration">Duration (hours): <span style="color:red;">*</span></label>
                            <input type="number" id="booking-duration" name="duration_hours" step="0.5" min="0.5" value="2" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-renter">Renter Name: <span style="color:red;">*</span></label>
                        <input type="text" id="booking-renter" name="renter_name" placeholder="e.g., John Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-email">Email: <span style="color:red;">*</span></label>
                        <input type="email" id="booking-email" name="renter_email" placeholder="john@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-phone">Phone:</label>
                        <input type="tel" id="booking-phone" name="renter_phone" placeholder="555-1234">
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-event-type">Event Type:</label>
                        <input type="text" id="booking-event-type" name="event_type" placeholder="e.g., Wedding, Conference">
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-details">Details:</label>
                        <textarea id="booking-details" name="event_details" rows="3" placeholder="Additional booking details..."></textarea>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="booking-recurring" name="is_recurring" value="1">
                        <label for="booking-recurring">Recurring Booking</label>
                    </div>
                    
                    <div id="booking-recurrence-options" style="display:none; background: #f0f7ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                        <div class="form-group">
                            <label for="booking-recurrence-type">Recurrence Pattern:</label>
                            <select id="booking-recurrence-type" name="recurrence_type">
                                <option value="weekly">Every Week</option>
                                <option value="fortnightly">Every 2 Weeks</option>
                                <option value="monthly">Every Month</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking-recurrence-until">Until Date:</label>
                            <input type="date" id="booking-recurrence-until" name="recurrence_until">
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="booking-auto-approve" name="auto_approve" value="1" checked>
                        <label for="booking-auto-approve">Auto-approve (skip pending review)</label>
                    </div>
                    
                    <button type="submit" class="button button-primary">Create Booking</button>
                    <button type="button" class="button" onclick="document.getElementById('hbs-booking-modal').style.display='none';">Cancel</button>
                </form>
            </div>
        </div>
        
        <!-- Create Booking Modal -->
        <div id="hbs-booking-modal" class="hbs-modal" style="display:none;">
            <div class="hbs-modal-content" style="max-width: 600px;">
                <span class="hbs-close-modal" onclick="document.getElementById('hbs-booking-modal').style.display='none';">×</span>
                <h2>Create Booking</h2>
                <form id="hbs-admin-booking-form">
                    <div class="form-group">
                        <label for="booking-area">Area: <span style="color:red;">*</span></label>
                        <select id="booking-area" required>
                            <?php $this->render_area_filter_options(); ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking-date">Date: <span style="color:red;">*</span></label>
                            <input type="date" id="booking-date" required>
                        </div>
                        <div class="form-group">
                            <label for="booking-start-time">Start Time: <span style="color:red;">*</span></label>
                            <input type="time" id="booking-start-time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-duration">Duration (hours): <span style="color:red;">*</span></label>
                        <input type="number" id="booking-duration" step="0.5" min="0.5" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-renter">Renter Name: <span style="color:red;">*</span></label>
                        <input type="text" id="booking-renter" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-email">Email: <span style="color:red;">*</span></label>
                        <input type="email" id="booking-email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-phone">Phone (optional):</label>
                        <input type="tel" id="booking-phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-event-type">Event Type (optional):</label>
                        <input type="text" id="booking-event-type" placeholder="e.g., Wedding, Conference">
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="booking-recurring" name="is_recurring" value="1">
                        <label for="booking-recurring">Recurring Booking</label>
                    </div>
                    
                    <div id="booking-recurrence-options" style="display:none; background: #f0f7ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                        <div class="form-group">
                            <label for="booking-recurrence-type">Recurrence Pattern:</label>
                            <select id="booking-recurrence-type" name="recurrence_type">
                                <option value="weekly">Every Week</option>
                                <option value="fortnightly">Every 2 Weeks</option>
                                <option value="monthly">Every Month</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking-recurrence-until">Until Date:</label>
                            <input type="date" id="booking-recurrence-until" name="recurrence_until">
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="booking-auto-approve" name="auto_approve" value="1" checked>
                        <label for="booking-auto-approve">Auto-approve (skip pending review)</label>
                    </div>
                    
                    <button type="submit" class="button button-primary">Create Booking</button>
                    <button type="button" class="button" onclick="document.getElementById('hbs-booking-modal').style.display='none';">Cancel</button>
                </form>
            </div>
        </div>
        
        <!-- Edit Booking Modal -->
        <div id="hbs-edit-booking-modal" class="hbs-modal" style="display:none;">
            <div class="hbs-modal-content" style="max-width: 600px;">
                <span class="hbs-close-modal" onclick="document.getElementById('hbs-edit-booking-modal').style.display='none';">&times;</span>
                <h2>Edit Booking</h2>
                <form id="hbs-edit-booking-form">
                    <input type="hidden" id="edit-booking-id" value="">
                    
                    <div class="form-group">
                        <label for="edit-renter-name">Renter Name:</label>
                        <input type="text" id="edit-renter-name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-renter-email">Renter Email:</label>
                        <input type="email" id="edit-renter-email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-renter-phone">Phone (optional):</label>
                        <input type="tel" id="edit-renter-phone" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-start-date">Start Date:</label>
                            <input type="date" id="edit-start-date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-start-time">Start Time:</label>
                            <input type="time" id="edit-start-time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-end-date">End Date:</label>
                            <input type="date" id="edit-end-date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-end-time">End Time:</label>
                            <input type="time" id="edit-end-time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-event-type">Event Type (optional):</label>
                        <input type="text" id="edit-event-type" class="form-control" placeholder="e.g., Wedding, Conference">
                    </div>
                    
                    <button type="submit" class="button button-primary">Update Booking</button>
                    <button type="button" class="button" onclick="document.getElementById('hbs-edit-booking-modal').style.display='none';">Cancel</button>
                </form>
            </div>
        </div>
        
        <style>
            .hbs-admin-wrapper { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .hbs-filters { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
            .filter-group { display: flex; align-items: center; gap: 8px; }
            .filter-group label { font-weight: 600; min-width: 80px; }
            .hbs-filter-select, .hbs-filter-input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            .hbs-actions { margin-bottom: 20px; }
            #hbs-bookings-table { margin-top: 20px; }
            .hbs-booking-status { display: inline-block; padding: 4px 8px; border-radius: 3px; font-weight: 600; font-size: 12px; }
            .hbs-booking-status.pending { background: #fff3cd; color: #856404; }
            .hbs-booking-status.approved { background: #d4edda; color: #155724; }
            .hbs-booking-status.rejected { background: #f8d7da; color: #721c24; }
            .hbs-booking-actions { display: flex; gap: 5px; }
            .hbs-booking-actions button { padding: 4px 8px; font-size: 12px; }
            
            .hbs-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
            .hbs-modal-content { background: white; padding: 30px; border-radius: 5px; max-width: 500px; width: 90%; }
            .hbs-close-modal { float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
            .hbs-modal-content form { margin-top: 20px; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
            .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            .form-row { display: flex; gap: 15px; }
            .form-row .form-group { flex: 1; }
        </style>
        
        <?php
    }
    
    /**
     * Render hall settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Hall Settings & Area Management</h1>
            
            <div class="hbs-settings-wrapper">
                <div class="hbs-settings-tabs">
                    <button class="tab-button active" data-tab="areas">Manage Areas</button>
                    <button class="tab-button" data-tab="default-area">Default Area</button>
                    <button class="tab-button" data-tab="booking-hours">Booking Hours</button>
                </div>
                
                <!-- Areas Management Tab -->
                <div id="areas-tab" class="tab-content active">
                    <h2>Bookable Areas</h2>
                    <p>Edit existing areas or create new ones for your hall.</p>
                    
                    <button id="hbs-add-area-btn" class="button button-primary" style="margin-bottom: 20px;">+ Add New Area</button>
                    
                    <table id="hbs-areas-table" class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>Area Name</th>
                                <th>Description</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hbs-areas-tbody">
                            <?php $this->render_areas_table(); ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Default Area Tab -->
                <div id="default-area-tab" class="tab-content">
                    <h2>Default Area Selection</h2>
                    <p>Choose which area appears by default on the booking form and availability calendar.</p>
                    
                    <div style="max-width: 400px; padding: 20px; background: #f9f9f9; border-radius: 5px; margin-top: 20px;">
                        <form id="hbs-default-area-form">
                            <div class="form-group">
                                <label for="default-area-select" style="font-weight: 600; display: block; margin-bottom: 10px;">Default Area:</label>
                                <select id="default-area-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <?php 
                                    $areas = HBS_Database::get_areas();
                                    $default_id = HBS_Database::get_default_area();
                                    foreach ($areas as $area) {
                                        $selected = $area->id == $default_id ? 'selected' : '';
                                        echo '<option value="' . esc_attr($area->id) . '" ' . esc_attr($selected) . '>' . esc_html($area->name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="button button-primary" style="margin-top: 15px;">Save Default Area</button>
                        </form>
                    </div>
                </div>
                
                <!-- Booking Hours Tab -->
                <div id="booking-hours-tab" class="tab-content">
                    <h2>Booking Hours by Day of Week</h2>
                    <p>Set the hours when bookings are available for each day. You can set different hours for each day or have days closed.</p>
                    
                    <table id="hbs-hours-table" class="wp-list-table widefat striped" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Day</th>
                                <th style="width: 15%;">Open/Closed</th>
                                <th style="width: 20%;">Start Time</th>
                                <th style="width: 20%;">End Time</th>
                                <th style="width: 30%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hbs-hours-tbody">
                            <?php $this->render_booking_hours_table(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Edit Area Modal -->
        <div id="hbs-area-modal" class="hbs-modal" style="display:none;">
            <div class="hbs-modal-content">
                <span class="hbs-close-modal" onclick="document.getElementById('hbs-area-modal').style.display='none';">&times;</span>
                <h2 id="area-modal-title">Add New Area</h2>
                
                <form id="hbs-area-form">
                    <input type="hidden" id="area-id" name="area_id" value="0">
                    
                    <div class="form-group">
                        <label for="area-name">Area Name: <span style="color:red;">*</span></label>
                        <input type="text" id="area-name" name="area_name" placeholder="e.g., Main Hall, Function Room" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="area-description">Description:</label>
                        <textarea id="area-description" name="area_description" rows="3" placeholder="Brief description of the area..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="area-capacity">Capacity (number of people):</label>
                        <input type="number" id="area-capacity" name="area_capacity" min="0" placeholder="e.g., 100">
                    </div>
                    
                    <button type="submit" class="button button-primary">Save Area</button>
                    <button type="button" class="button" onclick="document.getElementById('hbs-area-modal').style.display='none';">Cancel</button>
                </form>
            </div>
        </div>
        
        <style>
            .hbs-settings-wrapper { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .hbs-settings-tabs { display: flex; gap: 10px; border-bottom: 2px solid #e5e5e5; margin-bottom: 20px; }
            .tab-button { background: none; border: none; padding: 10px 15px; font-size: 16px; cursor: pointer; color: #666; border-bottom: 3px solid transparent; margin-bottom: -2px; }
            .tab-button.active { color: #007bff; border-bottom-color: #007bff; }
            .tab-button:hover { color: #007bff; }
            .tab-content { display: none; }
            .tab-content.active { display: block; }
            #hbs-areas-table { margin-top: 20px; }
            .area-action-buttons { display: flex; gap: 5px; }
            .area-action-buttons button { padding: 4px 8px; font-size: 12px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.tab-button').on('click', function() {
                const tabName = $(this).data('tab');
                $('.tab-button').removeClass('active');
                $('.tab-content').removeClass('active');
                $(this).addClass('active');
                $('#' + tabName + '-tab').addClass('active');
            });
            
            // Add area button
            $('#hbs-add-area-btn').on('click', function() {
                $('#area-id').val('0');
                $('#area-modal-title').text('Add New Area');
                $('#hbs-area-form')[0].reset();
                $('#hbs-area-modal').show();
            });
            
            // Edit area button
            $(document).on('click', '.hbs-edit-area-btn', function() {
                const areaId = $(this).data('area-id');
                const areaName = $(this).data('area-name');
                const areaDesc = $(this).data('area-desc');
                const areaCapacity = $(this).data('area-capacity');
                
                $('#area-id').val(areaId);
                $('#area-name').val(areaName);
                $('#area-description').val(areaDesc);
                $('#area-capacity').val(areaCapacity);
                $('#area-modal-title').text('Edit Area');
                $('#hbs-area-modal').show();
            });
            
            // Submit area form
            $('#hbs-area-form').on('submit', function(e) {
                e.preventDefault();
                
                const areaId = $('#area-id').val();
                const areaData = {
                    action: areaId === '0' ? 'hbs_create_area' : 'hbs_update_area',
                    nonce: hbsAdminData.nonce,
                    area_id: areaId,
                    area_name: $('#area-name').val(),
                    area_description: $('#area-description').val(),
                    area_capacity: $('#area-capacity').val()
                };
                
                $.post(hbsAdminData.ajax_url, areaData, function(response) {
                    if (response.success) {
                        alert(areaId === '0' ? 'Area created successfully' : 'Area updated successfully');
                        $('#hbs-area-modal').hide();
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                }, 'json').fail(function() {
                    alert('AJAX Error');
                });
            });
            
            // Delete area button
            $(document).on('click', '.hbs-delete-area-btn', function() {
                if (confirm('Are you sure you want to delete this area? This cannot be undone.')) {
                    const areaId = $(this).data('area-id');
                    const deleteData = {
                        action: 'hbs_delete_area',
                        nonce: hbsAdminData.nonce,
                        area_id: areaId
                    };
                    
                    $.post(hbsAdminData.ajax_url, deleteData, function(response) {
                        if (response.success) {
                            alert('Area deleted successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    }, 'json');
                }
            });
            
            // Close modal
            $('.hbs-close-modal').on('click', function() {
                $('#hbs-area-modal').hide();
            });
            
            // Default area save
            $('#hbs-default-area-form').on('submit', function(e) {
                e.preventDefault();
                const areaId = $('#default-area-select').val();
                const data = {
                    action: 'hbs_save_default_area',
                    nonce: hbsAdminData.nonce,
                    area_id: areaId
                };
                
                $.post(hbsAdminData.ajax_url, data, function(response) {
                    if (response.success) {
                        alert('Default area saved successfully');
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                }, 'json');
            });
            
            // Save booking hours
            $(document).on('click', '.hbs-save-hours-btn', function() {
                const day = $(this).data('day');
                const isOpen = $('[data-day="' + day + '"].hours-open-select').val();
                const startTime = $('[data-day="' + day + '"].hours-start-time').val();
                const endTime = $('[data-day="' + day + '"].hours-end-time').val();
                
                const data = {
                    action: 'hbs_save_booking_hours',
                    nonce: hbsAdminData.nonce,
                    day_of_week: day,
                    is_open: isOpen,
                    start_time: startTime,
                    end_time: endTime
                };
                
                $.post(hbsAdminData.ajax_url, data, function(response) {
                    if (response.success) {
                        alert('Hours saved for this day');
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                }, 'json');
            });
        });
        </script>
        
        <?php
    }
    
    /**
     * Render areas as table rows
     */
    private function render_areas_table() {
        $areas = HBS_Database::get_areas();
        foreach ($areas as $area) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($area->name) . '</strong></td>';
            echo '<td>' . esc_html($area->description) . '</td>';
            echo '<td>' . ($area->capacity ? esc_html($area->capacity) . ' people' : '—') . '</td>';
            echo '<td>';
            echo '<div class="area-action-buttons">';
            echo '<button class="button button-small hbs-edit-area-btn" 
                    data-area-id="' . esc_attr($area->id) . '" 
                    data-area-name="' . esc_attr($area->name) . '" 
                    data-area-desc="' . esc_attr($area->description) . '" 
                    data-area-capacity="' . ($area->capacity ? esc_attr($area->capacity) : '') . '">Edit</button>';
            echo '<button class="button button-small hbs-delete-area-btn" data-area-id="' . esc_attr($area->id) . '" style="background: #dc3545; color: white;">Delete</button>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
    }
    
    /**
     * Render area filter options
     */
    private function render_area_filter_options() {
        $areas = HBS_Database::get_areas();
        foreach ($areas as $area) {
            echo '<option value="' . esc_attr($area->id) . '">' . esc_html($area->name) . '</option>';
        }
    }
    
    /**
     * Render booking hours as table rows
     */
    private function render_booking_hours_table() {
        $hours = HBS_Database::get_booking_hours();
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        foreach ($hours as $day_num => $hour) {
            $day_name = $days[$day_num];
            $is_open = intval($hour->is_open);
            $start_time = $hour->start_time;
            $end_time = $hour->end_time;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($day_name) . '</strong></td>';
            echo '<td>';
            echo '<select class="hours-open-select" data-day="' . esc_attr($day_num) . '" style="padding: 5px; border: 1px solid #ddd;">';
            echo '<option value="0"' . ($is_open == 0 ? ' selected' : '') . '>Closed</option>';
            echo '<option value="1"' . ($is_open == 1 ? ' selected' : '') . '>Open</option>';
            echo '</select>';
            echo '</td>';
            echo '<td><input type="time" class="hours-start-time" data-day="' . esc_attr($day_num) . '" value="' . esc_attr($start_time) . '" style="padding: 5px; border: 1px solid #ddd; width: 100%;"></td>';
            echo '<td><input type="time" class="hours-end-time" data-day="' . esc_attr($day_num) . '" value="' . esc_attr($end_time) . '" style="padding: 5px; border: 1px solid #ddd; width: 100%;"></td>';
            echo '<td><button type="button" class="button button-primary hbs-save-hours-btn" data-day="' . esc_attr($day_num) . '" style="background-color: #0073aa; color: white; padding: 6px 12px; font-weight: 600;">Save</button></td>';
            echo '</tr>';
        }
    }
}
