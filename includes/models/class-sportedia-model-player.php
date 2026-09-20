<?php
/**
 * Player Model
 * Player registration is fully flexible: Full Name is the ONLY mandatory field.
 * All other fields are optional. Missing fields are tracked for visual pastel status capsules.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Model_Player {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'sportedia_players';
    }

    public static function generate_code() {
        global $wpdb;
        $table = self::get_table_name();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $code = 'PLY-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE player_code = %s", $code));
        if ($exists) {
            $code = 'PLY-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        }

        return $code;
    }

    public static function calculate_age($dob) {
        if (empty($dob)) {
            return 0;
        }
        try {
            $birth_date = new DateTime($dob, Sportedia_DateTime::get_timezone());
            $today = new DateTime('today', Sportedia_DateTime::get_timezone());
            return $birth_date->diff($today)->y;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function get_missing_fields($player) {
        $missing = array();
        if (empty($player->gender)) { $missing[] = __('Gender', 'sportedia'); }
        if (empty($player->date_of_birth)) { $missing[] = __('DOB', 'sportedia'); }
        if (empty($player->sport_id)) { $missing[] = __('Sport', 'sportedia'); }
        if (empty($player->group_id)) { $missing[] = __('Group', 'sportedia'); }
        if (empty($player->level)) { $missing[] = __('Level', 'sportedia'); }
        return $missing;
    }

    public static function create($data) {
        global $wpdb;
        $table = self::get_table_name();

        $full_name = sanitize_text_field($data['full_name'] ?? '');
        if (empty($full_name)) {
            return new WP_Error('missing_field', __('Player Full Name is mandatory.', 'sportedia'));
        }

        $player_code = !empty($data['player_code']) ? sanitize_text_field($data['player_code']) : self::generate_code();
        $gender = !empty($data['gender']) ? sanitize_text_field($data['gender']) : null;
        $date_of_birth = !empty($data['date_of_birth']) ? sanitize_text_field($data['date_of_birth']) : null;
        $age = !empty($data['age']) ? intval($data['age']) : ($date_of_birth ? self::calculate_age($date_of_birth) : null);
        $sport_id = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
        $group_id = !empty($data['group_id']) ? intval($data['group_id']) : null;
        $level = !empty($data['level']) ? sanitize_text_field($data['level']) : null;
        $status = sanitize_text_field($data['status'] ?? 'active');
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        // Ensure player code uniqueness
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE player_code = %s", $player_code));
        if ($existing) {
            $player_code = self::generate_code();
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'player_code' => $player_code,
                'full_name' => $full_name,
                'gender' => $gender,
                'date_of_birth' => $date_of_birth,
                'age' => $age,
                'sport_id' => $sport_id,
                'group_id' => $group_id,
                'level' => $level,
                'status' => $status,
                'notes' => $notes,
                'created_at' => Sportedia_DateTime::now(),
                'updated_at' => Sportedia_DateTime::now(),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return new WP_Error('db_error', __('Could not create player record.', 'sportedia'));
        }

        $player_id = $wpdb->insert_id;

        // Auto-initialize Default Session Package (8 Sessions)
        $pkg_sessions = !empty($data['package_sessions']) ? intval($data['package_sessions']) : 8;
        $pkg_name = sprintf(__('%d Sessions Package', 'sportedia'), $pkg_sessions);
        Sportedia_Model_Package::create($player_id, $pkg_name, $pkg_sessions);

        return $player_id;
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
        if (isset($data['gender'])) {
            $update_data['gender'] = !empty($data['gender']) ? sanitize_text_field($data['gender']) : null;
            $format[] = '%s';
        }
        if (isset($data['date_of_birth'])) {
            $dob = sanitize_text_field($data['date_of_birth']);
            $update_data['date_of_birth'] = $dob ?: null;
            $format[] = '%s';
            if (!isset($data['age'])) {
                $update_data['age'] = $dob ? self::calculate_age($dob) : null;
                $format[] = '%d';
            }
        }
        if (isset($data['age'])) {
            $update_data['age'] = !empty($data['age']) ? intval($data['age']) : null;
            $format[] = '%d';
        }
        if (isset($data['sport_id'])) {
            $update_data['sport_id'] = !empty($data['sport_id']) ? intval($data['sport_id']) : null;
            $format[] = '%d';
        }
        if (isset($data['group_id'])) {
            $update_data['group_id'] = !empty($data['group_id']) ? intval($data['group_id']) : null;
            $format[] = '%d';
        }
        if (isset($data['level'])) {
            $update_data['level'] = !empty($data['level']) ? sanitize_text_field($data['level']) : null;
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

        $update_data['updated_at'] = Sportedia_DateTime::now();
        $format[] = '%s';

        return $wpdb->update($table, $update_data, array('id' => intval($id)), $format, array('%d'));
    }

    public static function get($id) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';
        $table_groups = $wpdb->prefix . 'sportedia_groups';

        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, s.name as sport_name, g.name as group_name
             FROM $table p
             LEFT JOIN $table_sports s ON p.sport_id = s.id
             LEFT JOIN $table_groups g ON p.group_id = g.id
             WHERE p.id = %d",
            intval($id)
        ));

        if ($player) {
            $player->package = Sportedia_Model_Package::get_active_package($player->id);
            $player->missing_fields = self::get_missing_fields($player);
        }

        return $player;
    }

    public static function search($term, $limit = 15) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';
        $table_groups = $wpdb->prefix . 'sportedia_groups';

        $like = '%' . $wpdb->esc_like(trim($term)) . '%';

        $sql = "SELECT p.*, s.name as sport_name, g.name as group_name
                FROM $table p
                LEFT JOIN $table_sports s ON p.sport_id = s.id
                LEFT JOIN $table_groups g ON p.group_id = g.id
                WHERE (p.full_name LIKE %s OR p.player_code LIKE %s)
                AND p.status = 'active'
                ORDER BY p.full_name ASC
                LIMIT %d";

        $players = $wpdb->get_results($wpdb->prepare($sql, $like, $like, intval($limit)));

        foreach ($players as &$p) {
            $p->package = Sportedia_Model_Package::get_active_package($p->id);
            $p->missing_fields = self::get_missing_fields($p);
        }

        return $players;
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();
        $table_sports = $wpdb->prefix . 'sportedia_sports';
        $table_groups = $wpdb->prefix . 'sportedia_groups';

        $where = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[] = "p.status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "p.sport_id = %d";
            $params[] = intval($args['sport_id']);
        }
        if (!empty($args['group_id'])) {
            $where[] = "p.group_id = %d";
            $params[] = intval($args['group_id']);
        }
        if (!empty($args['search'])) {
            $where[] = "(p.full_name LIKE %s OR p.player_code LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);

        $limit = isset($args['limit']) ? intval($args['limit']) : 20;
        $offset = isset($args['offset']) ? intval($args['offset']) : 0;

        $sql = "SELECT p.*, s.name as sport_name, g.name as group_name
                FROM $table p
                LEFT JOIN $table_sports s ON p.sport_id = s.id
                LEFT JOIN $table_groups g ON p.group_id = g.id
                WHERE $where_sql
                ORDER BY p.id DESC";

        if ($limit > 0) {
            $sql .= " LIMIT $offset, $limit";
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $players = $wpdb->get_results($sql);

        foreach ($players as &$p) {
            $p->package = Sportedia_Model_Package::get_active_package($p->id);
            $p->missing_fields = self::get_missing_fields($p);
        }

        return $players;
    }

    public static function count_all($args = array()) {
        global $wpdb;
        $table = self::get_table_name();

        $where = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $params[] = $args['status'];
        }
        if (!empty($args['sport_id'])) {
            $where[] = "sport_id = %d";
            $params[] = intval($args['sport_id']);
        }
        if (!empty($args['search'])) {
            $where[] = "(full_name LIKE %s OR player_code LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function delete($id) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
}
