(function($) {
    'use strict';

    $(document).ready(function() {
        // Test API connection button handler.
        $('#we-test-api-btn').on('click', function() {
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
                success: function(response) {
                    button.prop('disabled', false).text('Test Connection');

                    if (response.success) {
                        resultSpan.text(response.data.message).addClass('success');
                    } else {
                        resultSpan.text(response.data.message).addClass('error');
                    }
                },
                error: function() {
                    button.prop('disabled', false).text('Test Connection');
                    resultSpan.text('An error occurred. Please try again.').addClass('error');
                }
            });
        });
    });
})(jQuery);

