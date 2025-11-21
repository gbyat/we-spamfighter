(function ($) {
    'use strict';

    $(document).ready(function () {
        // Move to normal button handler.
        $('.we-move-to-normal').on('click', function () {
            var button = $(this);
            var submissionId = button.data('submission-id');
            var row = button.closest('tr');

            if (!confirm(weSpamfighter.strings.confirmMove)) {
                return;
            }

            button.prop('disabled', true).text(weSpamfighter.strings.moving);

            $.ajax({
                url: weSpamfighter.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_move_to_normal',
                    nonce: weSpamfighter.nonce,
                    submission_id: submissionId
                },
                success: function (response) {
                    if (response.success) {
                        row.fadeOut(300, function () {
                            row.remove();
                        });
                        alert(response.data.message);
                    } else {
                        alert(weSpamfighter.strings.error + ': ' + response.data.message);
                        button.prop('disabled', false).text(weSpamfighter.strings.moveToNormal);
                    }
                },
                error: function () {
                    alert(weSpamfighter.strings.errorOccurred);
                    button.prop('disabled', false).text(weSpamfighter.strings.moveToNormal);
                }
            });
        });

        // View details button handler.
        $('.we-view-details').on('click', function () {
            var button = $(this);
            var submissionId = button.data('submission-id');
            var modal = $('#we-submission-details-modal');
            var content = $('#we-submission-details-content');

            button.prop('disabled', true).text(weSpamfighter.strings.loading);

            $.ajax({
                url: weSpamfighter.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_get_submission_details',
                    nonce: weSpamfighter.nonce,
                    submission_id: submissionId
                },
                success: function (response) {
                    button.prop('disabled', false).text(weSpamfighter.strings.viewDetails);

                    if (response.success) {
                        var data = response.data;
                        var html = '<h2>Submission Details</h2>';
                        html += '<p><strong>ID:</strong> ' + data.id + '</p>';
                        html += '<p><strong>Type:</strong> ' + data.submission_type + '</p>';
                        html += '<p><strong>Form/Post ID:</strong> ' + data.form_id + '</p>';
                        html += '<p><strong>Is Spam:</strong> ' + (data.is_spam == 1 ? 'Yes' : 'No') + '</p>';
                        html += '<p><strong>Spam Score:</strong> ' + parseFloat(data.spam_score).toFixed(2) + '</p>';
                        html += '<p><strong>Created:</strong> ' + data.created_at + '</p>';

                        if (data.submission_data) {
                            html += '<h3>Submission Data</h3>';
                            html += '<pre>' + JSON.stringify(data.submission_data, null, 2) + '</pre>';
                        }

                        if (data.detection_details) {
                            html += '<h3>Detection Details</h3>';
                            html += '<pre>' + JSON.stringify(data.detection_details, null, 2) + '</pre>';
                        }

                        content.html(html);
                        modal.fadeIn(300);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function () {
                    button.prop('disabled', false).text(weSpamfighter.strings.viewDetails);
                    alert(weSpamfighter.strings.errorOccurred);
                }
            });
        });

        // Close modal handler.
        $('.we-modal-close').on('click', function () {
            $('#we-submission-details-modal').fadeOut(300);
        });

        // Close modal when clicking outside.
        $(window).on('click', function (event) {
            var modal = $('#we-submission-details-modal');
            if ($(event.target).is(modal)) {
                modal.fadeOut(300);
            }
        });
    });
})(jQuery);

