<?php
/**
 * WP Cron Scheduler plugin for WordPress
 *
 * @package   cron-scheduler
 * @link      https://github.com/juanjopuntcat/cron-scheduler
 * @author    Juanjo Rubio
 * @copyright 2025 Juanjo Rubio
 * @license   GPL v2 or later
 *
 * Plugin Name:  WP Cron Scheduler
 * Description:  Provides a GUI to change the frequency of scheduled cron jobs.
 * Version:      1.0.1
 * Plugin URI:   https://github.com/juanjopuntcat/cron-scheduler
 * Author:       Juanjo Rubio
 * Author URI:   https://github.com/juanjopuntcat
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:  cron-scheduler
 * Domain Path:  /languages/
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

add_action('plugins_loaded', function () {
    load_plugin_textdomain('cron-scheduler', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('admin_menu', function () {
    add_submenu_page('tools.php', __('Cron Scheduler', 'cron-scheduler'), __('Cron Scheduler', 'cron-scheduler'), 'manage_options', 'cron-scheduler', 'cron_scheduler_admin_page');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'tools_page_cron-scheduler') return;
    wp_enqueue_style('cron-scheduler-style', plugin_dir_url(__FILE__) . 'assets/admin.css');
    wp_enqueue_script('cron-scheduler-script', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], false, true);
    wp_localize_script('cron-scheduler-script', 'wpCronScheduler', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('cron_scheduler_ajax')
    ]);
});

function cron_scheduler_admin_page() {
    $cron = _get_cron_array();
    if (empty($cron)) {
        echo '<p>' . esc_html__('No cron jobs found.', 'cron-scheduler') . '</p>';
        return;
    }

    $schedules = [
        'five_minutes' => ['display' => __('Every 5 Minutes', 'cron-scheduler')],
        'ten_minutes' => ['display' => __('Every 10 Minutes', 'cron-scheduler')],
        'fifteen_minutes' => ['display' => __('Every 15 Minutes', 'cron-scheduler')],
        'thirty_minutes' => ['display' => __('Every 30 Minutes', 'cron-scheduler')],
        'hourly' => ['display' => __('Every Hour', 'cron-scheduler')],
        'two_hours' => ['display' => __('Every 2 Hours', 'cron-scheduler')],
        'three_hours' => ['display' => __('Every 3 Hours', 'cron-scheduler')],
        'four_hours' => ['display' => __('Every 4 Hours', 'cron-scheduler')],
        'twelve_hours' => ['display' => __('Every 12 Hours', 'cron-scheduler')],
        'daily' => ['display' => __('Once Daily', 'cron-scheduler')],
        'two_days' => ['display' => __('Every 2 Days', 'cron-scheduler')],
        'three_days' => ['display' => __('Every 3 Days', 'cron-scheduler')],
        'four_days' => ['display' => __('Every 4 Days', 'cron-scheduler')],
        'five_days' => ['display' => __('Every 5 Days', 'cron-scheduler')],
        'weekly' => ['display' => __('Once Weekly', 'cron-scheduler')],
        'biweekly' => ['display' => __('Once Every 2 Weeks', 'cron-scheduler')],
        'monthly' => ['display' => __('Once Every Month', 'cron-scheduler')],
        'bimonthly' => ['display' => __('Once Every 2 Months', 'cron-scheduler')],
        'quarterly' => ['display' => __('Once Every 3 Months', 'cron-scheduler')],
        'semiannually' => ['display' => __('Once Every 6 Months', 'cron-scheduler')],
        'yearly' => ['display' => __('Once Every Year', 'cron-scheduler')]
    ];

    echo '<div class="wrap"><h1>' . esc_html__('Cron Scheduler', 'cron-scheduler') . '</h1>';
    echo '<p>' . esc_html__('Use the dropdowns below to change the schedule of each cron hook. Changes are saved automatically.', 'cron-scheduler') . '</p>';
    echo '<input type="text" id="cron-search" class="regular-text" placeholder="' . esc_attr__('Filter hooks...', 'cron-scheduler') . '" />';
    echo '<table class="wp-list-table widefat fixed striped" id="cron-table">';
    echo '<thead><tr><th>' . esc_html__('Hook', 'cron-scheduler') . '</th><th>' . esc_html__('Current Interval', 'cron-scheduler') . '</th><th>' . esc_html__('New Interval', 'cron-scheduler') . '</th></tr></thead>';
    echo '<tbody>';

    foreach ($cron as $timestamp => $cronhooks) {
        if (!is_array($cronhooks)) continue;
        foreach ($cronhooks as $hook => $args) {
            $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event($hook) : null;
            $existing_interval = $event && isset($event->interval) ? $event->interval : false;
            $saved_interval = get_option('cron_scheduler_interval_' . $hook, $existing_interval);
            echo '<tr><td>' . esc_html($hook) . '</td>';
            echo '<td>' . esc_html($existing_interval ?: __('One-time', 'cron-scheduler')) . '</td>';
            echo '<td><select class="cron-interval-select" data-hook="' . esc_attr($hook) . '">';
            foreach ($schedules as $key => $schedule) {
                $selected = ($key === $saved_interval) ? 'selected' : '';
                echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($schedule['display']) . '</option>';
            }
            echo '</select></td></tr>';
        }
    }

    echo '</tbody>';
    echo '<tfoot><tr><th>' . esc_html__('Hook', 'cron-scheduler') . '</th><th>' . esc_html__('Current Interval', 'cron-scheduler') . '</th><th>' . esc_html__('New Interval', 'cron-scheduler') . '</th></tr></tfoot>';
    echo '</table>';
    echo '<div class="tablenav">';
    echo '<div class="displaying-num">' . esc_html(count($cron)) . ' ' . esc_html__('items', 'cron-scheduler') . '</div>';
    echo '<div class="tablenav-pages" id="cron-pagination">';
    echo '<span class="pagination-links">';
    echo '<button type="button" class="first-page button" aria-label="' . esc_attr__('First page', 'cron-scheduler') . '">«</button>';
    echo '<button type="button" class="prev-page button" aria-label="' . esc_attr__('Previous page', 'cron-scheduler') . '">‹</button>';
    echo '<span class="paging-input"><span class="current-page">1</span> ' . esc_html__('of', 'cron-scheduler') . ' <span class="total-pages">1</span></span>';
    echo '<button type="button" class="next-page button" aria-label="' . esc_attr__('Next page', 'cron-scheduler') . '">›</button>';
    echo '<button type="button" class="last-page button" aria-label="' . esc_attr__('Last page', 'cron-scheduler') . '">»</button>';
    echo '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

add_action('wp_ajax_cron_scheduler_update', function () {
    check_ajax_referer('cron_scheduler_ajax', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $hook = sanitize_text_field($_POST['hook'] ?? '');
    $interval = sanitize_text_field($_POST['interval'] ?? '');
    if (!$hook || !$interval) wp_send_json_error('Missing data');

    update_option('cron_scheduler_interval_' . $hook, $interval);
    $next = wp_next_scheduled($hook);
    if ($next) wp_unschedule_event($next, $hook);
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(time(), $interval, $hook);
    }
    wp_send_json_success();
});

add_filter('cron_schedules', function ($schedules) {
    return array_merge($schedules, [
        'five_minutes' => ['interval' => 300, 'display' => 'Every 5 Minutes'],
        'ten_minutes' => ['interval' => 600, 'display' => 'Every 10 Minutes'],
        'fifteen_minutes' => ['interval' => 900, 'display' => 'Every 15 Minutes'],
        'thirty_minutes' => ['interval' => 1800, 'display' => 'Every 30 Minutes'],
        'hourly' => ['interval' => 3600, 'display' => 'Every Hour'],
        'two_hours' => ['interval' => 7200, 'display' => 'Every 2 Hours'],
        'three_hours' => ['interval' => 10800, 'display' => 'Every 3 Hours'],
        'four_hours' => ['interval' => 14400, 'display' => 'Every 4 Hours'],
        'twelve_hours' => ['interval' => 43200, 'display' => 'Every 12 Hours'],
        'daily' => ['interval' => 86400, 'display' => 'Once Daily'],
        'two_days' => ['interval' => 172800, 'display' => 'Every 2 Days'],
        'three_days' => ['interval' => 259200, 'display' => 'Every 3 Days'],
        'four_days' => ['interval' => 345600, 'display' => 'Every 4 Days'],
        'five_days' => ['interval' => 432000, 'display' => 'Every 5 Days'],
        'weekly' => ['interval' => 604800, 'display' => 'Once Weekly'],
        'biweekly' => ['interval' => 1209600, 'display' => 'Once Every 2 Weeks'],
        'monthly' => ['interval' => 2592000, 'display' => 'Once Every Month'],
        'bimonthly' => ['interval' => 5184000, 'display' => 'Once Every 2 Months'],
        'quarterly' => ['interval' => 7776000, 'display' => 'Once Every 3 Months'],
        'semiannually' => ['interval' => 15552000, 'display' => 'Once Every 6 Months'],
        'yearly' => ['interval' => 31536000, 'display' => 'Once Every Year']
    ]);
});