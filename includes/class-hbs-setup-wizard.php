<?php
/**
 * Hall Booking System - Setup Wizard
 */

class HBS_Setup_Wizard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Setup wizard is accessed via admin menu, no hooks needed here
        // The render_wizard method is called by HBS_Admin when the menu item is clicked
    }
    
    /**
     * Public render method for setup wizard
     */
    public function render_wizard() {
        $this->render_setup_page();
    }
    
    /**
     * Render setup wizard page
     */
    public function render_setup_page() {
        // Check if user has admin capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'hall-booking-system'));
        }
        
        ?>
        <div class="wrap">
            <div style="max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; margin-top: 20px;">
                <h1 style="color: #2c3e50; border-bottom: 2px solid #007bff; padding-bottom: 20px;">
                    ✓ Hall Booking System - Setup Complete!
                </h1>
                
                <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 4px; margin: 20px 0;">
                    <h3>Your Hall Booking System is Ready!</h3>
                    <p>Your plugin has been successfully activated and configured with:</p>
                    <ul>
                        <li>✓ Database tables created</li>
                        <li>✓ Main Hall created with 2 areas</li>
                        <li>✓ Admin dashboard configured</li>
                        <li>✓ Email notifications enabled</li>
                    </ul>
                </div>
                
                <h2 style="color: #2c3e50; margin-top: 40px;">Next Steps</h2>
                
                <div style="background: #f0f7ff; border-left: 4px solid #007bff; padding: 20px; border-radius: 4px; margin: 20px 0;">
                    <h3>1. Create Booking Pages</h3>
                    <p>Create new WordPress pages and add these shortcodes:</p>
                    <ul>
                        <li><code>[hbs_booking_form]</code> - Public booking form</li>
                        <li><code>[hbs_availability_calendar]</code> - Availability calendar</li>
                    </ul>
                </div>
                
                <div style="background: #f0f7ff; border-left: 4px solid #007bff; padding: 20px; border-radius: 4px; margin: 20px 0;">
                    <h3>2. Test the System</h3>
                    <ul>
                        <li>Visit your booking page</li>
                        <li>Submit a test booking request</li>
                        <li>Go to "Hall Bookings → Manage Bookings" to approve</li>
                        <li>Check your email for notifications</li>
                    </ul>
                </div>
                
                <div style="background: #f0f7ff; border-left: 4px solid #007bff; padding: 20px; border-radius: 4px; margin: 20px 0;">
                    <h3>3. Customize Areas</h3>
                    <ul>
                        <li>Go to <strong>Hall Bookings → Hall Settings</strong></li>
                        <li>Update area names and descriptions</li>
                        <li>Configure your specific venue details</li>
                    </ul>
                </div>
                
                <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                    <h3>Quick Links</h3>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=hbs-bookings'); ?>" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                            → Manage Bookings
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=hbs-settings'); ?>" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">
                            → Hall Settings
                        </a>
                        <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;">
                            → Create Pages
                        </a>
                    </p>
                </div>
                
                <p style="margin-top: 40px; color: #666; text-align: center;">
                    For detailed documentation, see <strong>README.md</strong> and <strong>DEPLOYMENT.md</strong> in the plugin folder.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Step 1: Welcome
     */
    private function render_step_welcome() {
        ?>
        <div class="hbs-setup-step">
            <h2>Welcome to Hall Booking System</h2>
            <p>This wizard will help you set up your hall booking system in just a few minutes. You'll configure:</p>
            
            <div class="setup-checklist">
                <h3>Setup Steps:</h3>
                <ul>
                    <li><strong>Step 2:</strong> Configure your hall(s)</li>
                    <li><strong>Step 3:</strong> Set up bookable areas</li>
                    <li><strong>Step 4:</strong> Finalize and test</li>
                </ul>
            </div>
            
            <p>The system is pre-configured with:</p>
            <ul>
                <li>✓ One main hall</li>
                <li>✓ Two bookable areas (Area 1 and Area 2)</li>
                <li>✓ Email notifications</li>
                <li>✓ Manual approval workflow</li>
            </ul>
            
            <p>You can customize these settings anytime after setup is complete.</p>
            
            <div class="setup-buttons">
                <a href="<?php echo admin_url('admin.php?page=hbs-setup&step=2'); ?>" class="button button-primary button-next">Start Setup →</a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Step 2: Halls Configuration
     */
    private function render_step_halls() {
        global $wpdb;
        $hall = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}hbs_halls LIMIT 1");
        
        ?>
        <div class="hbs-setup-step">
            <h2>Configure Your Hall</h2>
            <p>Your main hall has been created. Review and update the details below:</p>
            
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="hbs_setup_update_hall">
                <?php wp_nonce_field('hbs_setup_nonce'); ?>
                
                <div class="setup-form-group">
                    <label for="hall-name">Hall Name:</label>
                    <input type="text" id="hall-name" name="hall_name" value="<?php echo $hall ? esc_attr($hall->name) : 'Main Hall'; ?>" placeholder="e.g., Main Hall, Community Center" required>
                </div>
                
                <div class="setup-form-group">
                    <label for="hall-description">Description:</label>
                    <textarea id="hall-description" name="hall_description" rows="3" placeholder="Describe your hall..."><?php echo $hall ? esc_textarea($hall->description) : ''; ?></textarea>
                </div>
                
                <div class="setup-buttons">
                    <a href="<?php echo admin_url('admin.php?page=hbs-setup&step=1'); ?>" class="button button-skip">← Back</a>
                    <button type="submit" class="button button-primary button-next">Next: Areas →</button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Step 3: Areas Configuration
     */
    private function render_step_areas() {
        global $wpdb;
        $areas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hbs_areas LIMIT 2");
        
        ?>
        <div class="hbs-setup-step">
            <h2>Configure Bookable Areas</h2>
            <p>Set up the two areas within your hall. Each area will have its own availability calendar and booking management.</p>
            
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="hbs_setup_update_areas">
                <?php wp_nonce_field('hbs_setup_nonce'); ?>
                
                <?php for ($i = 1; $i <= 2; $i++): ?>
                    <?php $area = isset($areas[$i-1]) ? $areas[$i-1] : null; ?>
                    <div class="area-item">
                        <strong>Area <?php echo $i; ?></strong>
                        
                        <label>Area Name:</label>
                        <input type="text" name="area_name_<?php echo $i; ?>" value="<?php echo $area ? esc_attr($area->name) : 'Area ' . $i; ?>" placeholder="e.g., Main Room, Function Room" required>
                        
                        <label style="margin-top: 10px; display: block;">Description:</label>
                        <input type="text" name="area_description_<?php echo $i; ?>" value="<?php echo $area ? esc_attr($area->description) : ''; ?>" placeholder="Brief description...">
                        
                        <label style="margin-top: 10px; display: block;">Capacity (optional):</label>
                        <input type="number" name="area_capacity_<?php echo $i; ?>" value="<?php echo $area ? esc_attr($area->capacity) : ''; ?>" placeholder="e.g., 100">
                    </div>
                <?php endfor; ?>
                
                <div class="setup-buttons">
                    <a href="<?php echo admin_url('admin.php?page=hbs-setup&step=2'); ?>" class="button button-skip">← Back</a>
                    <button type="submit" class="button button-primary button-next">Next: Complete →</button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Step 4: Complete
     */
    private function render_step_complete() {
        ?>
        <div class="hbs-setup-step">
            <div class="success-message">
                <h3>✓ Setup Complete!</h3>
                <p>Your Hall Booking System is ready to use. Here's what's been configured:</p>
                <ul>
                    <li>✓ Database tables created</li>
                    <li>✓ Hall and areas configured</li>
                    <li>✓ Email notifications ready</li>
                    <li>✓ Admin interface active</li>
                </ul>
            </div>
            
            <h2>Next Steps</h2>
            
            <div class="setup-checklist">
                <h3>1. Add Booking Pages</h3>
                <p>Create new pages and add these shortcodes:</p>
                <ul>
                    <li><code>[hbs_booking_form]</code> - Public booking form</li>
                    <li><code>[hbs_availability_calendar]</code> - Calendar view</li>
                </ul>
            </div>
            
            <div class="setup-checklist">
                <h3>2. Test the System</h3>
                <ul>
                    <li>Visit your booking page and submit a test booking request</li>
                    <li>Check your admin email for the pending notification</li>
                    <li>Go to Hall Bookings → Manage Bookings and approve the test booking</li>
                    <li>Verify the renter receives the approval email</li>
                </ul>
            </div>
            
            <div class="setup-checklist">
                <h3>3. Customize (Optional)</h3>
                <ul>
                    <li>Adjust area names and descriptions at Hall Bookings → Hall Settings</li>
                    <li>Create internal blocks for maintenance dates</li>
                    <li>Customize colors and styling to match your site</li>
                </ul>
            </div>
            
            <div class="success-links">
                <a href="<?php echo admin_url('admin.php?page=hbs-bookings'); ?>">→ Manage Bookings</a>
                <a href="<?php echo admin_url('admin.php?page=hbs-settings'); ?>">→ Hall Settings</a>
                <a href="<?php echo admin_url('edit.php?post_type=page'); ?>">→ Create Pages</a>
            </div>
            
            <div class="setup-buttons" style="margin-top: 40px;">
                <form method="post" style="width: 100%;">
                    <?php wp_nonce_field('hbs_setup_complete'); ?>
                    <input type="hidden" name="action" value="hbs_setup_complete">
                    <button type="submit" class="button button-primary" style="width: 100%; padding: 15px;">Mark Setup Complete & Close Wizard</button>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialize setup wizard
HBS_Setup_Wizard::get_instance();
