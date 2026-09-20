<?php
/**
 * Group Model
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Group {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_groups';
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $name = sanitize_text_field($data['name'] ?? '');
        $sport_id = intval($data['sport_id'] ?? 0);

        if (empty($name)) {
            return new WP_Error('missing_field', __('Group name is required.', 'sportedia'));
        }
        if (!$sport_id) {
            return new WP_Error('missing_field', __('Sport selection is required.', 'sportedia'));
        }

        $coach_id = !empty($data['coach_id']) ? intval($data['coach_id']) : null;
        $level = sanitize_text_field($data['level'] ?? '');
        $training_time = sanitize_text_field($data['training_time'] ?? '');
        $max_capacity = !empty($data['max_capacity']) ? intval($data['max_capacity']) : 20;
        $status = sanitize_text_field($data['status'] ?? 'active');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        $inserted = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'sport_id' => $sport_id,
                'coach_id' => $coach_id,
                'level' => $level,
                'training_time' => $training_time,
                'max_capacity' => $max_capacity,
                'status' => $status,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create group.', 'sportedia'));
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        if (isset($data['sport_id'])) {
            $update_data['sport_id'] = intval($data['sport_id']);
            $format[] = '%d';
        }
        if (isset($data['coach_id'])) {
            $update_data['coach_id'] = !empty($data['coach_id']) ? intval($data['coach_id']) : null;
            $format[] = '%d';
        }
        if (isset($data['level'])) {
            $update_data['level'] = sanitize_text_field($data['level']);
            $format[] = '%s';
        }
        if (isset($data['training_time'])) {
            $update_data['training_time'] = sanitize_text_field($data['training_time']);
            $format[] = '%s';
        }
        if (isset($data['max_capacity'])) {
            $update_data['max_capacity'] = intval($data['max_capacity']);
            $format[] = '%d';
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }
        if (isset($data['notes'])) {
            $update_data['notes'] = sanitize_textarea_field($data['notes']);
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
        $table_coaches = $wpdb->prefix . 'sportedia_coaches';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, s.name as sport_name, c.full_name as coach_name
             FROM $table g
             LEFT JOIN $table_sports s ON g.sport_id = s.id
             LEFT JOIN $table_coaches c ON g.coach_id = c.id
             WHERE g.id = %d",
            intval($id)
        ));
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';
        $table_coaches = $wpdb->prefix . 'sportedia_coaches';

        $where = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[] = "g.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "g.sport_id = %d";
            $params[] = intval($args['sport_id']);
        }
        if (!empty($args['coach_id'])) {
            $where[] = "g.coach_id = %d";
            $params[] = intval($args['coach_id']);
        }
        if (!empty($args['search'])) {
            $where[] = "(g.name LIKE %s OR g.level LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT g.*, s.name as sport_name, c.full_name as coach_name
                FROM $table g
                LEFT JOIN $table_sports s ON g.sport_id = s.id
                LEFT JOIN $table_coaches c ON g.coach_id = c.id
                WHERE $where_sql
                ORDER BY g.name ASC";

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
