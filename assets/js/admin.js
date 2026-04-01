/**
 * WC Hide Price & Ask Button Pro — Admin JavaScript
 *
 * Handles AJAX-powered search (products/categories/exclude), tag management,
 * search button click, radio toggle, AJAX save, auto-show exclude section,
 * and client-side conflict checking.
 *
 * @package WCHPAB
 * @since   1.0.0
 */

/* global jQuery, wchpab_admin */
(function ($) {
	'use strict';

	/**
	 * Perform an AJAX search and display results.
	 *
	 * @param {jQuery}   $input       The search input.
	 * @param {jQuery}   $results     The results dropdown.
	 * @param {jQuery}   $tags        The tags container.
	 * @param {string}   ajaxAction   The AJAX action.
	 * @param {function} conflictFn   Optional conflict checker (receives id, returns string or false).
	 */
	function doSearch($input, $results, $tags, ajaxAction, conflictFn) {
		var term = $.trim($input.val());

		if (term.length < 2) {
			$results.removeClass('active').empty();
			return;
		}

		$results.addClass('active').html(
			'<div class="wchpab-search-no-results">' + wchpab_admin.i18n.searching + '</div>'
		);

		$.ajax({
			url: wchpab_admin.ajax_url,
			method: 'GET',
			data: {
				action: ajaxAction,
				nonce: wchpab_admin.nonce,
				term: term
			},
			success: function (data) {
				$results.empty();

				if (!data || data.length === 0) {
					$results.html(
						'<div class="wchpab-search-no-results">' + wchpab_admin.i18n.no_results + '</div>'
					);
					return;
				}

				$.each(data, function (i, item) {
					// Skip items already added as tags.
					if ($tags.find('.wchpab-tag[data-id="' + item.id + '"]').length > 0) {
						return;
					}

					var $item = $('<div class="wchpab-search-result-item"></div>')
						.text(item.text)
						.attr('data-id', item.id)
						.attr('data-text', item.text);

					// Mark conflicting items with warning icon.
					if (conflictFn) {
						var conflictMsg = conflictFn(item.id);
						if (conflictMsg) {
							$item.addClass('wchpab-conflict-item')
								.attr('title', conflictMsg)
								.append(' <span class="dashicons dashicons-warning wchpab-conflict-icon"></span>');
						}
					}

					$results.append($item);
				});

				if ($results.children('.wchpab-search-result-item').length === 0) {
					$results.html(
						'<div class="wchpab-search-no-results">' + wchpab_admin.i18n.no_results + '</div>'
					);
				}
			},
			error: function () {
				$results.html(
					'<div class="wchpab-search-no-results">' + wchpab_admin.i18n.no_results + '</div>'
				);
			}
		});
	}

	/**
	 * Initialize AJAX search for a given set of selectors.
	 *
	 * @param {string}   inputSelector     Selector for the search input.
	 * @param {string}   resultsSelector   Selector for the results dropdown.
	 * @param {string}   tagsSelector      Selector for the tags container.
	 * @param {string}   ajaxAction        The AJAX action to call.
	 * @param {string}   hiddenName        The hidden input name for submitted IDs.
	 * @param {string}   btnSelector       Selector for the search button.
	 * @param {function} conflictFn        Optional conflict checker.
	 * @param {function} onAddCallback     Optional callback after adding a tag.
	 * @param {function} onRemoveCallback  Optional callback after removing a tag.
	 */
	function initSearch(inputSelector, resultsSelector, tagsSelector, ajaxAction, hiddenName, btnSelector, conflictFn, onAddCallback, onRemoveCallback) {
		var $input = $(inputSelector);
		var $results = $(resultsSelector);
		var $tags = $(tagsSelector);
		var $btn = $(btnSelector);

		// Bail if elements don't exist on this page.
		if ($input.length === 0) return;

		// Local debounce timer — one per search instance to avoid cross-instance interference.
		var searchTimer = null;

		// Debounced search on keyup.
		$input.on('keyup', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				doSearch($input, $results, $tags, ajaxAction, conflictFn);
				return;
			}

			clearTimeout(searchTimer);
			var term = $.trim($input.val());
			if (term.length < 2) {
				$results.removeClass('active').empty();
				return;
			}

			searchTimer = setTimeout(function () {
				doSearch($input, $results, $tags, ajaxAction, conflictFn);
			}, 300);
		});

		// Click on search button.
		$btn.on('click', function () {
			doSearch($input, $results, $tags, ajaxAction, conflictFn);
		});

		// Click on a search result to add a tag.
		$results.on('click', '.wchpab-search-result-item', function () {
			var id = $(this).data('id');
			var text = $(this).data('text');

			// Block if conflict.
			if (conflictFn) {
				var conflictMsg = conflictFn(id);
				if (conflictMsg) {
					alert(conflictMsg);
					return;
				}
			}

			// Prevent duplicates.
			if ($tags.find('.wchpab-tag[data-id="' + id + '"]').length > 0) {
				return;
			}

			// Enforce max items limit
			var maxItems = parseInt($tags.attr('data-max-items'), 10) || 0;
			if (maxItems > 0 && $tags.find('.wchpab-tag').length >= maxItems) {
				alert(wchpab_admin.i18n.limit_reached);
				return;
			}

			var $tag = $(
				'<span class="wchpab-tag" data-id="' + id + '">' +
				escHtml(text) +
				'<button type="button" class="wchpab-tag-remove" aria-label="' + wchpab_admin.i18n.remove + '">&times;</button>' +
				'<input type="hidden" name="' + hiddenName + '" value="' + id + '" />' +
				'</span>'
			);

			// Remove empty state message if it exists
			$tags.find('.wchpab-no-tags-message').remove();

			$tags.append($tag);
			$input.val('');
			$results.removeClass('active').empty();

			if (onAddCallback) {
				onAddCallback(id);
			}
		});

		// Remove a tag.
		$tags.on('click', '.wchpab-tag-remove', function () {
			var removedId = $(this).closest('.wchpab-tag').data('id');
			$(this).closest('.wchpab-tag').remove();
			if (onRemoveCallback) {
				onRemoveCallback(removedId);
			}
		});

		// Close search results when clicking outside.
		$(document).on('click', function (e) {
			if (!$(e.target).closest(inputSelector + ', ' + resultsSelector + ', ' + btnSelector).length) {
				$results.removeClass('active').empty();
			}
		});
	}

	/**
	 * Basic HTML escaping for text content.
	 */
	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Initialize radio toggle behavior for button text and email settings.
	 */
	function initRadioToggles() {
		$('input[name="product_text_mode"]').on('change click', function (e) {
			e.stopPropagation();
			$('input[name="product_text"]').prop('disabled', $(this).val() !== 'custom');
		});
		$('input[name="category_text_mode"]').on('change click', function (e) {
			e.stopPropagation();
			$('input[name="category_text"]').prop('disabled', $(this).val() !== 'custom');
		});
		$('input[name="login_text_mode"]').on('change click', function (e) {
			e.stopPropagation();
			$('input[name="login_text"]').prop('disabled', $(this).val() !== 'custom');
		});
		$('input[name="email_receiver_mode"]').on('change click', function (e) {
			e.stopPropagation();
			$('input[name="custom_email"]').prop('disabled', $(this).val() !== 'custom');
		});
		$('input[name="login_action"]').on('change click', function (e) {
			e.stopPropagation();
			$('input[name="custom_login_url"]').prop('disabled', $(this).val() !== 'custom_url');
		});
		$('input[name="product_icon_mode"]').on('change click', function (e) {
			e.stopPropagation();
		});
		$('input[name="category_icon_mode"]').on('change click', function (e) {
			e.stopPropagation();
		});
		$('input[name="login_icon_mode"]').on('change click', function (e) {
			e.stopPropagation();
		});
	}

	/**
	 * Initialize global settings toggles (hide all products/categories, visibility mode).
	 */
	function initGlobalToggles() {
		// Handle hide all products checkbox on Products page
		$('#wchpab-hide-all-products').on('change', function () {
			var isChecked = $(this).is(':checked');
			toggleProductsPageSections(isChecked);
		});

		// Handle hide all categories checkbox on Categories page
		$('#wchpab-hide-all-categories').on('change', function () {
			var isChecked = $(this).is(':checked');
			toggleCategoriesPageSections(isChecked);
		});

		// Handle visibility mode radio buttons (Settings page)
		$('input[name="visibility_mode"]').on('change', function () {
			var mode = $(this).val();

			// Show/hide roles selector
			if (mode === 'roles') {
				$('#wchpab-roles-selector').slideDown(200);
			} else {
				$('#wchpab-roles-selector').slideUp(200);
			}

			// Show/hide login settings vs contact settings
			if (mode === 'guests') {
				// Show login settings, hide contact settings
				$('#wchpab-login-settings-section').slideDown(200);
				$('.wchpab-login-setting-row').slideDown(200);
				$('.wchpab-contact-setting-row').slideUp(200);
			} else {
				// Show contact settings, hide login settings
				$('#wchpab-login-settings-section').slideUp(200);
				$('.wchpab-login-setting-row').slideUp(200);
				$('.wchpab-contact-setting-row').slideDown(200);
			}
		});

		// Scroll to global option when clicking the link in disabled overlay
		$('.wchpab-scroll-to-global').on('click', function (e) {
			e.preventDefault();
			$('html, body').animate({
				scrollTop: $('.wchpab-global-option-box').offset().top - 50
			}, 500);
			$('.wchpab-global-option-box').addClass('wchpab-highlight-flash');
			setTimeout(function () {
				$('.wchpab-global-option-box').removeClass('wchpab-highlight-flash');
			}, 2000);
		});
	}

	/**
	 * Toggle sections on Products page based on hide all products state.
	 */
	function toggleProductsPageSections(isDisabled) {
		if (isDisabled) {
			$('#wchpab-products-disabled-overlay').slideDown(200);
			$('.wchpab-search-section, .wchpab-tags-section, #wchpab-save-products').addClass('wchpab-section-disabled');
		} else {
			$('#wchpab-products-disabled-overlay').slideUp(200);
			$('.wchpab-search-section, .wchpab-tags-section, #wchpab-save-products').removeClass('wchpab-section-disabled');
		}
	}

	/**
	 * Toggle sections on Categories page based on hide all categories state.
	 */
	function toggleCategoriesPageSections(isDisabled) {
		if (isDisabled) {
			$('#wchpab-categories-disabled-overlay').slideDown(200);
			$('.wchpab-search-section, .wchpab-tags-section, #wchpab-save-categories, #wchpab-save-excluded, #wchpab-exclude-section').addClass('wchpab-section-disabled');
		} else {
			$('#wchpab-categories-disabled-overlay').slideUp(200);
			$('.wchpab-search-section, .wchpab-tags-section, #wchpab-save-categories, #wchpab-save-excluded, #wchpab-exclude-section').removeClass('wchpab-section-disabled');
		}
	}

	/**
	 * AJAX save handler for tag-based pages.
	 */
	function initTagSave(btnSelector, tagsSelector, noticeSelector) {
		var $btn = $(btnSelector);
		if ($btn.length === 0) return;

		$btn.each(function () {
			$(this).data('original-text', $(this).text());
		});

		$btn.on('click', function () {
			var $b = $(this);
			var $notice = $(noticeSelector);
			var action = $b.data('save-action');
			var ids = [];

			$(tagsSelector).find('input[type="hidden"]').each(function () {
				ids.push($(this).val());
			});

			$b.prop('disabled', true).text(wchpab_admin.i18n.saving);
			$notice.removeClass('wchpab-notice-success wchpab-notice-error').text('').hide();

			$.ajax({
				url: wchpab_admin.ajax_url,
				method: 'POST',
				data: { action: action, nonce: wchpab_admin.nonce, ids: ids },
				success: function (response) {
					if (response.success) {
						$notice.addClass('wchpab-notice-success').text(response.data).show();
					} else {
						$notice.addClass('wchpab-notice-error').text(response.data || wchpab_admin.i18n.save_error).show();
					}
				},
				error: function () {
					$notice.addClass('wchpab-notice-error').text(wchpab_admin.i18n.save_error).show();
				},
				complete: function () {
					$b.prop('disabled', false).text($b.data('original-text'));
				}
			});
		});
	}

	/**
	 * Helper function to validate email addresses.
	 */
	function isValidEmail(email) {
		var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return re.test(email);
	}

	/**
	 * AJAX save handler for the settings page.
	 */
	function initSettingsSave() {
		var $btn = $('#wchpab-save-settings');
		if ($btn.length === 0) return;

		$btn.data('original-text', $btn.text());

		$btn.on('click', function () {
			var $notice = $('#wchpab-settings-notice');
			var $emailInput = $('#wchpab_custom_email');
			var $emailError = $('#wchpab_custom_email_error');
			var action = $btn.data('save-action');

			// Clear previous errors
			$emailError.hide();

			// Validate custom email if enabled
			var receiverMode = $('input[name="email_receiver_mode"]:checked').val();
			if (receiverMode === 'custom') {
				if (!isValidEmail($emailInput.val())) {
					$emailError.show();
					return; // Stop the save
				}
			}

			var $disabledInputs = $('#wchpab-settings-form input:disabled');
			$disabledInputs.prop('disabled', false);
			var formData = $('#wchpab-settings-form').serializeArray();
			$disabledInputs.prop('disabled', true);

			var postData = { action: action, nonce: wchpab_admin.nonce };
			$.each(formData, function (i, field) { postData[field.name] = field.value; });

			$btn.prop('disabled', true).text(wchpab_admin.i18n.saving);
			$notice.removeClass('wchpab-notice-success wchpab-notice-error').text('').hide();

			$.ajax({
				url: wchpab_admin.ajax_url,
				method: 'POST',
				data: postData,
				success: function (response) {
					if (response.success) {
						$notice.addClass('wchpab-notice-success').text(response.data).show();
					} else {
						$notice.addClass('wchpab-notice-error').text(response.data || wchpab_admin.i18n.save_error).show();
					}
				},
				error: function () {
					$notice.addClass('wchpab-notice-error').text(wchpab_admin.i18n.save_error).show();
				},
				complete: function () {
					$btn.prop('disabled', false).text($btn.data('original-text'));
				}
			});
		});
	}

	/**
	 * AJAX save handler for hide all products checkbox.
	 */
	function initHideAllProductsSave() {
		var $btn = $('#wchpab-save-hide-all-products');
		if ($btn.length === 0) return;

		$btn.data('original-text', $btn.text());

		$btn.on('click', function () {
			var $notice = $('#wchpab-hide-all-products-notice');
			var isChecked = $('#wchpab-hide-all-products').is(':checked');

			$btn.prop('disabled', true).text(wchpab_admin.i18n.saving);
			$notice.removeClass('wchpab-notice-success wchpab-notice-error').text('').hide();

			$.ajax({
				url: wchpab_admin.ajax_url,
				method: 'POST',
				data: {
					action: 'wchpab_save_hide_all_products',
					nonce: wchpab_admin.nonce,
					hide_all_products: isChecked ? '1' : '0'
				},
				success: function (response) {
					if (response.success) {
						$notice.addClass('wchpab-notice-success').text(response.data).show();
						// Reload page to update UI state
						setTimeout(function () {
							location.reload();
						}, 1000);
					} else {
						$notice.addClass('wchpab-notice-error').text(response.data || wchpab_admin.i18n.save_error).show();
					}
				},
				error: function () {
					$notice.addClass('wchpab-notice-error').text(wchpab_admin.i18n.save_error).show();
				},
				complete: function () {
					$btn.prop('disabled', false).text($btn.data('original-text'));
				}
			});
		});
	}

	/**
	 * AJAX save handler for hide all categories checkbox.
	 */
	function initHideAllCategoriesSave() {
		var $btn = $('#wchpab-save-hide-all-categories');
		if ($btn.length === 0) return;

		$btn.data('original-text', $btn.text());

		$btn.on('click', function () {
			var $notice = $('#wchpab-hide-all-categories-notice');
			var isChecked = $('#wchpab-hide-all-categories').is(':checked');

			$btn.prop('disabled', true).text(wchpab_admin.i18n.saving);
			$notice.removeClass('wchpab-notice-success wchpab-notice-error').text('').hide();

			$.ajax({
				url: wchpab_admin.ajax_url,
				method: 'POST',
				data: {
					action: 'wchpab_save_hide_all_categories',
					nonce: wchpab_admin.nonce,
					hide_all_categories: isChecked ? '1' : '0'
				},
				success: function (response) {
					if (response.success) {
						$notice.addClass('wchpab-notice-success').text(response.data).show();
						// Reload page to update UI state
						setTimeout(function () {
							location.reload();
						}, 1000);
					} else {
						$notice.addClass('wchpab-notice-error').text(response.data || wchpab_admin.i18n.save_error).show();
					}
				},
				error: function () {
					$notice.addClass('wchpab-notice-error').text(wchpab_admin.i18n.save_error).show();
				},
				complete: function () {
					$btn.prop('disabled', false).text($btn.data('original-text'));
				}
			});
		});
	}

	/**
	 * Toggle the exclude section visibility based on category tags.
	 */
	function updateExcludeVisibility() {
		var $section = $('#wchpab-exclude-section');
		if ($section.length === 0) return;
		var catCount = $('#wchpab-category-tags').find('.wchpab-tag').length;
		if (catCount > 0) {
			$section.slideDown(200);
		} else {
			$section.slideUp(200);
		}
	}

	/**
	 * Handles the conditional visibility of the Theme Integration button tag sections.
	 */
	function initThemeIntegrationToggles() {
		const productCheckbox = $( 'input[name="target_product"]' );
		const archiveCheckbox = $( 'input[name="target_archive"]' );
		const productRow = $( '#wchpab-product-button-tag-row' );
		const archiveRow = $( '#wchpab-archive-button-tag-row' );

		function toggleSections() {
			if ( productCheckbox.is( ':checked' ) ) {
				productRow.show();
			} else {
				productRow.hide();
			}

			if ( archiveCheckbox.is( ':checked' ) ) {
				archiveRow.show();
			} else {
				archiveRow.hide();
			}
		}

		// Initial check on page load.
		toggleSections();

		// Bind change events.
		productCheckbox.on( 'change', toggleSections );
		archiveCheckbox.on( 'change', toggleSections );
	}

	// DOM Ready.
	$(function () {

		// ---- Initialize disabled states on page load ----
		// Check if hide all products is enabled on Products page
		if ($('#wchpab-hide-all-products').length && $('#wchpab-hide-all-products').is(':checked')) {
			toggleProductsPageSections(true);
		}

		// Check if hide all categories is enabled on Categories page
		if ($('#wchpab-hide-all-categories').length && $('#wchpab-hide-all-categories').is(':checked')) {
			toggleCategoriesPageSections(true);
		}

		// Check if we're on Categories page and hide all products is enabled (from settings)
		// This handles the case where hide all products is enabled but we're viewing the categories page
		if ($('#wchpab-hide-all-categories').length) {
			// Check if the checkbox is disabled (which means hide all products is enabled)
			if ($('#wchpab-hide-all-categories').is(':disabled')) {
				toggleCategoriesPageSections(true);
			}
		}

		// ---- Products Page ----
		// Conflict check: prevent adding a product that's already in the excluded list.
		var productConflictFn = function (id) {
			var excludedIds = wchpab_admin.excluded_products || [];
			if ($.inArray(parseInt(id, 10), excludedIds) !== -1) {
				return wchpab_admin.i18n.conflict_exclude;
			}
			return false;
		};

		initSearch(
			'#wchpab-product-search',
			'#wchpab-product-results',
			'#wchpab-product-tags',
			'wchpab_search_products',
			'wchpab_product_ids[]',
			'#wchpab-product-search-btn',
			productConflictFn
		);

		// ---- Categories Page ----
		initSearch(
			'#wchpab-category-search',
			'#wchpab-category-results',
			'#wchpab-category-tags',
			'wchpab_search_categories',
			'wchpab_category_ids[]',
			'#wchpab-category-search-btn',
			null,
			function () { updateExcludeVisibility(); },  // onAdd
			function () { updateExcludeVisibility(); }   // onRemove
		);

		// ---- Exclude Products (on Categories Page) ----
		// Conflict check: prevent adding a product that's already in the hidden products list.
		var excludeConflictFn = function (id) {
			var hiddenIds = wchpab_admin.hidden_products || [];
			if ($.inArray(parseInt(id, 10), hiddenIds) !== -1) {
				return wchpab_admin.i18n.conflict_hidden;
			}
			return false;
		};

		initSearch(
			'#wchpab-exclude-search',
			'#wchpab-exclude-results',
			'#wchpab-exclude-tags',
			'wchpab_search_products',
			'wchpab_exclude_ids[]',
			'#wchpab-exclude-search-btn',
			excludeConflictFn
		);

		// ---- AJAX Saves ----
		initTagSave('#wchpab-save-products', '#wchpab-product-tags', '#wchpab-products-notice');
		initTagSave('#wchpab-save-categories', '#wchpab-category-tags', '#wchpab-categories-notice');
		initTagSave('#wchpab-save-excluded', '#wchpab-exclude-tags', '#wchpab-excluded-notice');
		initSettingsSave();
		initHideAllProductsSave();
		initHideAllCategoriesSave();

		// Radio toggles.
		initRadioToggles();

		// Global settings toggles.
		initGlobalToggles();

		// Theme Integration toggles.
		initThemeIntegrationToggles();

		// ---- Icon Selector Logic ----
		function initIconSelectors() {
			// A sample subset of common Dashicons. For a full list, we would expand this array.
			var dashiconsList = [
				'dashicons-cart', 'dashicons-store', 'dashicons-products', 'dashicons-star-filled', 'dashicons-heart',
				'dashicons-yes', 'dashicons-yes-alt', 'dashicons-info', 'dashicons-info-outline', 'dashicons-warning',
				'dashicons-tag', 'dashicons-money-alt', 'dashicons-edit', 'dashicons-email', 'dashicons-bell',
				'dashicons-megaphone', 'dashicons-format-chat', 'dashicons-smartphone'
			];

			function renderGrid($grid, icons, currentClass) {
				$grid.empty();
				$.each(icons, function (i, iconClass) {
					var $btn = $('<button type="button" class="wchpab-icon-btn"></button>')
						.append('<i class="dashicons ' + iconClass + '"></i>')
						.data('icon', iconClass);

					if (iconClass === currentClass) {
						$btn.addClass('active');
					}

					$btn.on('click', function (e) {
						e.preventDefault();

						// Remove active from peers
						$grid.find('.wchpab-icon-btn').removeClass('active');
						$(this).addClass('active');

						// Determine target type from grid ID
						var gridId = $grid.attr('id');
						var targetType = 'product'; // default
						if (gridId.indexOf('login') !== -1) {
							targetType = 'login';
						} else if (gridId.indexOf('category') !== -1) {
							targetType = 'category';
						} else if (gridId.indexOf('product') !== -1) {
							targetType = 'product';
						}

						// Update hidden input
						$('#wchpab-' + targetType + '-icon-class').val(iconClass);

						// Update preview
						$('#wchpab-' + targetType + '-icon-preview').attr('class', 'dashicons ' + iconClass);
					});

					$grid.append($btn);
				});
			}

			// Initialize the grids
			$('.wchpab-icon-grid').each(function () {
				var $grid = $(this);
				var gridId = $grid.attr('id');
				var targetType = 'product'; // default
				var defaultIcon = 'dashicons-info-outline';

				if (gridId.indexOf('login') !== -1) {
					targetType = 'login';
					defaultIcon = 'dashicons-admin-users';
				} else if (gridId.indexOf('category') !== -1) {
					targetType = 'category';
				} else if (gridId.indexOf('product') !== -1) {
					targetType = 'product';
				}

				var currentClass = $('#wchpab-' + targetType + '-icon-class').val() || defaultIcon;
				renderGrid($grid, dashiconsList, currentClass);
			});

			// Search capability
			$('.wchpab-icon-search').on('input', function () {
				var term = $(this).val().toLowerCase();
				var targetType = $(this).data('target');
				var $grid = $('#wchpab-' + targetType + '-icon-grid');
				var currentClass = $('#wchpab-' + targetType + '-icon-class').val();

				var filtered = dashiconsList.filter(function (icon) {
					return icon.indexOf(term) !== -1;
				});

				renderGrid($grid, filtered, currentClass);
			});

			// Watch for radio changes to show/hide the wrap
			$('input[name="product_icon_mode"]').on('change', function () {
				if ($(this).val() === 'custom') {
					$('#wchpab-product-icon-wrap').slideDown(200);
				} else {
					$('#wchpab-product-icon-wrap').slideUp(200);
				}
			});

			$('input[name="category_icon_mode"]').on('change', function () {
				if ($(this).val() === 'custom') {
					$('#wchpab-category-icon-wrap').slideDown(200);
				} else {
					$('#wchpab-category-icon-wrap').slideUp(200);
				}
			});

			$('input[name="login_icon_mode"]').on('change', function () {
				if ($(this).val() === 'custom') {
					$('#wchpab-login-icon-wrap').slideDown(200);
				} else {
					$('#wchpab-login-icon-wrap').slideUp(200);
				}
			});
		}

		initIconSelectors();
	});

})(jQuery);
