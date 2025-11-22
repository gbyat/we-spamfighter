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
                            updateSelectedCount();
                        });
                        alert(response.data.message);
                        location.reload(); // Reload to update tab counts
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
                        // Escape HTML to prevent XSS.
                        function escapeHtml(text) {
                            var map = {
                                '&': '&amp;',
                                '<': '&lt;',
                                '>': '&gt;',
                                '"': '&quot;',
                                "'": '&#039;'
                            };
                            return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
                        }

                        var html = '<h2>Submission Details</h2>';
                        html += '<p><strong>ID:</strong> ' + escapeHtml(data.id) + '</p>';
                        html += '<p><strong>Type:</strong> ' + escapeHtml(data.submission_type) + '</p>';
                        html += '<p><strong>Form/Post ID:</strong> ' + escapeHtml(data.form_id) + '</p>';
                        html += '<p><strong>Is Spam:</strong> ' + (data.is_spam == 1 ? 'Yes' : 'No') + '</p>';
                        html += '<p><strong>Spam Score:</strong> ' + escapeHtml(parseFloat(data.spam_score).toFixed(2)) + '</p>';
                        html += '<p><strong>Created:</strong> ' + escapeHtml(data.created_at) + '</p>';

                        if (data.submission_data) {
                            html += '<h3>Submission Data</h3>';
                            html += '<pre>' + escapeHtml(JSON.stringify(data.submission_data, null, 2)) + '</pre>';
                        }

                        if (data.detection_details) {
                            html += '<h3>Detection Details</h3>';
                            html += '<pre>' + escapeHtml(JSON.stringify(data.detection_details, null, 2)) + '</pre>';
                        }

                        content.html(html);
                        modal.fadeIn(300);
                    } else {
                        alert(weSpamfighter.strings.error + ': ' + response.data.message);
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

        // Move to spam button handler.
        $('.we-move-to-spam').on('click', function () {
            var button = $(this);
            var submissionId = button.data('submission-id');
            var row = button.closest('tr');

            if (!confirm(weSpamfighter.strings.confirmMoveToSpam)) {
                return;
            }

            button.prop('disabled', true).text(weSpamfighter.strings.moving);

            $.ajax({
                url: weSpamfighter.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_move_to_spam',
                    nonce: weSpamfighter.nonce,
                    submission_id: submissionId
                },
                success: function (response) {
                    if (response.success) {
                        row.fadeOut(300, function () {
                            row.remove();
                            updateSelectedCount();
                        });
                        alert(response.data.message);
                        location.reload(); // Reload to update tab counts
                    } else {
                        alert(weSpamfighter.strings.error + ': ' + response.data.message);
                        button.prop('disabled', false).text(weSpamfighter.strings.moveToSpam);
                    }
                },
                error: function () {
                    alert(weSpamfighter.strings.errorOccurred);
                    button.prop('disabled', false).text(weSpamfighter.strings.moveToSpam);
                }
            });
        });

        // Delete submission button handler.
        $(document).on('click', '.we-delete-submission', function () {
            var button = $(this);
            var submissionId = button.data('submission-id');
            var row = button.closest('tr');

            if (!confirm(weSpamfighter.strings.confirmDelete)) {
                return;
            }

            button.prop('disabled', true).text(weSpamfighter.strings.deleting);

            $.ajax({
                url: weSpamfighter.ajax_url,
                type: 'POST',
                data: {
                    action: 'we_spamfighter_delete_submission',
                    nonce: weSpamfighter.nonce,
                    submission_id: submissionId
                },
                success: function (response) {
                    if (response.success) {
                        row.fadeOut(300, function () {
                            row.remove();
                            updateSelectedCount();
                        });
                        alert(response.data.message);
                        location.reload(); // Reload to update counts
                    } else {
                        alert(weSpamfighter.strings.error + ': ' + response.data.message);
                        button.prop('disabled', false).text(weSpamfighter.strings.delete);
                    }
                },
                error: function () {
                    alert(weSpamfighter.strings.errorOccurred);
                    button.prop('disabled', false).text(weSpamfighter.strings.delete);
                }
            });
        });

        // Select all checkbox handler.
        $('#we-select-all').on('change', function () {
            var isChecked = $(this).prop('checked');
            $('.we-submission-checkbox').prop('checked', isChecked);
            updateSelectedCount();
        });

        // Individual checkbox handler.
        $(document).on('change', '.we-submission-checkbox', function () {
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Update selected count and show/hide bulk actions.
        function updateSelectedCount() {
            var checked = $('.we-submission-checkbox:checked').length;
            var bulkActions = $('.we-bulk-actions');
            var countSpan = $('.we-selected-count');

            if (checked > 0) {
                bulkActions.show();
                countSpan.text(checked + ' ' + weSpamfighter.strings.selectedCount);
            } else {
                bulkActions.hide();
                countSpan.text('');
            }
        }

        // Update select all checkbox state.
        function updateSelectAllCheckbox() {
            var total = $('.we-submission-checkbox').length;
            var checked = $('.we-submission-checkbox:checked').length;
            $('#we-select-all').prop('checked', total > 0 && checked === total);
        }

        // Bulk action apply button handler.
        $('.we-bulk-action-apply').on('click', function () {
            var action = $('#we-bulk-action-select').val();
            var checked = $('.we-submission-checkbox:checked');

            if (!action) {
                alert(weSpamfighter.strings.selectItems);
                return;
            }

            if (checked.length === 0) {
                alert(weSpamfighter.strings.selectItems);
                return;
            }

            var submissionIds = [];
            checked.each(function () {
                submissionIds.push($(this).val());
            });

            if (action === 'delete') {
                if (!confirm(weSpamfighter.strings.confirmBulkDelete)) {
                    return;
                }

                var button = $(this);
                button.prop('disabled', true).text(weSpamfighter.strings.deleting);

                $.ajax({
                    url: weSpamfighter.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'we_spamfighter_bulk_delete_submissions',
                        nonce: weSpamfighter.nonce,
                        submission_ids: submissionIds
                    },
                    success: function (response) {
                        button.prop('disabled', false).text(weSpamfighter.strings.apply);

                        if (response.success) {
                            checked.each(function () {
                                $(this).closest('tr').fadeOut(300, function () {
                                    $(this).remove();
                                });
                            });
                            alert(response.data.message);
                            updateSelectedCount();
                            location.reload(); // Reload to update counts
                        } else {
                            alert(weSpamfighter.strings.error + ': ' + response.data.message);
                        }
                    },
                    error: function () {
                        button.prop('disabled', false).text(weSpamfighter.strings.apply);
                        alert(weSpamfighter.strings.errorOccurred);
                    }
                });
            }
        });
    });
})(jQuery);

