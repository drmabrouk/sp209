<?php
/**
 * Admin Controller handling page routing, form actions, and AJAX endpoints
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Admin_Controller {

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
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_sportedia_search_players', array($this, 'ajax_search_players'));
        add_action('wp_ajax_sportedia_add_session_player', array($this, 'ajax_add_session_player'));
        add_action('wp_ajax_sportedia_remove_session_player', array($this, 'ajax_remove_session_player'));
    }

    public function handle_form_submissions() {
        if (!isset($_POST['sportedia_action'])) {
            return;
        }

        if (!current_user_can('sportedia_manage') && !current_user_can('sportedia_manage_sessions')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sportedia'));
        }

        $action = sanitize_text_field($_POST['sportedia_action']);
        check_admin_referer('sportedia_action_nonce', 'sportedia_nonce');

        switch ($action) {
            // SPORTS
            case 'save_sport':
                $id = !empty($_POST['sport_id']) ? intval($_POST['sport_id']) : 0;
                $data = array(
                    'name' => sanitize_text_field($_POST['name']),
                    'description' => sanitize_textarea_field($_POST['description']),
                    'status' => sanitize_text_field($_POST['status']),
                );
                if ($id > 0) {
                    Sportedia_Model_Sport::update($id, $data);
                    $msg = 'sport_updated';
                } else {
                    Sportedia_Model_Sport::create($data);
                    $msg = 'sport_created';
                }
                wp_redirect(admin_url('admin.php?page=sportedia-sports&msg=' . $msg));
                exit;

            case 'delete_sport':
                $id = intval($_POST['sport_id']);
                Sportedia_Model_Sport::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-sports&msg=sport_deleted'));
                exit;

            // COACHES
            case 'save_coach':
                $id = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : 0;
                $data = array(
                    'coach_code' => sanitize_text_field($_POST['coach_code']),
                    'full_name' => sanitize_text_field($_POST['full_name']),
                    'sport_id' => !empty($_POST['sport_id']) ? intval($_POST['sport_id']) : null,
                    'specialization' => sanitize_text_field($_POST['specialization']),
                    'phone' => sanitize_text_field($_POST['phone']),
                    'email' => sanitize_email($_POST['email']),
                    'color_hex' => sanitize_text_field($_POST['color_hex']),
                    'status' => sanitize_text_field($_POST['status']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );
                if ($id > 0) {
                    Sportedia_Model_Coach::update($id, $data);
                    $msg = 'coach_updated';
                } else {
                    $res = Sportedia_Model_Coach::create($data);
                    $msg = is_wp_error($res) ? 'error&err_msg=' . urlencode($res->get_error_message()) : 'coach_created';
                }
                wp_redirect(admin_url('admin.php?page=sportedia-coaches&msg=' . $msg));
                exit;

            case 'delete_coach':
                $id = intval($_POST['coach_id']);
                Sportedia_Model_Coach::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-coaches&msg=coach_deleted'));
                exit;

            // GROUPS
            case 'save_group':
                $id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : 0;
                $data = array(
                    'name' => sanitize_text_field($_POST['name']),
                    'sport_id' => intval($_POST['sport_id']),
                    'coach_id' => !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null,
                    'level' => sanitize_text_field($_POST['level']),
                    'training_time' => sanitize_text_field($_POST['training_time']),
                    'max_capacity' => intval($_POST['max_capacity']),
                    'status' => sanitize_text_field($_POST['status']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );
                if ($id > 0) {
                    Sportedia_Model_Group::update($id, $data);
                    $msg = 'group_updated';
                } else {
                    Sportedia_Model_Group::create($data);
                    $msg = 'group_created';
                }
                wp_redirect(admin_url('admin.php?page=sportedia-groups&msg=' . $msg));
                exit;

            case 'delete_group':
                $id = intval($_POST['group_id']);
                Sportedia_Model_Group::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-groups&msg=group_deleted'));
                exit;

            // TIME SLOTS
            case 'save_timeslot':
                $id = !empty($_POST['timeslot_id']) ? intval($_POST['timeslot_id']) : 0;
                $data = array(
                    'display_name' => sanitize_text_field($_POST['display_name']),
                    'start_time' => sanitize_text_field($_POST['start_time']),
                    'end_time' => sanitize_text_field($_POST['end_time']),
                    'sport_id' => !empty($_POST['sport_id']) ? intval($_POST['sport_id']) : null,
                    'status' => sanitize_text_field($_POST['status']),
                );
                if ($id > 0) {
                    Sportedia_Model_TimeSlot::update($id, $data);
                    $msg = 'timeslot_updated';
                } else {
                    Sportedia_Model_TimeSlot::create($data);
                    $msg = 'timeslot_created';
                }
                wp_redirect(admin_url('admin.php?page=sportedia-time-slots&msg=' . $msg));
                exit;

            case 'delete_timeslot':
                $id = intval($_POST['timeslot_id']);
                Sportedia_Model_TimeSlot::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-time-slots&msg=timeslot_deleted'));
                exit;

            // PLAYERS
            case 'save_player':
                $id = !empty($_POST['player_id']) ? intval($_POST['player_id']) : 0;
                $data = array(
                    'player_code' => sanitize_text_field($_POST['player_code']),
                    'full_name' => sanitize_text_field($_POST['full_name']),
                    'gender' => sanitize_text_field($_POST['gender']),
                    'date_of_birth' => sanitize_text_field($_POST['date_of_birth']),
                    'age' => intval($_POST['age']),
                    'sport_id' => !empty($_POST['sport_id']) ? intval($_POST['sport_id']) : null,
                    'group_id' => !empty($_POST['group_id']) ? intval($_POST['group_id']) : null,
                    'level' => sanitize_text_field($_POST['level']),
                    'status' => sanitize_text_field($_POST['status']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );
                if ($id > 0) {
                    Sportedia_Model_Player::update($id, $data);
                    $msg = 'player_updated';
                } else {
                    $res = Sportedia_Model_Player::create($data);
                    $msg = is_wp_error($res) ? 'error&err_msg=' . urlencode($res->get_error_message()) : 'player_created';
                }
                wp_redirect(admin_url('admin.php?page=sportedia-players&msg=' . $msg));
                exit;

            case 'delete_player':
                $id = intval($_POST['player_id']);
                Sportedia_Model_Player::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-players&msg=player_deleted'));
                exit;

            // SESSIONS
            case 'save_session':
                $id = !empty($_POST['session_id']) ? intval($_POST['session_id']) : 0;
                $data = array(
                    'session_date' => sanitize_text_field($_POST['session_date']),
                    'sport_id' => intval($_POST['sport_id']),
                    'time_slot_id' => intval($_POST['time_slot_id']),
                    'group_id' => intval($_POST['group_id']),
                    'coach_id' => intval($_POST['coach_id']),
                    'status' => sanitize_text_field($_POST['status']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );

                if ($id > 0) {
                    Sportedia_Model_Session::update($id, $data);
                    $session_id = $id;
                    $msg = 'session_updated';
                } else {
                    $session_id = Sportedia_Model_Session::create($data);
                    $msg = is_wp_error($session_id) ? 'error&err_msg=' . urlencode($session_id->get_error_message()) : 'session_created';
                }

                if (is_numeric($session_id) && $session_id > 0) {
                    wp_redirect(admin_url('admin.php?page=sportedia-sessions&action=edit&id=' . $session_id . '&msg=' . $msg));
                } else {
                    wp_redirect(admin_url('admin.php?page=sportedia-sessions&msg=' . $msg));
                }
                exit;

            case 'duplicate_session':
                $id = intval($_POST['session_id']);
                $new_id = Sportedia_Model_Session::duplicate($id);
                if (is_wp_error($new_id)) {
                    wp_redirect(admin_url('admin.php?page=sportedia-sessions&msg=error&err_msg=' . urlencode($new_id->get_error_message())));
                } else {
                    wp_redirect(admin_url('admin.php?page=sportedia-sessions&action=edit&id=' . $new_id . '&msg=session_duplicated'));
                }
                exit;

            case 'delete_session':
                $id = intval($_POST['session_id']);
                Sportedia_Model_Session::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-sessions&msg=session_deleted'));
                exit;

            // EXPORT HISTORY
            case 'delete_export_history':
                $id = intval($_POST['history_id']);
                Sportedia_Model_Export_History::delete($id);
                wp_redirect(admin_url('admin.php?page=sportedia-export&msg=export_deleted'));
                exit;

            // SETTINGS
            case 'save_settings':
                update_option('sportedia_academy_name', sanitize_text_field($_POST['academy_name']));
                update_option('sportedia_date_format', sanitize_text_field($_POST['date_format']));
                update_option('sportedia_time_format', sanitize_text_field($_POST['time_format']));
                wp_redirect(admin_url('admin.php?page=sportedia-settings&msg=settings_saved'));
                exit;
        }
    }

    // AJAX: Search Players
    public function ajax_search_players() {
        check_ajax_referer('sportedia_nonce', 'nonce');
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        if (empty($term)) {
            wp_send_json_success(array());
        }

        $results = Sportedia_Model_Player::search($term, 15);
        wp_send_json_success($results);
    }

    // AJAX: Add Player to Session
    public function ajax_add_session_player() {
        check_ajax_referer('sportedia_nonce', 'nonce');

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;

        if (!$session_id || !$player_id) {
            wp_send_json_error(array('message' => __('Invalid Session or Player parameters.', 'sportedia')));
        }

        $res = Sportedia_Model_Session::add_player($session_id, $player_id);

        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()));
        }

        $players = Sportedia_Model_Session::get_players($session_id);
        wp_send_json_success(array(
            'message' => __('Player added successfully!', 'sportedia'),
            'players' => $players
        ));
    }

    // AJAX: Remove Player from Session
    public function ajax_remove_session_player() {
        check_ajax_referer('sportedia_nonce', 'nonce');

        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;

        if (!$session_id || !$player_id) {
            wp_send_json_error(array('message' => __('Invalid parameters.', 'sportedia')));
        }

        Sportedia_Model_Session::remove_player($session_id, $player_id);
        $players = Sportedia_Model_Session::get_players($session_id);

        wp_send_json_success(array(
            'message' => __('Player removed from session.', 'sportedia'),
            'players' => $players
        ));
    }
}

function sportedia_admin_controller() {
    return Sportedia_Admin_Controller::get_instance();
}

sportedia_admin_controller();
