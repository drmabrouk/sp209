<?php
/**
 * Session Model
 * Handles operational sessions and player assignment.
 * Incrementing session package usage upon player addition and decrementing upon removal.
 * Allows session assignment even when package is completed (recording as additional sessions).
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Session {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_sessions';
    }

    public static function get_session_players_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_session_players';
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $session_date = sanitize_text_field($data['session_date'] ?? '');
        $sport_id = intval($data['sport_id'] ?? 0);
        $time_slot_id = intval($data['time_slot_id'] ?? 0);
        $group_id = intval($data['group_id'] ?? 0);
        $coach_id = intval($data['coach_id'] ?? 0);

        if (empty($session_date) || !$sport_id || !$time_slot_id || !$group_id || !$coach_id) {
            return new WP_Error('missing_fields', __('Date, Sport, Time Slot, Group, and Coach are required fields.', 'sportedia'));
        }

        $session_date = date('Y-m-d', strtotime($session_date));
        $status = sanitize_text_field($data['status'] ?? 'completed');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        // Check duplicate session
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_date = %s AND time_slot_id = %d AND coach_id = %d AND group_id = %d",
            $session_date, $time_slot_id, $coach_id, $group_id
        ));

        if ($existing) {
            return $existing;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'session_date' => $session_date,
                'sport_id' => $sport_id,
                'time_slot_id' => $time_slot_id,
                'group_id' => $group_id,
                'coach_id' => $coach_id,
                'status' => $status,
                'notes' => $notes,
                'created_at' => Sportedia_DateTime::now(),
                'updated_at' => Sportedia_DateTime::now(),
            ),
            array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create session.', 'sportedia'));
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if (isset($data['session_date'])) {
            $update_data['session_date'] = date('Y-m-d', strtotime($data['session_date']));
            $format[] = '%s';
        }
        if (isset($data['sport_id'])) {
            $update_data['sport_id'] = intval($data['sport_id']);
            $format[] = '%d';
        }
        if (isset($data['time_slot_id'])) {
            $update_data['time_slot_id'] = intval($data['time_slot_id']);
            $format[] = '%d';
        }
        if (isset($data['group_id'])) {
            $update_data['group_id'] = intval($data['group_id']);
            $format[] = '%d';
        }
        if (isset($data['coach_id'])) {
            $update_data['coach_id'] = intval($data['coach_id']);
            $format[] = '%d';
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = Sportedia_DateTime::now();
        $format[] = '%s';

        return $wpdb->update($table, $update_data, array('id' => intval($id)), $format, array('%d'));
    }

    public static function add_player($session_id, $player_id) {
        global $wpdb;
        $table_sp = self::get_session_players_table_name();

        $session_id = intval($session_id);
        $player_id = intval($player_id);

        $session = self::get($session_id);
        if (!$session) {
            return new WP_Error('invalid_session', __('Session not found.', 'sportedia'));
        }

        $player = Sportedia_Model_Player::get($player_id);
        if (!$player) {
            return new WP_Error('invalid_player', __('Player not found.', 'sportedia'));
        }

        // Duplicate Check in same session
        $existing_in_session = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_sp WHERE session_id = %d AND player_id = %d",
            $session_id, $player_id
        ));

        if ($existing_in_session) {
            return new WP_Error('duplicate_assignment', sprintf(
                __('Player "%s" (%s) is already assigned to this session.', 'sportedia'),
                $player->full_name,
                $player->player_code
            ));
        }

        // Cross-Session Duplicate Check
        $duplicate_cross = null;
        if ($session && !empty($session->session_date)) {
            $table_sessions = self::get_table_name();
            $duplicate_cross = $wpdb->get_var($wpdb->prepare(
                "SELECT sp.id
                 FROM $table_sp sp
                 INNER JOIN $table_sessions s ON sp.session_id = s.id
                 WHERE sp.player_id = %d
                   AND s.session_date = %s
                   AND s.time_slot_id = %d
                   AND s.coach_id = %d
                   AND s.group_id = %d",
                $player_id,
                $session->session_date,
                $session->time_slot_id,
                $session->coach_id,
                $session->group_id
            ));
        }

        if ($duplicate_cross) {
            return new WP_Error('duplicate_assignment', sprintf(
                __('Player "%s" is already recorded for this exact Date, Time Slot, Coach, and Group.', 'sportedia'),
                $player->full_name
            ));
        }

        $inserted = $wpdb->insert(
            $table_sp,
            array(
                'session_id' => $session_id,
                'player_id' => $player_id,
                'created_at' => Sportedia_DateTime::now(),
            ),
            array('%d', '%d', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not add player to session.', 'sportedia'));
        }

        // Update player package usage credits (Increment usage)
        Sportedia_Model_Package::record_session_usage($player_id);

        return $wpdb->insert_id;
    }

    public static function remove_player($session_id, $player_id) {
        global $wpdb;
        $table_sp = self::get_session_players_table_name();

        $deleted = $wpdb->delete(
            $table_sp,
            array(
                'session_id' => intval($session_id),
                'player_id' => intval($player_id),
            ),
            array('%d', '%d')
        );

        if ($deleted) {
            // Decrement session package usage
            Sportedia_Model_Package::decrement_session_usage($player_id);
        }

        return $deleted;
    }

    public static function get_players($session_id) {
        global $wpdb;
        $table_sp = self::get_session_players_table_name();
        $table_p = Sportedia_Model_Player::get_table_name();
        $table_s = Sportedia_Model_Sport::get_table_name();
        $table_g = Sportedia_Model_Group::get_table_name();

        $players = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, sp.created_at as assigned_at, s.name as sport_name, g.name as group_name
             FROM $table_sp sp
             INNER JOIN $table_p p ON sp.player_id = p.id
             LEFT JOIN $table_s s ON p.sport_id = s.id
             LEFT JOIN $table_g g ON p.group_id = g.id
             WHERE sp.session_id = %d
             ORDER BY p.full_name ASC",
            intval($session_id)
        ));

        foreach ($players as &$p) {
            $p->package = Sportedia_Model_Package::get_active_package($p->id);
            $p->missing_fields = Sportedia_Model_Player::get_missing_fields($p);
        }

        return $players;
    }

    public static function get($id) {
        global $wpdb;
        $table = self::get_table_name();
        $table_s = Sportedia_Model_Sport::get_table_name();
        $table_ts = Sportedia_Model_TimeSlot::get_table_name();
        $table_g = Sportedia_Model_Group::get_table_name();
        $table_c = Sportedia_Model_Coach::get_table_name();
        $table_sp = self::get_session_players_table_name();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT s.*,
                    sp.name as sport_name,
                    ts.display_name as time_slot_name, ts.start_time, ts.end_time,
                    g.name as group_name,
                    c.full_name as coach_name, c.color_hex as coach_color, c.coach_code,
                    (SELECT COUNT(*) FROM $table_sp WHERE session_id = s.id) as player_count
             FROM $table s
             LEFT JOIN $table_s sp ON s.sport_id = sp.id
             LEFT JOIN $table_ts ts ON s.time_slot_id = ts.id
             LEFT JOIN $table_g g ON s.group_id = g.id
             LEFT JOIN $table_c c ON s.coach_id = c.id
             WHERE s.id = %d",
            intval($id)
        ));
    }

    public static function duplicate($session_id, $new_date = null) {
        $original = self::get($session_id);
        if (!$original) {
            return new WP_Error('not_found', __('Original session not found.', 'sportedia'));
        }

        $target_date = $new_date ?: $original->session_date;

        $new_session_id = self::create(array(
            'session_date' => $target_date,
            'sport_id' => $original->sport_id,
            'time_slot_id' => $original->time_slot_id,
            'group_id' => $original->group_id,
            'coach_id' => $original->coach_id,
            'status' => $original->status,
            'notes' => sprintf(__('Duplicated from session #%d', 'sportedia'), $original->id),
        ));

        if (is_wp_error($new_session_id)) {
            return $new_session_id;
        }

        $players = self::get_players($session_id);
        foreach ($players as $p) {
            self::add_player($new_session_id, $p->id);
        }

        return $new_session_id;
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $table_s = Sportedia_Model_Sport::get_table_name();
        $table_ts = Sportedia_Model_TimeSlot::get_table_name();
        $table_g = Sportedia_Model_Group::get_table_name();
        $table_c = Sportedia_Model_Coach::get_table_name();
        $table_sp = self::get_session_players_table_name();

        $where = array('1=1');
        $params = array();

        if (!empty($args['session_date'])) {
            $where[] = "s.session_date = %s";
            $params[] = $args['session_date'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "s.sport_id = %d";
            $params[] = intval($args['sport_id']);
        }
        if (!empty($args['coach_id'])) {
            $where[] = "s.coach_id = %d";
            $params[] = intval($args['coach_id']);
        }

        $where_sql = implode(' AND ', $where);

        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;

        $sql = "SELECT s.*,
                       sp.name as sport_name,
                       ts.display_name as time_slot_name, ts.start_time, ts.end_time,
                       g.name as group_name,
                       c.full_name as coach_name, c.color_hex as coach_color, c.coach_code,
                       (SELECT COUNT(*) FROM $table_sp WHERE session_id = s.id) as player_count
                FROM $table s
                LEFT JOIN $table_s sp ON s.sport_id = sp.id
                LEFT JOIN $table_ts ts ON s.time_slot_id = ts.id
                LEFT JOIN $table_g g ON s.group_id = g.id
                LEFT JOIN $table_c c ON s.coach_id = c.id
                WHERE $where_sql
                ORDER BY s.session_date DESC, ts.start_time ASC, s.id DESC";

        if ($limit > 0) {
            $sql .= " LIMIT $offset, $limit";
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    public static function count_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sp = self::get_session_players_table_name();

        $wpdb->delete($table_sp, array('session_id' => intval($id)), array('%d'));
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
}
