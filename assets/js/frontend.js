/**
 * Hall Booking System - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Form state
        const formState = {
            bookingDate: null,
            startTime: '09:00',
            durationHours: 2,
            areaId: 1,
            isRecurring: false
        };
        
        // Toggle recurring options
        $('#booking-recurring').on('change', function() {
            formState.isRecurring = $(this).is(':checked');
            $('#recurring-options').slideToggle();
        });
        
        // Duration presets
        $('.duration-btn').on('click', function(e) {
            e.preventDefault();
            const hours = $(this).data('hours');
            $('#booking-duration').val(hours);
            formState.durationHours = hours;
        });
        
        // Update duration
        $('#booking-duration').on('change', function() {
            formState.durationHours = parseFloat($(this).val());
        });
        
        // Update start time and check against operating hours
        $('#booking-start-time').on('change', function() {
            formState.startTime = $(this).val();
            checkOperatingHours();
        });
        
        // Update area and reset hours warning
        $('#booking-area').on('change', function() {
            formState.areaId = parseInt($(this).val());
            checkOperatingHours();
        });
        
        // Update booking date and check hours
        $('#booking-date').on('change', function() {
            formState.bookingDate = $(this).val();
            checkOperatingHours();
        });
        
        // Check if booking time is within operating hours
        function checkOperatingHours() {
            if (!formState.bookingDate || !formState.startTime) {
                return;
            }
            
            const selectedDate = new Date(formState.bookingDate);
            const dayOfWeek = selectedDate.getDay(); // 0=Sunday, 6=Saturday
            
            // Get stored operating hours from data attribute (set by server)
            const hoursData = window.hbsBookingHours || {};
            const dayHours = hoursData[dayOfWeek];
            
            if (!dayHours || !dayHours.is_open) {
                showHoursWarning('This day is closed for bookings', 'error');
                return;
            }
            
            // Compare times and account for duration
            const bookingTime = formState.startTime;
            const duration = parseFloat(formState.durationHours || 2);
            const openTime = dayHours.start_time.substring(0, 5);
            const closeTime = dayHours.end_time.substring(0, 5);
            
            // Calculate end time
            const [startHour, startMin] = bookingTime.split(':').map(Number);
            let endHour = startHour + Math.floor(duration);
            let endMin = startMin + ((duration % 1) * 60);
            
            if (endMin >= 60) {
                endHour += 1;
                endMin -= 60;
            }
            
            const endTime = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0');
            
            // Check if start time is before opening
            if (bookingTime < openTime) {
                showHoursWarning('⚠️ Booking starts before opening (' + openTime + ')', 'warning');
                return;
            }
            
            // Check if end time exceeds closing
            if (endTime > closeTime) {
                showHoursWarning('⚠️ Booking duration exceeds closing time. Ends at ' + endTime + ' but close at ' + closeTime, 'warning');
                return;
            }
            
            // All good
            hideHoursWarning();
        }
        
        function showHoursWarning(message, type) {
            let $warning = $('#hbs-hours-warning');
            if (!$warning.length) {
                $warning = $('<div id="hbs-hours-warning" style="margin: 15px 0; padding: 12px; border-radius: 4px; border-left: 4px solid;"></div>');
                $('#hbs-booking-form').prepend($warning);
            }
            
            $warning.text(message)
                .removeClass('warning error')
                .addClass(type)
                .css({
                    backgroundColor: type === 'warning' ? '#fff3cd' : '#f8d7da',
                    borderColor: type === 'warning' ? '#ffc107' : '#f5c6cb',
                    color: type === 'warning' ? '#856404' : '#721c24'
                })
                .show();
        }
        
        function hideHoursWarning() {
            $('#hbs-hours-warning').hide();
        }
        
        // Submit booking form
        $('#hbs-booking-form').on('submit', function(e) {
            e.preventDefault();
            
            // Validation
            if (!$('#renter-name').val() || !$('#renter-email').val()) {
                showFormStatus('Please fill in all required fields', 'error');
                return;
            }
            
            if (!formState.bookingDate) {
                showFormStatus('Please select a booking date', 'error');
                return;
            }
            
            // Check if date is in the past
            const selectedDate = new Date(formState.bookingDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                showFormStatus('Please select a future date', 'error');
                return;
            }
            
            // Disable submit button
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Submitting...');
            
            // Prepare data
            const bookingData = {
                action: 'hbs_submit_booking_request',
                nonce: hbsData.nonce,
                area_id: formState.areaId,
                booking_date: formState.bookingDate,
                start_time: formState.startTime,
                duration_hours: formState.durationHours,
                renter_name: $('#renter-name').val(),
                renter_email: $('#renter-email').val(),
                renter_phone: $('#renter-phone').val(),
                event_type: $('#event-type').val(),
                event_details: $('#event-details').val(),
                recurrence_type: $('#recurrence-type').val(),
                recurrence_until: $('#recurrence-until').val()
            };
            
            // Only add is_recurring if it's checked
            if (formState.isRecurring) {
                bookingData.is_recurring = 1;
            }
            
            // Submit via AJAX
            $.post(hbsData.ajax_url, bookingData, function(response) {
                $submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showFormStatus(response.data.message, 'success');
                    $('#hbs-booking-form')[0].reset();
                    $('#recurring-options').hide();
                    $('#hbs-success-message').slideDown();
                    
                    // Scroll to success message
                    $('html, body').animate({
                        scrollTop: $('#hbs-success-message').offset().top - 100
                    }, 500);
                    
                    // Reset form state
                    formState.isRecurring = false;
                } else {
                    showFormStatus(response.data, 'error');
                }
            }, 'json').fail(function() {
                $submitBtn.prop('disabled', false).text(originalText);
                showFormStatus('An error occurred. Please try again.', 'error');
            });
        });
        
        // Show form status message
        function showFormStatus(message, type) {
            const $status = $('.form-status');
            $status.removeClass('success error').addClass(type).text(message).slideDown();
            
            if (type === 'error') {
                setTimeout(() => $status.slideUp(), 5000);
            }
        }
        
        // Calendar functionality
        if ($('#hbs-calendar').length) {
            initializeCalendar();
        }
        
        // Mini calendar for booking form (independent of main calendar)
        if ($('#hbs-mini-calendar').length) {
            initializeBookingMiniCalendar();
        }
    });
    
    /**
     * Initialize availability calendar
     */
    function initializeCalendar() {
        let currentMonth = new Date().toISOString().slice(0, 7);
        
        // FIRST: Set calendar dropdown to default area
        const calendarDropdown = $('#calendar-area-select');
        const defaultArea = parseInt(hbsData.defaultAreaId || 1);
        if (calendarDropdown.length) {
            calendarDropdown.val(defaultArea);
        }
        
        // THEN: Get area_id from the now-correct dropdown
        let areaId = parseInt(calendarDropdown.val() || defaultArea);
        
        console.log('Calendar initialized with area_id:', areaId, 'default:', hbsData.defaultAreaId);
        
        // Set default area in booking form dropdown if it exists
        const bookingAreaSelect = $('#booking-area');
        if (bookingAreaSelect.length) {
            bookingAreaSelect.val(defaultArea);
        }
        
        function loadCalendar(month, useAreaId = null) {
            // Use specified area ID or fall back to current areaId
            const currentAreaId = useAreaId !== null ? useAreaId : areaId;
            
            console.log('Loading calendar for area:', currentAreaId, 'month:', month);
            
            $.ajax({
                url: hbsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'hbs_get_availability',
                    area_id: currentAreaId,
                    month: month,
                    nonce: hbsData.nonce
                },
                success: function(response) {
                    console.log('Calendar response received:', response);
                    if (response.success) {
                        console.log('Calendar data keys:', Object.keys(response.data).slice(0, 5));
                        renderCalendar(response.data, month);
                    } else {
                        console.error('Calendar load error:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Calendar AJAX error:', status, error, xhr.responseText);
                }
            });
        }
        
        // Sync calendar when area selection changes
        $('#booking-area').on('change', function() {
            areaId = parseInt($(this).val());
            loadCalendar(currentMonth, areaId);
        });
        
        // Also handle calendar area selector on separate page
        $('#calendar-area-select').on('change', function() {
            areaId = parseInt($(this).val());
            loadCalendar(currentMonth, areaId);
        });
        
        function renderCalendar(availability, month) {
            const monthDate = new Date(month + '-01');
            const monthName = monthDate.toLocaleDateString('en-US', { 
                month: 'long', 
                year: 'numeric' 
            });
            $('#hbs-current-month').text(monthName);
            
            const calendarDiv = $('#hbs-calendar');
            calendarDiv.empty();
            
            // Add day headers
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(day => {
                calendarDiv.append('<div class="hbs-calendar-day header">' + day + '</div>');
            });
            
            // Get first day of month and number of days
            const firstDay = monthDate.getDay();
            const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
            const prevMonthDays = new Date(monthDate.getFullYear(), monthDate.getMonth(), 0).getDate();
            
            // Add previous month's days
            for (let i = firstDay - 1; i >= 0; i--) {
                const day = prevMonthDays - i;
                calendarDiv.append('<div class="hbs-calendar-day other-month">' + day + '</div>');
            }
            
            // Add current month's days
            const todayStr = new Date().toISOString().split('T')[0];
            
            let bookedDetails = ''; // Store booking details to show below calendar
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = month + '-' + String(day).padStart(2, '0');
                const data = availability[dateStr] || { bookings: [] };
                
                console.log('Calendar day ' + dateStr + ' has ' + (data.bookings ? data.bookings.length : 0) + ' bookings');
                
                let classes = 'hbs-calendar-day';
                let hasInternalBlock = false;
                let hasBooking = false;
                
                if (data.bookings && data.bookings.length > 0) {
                    for (let booking of data.bookings) {
                        console.log('  Booking:', booking);
                        if (booking.is_internal) {
                            hasInternalBlock = true;
                        } else {
                            hasBooking = true;
                            // Add to details list
                            bookedDetails += '<div style="margin: 10px 0; padding: 10px; background: #f0f7ff; border-left: 3px solid #007bff; border-radius: 3px;">';
                            bookedDetails += '<strong>' + dateStr + '</strong><br>';
                            bookedDetails += 'Time: ' + booking.start_time.substring(0, 5) + ' - ' + booking.end_time.substring(0, 5);
                            bookedDetails += '</div>';
                        }
                    }
                }
                
                // Determine status
                if (data.is_closed) {
                    classes += ' closed';
                } else if (data.is_fully_booked) {
                    classes += ' fully-booked';
                } else if (hasInternalBlock) {
                    classes += ' blocked';
                } else if (hasBooking) {
                    classes += ' booked';
                } else {
                    classes += ' available';
                }
                
                if (dateStr === todayStr) {
                    classes += ' today';
                }
                
                const dayDiv = $('<div class="' + classes + '" data-date="' + dateStr + '">' + day + '</div>');
                
                // Add click handler for available dates
                if (classes.includes('available')) {
                    dayDiv.css('cursor', 'pointer').on('click', function() {
                        $('#booking-date').val(dateStr).change();
                        $('html, body').animate({
                            scrollTop: $('#hbs-booking-form').offset().top - 100
                        }, 500);
                        $('#booking-date').focus();
                    });
                }
                
                calendarDiv.append(dayDiv);
            }
            
            // Show booking details below calendar
            // First remove any old details (but not the legend)
            $('#hbs-calendar').nextUntil('.hbs-legend').remove();
            
            if (bookedDetails) {
                const detailsHTML = '<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 4px;"><h3 style="margin-top: 0; color: #2c3e50;">Limited Availability Slots</h3>' + bookedDetails + '</div>';
                $('#hbs-calendar').after(detailsHTML);
            }
            
            // Add next month's days
            const totalCells = calendarDiv.children().length - 7; // Subtract header row
            const remainingCells = 42 - totalCells; // 6 rows x 7 days
            for (let i = 1; i <= remainingCells; i++) {
                calendarDiv.append('<div class="hbs-calendar-day other-month">' + i + '</div>');
            }
        }
        
        $('#hbs-prev-month').on('click', function() {
            const date = new Date(currentMonth + '-01');
            date.setMonth(date.getMonth() - 1);
            currentMonth = date.toISOString().slice(0, 7);
            loadCalendar(currentMonth);
        });
        
        $('#hbs-next-month').on('click', function() {
            const date = new Date(currentMonth + '-01');
            date.setMonth(date.getMonth() + 1);
            currentMonth = date.toISOString().slice(0, 7);
            loadCalendar(currentMonth);
        });
        
        // Initial load
        loadCalendar(currentMonth);
    }
    
    /**
     * Initialize mini calendar and available times for booking form
     */
    function initializeBookingMiniCalendar() {
        const defaultArea = parseInt(hbsData.defaultAreaId || 1);
        let currentMonth = new Date().toISOString().slice(0, 7);
        let selectedDate = null;
        
        // Set booking area to default
        $('#booking-area').val(defaultArea);
        
        function renderMiniCalendar() {
            // Get availability data for current month
            $.post(hbsData.ajax_url, {
                action: 'hbs_get_availability',
                area_id: defaultArea,
                month: currentMonth,
                nonce: hbsData.nonce
            }, function(response) {
                if (!response.success) {
                    console.error('Failed to fetch availability:', response);
                    return;
                }
                
                const availability = response.data;
                const monthDate = new Date(currentMonth + '-01');
                const year = monthDate.getFullYear();
                const month = monthDate.getMonth();
                
                // Get calendar info
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const daysInPrevMonth = new Date(year, month, 0).getDate();
                
                let html = '';
                const today = new Date();
                const todayStr = today.toISOString().split('T')[0];
                
                // Month/Year selector dropdown
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
                
                // Generate month options (next 24 months)
                let monthOptions = '';
                for (let i = 0; i < 24; i++) {
                    const d = new Date(today.getFullYear(), today.getMonth() + i, 1);
                    const yearMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                    const displayName = monthNames[d.getMonth()] + ' ' + d.getFullYear();
                    const selected = yearMonth === currentMonth ? ' selected' : '';
                    monthOptions += '<option value="' + yearMonth + '"' + selected + '>' + displayName + '</option>';
                }
                
                html += '<div style="margin-bottom: 20px; text-align: center;">';
                html += '<select class="hbs-month-picker" style="padding: 8px 12px; font-size: 15px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; font-weight: 500;">';
                html += monthOptions;
                html += '</select>';
                html += '</div>';
                
                // Also keep Prev/Next buttons for quick navigation
                html += '<div style="margin-bottom: 15px; text-align: center;">';
                html += '<button type="button" class="hbs-prev-month" style="background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px; margin-right: 8px;">← Prev</button>';
                html += '<button type="button" class="hbs-next-month" style="background: #6c757d; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">Next →</button>';
                html += '</div>';
                
                // Days of week header - NOW part of the same grid as calendar days!
                const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                html += '<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-bottom: 15px;">';
                dayHeaders.forEach(day => {
                    html += '<div style="text-align: center; font-weight: bold; padding: 8px; font-size: 13px; background: #f0f0f0; border-radius: 3px;">' + day + '</div>';
                });
                html += '</div>';
                
                // Calendar grid - SEPARATE from day headers, but same structure
                html += '<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">';
                
                // Previous month's days
                for (let i = firstDay - 1; i >= 0; i--) {
                    html += '<div style="padding: 8px; text-align: center; background: #f0f0f0; color: #999; border-radius: 4px; cursor: default;">' + (daysInPrevMonth - i) + '</div>';
                }
                
                // This month's days
                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = currentMonth + '-' + String(day).padStart(2, '0');
                    const dayData = availability[dateStr];
                    
                    let dayClass = 'hbs-mini-calendar-day';
                    let style = 'padding: 8px; text-align: center; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;';
                    let bgColor = '#d4edda'; // green - available
                    
                    if (dateStr < todayStr) {
                        // Past date
                        bgColor = '#f0f0f0';
                        style += 'cursor: default; color: #999;';
                    } else if (dayData) {
                        if (dayData.is_closed) {
                            bgColor = '#e2e3e5'; // gray - closed
                            style += 'cursor: default; opacity: 0.6;';
                        } else if (dayData.is_fully_booked) {
                            bgColor = '#f8d7da'; // red - fully booked
                            style += 'cursor: default;';
                        } else if (dayData.bookings && dayData.bookings.length > 0) {
                            bgColor = '#fff3cd'; // yellow - partially booked
                        }
                        // else: green is already set
                    }
                    
                    style += 'background: ' + bgColor + ';';
                    
                    // Add selection styling
                    if (dateStr === selectedDate) {
                        style += 'border: 3px solid #007bff; font-weight: bold;';
                    }
                    
                    html += '<div class="hbs-mini-cal-day" data-date="' + dateStr + '" style="' + style + '">' + day + '</div>';
                }
                
                // Next month's days
                const totalCells = firstDay + daysInMonth;
                const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
                for (let i = 1; i <= remainingCells; i++) {
                    html += '<div style="padding: 8px; text-align: center; background: #f0f0f0; color: #999; border-radius: 4px; cursor: default;">' + i + '</div>';
                }
                
                html += '</div>';
                
                $('#hbs-mini-calendar').html(html);
                
                // Attach click handlers
                $('#hbs-mini-calendar').find('.hbs-mini-cal-day').on('click', function() {
                    const dateStr = $(this).data('date');
                    const dayData = availability[dateStr];
                    
                    // Can't click if closed, fully booked, or past date
                    if (!dayData || dayData.is_closed || dayData.is_fully_booked || dateStr < todayStr) {
                        return;
                    }
                    
                    // Update selection
                    $('#hbs-mini-calendar').find('[data-date]').css('border', '1px solid #ddd').css('font-weight', '600');
                    $(this).css('border', '3px solid #007bff').css('font-weight', 'bold');
                    
                    selectedDate = dateStr;
                    $('#booking-date').val(dateStr).change(); // Trigger change event to update formState
                    
                    // Show available times
                    showAvailableTimes(dateStr, defaultArea, dayData);
                });
                
                // Month picker dropdown handler
                $('#hbs-mini-calendar').find('.hbs-month-picker').on('change', function() {
                    currentMonth = $(this).val();
                    renderMiniCalendar();
                });
                
                // Month navigation handlers
                $('#hbs-mini-calendar').find('.hbs-prev-month').on('click', function(e) {
                    e.preventDefault();
                    const date = new Date(currentMonth + '-01');
                    date.setMonth(date.getMonth() - 1);
                    currentMonth = date.toISOString().slice(0, 7);
                    renderMiniCalendar();
                });
                
                $('#hbs-mini-calendar').find('.hbs-next-month').on('click', function(e) {
                    e.preventDefault();
                    const date = new Date(currentMonth + '-01');
                    date.setMonth(date.getMonth() + 1);
                    currentMonth = date.toISOString().slice(0, 7);
                    renderMiniCalendar();
                });
            }, 'json');
        }
        
        function showAvailableTimes(dateStr, areaId, dayData) {
            const dayOfWeek = new Date(dateStr).getDay();
            const dayHours = window.hbsBookingHours[dayOfWeek];
            
            if (!dayHours || !dayHours.is_open) {
                $('#hbs-available-times').hide();
                return;
            }
            
            const bookings = dayData.bookings || [];
            const openTime = dayHours.start_time.substring(0, 5);
            const closeTime = dayHours.end_time.substring(0, 5);
            
            // Generate time slots
            const slots = [];
            const [openHour, openMin] = openTime.split(':').map(Number);
            const [closeHour, closeMin] = closeTime.split(':').map(Number);
            
            let hour = openHour;
            let min = openMin;
            
            while (hour < closeHour || (hour === closeHour && min < closeMin)) {
                const timeStr = String(hour).padStart(2, '0') + ':' + String(min).padStart(2, '0');
                
                // Check if booked
                let isAvailable = true;
                for (let booking of bookings) {
                    const bStart = booking.start_time.substring(0, 5);
                    const bEnd = booking.end_time.substring(0, 5);
                    if (timeStr >= bStart && timeStr < bEnd) {
                        isAvailable = false;
                        break;
                    }
                }
                
                slots.push({ time: timeStr, available: isAvailable });
                
                min += 30;
                if (min >= 60) {
                    min = 0;
                    hour += 1;
                }
            }
            
            // Render time slots
            let slotsHtml = '';
            slots.forEach(slot => {
                if (slot.available) {
                    slotsHtml += '<button type="button" class="hbs-time-slot" data-time="' + slot.time + '" style="padding: 8px 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">' + slot.time + '</button>';
                } else {
                    slotsHtml += '<button type="button" style="padding: 8px 12px; background: #ccc; color: #666; border: none; border-radius: 4px; cursor: not-allowed; font-weight: 600; font-size: 12px; opacity: 0.5;" disabled>' + slot.time + '</button>';
                }
            });
            
            $('#selected-date-display').text(dateStr);
            $('#hbs-time-slots').html(slotsHtml);
            $('#hbs-available-times').show();
            
            // Attach handlers to time slots
            $('#hbs-time-slots').find('button:not(:disabled)').on('click', function(e) {
                e.preventDefault();
                const time = $(this).data('time');
                $('#booking-start-time').val(time).change(); // Trigger change to update formState
                checkOperatingHours();
            });
        }
        
        // Initial render
        renderMiniCalendar();
        
        // Handle area change
        $('#booking-area').on('change', function() {
            const newAreaId = parseInt($(this).val());
            if (newAreaId !== defaultArea) {
                // Re-render with new area
                $.post(hbsData.ajax_url, {
                    action: 'hbs_get_availability',
                    area_id: newAreaId,
                    month: currentMonth,
                    nonce: hbsData.nonce
                }, function(response) {
                    if (response.success) {
                        $('#hbs-mini-calendar').html('<p>Loading...</p>');
                        renderMiniCalendar();
                    }
                }, 'json');
            }
        });
    }
    
})(jQuery);
