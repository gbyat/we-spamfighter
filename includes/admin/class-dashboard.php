<?php

/**
 * Admin dashboard.
 *
 * @package WeSpamfighter
 */

namespace WeSpamfighter\Admin;

use WeSpamfighter\Core\Database;

/**
 * Dashboard class.
 */
class Dashboard
{

    /**
     * Instance.
     *
     * @var Dashboard
     */
    private static $instance = null;

    /**
     * Get instance.
     *
     * @return Dashboard
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_we_spamfighter_move_to_normal', array($this, 'ajax_move_to_normal'));
        add_action('wp_ajax_we_spamfighter_move_to_spam', array($this, 'ajax_move_to_spam'));
        add_action('wp_ajax_we_spamfighter_delete_submission', array($this, 'ajax_delete_submission'));
        add_action('wp_ajax_we_spamfighter_bulk_delete_submissions', array($this, 'ajax_bulk_delete_submissions'));
        add_action('wp_ajax_we_spamfighter_get_submission_details', array($this, 'ajax_get_submission_details'));
    }

    /**
     * Add admin menu.
     */
    public function add_menu()
    {
        add_menu_page(
            __('WE Spamfighter', 'we-spamfighter'),
            __('Spamfighter', 'we-spamfighter'),
            'manage_options',
            'we-spamfighter',
            array($this, 'render_dashboard'),
            'dashicons-shield',
            80
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_scripts($hook)
    {
        if ('toplevel_page_we-spamfighter' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'we-spamfighter-dashboard',
            WE_SPAMFIGHTER_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            WE_SPAMFIGHTER_VERSION
        );

        wp_enqueue_script(
            'we-spamfighter-dashboard',
            WE_SPAMFIGHTER_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery'),
            WE_SPAMFIGHTER_VERSION,
            true
        );

        wp_localize_script(
            'we-spamfighter-dashboard',
            'weSpamfighter',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('we_spamfighter_nonce'),
                'strings'  => array(
                    'moving'                 => __('Moving...', 'we-spamfighter'),
                    'deleting'               => __('Deleting...', 'we-spamfighter'),
                    'moveToNormal'           => __('Move to Normal', 'we-spamfighter'),
                    'moveToSpam'             => __('Move to Spam', 'we-spamfighter'),
                    'delete'                 => __('Delete', 'we-spamfighter'),
                    'loading'                => __('Loading...', 'we-spamfighter'),
                    'viewDetails'            => __('View Details', 'we-spamfighter'),
                    'confirmMove'            => __('Are you sure you want to move this submission to normal mails?', 'we-spamfighter'),
                    'confirmMoveToSpam'      => __('Are you sure you want to move this submission to spam?', 'we-spamfighter'),
                    'confirmDelete'          => __('Are you sure you want to delete this submission? This action cannot be undone.', 'we-spamfighter'),
                    'confirmBulkDelete'      => __('Are you sure you want to delete the selected submissions? This action cannot be undone.', 'we-spamfighter'),
                    'selectItems'            => __('Please select at least one item to delete.', 'we-spamfighter'),
                    'errorOccurred'          => __('An error occurred. Please try again.', 'we-spamfighter'),
                    'error'                  => __('Error', 'we-spamfighter'),
                    'selectAll'              => __('Select All', 'we-spamfighter'),
                    'selectNone'             => __('Select None', 'we-spamfighter'),
                    'selectedCount'          => __('selected', 'we-spamfighter'),
                    'bulkActions'            => __('Bulk Actions', 'we-spamfighter'),
                    'apply'                  => __('Apply', 'we-spamfighter'),
                ),
            )
        );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $db = Database::get_instance();
        // Get all-time statistics (since plugin activation).
        // Note: Statistics only include CF7 submissions, not comments (which are handled by WordPress).
        $stats = $db->get_statistics(null);

        // Get WordPress spam comments count (comments are NOT saved in our database).
        $wp_spam_comments_count = wp_count_comments();
        $spam_comments_count = isset($wp_spam_comments_count->spam) ? (int) $wp_spam_comments_count->spam : 0;

        // Get current tab.
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'normal';

        // Get total counts for tabs (all time) - only CF7 submissions.
        $total_normal_count = $db->get_total_count(0);
        $total_spam_count = $db->get_total_count(1);

        // Get submissions for current tab - only CF7 (exclude comments).
        $is_spam = ('spam' === $tab) ? 1 : 0;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Only get CF7 submissions (comments are handled by WordPress).
        $submissions = $db->get_submissions(array(
            'submission_type' => 'cf7', // Only CF7, exclude comments.
            'is_spam'         => $is_spam,
            'limit'           => $per_page,
            'offset'          => $offset,
        ));

?>
        <div class="wrap we-spamfighter-dashboard">
            <h1><?php esc_html_e('WE Spamfighter Dashboard', 'we-spamfighter'); ?></h1>

            <div class="we-spamfighter-stats">
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['total'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('CF7 Submissions', 'we-spamfighter'); ?></div>
                </div>
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['normal'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('Normal Mails', 'we-spamfighter'); ?></div>
                </div>
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['spam'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('Spam (CF7)', 'we-spamfighter'); ?></div>
                </div>
                <?php if ($spam_comments_count > 0) : ?>
                    <div class="we-stat-box">
                        <div class="we-stat-value">
                            <a href="<?php echo esc_url(admin_url('edit-comments.php?comment_status=spam')); ?>" style="text-decoration: none; color: inherit;">
                                <?php echo esc_html(number_format_i18n($spam_comments_count)); ?>
                            </a>
                        </div>
                        <div class="we-stat-label">
                            <a href="<?php echo esc_url(admin_url('edit-comments.php?comment_status=spam')); ?>" style="text-decoration: none; color: inherit;">
                                <?php esc_html_e('Spam Comments', 'we-spamfighter'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'normal')); ?>" class="nav-tab <?php echo ('normal' === $tab) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Normal Mails', 'we-spamfighter'); ?> (<?php echo esc_html(number_format_i18n($total_normal_count)); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'spam')); ?>" class="nav-tab <?php echo ('spam' === $tab) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Spam', 'we-spamfighter'); ?> (<?php echo esc_html(number_format_i18n($total_spam_count)); ?>)
                </a>
            </h2>

            <!-- Bulk Actions -->
            <div class="we-bulk-actions" style="margin: 10px 0; display: none;">
                <select id="we-bulk-action-select" class="we-bulk-action-select">
                    <option value=""><?php esc_html_e('Bulk Actions', 'we-spamfighter'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'we-spamfighter'); ?></option>
                </select>
                <button type="button" class="button we-bulk-action-apply">
                    <?php esc_html_e('Apply', 'we-spamfighter'); ?>
                </button>
                <span class="we-selected-count" style="margin-left: 10px;"></span>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="we-select-all" class="we-select-all">
                        </td>
                        <th><?php esc_html_e('Date', 'we-spamfighter'); ?></th>
                        <th><?php esc_html_e('Type', 'we-spamfighter'); ?></th>
                        <th><?php esc_html_e('Form/Post ID', 'we-spamfighter'); ?></th>
                        <?php if ('spam' === $tab) : ?>
                            <th><?php esc_html_e('Spam Score', 'we-spamfighter'); ?></th>
                            <th><?php esc_html_e('Action', 'we-spamfighter'); ?></th>
                        <?php else : ?>
                            <th><?php esc_html_e('Spam Score', 'we-spamfighter'); ?></th>
                            <th><?php esc_html_e('Action', 'we-spamfighter'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Details', 'we-spamfighter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)) : ?>
                        <tr>
                            <td colspan="<?php echo ('spam' === $tab) ? '7' : '7'; ?>">
                                <?php esc_html_e('No submissions found.', 'we-spamfighter'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($submissions as $submission) : ?>
                            <?php
                            $submission_data = json_decode($submission['submission_data'], true);
                            if (! $submission_data) {
                                $submission_data = array();
                            }
                            ?>
                            <tr data-submission-id="<?php echo esc_attr($submission['id']); ?>">
                                <th class="check-column">
                                    <input type="checkbox" class="we-submission-checkbox" value="<?php echo esc_attr($submission['id']); ?>">
                                </th>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $submission['created_at'])); ?></td>
                                <td><?php echo esc_html(ucfirst($submission['submission_type'])); ?></td>
                                <td><?php echo esc_html($submission['form_id']); ?></td>
                                <?php if ('spam' === $tab) : ?>
                                    <td><?php echo esc_html(number_format($submission['spam_score'], 2)); ?></td>
                                    <td>
                                        <button type="button" class="button button-small button-primary we-move-to-normal" data-submission-id="<?php echo esc_attr($submission['id']); ?>">
                                            <?php esc_html_e('Move to Normal', 'we-spamfighter'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete we-delete-submission" data-submission-id="<?php echo esc_attr($submission['id']); ?>" style="color: #b32d2e; margin-left: 5px;">
                                            <?php esc_html_e('Delete', 'we-spamfighter'); ?>
                                        </button>
                                    </td>
                                <?php else : ?>
                                    <?php if (!empty($submission['spam_score']) && $submission['spam_score'] > 0) : ?>
                                        <td><?php echo esc_html(number_format($submission['spam_score'], 2)); ?></td>
                                    <?php else : ?>
                                        <td>-</td>
                                    <?php endif; ?>
                                    <td>
                                        <button type="button" class="button button-small button-primary we-move-to-spam" data-submission-id="<?php echo esc_attr($submission['id']); ?>">
                                            <?php esc_html_e('Move to Spam', 'we-spamfighter'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete we-delete-submission" data-submission-id="<?php echo esc_attr($submission['id']); ?>" style="color: #b32d2e; margin-left: 5px;">
                                            <?php esc_html_e('Delete', 'we-spamfighter'); ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <button type="button" class="button button-small we-view-details" data-submission-id="<?php echo esc_attr($submission['id']); ?>">
                                        <?php esc_html_e('View Details', 'we-spamfighter'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Pagination.
            $total_count = ('spam' === $tab) ? $total_spam_count : $total_normal_count;
            $total_pages = ceil($total_count / $per_page);

            if ($total_pages > 1) {
                $has_prev = $page > 1;
                $has_next = $page < $total_pages;

                echo '<div class="we-pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">';

                // Previous button (left side).
                if ($has_prev) {
                    printf(
                        '<a href="%s" class="button">%s</a>',
                        esc_url(add_query_arg(array('paged' => $page - 1, 'tab' => $tab))),
                        esc_html__('Previous', 'we-spamfighter')
                    );
                } else {
                    echo '<span></span>'; // Placeholder to keep Next button on the right.
                }

                // Page numbers (center) - only show if more than 2 pages.
                if ($total_pages > 2) {
                    echo '<span class="we-pagination-pages" style="display: inline-flex; gap: 5px; align-items: center;">';

                    // Always show first page.
                    if ($page > 3) {
                        printf(
                            '<a href="%s" class="button">%d</a>',
                            esc_url(add_query_arg(array('paged' => 1, 'tab' => $tab))),
                            1
                        );
                        if ($page > 4) {
                            echo '<span style="padding: 0 5px;">&hellip;</span>';
                        }
                    }

                    // Show pages around current page.
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i === $page) {
                            printf(
                                '<span class="button button-primary" style="cursor: default;">%d</span>',
                                $i
                            );
                        } else {
                            printf(
                                '<a href="%s" class="button">%d</a>',
                                esc_url(add_query_arg(array('paged' => $i, 'tab' => $tab))),
                                $i
                            );
                        }
                    }

                    // Always show last page.
                    if ($page < $total_pages - 2) {
                        if ($page < $total_pages - 3) {
                            echo '<span style="padding: 0 5px;">&hellip;</span>';
                        }
                        printf(
                            '<a href="%s" class="button">%d</a>',
                            esc_url(add_query_arg(array('paged' => $total_pages, 'tab' => $tab))),
                            $total_pages
                        );
                    }

                    echo '</span>';
                }

                // Next button (right side).
                if ($has_next) {
                    printf(
                        '<a href="%s" class="button">%s</a>',
                        esc_url(add_query_arg(array('paged' => $page + 1, 'tab' => $tab))),
                        esc_html__('Next', 'we-spamfighter')
                    );
                } else {
                    echo '<span></span>'; // Placeholder to keep Previous button on the left.
                }

                echo '</div>';
            }
            ?>

            <!-- Details Modal -->
            <div id="we-submission-details-modal" style="display: none;">
                <div class="we-modal-content">
                    <span class="we-modal-close">&times;</span>
                    <div id="we-submission-details-content"></div>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * AJAX handler for moving submission to normal.
     */
    public function ajax_move_to_normal()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized', 'we-spamfighter')));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => esc_html__('Invalid submission ID', 'we-spamfighter')));
        }

        $db = Database::get_instance();
        $result = $db->move_to_normal($submission_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Submission moved to normal mails.', 'we-spamfighter')));
        } else {
            wp_send_json_error(array('message' => __('Failed to move submission.', 'we-spamfighter')));
        }
    }

    /**
     * AJAX handler for moving submission to spam.
     */
    public function ajax_move_to_spam()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized', 'we-spamfighter')));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => esc_html__('Invalid submission ID', 'we-spamfighter')));
        }

        $db = Database::get_instance();
        $result = $db->move_to_spam($submission_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Submission moved to spam.', 'we-spamfighter')));
        } else {
            wp_send_json_error(array('message' => __('Failed to move submission.', 'we-spamfighter')));
        }
    }

    /**
     * AJAX handler for deleting a single submission.
     */
    public function ajax_delete_submission()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized', 'we-spamfighter')));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => esc_html__('Invalid submission ID', 'we-spamfighter')));
        }

        $db = Database::get_instance();
        $result = $db->delete_submission($submission_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Submission deleted successfully.', 'we-spamfighter')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete submission.', 'we-spamfighter')));
        }
    }

    /**
     * AJAX handler for bulk deleting submissions.
     */
    public function ajax_bulk_delete_submissions()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized', 'we-spamfighter')));
        }

        $submission_ids = isset($_POST['submission_ids']) ? array_map('absint', (array) $_POST['submission_ids']) : array();

        if (empty($submission_ids) || ! is_array($submission_ids)) {
            wp_send_json_error(array('message' => __('No submissions selected.', 'we-spamfighter')));
        }

        $db = Database::get_instance();
        $deleted = $db->bulk_delete_submissions($submission_ids);

        if ($deleted > 0) {
            wp_send_json_success(
                array(
                    'message' => sprintf(
                        /* translators: %d: Number of deleted submissions */
                        _n('%d submission deleted.', '%d submissions deleted.', $deleted, 'we-spamfighter'),
                        $deleted
                    ),
                    'deleted' => $deleted,
                )
            );
        } else {
            wp_send_json_error(array('message' => __('Failed to delete submissions.', 'we-spamfighter')));
        }
    }

    /**
     * AJAX handler for getting submission details.
     */
    public function ajax_get_submission_details()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized', 'we-spamfighter')));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => esc_html__('Invalid submission ID', 'we-spamfighter')));
        }

        $db = Database::get_instance();
        $submission = $db->get_submission($submission_id);

        if (! $submission) {
            wp_send_json_error(array('message' => esc_html__('Submission not found', 'we-spamfighter')));
        }

        // Decode JSON fields.
        if (! empty($submission['submission_data'])) {
            $submission['submission_data'] = json_decode($submission['submission_data'], true);
        }
        if (! empty($submission['detection_details'])) {
            $submission['detection_details'] = json_decode($submission['detection_details'], true);
        }

        wp_send_json_success($submission);
    }
}
