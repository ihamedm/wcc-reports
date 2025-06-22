jQuery(document).ready(function($) {
    'use strict';

    // Handle Generate Report button clicks
    $(document).on('click', '.generate-report-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const reportId = $button.data('report');
        const $reportValue = $('#' + reportId);
        const action = $button.data('action');
        
        // Get parameter input value
        const $parameterInput = $('#params-' + reportId);
        const userParameters = $parameterInput.length ? $parameterInput.val().trim() : '';
        
        // Don't allow multiple clicks
        if ($button.hasClass('loading')) {
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.find('.button-text').text(wccreports_ajax.loading_text);
        $button.find('.spinner').show();
        
        // Clear previous results
        $reportValue.html('<span class="loading-text">' + wccreports_ajax.loading_text + '</span>');
        
        // Make AJAX request
        $.ajax({
            url: wccreports_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                report_id: reportId,
                user_parameters: userParameters,
                nonce: wccreports_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $reportValue.html('<span class="report-number">' + response.data.count.toLocaleString() + '</span>');
                    showToast('گزارش با موفقیت تولید شد!', 'success');
                } else {
                    $reportValue.html('<span class="error-text">' + wccreports_ajax.error_text + '</span>');
                    showToast('خطا در تولید گزارش: ' + (response.data.message || 'خطای نامشخص'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $reportValue.html('<span class="error-text">' + wccreports_ajax.error_text + '</span>');
                showToast('خطای AJAX: ' + error, 'error');
            },
            complete: function() {
                // Reset button state
                $button.removeClass('loading');
                $button.find('.button-text').text('تولید گزارش');
                $button.find('.spinner').hide();
            }
        });
    });

    // Handle Refresh Cache button clicks
    $(document).on('click', '.refresh-cache-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const reportId = $button.data('report');
        const $reportValue = $('#' + reportId);
        
        // Get parameter input value
        const $parameterInput = $('#params-' + reportId);
        const userParameters = $parameterInput.length ? $parameterInput.val().trim() : '';
        
        // Don't allow multiple clicks
        if ($button.hasClass('loading')) {
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.text(wccreports_ajax.loading_text);
        
        // Get the action from the generate button in the same card
        const action = $button.closest('.report-actions').find('.generate-report-btn').data('action');
        
        $.ajax({
            url: wccreports_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                report_id: reportId,
                user_parameters: userParameters,
                nonce: wccreports_ajax.nonce,
                refresh_cache: true
            },
            success: function(response) {
                if (response.success) {
                    $reportValue.html('<span class="report-number">' + response.data.count.toLocaleString() + '</span>');
                    showToast('کش با موفقیت بروزرسانی شد!', 'success');
                } else {
                    showToast('خطا در بروزرسانی کش: ' + (response.data.message || 'خطای نامشخص'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('خطای AJAX: ' + error, 'error');
            },
            complete: function() {
                // Reset button state
                $button.removeClass('loading');
                $button.text('بروزرسانی کش');
            }
        });
    });

    // Toast notification function using Toastify
    function showToast(message, type = 'info') {
        const toastConfig = {
            text: message,
            duration: type === 'error' ? 5000 : 3000,
            gravity: 'bottom',
            position: 'left',
            stopOnFocus: true,
        };

        Toastify(toastConfig).showToast();
    }


    // Handle Export to XLS button clicks
    $(document).on('click', '.export-users-btn', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const reportId = $button.data('report-id');
        const reportName = $button.data('report-name');
        
        // Don't allow multiple clicks
        if ($button.hasClass('loading')) {
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.text(wccreports_ajax.loading_text);
        
        // Make AJAX request
        $.ajax({
            url: wccreports_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wccreports_export_' + reportId,
                report_id: reportId,
                nonce: wccreports_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = response.data.file_url;
                    link.download = reportName + '_' + new Date().toISOString().slice(0, 10) + '.xls';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast('خروجی با موفقیت تکمیل شد! فایل دانلود شد.', 'success');
                } else {
                    showToast('خطا در خروجی: ' + (response.data.message || 'خطای نامشخص'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('خطای خروجی: ' + error, 'error');
            },
            complete: function() {
                // Reset button state
                $button.removeClass('loading');
                $button.text('اکسل');
            }
        });
    });


});