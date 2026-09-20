<?php
/**
 * Fired during plugin activation & DB migrations.
 * Preserves existing data permanently across updates/reactivations.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Activator {

    public static function activate() {
        self::create_tables();
        self::add_roles_and_capabilities();
        self::set_default_options();
        self::create_protected_upload_dir();
        flush_rewrite_rules();
    }

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Sports
        $table_sports = $wpdb->prefix . 'sportedia_sports';
        $sql_sports = "CREATE TABLE IF NOT EXISTS $table_sports (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_sports);

        // 2. Coaches
        $table_coaches = $wpdb->prefix . 'sportedia_coaches';
        $sql_coaches = "CREATE TABLE IF NOT EXISTS $table_coaches (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            coach_code VARCHAR(50) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            sport_id BIGINT(20) UNSIGNED NULL,
            specialization VARCHAR(100) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(100) NULL,
            color_hex VARCHAR(7) NOT NULL DEFAULT '#3B82F6',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY coach_code_unique (coach_code),
            KEY sport_id_idx (sport_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_coaches);

        // 3. Groups
        $table_groups = $wpdb->prefix . 'sportedia_groups';
        $sql_groups = "CREATE TABLE IF NOT EXISTS $table_groups (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            sport_id BIGINT(20) UNSIGNED NOT NULL,
            coach_id BIGINT(20) UNSIGNED NULL,
            level VARCHAR(50) NULL,
            training_time VARCHAR(100) NULL,
            max_capacity INT(11) NOT NULL DEFAULT 20,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sport_id_idx (sport_id),
            KEY coach_id_idx (coach_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_groups);

        // 4. Time Slots
        $table_time_slots = $wpdb->prefix . 'sportedia_time_slots';
        $sql_time_slots = "CREATE TABLE IF NOT EXISTS $table_time_slots (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            display_name VARCHAR(100) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            sport_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sport_id_idx (sport_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_time_slots);

        // 5. Players (Flexible: Full Name mandatory, everything else optional)
        $table_players = $wpdb->prefix . 'sportedia_players';
        $sql_players = "CREATE TABLE IF NOT EXISTS $table_players (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            player_code VARCHAR(50) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            gender VARCHAR(20) NULL DEFAULT NULL,
            date_of_birth DATE NULL DEFAULT NULL,
            age INT(11) NULL DEFAULT NULL,
            sport_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            group_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            level VARCHAR(50) NULL DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY player_code_unique (player_code),
            KEY full_name_idx (full_name),
            KEY sport_id_idx (sport_id),
            KEY group_id_idx (group_id),
            KEY level_idx (level),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_players);

        // 6. Sessions
        $table_sessions = $wpdb->prefix . 'sportedia_sessions';
        $sql_sessions = "CREATE TABLE IF NOT EXISTS $table_sessions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_date DATE NOT NULL,
            sport_id BIGINT(20) UNSIGNED NOT NULL,
            time_slot_id BIGINT(20) UNSIGNED NOT NULL,
            group_id BIGINT(20) UNSIGNED NOT NULL,
            coach_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session (session_date, time_slot_id, coach_id, group_id),
            KEY session_date_idx (session_date),
            KEY sport_id_idx (sport_id),
            KEY time_slot_id_idx (time_slot_id),
            KEY group_id_idx (group_id),
            KEY coach_id_idx (coach_id)
        ) $charset_collate;";
        dbDelta($sql_sessions);

        // 7. Session Players
        $table_session_players = $wpdb->prefix . 'sportedia_session_players';
        $sql_session_players = "CREATE TABLE IF NOT EXISTS $table_session_players (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT(20) UNSIGNED NOT NULL,
            player_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_player (session_id, player_id),
            KEY session_id_idx (session_id),
            KEY player_id_idx (player_id)
        ) $charset_collate;";
        dbDelta($sql_session_players);

        // 8. Player Session Packages
        $table_player_packages = $wpdb->prefix . 'sportedia_player_packages';
        $sql_player_packages = "CREATE TABLE IF NOT EXISTS $table_player_packages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id BIGINT(20) UNSIGNED NOT NULL,
            package_name VARCHAR(100) NOT NULL DEFAULT 'Standard Package',
            total_sessions INT(11) NOT NULL DEFAULT 8,
            used_sessions INT(11) NOT NULL DEFAULT 0,
            additional_sessions INT(11) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            completed_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY player_id_idx (player_id),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_player_packages);

        // 9. Excel Export History
        $table_export_history = $wpdb->prefix . 'sportedia_export_history';
        $sql_export_history = "CREATE TABLE IF NOT EXISTS $table_export_history (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            report_type VARCHAR(100) NOT NULL,
            date_range VARCHAR(100) NULL,
            generated_by VARCHAR(150) NOT NULL DEFAULT 'System Administrator',
            generation_time DATETIME NOT NULL,
            file_size BIGINT(20) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'available',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_type_idx (report_type),
            KEY status_idx (status)
        ) $charset_collate;";
        dbDelta($sql_export_history);
    }

    public static function create_protected_upload_dir() {
        if (function_exists('wp_upload_dir')) {
            $upload = wp_upload_dir();
            $dir = $upload['basedir'] . '/sportedia-exports';
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            // Protect directory against direct browser directory listing / access
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\n<Files *>\n  Order Allow,Deny\n  Deny from all\n</Files>\n");
            }
            $index = $dir . '/index.php';
            if (!file_exists($index)) {
                @file_put_contents($index, "<?php // Silence is golden.");
            }
        }
    }

    public static function add_roles_and_capabilities() {
        $capabilities = array(
            'sportedia_manage' => true,
            'sportedia_view_dashboard' => true,
            'sportedia_manage_players' => true,
            'sportedia_manage_coaches' => true,
            'sportedia_manage_sports' => true,
            'sportedia_manage_groups' => true,
            'sportedia_manage_timeslots' => true,
            'sportedia_manage_sessions' => true,
            'sportedia_view_reports' => true,
            'sportedia_import_export' => true,
            'sportedia_manage_settings' => true,
        );

        $admin = get_role('administrator');
        if ($admin) {
            foreach ($capabilities as $cap => $grant) {
                $admin->add_cap($cap);
            }
        }

        // Sports Supervisor Role
        add_role(
            'sports_supervisor',
            __('Sports Supervisor', 'sportedia'),
            array(
                'read' => true,
                'sportedia_manage' => true,
                'sportedia_view_dashboard' => true,
                'sportedia_manage_players' => true,
                'sportedia_manage_coaches' => true,
                'sportedia_manage_sports' => true,
                'sportedia_manage_groups' => true,
                'sportedia_manage_timeslots' => true,
                'sportedia_manage_sessions' => true,
                'sportedia_view_reports' => true,
                'sportedia_import_export' => true,
            )
        );
    }

    public static function set_default_options() {
        if (get_option('sportedia_academy_name') === false) {
            update_option('sportedia_academy_name', 'Sportedia Sports Academy');
        }
        if (get_option('sportedia_date_format') === false) {
            update_option('sportedia_date_format', 'Y-m-d');
        }
        if (get_option('sportedia_time_format') === false) {
            update_option('sportedia_time_format', 'h:i A');
        }
        if (get_option('sportedia_timezone') === false) {
            update_option('sportedia_timezone', 'Asia/Dubai');
        }
    }
}
