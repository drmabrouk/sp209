<?php
/**
 * Plugin Name: Sportedia
 * Plugin URI: https://sportedia.com
 * Description: Professional Sports Academy Management System designed to record, organize, monitor, and report player participation across coaches, sports, groups, dates, and training time slots.
 * Version: 1.0.0
 * Author: Sportedia
 * Text Domain: sportedia
 * License: GPL-2.0+
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SPORTEDIA_VERSION', '1.0.0');
define('SPORTEDIA_PATH', plugin_dir_path(__FILE__));
define('SPORTEDIA_URL', plugin_dir_url(__FILE__));
define('SPORTEDIA_BASENAME', plugin_basename(__FILE__));

// Composer Autoloader
if (file_exists(SPORTEDIA_PATH . 'vendor/autoload.php')) {
    require_once SPORTEDIA_PATH . 'vendor/autoload.php';
}

require_once SPORTEDIA_PATH . 'includes/class-sportedia-activator.php';

// Core Utilities & Models
require_once SPORTEDIA_PATH . 'includes/utilities/class-sportedia-datetime.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-sport.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-coach.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-group.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-timeslot.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-package.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-player.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-session.php';
require_once SPORTEDIA_PATH . 'includes/models/class-sportedia-model-export-history.php';

// Admin Controllers & Views
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-admin-controller.php';
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-admin-views.php';
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-reports.php';
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-excel-import.php';
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-excel-export.php';
require_once SPORTEDIA_PATH . 'includes/admin/class-sportedia-settings.php';

register_activation_hook(__FILE__, array('Sportedia_Activator', 'activate'));

/**
 * Main Sportedia Core Class
 */
class Sportedia {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'register_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_post_sportedia_admin_action', array($this, 'handle_admin_post'));
        add_action('admin_post_sportedia_export_excel', array($this, 'handle_export_excel'));
        add_action('admin_post_sportedia_download_export_history', array($this, 'handle_download_export_history'));
    }

    public function handle_admin_post() {
        sportedia_admin_controller()->handle_form_submissions();
    }

    public function handle_export_excel() {
        if (!current_user_can('sportedia_import_export') && !current_user_can('sportedia_view_reports') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sportedia'));
        }

        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'complete_database';
        $filters = array(
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'sport_id' => isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 0,
            'coach_id' => isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0,
            'group_id' => isset($_GET['group_id']) ? intval($_GET['group_id']) : 0,
        );

        Sportedia_Excel_Export::generate_and_download_export($report_type, $filters);
    }

    public function handle_download_export_history() {
        if (!current_user_can('sportedia_import_export') && !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to download this file.', 'sportedia'));
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $record = Sportedia_Model_Export_History::get($id);

        if (!$record || empty($record->file_path) || !file_exists($record->file_path)) {
            wp_die(__('The requested export history file does not exist or has been deleted.', 'sportedia'));
        }

        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . esc_attr($record->file_name) . '"');
        header('Content-Length: ' . filesize($record->file_path));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($record->file_path);
        exit;
    }

    public function register_admin_menus() {
        // Parent Menu
        add_menu_page(
            __('Sportedia', 'sportedia'),
            __('Sportedia', 'sportedia'),
            'sportedia_view_dashboard',
            'sportedia',
            array('Sportedia_Admin_Views', 'render_dashboard'),
            'dashicons-groups',
            6
        );

        // Submenus
        add_submenu_page('sportedia', __('Dashboard', 'sportedia'), __('Dashboard', 'sportedia'), 'sportedia_view_dashboard', 'sportedia', array('Sportedia_Admin_Views', 'render_dashboard'));
        add_submenu_page('sportedia', __('Players', 'sportedia'), __('Players', 'sportedia'), 'sportedia_manage_players', 'sportedia-players', array('Sportedia_Admin_Views', 'render_players'));
        add_submenu_page('sportedia', __('Coaches', 'sportedia'), __('Coaches', 'sportedia'), 'sportedia_manage_coaches', 'sportedia-coaches', array('Sportedia_Admin_Views', 'render_coaches'));
        add_submenu_page('sportedia', __('Sports', 'sportedia'), __('Sports', 'sportedia'), 'sportedia_manage_sports', 'sportedia-sports', array('Sportedia_Admin_Views', 'render_sports'));
        add_submenu_page('sportedia', __('Groups', 'sportedia'), __('Groups', 'sportedia'), 'sportedia_manage_groups', 'sportedia-groups', array('Sportedia_Admin_Views', 'render_groups'));
        add_submenu_page('sportedia', __('Time Slots', 'sportedia'), __('Time Slots', 'sportedia'), 'sportedia_manage_timeslots', 'sportedia-time-slots', array('Sportedia_Admin_Views', 'render_timeslots'));
        add_submenu_page('sportedia', __('Sessions', 'sportedia'), __('Sessions', 'sportedia'), 'sportedia_manage_sessions', 'sportedia-sessions', array('Sportedia_Admin_Views', 'render_sessions'));
        add_submenu_page('sportedia', __('Reports', 'sportedia'), __('Reports', 'sportedia'), 'sportedia_view_reports', 'sportedia-reports', array('Sportedia_Reports', 'render_page'));
        add_submenu_page('sportedia', __('Excel Import', 'sportedia'), __('Excel Import', 'sportedia'), 'sportedia_import_export', 'sportedia-import', array('Sportedia_Excel_Import', 'render_page'));
        add_submenu_page('sportedia', __('Excel Export', 'sportedia'), __('Excel Export', 'sportedia'), 'sportedia_import_export', 'sportedia-export', array('Sportedia_Excel_Export', 'render_page'));
        add_submenu_page('sportedia', __('Settings', 'sportedia'), __('Settings', 'sportedia'), 'sportedia_manage_settings', 'sportedia-settings', array('Sportedia_Settings', 'render_page'));
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'sportedia') === false) {
            return;
        }

        wp_enqueue_style('sportedia-admin-css', SPORTEDIA_URL . 'assets/css/admin.css', array(), SPORTEDIA_VERSION);
        wp_enqueue_script('sportedia-admin-js', SPORTEDIA_URL . 'assets/js/admin.js', array('jquery'), SPORTEDIA_VERSION, true);

        wp_localize_script('sportedia-admin-js', 'sportediaVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sportedia_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this item? This action cannot be undone.', 'sportedia'),
        ));
    }
}

function sportedia() {
    return Sportedia::get_instance();
}

$GLOBALS['sportedia'] = sportedia();
