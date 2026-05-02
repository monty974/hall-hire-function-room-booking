/**
 * Hall Booking System - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Load bookings on page load
        loadBookings();
        
        // Refresh button
        $('#hbs-refresh-btn').on('click', function() {
            loadBookings();
        });
        
        // Filter changes
        $('#admin-area-filter, #admin-status-filter, #admin-month-filter').on('change', function() {
            loadBookings();
        });
        
        // Month range dropdown
        $('#admin-month-range').on('change', function() {
            const value = $(this).val();
            if (value === 'custom') {
                $('#custom-month-group').show();
            } else {
                $('#custom-month-group').hide();
            }
            loadBookings();
        });
        
        // Create booking button
        $('#hbs-create-booking-btn').on('click', function() {
            // Reset form
            $('#hbs-admin-booking-form')[0].reset();
            $('#booking-recurring').prop('checked', false);
            $('#booking-auto-approve').prop('checked', true);
            $('#booking-recurrence-options').hide();
            
            $('#hbs-booking-modal').show();
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            $('#booking-date').attr('min', today);
            $('#booking-recurrence-until').attr('min', today);
        });
        
        // Create internal block button
        $('#hbs-create-block-btn').on('click', function() {
            $('#hbs-block-modal').show();
        });
        
        // Toggle recurrence options
        $('#booking-recurring').on('change', function() {
            if ($(this).is(':checked')) {
                $('#booking-recurrence-options').show();
            } else {
                $('#booking-recurrence-options').hide();
            }
        });
        
        // Close modals
        $('.hbs-close-modal').on('click', function() {
            $('#hbs-block-modal').hide();
            $('#hbs-booking-modal').hide();
        });
        
        // Create block form
        $('#hbs-block-form').on('submit', function(e) {
            e.preventDefault();
            
            const blockData = {
                action: 'hbs_create_internal_block',
                nonce: hbsAdminData.nonce,
                area_id: $('#block-area').val(),
                block_date: $('#block-date').val(),
                start_time: $('#block-start').val(),
                end_time: $('#block-end').val(),
                reason: $('#block-reason').val()
            };
            
            $.post(hbsAdminData.ajax_url, blockData, function(response) {
                if (response.success) {
                    alert('Internal block created successfully');
                    $('#hbs-block-modal').hide();
                    $('#hbs-block-form')[0].reset();
                    loadBookings();
                } else {
                    alert('Error: ' + response.data);
                }
            }, 'json');
        });
        
        // Create booking form
        $('#hbs-admin-booking-form').on('submit', function(e) {
            e.preventDefault();
            
            const bookingData = {
                action: 'hbs_submit_booking_request',
                nonce: hbsAdminData.nonce,
                area_id: $('#booking-area').val(),
                booking_date: $('#booking-date').val(),
                start_time: $('#booking-start-time').val(),
                duration_hours: $('#booking-duration').val(),
                renter_name: $('#booking-renter').val(),
                renter_email: $('#booking-email').val(),
                renter_phone: $('#booking-phone').val(),
                event_type: $('#booking-event-type').val(),
                event_details: $('#booking-details').val(),
                recurrence_type: $('#booking-recurrence-type').val(),
                recurrence_until: $('#booking-recurrence-until').val(),
                auto_approve: $('#booking-auto-approve').is(':checked') ? 1 : 0
            };
            
            // Only add is_recurring if checked
            if ($('#booking-recurring').is(':checked')) {
                bookingData.is_recurring = 1;
            }
            
            $.post(hbsAdminData.ajax_url, bookingData, function(response) {
                if (response.success) {
                    const bookingId = response.data.booking_id;
                    
                    // If auto-approve is checked, approve it immediately
                    if ($('#booking-auto-approve').is(':checked')) {
                        const approveData = {
                            action: 'hbs_approve_booking',
                            nonce: hbsAdminData.nonce,
                            booking_id: bookingId
                        };
                        
                        $.post(hbsAdminData.ajax_url, approveData, function(approveResponse) {
                            if (approveResponse.success) {
                                alert('Booking created and approved successfully');
                            } else {
                                alert('Booking created but auto-approval failed: ' + (approveResponse.data || 'Unknown error'));
                            }
                            $('#hbs-booking-modal').hide();
                            $('#hbs-admin-booking-form')[0].reset();
                            $('#booking-recurrence-options').hide();
                            loadBookings();
                        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Approval error:', textStatus, errorThrown);
                            alert('Error approving booking: ' + textStatus);
                        });
                    } else {
                        alert('Booking created and sent for review');
                        $('#hbs-booking-modal').hide();
                        $('#hbs-admin-booking-form')[0].reset();
                        $('#booking-recurrence-options').hide();
                        loadBookings();
                    }
                } else {
                    alert('Error creating booking: ' + (response.data || 'Unknown error'));
                    console.error('Booking error response:', response);
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown, jqXHR.responseText);
                alert('AJAX Error: ' + textStatus + '\n\n' + jqXHR.responseText);
            });
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(event) {
            const blockModal = $('#hbs-block-modal');
            const bookingModal = $('#hbs-booking-modal');
            if (event.target === blockModal[0]) {
                blockModal.hide();
            }
            if (event.target === bookingModal[0]) {
                bookingModal.hide();
            }
        });
    });
    
    /**
     * Load and display bookings
     */
    function loadBookings() {
        const areaId = $('#admin-area-filter').val();
        const status = $('#admin-status-filter').val();
        const monthRange = $('#admin-month-range').val();
        
        // Calculate months to fetch
        let months = [];
        const today = new Date();
        const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
        
        if (monthRange === 'current') {
            months = [currentMonth];
        } else if (monthRange === 'next3') {
            for (let i = 0; i < 3; i++) {
                const d = new Date(today.getFullYear(), today.getMonth() + i, 1);
                months.push(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'));
            }
        } else if (monthRange === 'next6') {
            for (let i = 0; i < 6; i++) {
                const d = new Date(today.getFullYear(), today.getMonth() + i, 1);
                months.push(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'));
            }
        } else if (monthRange === 'custom') {
            months = [$('#admin-month-filter').val()];
        }
        
        // Fetch all months and combine results
        let allBookings = [];
        let completed = 0;
        
        months.forEach(function(month) {
            const data = {
                action: 'hbs_get_bookings_list',
                nonce: hbsAdminData.nonce,
                area_id: areaId,
                status: status,
                month: month
            };
            
            $.post(hbsAdminData.ajax_url, data, function(response) {
                if (response.success) {
                    allBookings = allBookings.concat(response.data);
                }
                completed++;
                
                // When all months are loaded, render
                if (completed === months.length) {
                    // Sort by date descending
                    allBookings.sort(function(a, b) {
                        const dateCmp = b.start_date.localeCompare(a.start_date);
                        if (dateCmp !== 0) return dateCmp;
                        return b.start_time.localeCompare(a.start_time);
                    });
                    renderBookingsTable(allBookings);
                }
            }, 'json');
        });
    }
    
    /**
     * Render bookings in table
     */
    function renderBookingsTable(bookings) {
        const tbody = $('#hbs-bookings-tbody');
        tbody.empty();
        
        if (bookings.length === 0) {
            tbody.html(
                '<tr><td colspan="9" style="text-align:center;">No bookings found</td></tr>'
            );
            return;
        }
        
        bookings.forEach(function(booking) {
            const endDate = booking.end_date !== booking.start_date ? 
                ' to ' + formatDate(booking.end_date) : '';
            
            const statusClass = 'hbs-booking-status ' + booking.status;
            const statusLabel = booking.status.charAt(0).toUpperCase() + booking.status.slice(1);
            
            // Only treat as internal block if is_internal_block=1 AND no renter_email
            // Regular bookings always have renter_email
            const isInternalBlock = booking.is_internal_block && !booking.renter_email;
            
            // Show internal block designation instead of renter name
            const nameDisplay = isInternalBlock ? 
                '<strong style="color: #d9534f;">🚫 Internal Block</strong>' : 
                booking.renter_name;
            
            // Show dash for email/event type if internal block
            const emailDisplay = isInternalBlock ? '-' : (booking.renter_email || '-');
            const eventDisplay = isInternalBlock ? '-' : (booking.event_type || '-');
            
            const actions = getBookingActions(booking);
            
            const row = $('<tr>')
                .append($('<td>').html(nameDisplay))
                .append($('<td>').text(emailDisplay))
                .append($('<td>').text(formatDate(booking.start_date) + endDate))
                .append($('<td>').text(booking.start_time.substring(0, 5) + ' - ' + booking.end_time.substring(0, 5)))
                .append($('<td>').text(booking.duration_hours + 'h'))
                .append($('<td>').text(getAreaName(booking.area_id)))
                .append($('<td>').text(eventDisplay))
                .append($('<td>').html('<span class="' + statusClass + '">' + statusLabel + '</span>'))
                .append($('<td>').html(actions));
            
            tbody.append(row);
        });
        
        // Attach event handlers to action buttons
        attachActionHandlers();
    }
    
    /**
     * Get area name from ID
     */
    function getAreaName(areaId) {
        // Get area name from the filter dropdown options
        const areaSelect = $('#admin-area-filter');
        const areaOption = areaSelect.find('option[value="' + areaId + '"]');
        
        if (areaOption.length > 0) {
            return areaOption.text();
        }
        
        return 'Area ' + areaId;
    }
    
    /**
     * Get booking action buttons
     */
    function getBookingActions(booking) {
        let actions = '<div class="hbs-booking-actions">';
        
        actions += '<button class="button button-small edit-btn" data-id="' + booking.id + '" style="background:#0073aa; color:white;">Edit</button>';
        
        if (booking.status === 'pending') {
            actions += '<button class="button button-small approve-btn" data-id="' + booking.id + '">Approve</button>';
            actions += '<button class="button button-small reject-btn" data-id="' + booking.id + '">Reject</button>';
        }
        
        actions += '<button class="button button-small delete-btn" data-id="' + booking.id + '" style="background:#dc3545; color:white;">Delete</button>';
        actions += '</div>';
        
        return actions;
    }
    
    /**
     * Attach action button handlers
     */
    function attachActionHandlers() {
        // Edit button
        $('.edit-btn').off('click').on('click', function() {
            const bookingId = $(this).data('id');
            openEditBookingModal(bookingId);
        });
        
        // Approve button
        $('.approve-btn').off('click').on('click', function() {
            const bookingId = $(this).data('id');
            if (confirm('Approve this booking?')) {
                approveBooking(bookingId);
            }
        });
        
        // Reject button
        $('.reject-btn').off('click').on('click', function() {
            const bookingId = $(this).data('id');
            if (confirm('Reject this booking?')) {
                rejectBooking(bookingId);
            }
        });
        
        // Delete button
        $('.delete-btn').off('click').on('click', function() {
            const bookingId = $(this).data('id');
            if (confirm('Delete this booking? This action cannot be undone.')) {
                deleteBooking(bookingId);
            }
        });
    }
    
    /**
     * Approve booking
     */
    function approveBooking(bookingId) {
        const data = {
            action: 'hbs_approve_booking',
            nonce: hbsAdminData.nonce,
            booking_id: bookingId
        };
        
        $.post(hbsAdminData.ajax_url, data, function(response) {
            if (response.success) {
                alert('Booking approved and email sent');
                loadBookings();
            } else {
                alert('Error: ' + response.data);
            }
        }, 'json');
    }
    
    /**
     * Reject booking
     */
    function rejectBooking(bookingId) {
        const data = {
            action: 'hbs_reject_booking',
            nonce: hbsAdminData.nonce,
            booking_id: bookingId
        };
        
        $.post(hbsAdminData.ajax_url, data, function(response) {
            if (response.success) {
                alert('Booking rejected and email sent');
                loadBookings();
            } else {
                alert('Error: ' + response.data);
            }
        }, 'json');
    }
    
    /**
     * Delete booking
     */
    function deleteBooking(bookingId) {
        const data = {
            action: 'hbs_delete_booking',
            nonce: hbsAdminData.nonce,
            booking_id: bookingId
        };
        
        $.post(hbsAdminData.ajax_url, data, function(response) {
            if (response.success) {
                alert('Booking deleted');
                loadBookings();
            } else {
                alert('Error: ' + response.data);
            }
        }, 'json');
    }
    
    /**
     * Format date
     */
    function formatDate(dateStr) {
        // Parse YYYY-MM-DD format safely (avoid timezone issues)
        const [year, month, day] = dateStr.split('-');
        return new Date(year, parseInt(month) - 1, day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    /**
     * Open edit booking modal and populate form
     */
    function openEditBookingModal(bookingId) {
        console.log('openEditBookingModal called with ID:', bookingId);
        
        // Find the booking row to get data
        const $row = $('button.edit-btn[data-id="' + bookingId + '"]').closest('tr');
        
        if ($row.length === 0) {
            console.error('Could not find booking row for ID:', bookingId);
            alert('Error: Could not find booking in table');
            return;
        }
        
        console.log('Found row, extracting data...');
        
        // Get data from row cells
        const cells = $row.find('td');
        const renterName = cells.eq(0).text().trim();
        const renterEmail = cells.eq(1).text().trim();
        const dateCell = cells.eq(2).text().trim();
        const timeCell = cells.eq(3).text().trim();
        const eventType = cells.eq(6).text().trim();
        
        console.log('Extracted: name=' + renterName + ', email=' + renterEmail + ', date=' + dateCell + ', time=' + timeCell);
        
        // Simple date parsing - just split on "to"
        const dateParts = dateCell.split(' to ');
        const startDateStr = dateParts[0].trim();
        const endDateStr = dateParts[1] ? dateParts[1].trim() : startDateStr;
        
        console.log('Date parsing: start=' + startDateStr + ', end=' + endDateStr);
        
        // Parse dates - convert "Apr 28, 2026" to "2026-04-28"
        try {
            const startDate = new Date(startDateStr);
            const endDate = new Date(endDateStr);
            
            if (isNaN(startDate.getTime())) {
                throw new Error('Invalid start date: ' + startDateStr);
            }
            
            const formattedStart = startDate.toISOString().split('T')[0];
            const formattedEnd = endDate.toISOString().split('T')[0];
            
            console.log('Formatted dates: start=' + formattedStart + ', end=' + formattedEnd);
            
            // Parse time
            const timeParts = timeCell.split(' - ');
            const startTime = timeParts[0].trim();
            const endTime = timeParts[1] ? timeParts[1].trim() : '';
            
            console.log('Times: start=' + startTime + ', end=' + endTime);
            
            // Populate form fields
            $('#edit-booking-id').val(bookingId);
            $('#edit-renter-name').val(renterName);
            $('#edit-renter-email').val(renterEmail);
            $('#edit-start-date').val(formattedStart);
            $('#edit-start-time').val(startTime);
            $('#edit-end-date').val(formattedEnd);
            $('#edit-end-time').val(endTime);
            $('#edit-event-type').val(eventType !== '-' ? eventType : '');
            
            console.log('Form fields populated, showing modal...');
            
            // Show the modal
            const modal = $('#hbs-edit-booking-modal');
            if (modal.length === 0) {
                console.error('Edit modal not found in DOM!');
                alert('Error: Edit modal not found');
                return;
            }
            
            modal.show();
            console.log('Modal shown successfully');
            
        } catch (error) {
            console.error('Error opening edit modal:', error);
            alert('Error: ' + error.message);
        }
    }
    
    /**
     * Handle edit booking form submission
     */
    $(document).on('submit', '#hbs-edit-booking-form', function(e) {
        e.preventDefault();
        
        const bookingData = {
            action: 'hbs_update_booking',
            nonce: hbsAdminData.nonce,
            booking_id: $('#edit-booking-id').val(),
            renter_name: $('#edit-renter-name').val(),
            renter_email: $('#edit-renter-email').val(),
            renter_phone: $('#edit-renter-phone').val(),
            start_date: $('#edit-start-date').val(),
            start_time: $('#edit-start-time').val(),
            end_date: $('#edit-end-date').val(),
            end_time: $('#edit-end-time').val(),
            event_type: $('#edit-event-type').val()
        };
        
        console.log('Submitting edit booking:', bookingData);
        
        $.post(hbsAdminData.ajax_url, bookingData, function(response) {
            console.log('Edit response:', response);
            if (response.success) {
                alert('Booking updated successfully');
                $('#hbs-edit-booking-modal').hide();
                loadBookings();
            } else {
                alert('Error: ' + response.data);
            }
        }, 'json');
    });
    
    /**
     * Close edit modal handler
     */
    $(document).on('click', '#hbs-edit-booking-modal .hbs-close-modal', function() {
        $('#hbs-edit-booking-modal').hide();
    });
    
})(jQuery);
