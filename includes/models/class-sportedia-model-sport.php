<?php
/**
 * Sport Model
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Sport {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_sports';
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $name = sanitize_text_field($data['name'] ?? '');
        $description = sanitize_textarea_field($data['description'] ?? '');
        $status = sanitize_text_field($data['status'] ?? 'active');

        if (empty($name)) {
            return new WP_Error('missing_field', __('Sport name is required.', 'sportedia'));
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create sport.', 'sportedia'));
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
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
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
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
    }

    public static function get_all($status = null) {
        global $wpdb;
        $table = self::get_table_name();

        if ($status) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = %s ORDER BY name ASC", $status));
        }

        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
}
