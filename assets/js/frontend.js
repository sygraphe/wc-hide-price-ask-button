/**
 * WC Hide Price & Ask Button Pro — Frontend JavaScript
 *
 * Handles modal open/close, form validation, and AJAX form submission.
 *
 * @package WCHPAB
 * @since   1.0.0
 */

/* global jQuery, wchpab_front */
(function ($) {
    'use strict';

    var $modal = null;
    var $form = null;
    var $notice = null;
    var $title = null;
    var $productName = null;
    var $productId = null;
    var $submitBtn = null;

    /**
     * Open the modal and populate the product name and ID.
     *
     * @param {string} productName The name of the product.
     * @param {string|number} productId The ID of the product.
     */
    function openModal(productName, productId) {
        $title.text(wchpab_front.i18n.form_title + ' ' + productName);
        $productName.val(productName);
        $productId.val(productId);

        // Reset form state.
        $form[0].reset();
        hideNotice();
        $form.find('.wchpab-field-error').removeClass('wchpab-field-error');
        $submitBtn.prop('disabled', false).text(wchpab_front.i18n.submit_btn);

        $modal.addClass('wchpab-modal-open').show();
        $('body').css('overflow', 'hidden');

        // Focus the first input for accessibility.
        setTimeout(function () {
            $form.find('input[type="text"]').first().focus();
        }, 100);
    }

    /**
     * Close the modal.
     */
    function closeModal() {
        $modal.removeClass('wchpab-modal-open').hide();
        $('body').css('overflow', '');
    }

    /**
     * Show a notice message inside the modal.
     *
     * @param {string} message The message text.
     * @param {string} type    'success' or 'error'.
     */
    function showNotice(message, type) {
        $notice
            .removeClass('wchpab-notice-success wchpab-notice-error')
            .addClass('wchpab-notice-' + type)
            .text(message)
            .show();
    }

    /**
     * Hide the notice message.
     */
    function hideNotice() {
        $notice.hide().removeClass('wchpab-notice-success wchpab-notice-error').text('');
    }

    /**
     * Validate the form and return true if valid.
     *
     * @return {boolean}
     */
    function validateForm() {
        var isValid = true;

        // Clear previous errors.
        $form.find('.wchpab-field-error').removeClass('wchpab-field-error');

        // Name (required).
        var $name = $('#wchpab-name');
        if ($.trim($name.val()) === '') {
            $name.addClass('wchpab-field-error');
            isValid = false;
        }

        // Email (required, valid format).
        var $email = $('#wchpab-email');
        var emailVal = $.trim($email.val());
        if (emailVal === '' || !isValidEmail(emailVal)) {
            $email.addClass('wchpab-field-error');
            isValid = false;
        }

        // Message (required).
        var $message = $('#wchpab-message');
        if ($.trim($message.val()) === '') {
            $message.addClass('wchpab-field-error');
            isValid = false;
        }

        if (!isValid) {
            showNotice(wchpab_front.i18n.fill_required, 'error');
        }

        return isValid;
    }

    /**
     * Simple email validation.
     *
     * @param {string} email Email address to validate.
     * @return {boolean}
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // DOM Ready.
    $(function () {
        $modal = $('#wchpab-modal');
        $form = $('#wchpab-contact-form');
        $notice = $('#wchpab-form-notice');
        $title = $('#wchpab-modal-title');
        $productName = $('#wchpab-product-name');
        $productId = $('#wchpab-product-id');
        $submitBtn = $form.find('.wchpab-submit-btn');

        // Bail if modal does not exist.
        if ($modal.length === 0) {
            return;
        }

        // Open modal when "Ask for product" button is clicked.
        $(document).on('click', '.wchpab-ask-button', function (e) {
            e.preventDefault();
            var name = $(this).data('product-name') || '';
            var id = $(this).data('product-id') || 0;
            openModal(name, id);
        });

        // Handle login button clicks - only for button elements (product page).
        // Anchor tags (archive/category pages) work natively.
        $(document).on('click', 'button.wchpab-login-button', function (e) {
            e.preventDefault();
            var loginUrl = $(this).data('login-url');
            if (loginUrl) {
                window.location.href = loginUrl;
            }
        });

        // Handle link button clicks - only for button elements that link to product page.
        // Anchor tags work natively.
        $(document).on('click', 'button.wchpab-ask-link-button', function (e) {
            e.preventDefault();
            var productUrl = $(this).data('product-url');
            if (productUrl) {
                window.location.href = productUrl;
            }
        });

        // Close modal — close button.
        $modal.on('click', '.wchpab-modal-close', function () {
            closeModal();
        });

        // Close modal — overlay click.
        $modal.on('click', '.wchpab-modal-overlay', function () {
            closeModal();
        });

        // Close modal — Escape key.
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal.hasClass('wchpab-modal-open')) {
                closeModal();
            }
        });

        // Form submission.
        $form.on('submit', function (e) {
            e.preventDefault();
            hideNotice();

            if (!validateForm()) {
                return;
            }

            // Disable button and show loading.
            $submitBtn.prop('disabled', true).text(wchpab_front.i18n.sending);

            $.ajax({
                url: wchpab_front.ajax_url,
                method: 'POST',
                data: {
                    action: 'wchpab_submit_form',
                    nonce: wchpab_front.nonce,
                    wchpab_name: $.trim($('#wchpab-name').val()),
                    wchpab_phone: $.trim($('#wchpab-phone').val()),
                    wchpab_email: $.trim($('#wchpab-email').val()),
                    wchpab_message: $.trim($('#wchpab-message').val()),
                    wchpab_product: $productName.val(),
                    wchpab_product_id: $productId.val()
                },
                success: function (response) {
                    if (response.success) {
                        showNotice(response.data, 'success');
                        $form[0].reset();
                        // Auto-close modal after 3 seconds on success.
                        setTimeout(function () {
                            closeModal();
                        }, 3000);
                    } else {
                        showNotice(response.data || wchpab_front.i18n.error, 'error');
                        $submitBtn.prop('disabled', false).text(wchpab_front.i18n.submit_btn);
                    }
                },
                error: function () {
                    showNotice(wchpab_front.i18n.error, 'error');
                    $submitBtn.prop('disabled', false).text(wchpab_front.i18n.submit_btn);
                }
            });
        });
    });

})(jQuery);
