<?php
/**
 * Player Package Model
 * Handles predefined (8, 12, 24) and custom session packages, session usage tracking,
 * completed package state, and additional sessions without blocking.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Package {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_player_packages';
    }

    public static function create($player_id, $package_name = 'Standard Package', $total_sessions = 8) {
        global $wpdb;
        $table = self::get_table_name();

        $player_id = intval($player_id);
        $total_sessions = max(1, intval($total_sessions));
        $package_name = sanitize_text_field($package_name);

        // Mark any existing active packages as completed or replaced
        $wpdb->update(
            $table,
            array('status' => 'completed', 'updated_at' => Sportedia_DateTime::now()),
            array('player_id' => $player_id, 'status' => 'active'),
            array('%s', '%s'),
            array('%d', '%s')
        );

        $inserted = $wpdb->insert(
            $table,
            array(
                'player_id' => $player_id,
                'package_name' => $package_name,
                'total_sessions' => $total_sessions,
                'used_sessions' => 0,
                'additional_sessions' => 0,
                'status' => 'active',
                'completed_at' => null,
                'created_at' => Sportedia_DateTime::now(),
                'updated_at' => Sportedia_DateTime::now(),
            ),
            array('%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create session package.', 'sportedia'));
        }

        return $wpdb->insert_id;
    }

    public static function get_active_package($player_id) {
        global $wpdb;
        $table = self::get_table_name();

        $package = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE player_id = %d ORDER BY id DESC LIMIT 1",
            intval($player_id)
        ));

        if (!$package) {
            // Auto-create default 8-session package if none exists
            $pkg_id = self::create($player_id, '8 Sessions Package', 8);
            if (!is_wp_error($pkg_id)) {
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $pkg_id));
            }
        }

        return $package;
    }

    public static function record_session_usage($player_id) {
        global $wpdb;
        $table = self::get_table_name();

        $package = self::get_active_package($player_id);
        if (!$package) {
            return false;
        }

        $used = $package->used_sessions + 1;
        $additional = $package->additional_sessions;
        $status = $package->status;
        $completed_at = $package->completed_at;

        if ($used >= $package->total_sessions) {
            $status = 'completed';
            if (empty($completed_at)) {
                $completed_at = Sportedia_DateTime::now();
            }
            if ($used > $package->total_sessions) {
                $additional++;
            }
        }

        return $wpdb->update(
            $table,
            array(
                'used_sessions' => $used,
                'additional_sessions' => $additional,
                'status' => $status,
                'completed_at' => $completed_at,
                'updated_at' => Sportedia_DateTime::now(),
            ),
            array('id' => $package->id),
            array('%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );
    }

    public static function decrement_session_usage($player_id) {
        global $wpdb;
        $table = self::get_table_name();

        $package = self::get_active_package($player_id);
        if (!$package || $package->used_sessions <= 0) {
            return false;
        }

        $used = $package->used_sessions - 1;
        $additional = max(0, $package->additional_sessions - 1);
        $status = $used >= $package->total_sessions ? 'completed' : 'active';

        return $wpdb->update(
            $table,
            array(
                'used_sessions' => $used,
                'additional_sessions' => $additional,
                'status' => $status,
                'updated_at' => Sportedia_DateTime::now(),
            ),
            array('id' => $package->id),
            array('%d', '%d', '%s', '%s'),
            array('%d')
        );
    }
}
