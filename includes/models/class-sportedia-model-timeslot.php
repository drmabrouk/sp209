<?php
/**
 * TimeSlot Model
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_TimeSlot {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_time_slots';
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $display_name = sanitize_text_field($data['display_name'] ?? '');
        $start_time = sanitize_text_field($data['start_time'] ?? '');
        $end_time = sanitize_text_field($data['end_time'] ?? '');

        if (empty($display_name)) {
            return new WP_Error('missing_field', __('Time slot display name is required.', 'sportedia'));
        }
        if (empty($start_time) || empty($end_time)) {
            return new WP_Error('missing_field', __('Start time and end time are required.', 'sportedia'));
        }

        // Format times to HH:MM:SS
        $start_time_formatted = date('H:i:s', strtotime($start_time));
        $end_time_formatted = date('H:i:s', strtotime($end_time));

        $sport_id = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
        $status = sanitize_text_field($data['status'] ?? 'active');

        $inserted = $wpdb->insert(
            $table,
            array(
                'display_name' => $display_name,
                'start_time' => $start_time_formatted,
                'end_time' => $end_time_formatted,
                'sport_id' => $sport_id,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create time slot.', 'sportedia'));
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if (isset($data['display_name'])) {
            $update_data['display_name'] = sanitize_text_field($data['display_name']);
            $format[] = '%s';
        }
        if (isset($data['start_time'])) {
            $update_data['start_time'] = date('H:i:s', strtotime($data['start_time']));
            $format[] = '%s';
        }
        if (isset($data['end_time'])) {
            $update_data['end_time'] = date('H:i:s', strtotime($data['end_time']));
            $format[] = '%s';
        }
        if (isset($data['sport_id'])) {
            $update_data['sport_id'] = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
            $format[] = '%d';
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        return $wpdb->update($table, $update_data, array('id' => intval($id)), $format, array('%d'));
    }

    public static function get($id) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, s.name as sport_name
             FROM $table t
             LEFT JOIN $table_sports s ON t.sport_id = s.id
             WHERE t.id = %d",
            intval($id)
        ));
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';

        $where = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[] = "t.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "t.sport_id = %d";
            $params[] = intval($args['sport_id']);
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT t.*, s.name as sport_name
                FROM $table t
                LEFT JOIN $table_sports s ON t.sport_id = s.id
                WHERE $where_sql
                ORDER BY t.start_time ASC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
}
