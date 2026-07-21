(function ($) {
    'use strict';

    $(document).ready(function () {
        // Test API connection button handler.
        $('#we-test-api-btn').on('click', function () {
            var button = $(this);
            var resultSpan = $('#we-test-api-result');

            button.prop('disabled', true).text('Testing...');
            resultSpan.text('').removeClass('error success');

            $.ajax({
                url: weSpamfighterAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_test_api',
                    nonce: weSpamfighterAdmin.nonce
                },
                success: function (response) {
                    button.prop('disabled', false).text('Test Connection');

                    if (response.success) {
                        resultSpan.text(response.data.message).addClass('success');
                    } else {
                        resultSpan.text(response.data.message).addClass('error');
                    }
                },
                error: function () {
                    button.prop('disabled', false).text('Test Connection');
                    resultSpan.text('An error occurred. Please try again.').addClass('error');
                }
            });
        });

        // Clear activity log button handler.
        $('#we-clear-activity-log-btn').on('click', function () {
            var button = $(this);
            var resultSpan = $('#we-clear-activity-log-result');

            if (!confirm('Are you sure you want to clear the activity log? This action cannot be undone.')) {
                return;
            }

            button.prop('disabled', true).text('Clearing...');
            resultSpan.text('').removeClass('error success');

            $.ajax({
                url: weSpamfighterAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_clear_activity_log',
                    nonce: weSpamfighterAdmin.nonce
                },
                success: function (response) {
                    button.prop('disabled', false).text('Clear Activity Log');

                    if (response.success) {
                        resultSpan.text(response.data.message).addClass('success');
                        // Reload page after 1 second to show empty state.
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        resultSpan.text(response.data.message).addClass('error');
                    }
                },
                error: function () {
                    button.prop('disabled', false).text('Clear Activity Log');
                    resultSpan.text('An error occurred. Please try again.').addClass('error');
                }
            });
        });

        // Show/hide pingback option based on comments_enabled checkbox.
        function togglePingbackOption() {
            var commentsEnabled = $('input[name="we_spamfighter_settings[comments_enabled]"]').is(':checked');
            var pingbackRow = $('label.we-spamfighter-pingback-option').closest('tr');

            if (commentsEnabled) {
                pingbackRow.show();
            } else {
                pingbackRow.hide();
            }
        }

        // Toggle on page load.
        togglePingbackOption();

        // Toggle when comments_enabled checkbox changes.
        $('input[name="we_spamfighter_settings[comments_enabled]"]').on('change', function () {
            togglePingbackOption();
        });

        // Show/hide AI fields based on connection mode.
        function toggleAiBackendFields() {
            var backendSelect = $('select[name="we_spamfighter_settings[ai_backend]"]');
            if (!backendSelect.length) {
                return;
            }

            var backend = backendSelect.val();
            var useConnectors = backend === 'wp_connectors';

            $('tr:has(select[name="we_spamfighter_settings[ai_provider]"])').toggle(useConnectors);
            $('tr:has(input[name="we_spamfighter_settings[ai_model_preference]"])').toggle(useConnectors);
            $('tr:has(input[name="we_spamfighter_settings[openai_api_key]"])').toggle(!useConnectors);
            $('tr:has(select[name="we_spamfighter_settings[openai_model]"])').toggle(!useConnectors);
        }

        toggleAiBackendFields();
        $('select[name="we_spamfighter_settings[ai_backend]"]').on('change', toggleAiBackendFields);

        function getActiveTabFromUrl() {
            var match = window.location.search.match(/[?&]tab=([^&]+)/);
            return match ? match[1] : 'general';
        }

        function updateSettingsReferer(tabId) {
            var refererInput = $('input[name="_wp_http_referer"]');
            if (!refererInput.length) {
                return;
            }

            var query = '?page=we-spamfighter-settings&tab=' + tabId;
            var current = refererInput.val();

            if (current.indexOf('http') === 0) {
                refererInput.val(window.location.protocol + '//' + window.location.host + window.location.pathname + query);
            } else {
                refererInput.val(window.location.pathname + query);
            }
        }

        // Tab navigation - show/hide tab content.
        $('.we-settings-nav-tabs .nav-tab').on('click', function (e) {
            e.preventDefault();
            var tabId = $(this).attr('href').split('tab=')[1];

            // Update active tab.
            $('.we-settings-nav-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show/hide tab content.
            $('.we-settings-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            // Keep WordPress redirect on the active tab after save.
            updateSettingsReferer(tabId);

            // Update URL without reload.
            if (history.pushState) {
                var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=we-spamfighter-settings&tab=' + tabId;
                window.history.pushState({ path: newUrl }, '', newUrl);
            }
        });

        updateSettingsReferer(getActiveTabFromUrl());
    });
})(jQuery);

