<?php
/**
 * Coach Model
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Coach {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_coaches';
    }

    public static function generate_code() {
        global $wpdb;
        $table = self::get_table_name();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $code = 'CCH-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        // Ensure uniqueness
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE coach_code = %s", $code));
        if ($exists) {
            $code = 'CCH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        return $code;
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $full_name = sanitize_text_field($data['full_name'] ?? '');
        if (empty($full_name)) {
            return new WP_Error('missing_field', __('Coach full name is required.', 'sportedia'));
        }

        $coach_code = !empty($data['coach_code']) ? sanitize_text_field($data['coach_code']) : self::generate_code();
        $sport_id = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
        $specialization = sanitize_text_field($data['specialization'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $color_hex = !empty($data['color_hex']) ? sanitize_hex_color($data['color_hex']) : '#3B82F6';
        $status = sanitize_text_field($data['status'] ?? 'active');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        // Check unique code
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE coach_code = %s", $coach_code));
        if ($existing) {
            return new WP_Error('duplicate_code', __('Coach ID/Code already exists.', 'sportedia'));
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'coach_code' => $coach_code,
                'full_name' => $full_name,
                'sport_id' => $sport_id,
                'specialization' => $specialization,
                'phone' => $phone,
                'email' => $email,
                'color_hex' => $color_hex ?: '#3B82F6',
                'status' => $status,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create coach.', 'sportedia'));
        }

        return $wpdb->insert_id;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = self::get_table_name();

        $update_data = array();
        $format = array();

        if (isset($data['full_name'])) {
            $update_data['full_name'] = sanitize_text_field($data['full_name']);
            $format[] = '%s';
        }
        if (isset($data['sport_id'])) {
            $update_data['sport_id'] = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
            $format[] = '%d';
        }
        if (isset($data['specialization'])) {
            $update_data['specialization'] = sanitize_text_field($data['specialization']);
            $format[] = '%s';
        }
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
            $format[] = '%s';
        }
        if (isset($data['email'])) {
            $update_data['email'] = sanitize_email($data['email']);
            $format[] = '%s';
        }
        if (isset($data['color_hex'])) {
            $update_data['color_hex'] = sanitize_hex_color($data['color_hex']) ?: '#3B82F6';
            $format[] = '%s';
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

        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, s.name as sport_name
             FROM $table c
             LEFT JOIN $table_sports s ON c.sport_id = s.id
             WHERE c.id = %d",
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
            $where[] = "c.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "c.sport_id = %d";
            $params[] = intval($args['sport_id']);
        }
        if (!empty($args['search'])) {
            $where[] = "(c.full_name LIKE %s OR c.coach_code LIKE %s OR c.specialization LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT c.*, s.name as sport_name
                FROM $table c
                LEFT JOIN $table_sports s ON c.sport_id = s.id
                WHERE $where_sql
                ORDER BY c.full_name ASC";

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
