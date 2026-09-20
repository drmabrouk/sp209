<?php
/**
 * Export History Model
 * Stores history logs for generated Excel workbooks.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Export_History {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_export_history';
    }

    public static function log_export($file_name, $file_path, $report_type, $date_range, $file_size) {
        global $wpdb;
        $table = self::get_table_name();

        $current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $generated_by = ($current_user && !empty($current_user->display_name)) ? $current_user->display_name : 'System Administrator';

        $inserted = $wpdb->insert(
            $table,
            array(
                'file_name' => sanitize_file_name($file_name),
                'file_path' => sanitize_text_field($file_path),
                'report_type' => sanitize_text_field($report_type),
                'date_range' => sanitize_text_field($date_range),
                'generated_by' => sanitize_text_field($generated_by),
                'generation_time' => Sportedia_DateTime::now(),
                'file_size' => intval($file_size),
                'status' => 'available',
                'created_at' => Sportedia_DateTime::now(),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        return $inserted !== false ? $wpdb->insert_id : false;
    }

    public static function get($id) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)));
    }

    public static function get_all() {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    }

    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();

        $record = self::get($id);
        if ($record && !empty($record->file_path) && file_exists($record->file_path)) {
            @unlink($record->file_path);
        }

        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
}
