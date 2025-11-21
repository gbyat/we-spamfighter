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
                    'moving'             => __('Moving...', 'we-spamfighter'),
                    'moveToNormal'       => __('Move to Normal', 'we-spamfighter'),
                    'loading'            => __('Loading...', 'we-spamfighter'),
                    'viewDetails'        => __('View Details', 'we-spamfighter'),
                    'confirmMove'        => __('Are you sure you want to move this submission to normal mails?', 'we-spamfighter'),
                    'errorOccurred'      => __('An error occurred. Please try again.', 'we-spamfighter'),
                    'error'              => __('Error', 'we-spamfighter'),
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
        $stats = $db->get_statistics(null);

        // Get current tab.
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'normal';

        // Get total counts for tabs (all time).
        $total_normal_count = $db->get_total_count(0);
        $total_spam_count = $db->get_total_count(1);

        // Get submissions for current tab.
        $is_spam = ('spam' === $tab) ? 1 : 0;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $submissions = $db->get_submissions(array(
            'is_spam' => $is_spam,
            'limit'   => $per_page,
            'offset'  => $offset,
        ));

?>
        <div class="wrap we-spamfighter-dashboard">
            <h1><?php esc_html_e('WE Spamfighter Dashboard', 'we-spamfighter'); ?></h1>

            <div class="we-spamfighter-stats">
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['total'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('Total Submissions', 'we-spamfighter'); ?></div>
                </div>
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['normal'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('Normal Mails', 'we-spamfighter'); ?></div>
                </div>
                <div class="we-stat-box">
                    <div class="we-stat-value"><?php echo esc_html(number_format_i18n($stats['spam'])); ?></div>
                    <div class="we-stat-label"><?php esc_html_e('Spam', 'we-spamfighter'); ?></div>
                </div>
            </div>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'normal')); ?>" class="nav-tab <?php echo ('normal' === $tab) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Normal Mails', 'we-spamfighter'); ?> (<?php echo esc_html(number_format_i18n($total_normal_count)); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'spam')); ?>" class="nav-tab <?php echo ('spam' === $tab) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Spam', 'we-spamfighter'); ?> (<?php echo esc_html(number_format_i18n($total_spam_count)); ?>)
                </a>
            </h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'we-spamfighter'); ?></th>
                        <th><?php esc_html_e('Type', 'we-spamfighter'); ?></th>
                        <th><?php esc_html_e('Form/Post ID', 'we-spamfighter'); ?></th>
                        <?php if ('spam' === $tab) : ?>
                            <th><?php esc_html_e('Spam Score', 'we-spamfighter'); ?></th>
                            <th><?php esc_html_e('Action', 'we-spamfighter'); ?></th>
                        <?php else : ?>
                            <th><?php esc_html_e('Spam Score', 'we-spamfighter'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Details', 'we-spamfighter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)) : ?>
                        <tr>
                            <td colspan="<?php echo ('spam' === $tab) ? '6' : '5'; ?>">
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
                            <tr>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $submission['created_at'])); ?></td>
                                <td><?php echo esc_html(ucfirst($submission['submission_type'])); ?></td>
                                <td><?php echo esc_html($submission['form_id']); ?></td>
                                <?php if ('spam' === $tab) : ?>
                                    <td><?php echo esc_html(number_format($submission['spam_score'], 2)); ?></td>
                                    <td>
                                        <button type="button" class="button button-small button-primary we-move-to-normal" data-submission-id="<?php echo esc_attr($submission['id']); ?>">
                                            <?php esc_html_e('Move to Normal', 'we-spamfighter'); ?>
                                        </button>
                                    </td>
                                <?php else : ?>
                                    <?php if (!empty($submission['spam_score']) && $submission['spam_score'] > 0) : ?>
                                        <td><?php echo esc_html(number_format($submission['spam_score'], 2)); ?></td>
                                    <?php else : ?>
                                        <td>-</td>
                                    <?php endif; ?>
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
            // Simple pagination.
            if (count($submissions) === $per_page) {
                $next_page = $page + 1;
                echo '<p>';
                if ($page > 1) {
                    printf(
                        '<a href="%s" class="button">%s</a> ',
                        esc_url(add_query_arg(array('paged' => $page - 1, 'tab' => $tab))),
                        esc_html__('Previous', 'we-spamfighter')
                    );
                }
                printf(
                    '<a href="%s" class="button">%s</a>',
                    esc_url(add_query_arg(array('paged' => $next_page, 'tab' => $tab))),
                    esc_html__('Next', 'we-spamfighter')
                );
                echo '</p>';
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
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => 'Invalid submission ID'));
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
     * AJAX handler for getting submission details.
     */
    public function ajax_get_submission_details()
    {
        check_ajax_referer('we_spamfighter_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;

        if (! $submission_id) {
            wp_send_json_error(array('message' => 'Invalid submission ID'));
        }

        $db = Database::get_instance();
        $submission = $db->get_submission($submission_id);

        if (! $submission) {
            wp_send_json_error(array('message' => 'Submission not found'));
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
