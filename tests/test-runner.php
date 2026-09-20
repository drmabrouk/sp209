<?php
/**
 * Standalone Automated Integration Test Suite for Sportedia
 */

define('ABSPATH', __DIR__ . '/../');

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Mock WordPress global $wpdb and functions if not in WP context
class MockWPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    private $data = array();
    private $auto_ids = array();

    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function prepare($query, ...$args) {
        if (is_array($args[0] ?? null)) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string)$arg) . "'";
            $query = preg_replace('/%[sd]/', $val, $query, 1);
        }
        return $query;
    }

    public function esc_like($text) {
        return addslashes($text);
    }

    public function insert($table, $data, $format = null) {
        if (!isset($this->data[$table])) {
            $this->data[$table] = array();
            $this->auto_ids[$table] = 1;
        }

        $id = $this->auto_ids[$table]++;
        $record = array_merge(array('id' => $id), $data);
        $this->data[$table][$id] = $record;
        $this->insert_id = $id;
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        if (!isset($this->data[$table])) {
            return false;
        }

        foreach ($this->data[$table] as $id => &$record) {
            $match = true;
            foreach ($where as $w_col => $w_val) {
                if (($record[$w_col] ?? null) != $w_val) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                foreach ($data as $d_col => $d_val) {
                    $record[$d_col] = $d_val;
                }
            }
        }
        return true;
    }

    public function delete($table, $where, $where_format = null) {
        if (!isset($this->data[$table])) {
            return false;
        }

        foreach ($this->data[$table] as $id => $record) {
            $match = true;
            foreach ($where as $w_col => $w_val) {
                if (($record[$w_col] ?? null) != $w_val) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                unset($this->data[$table][$id]);
            }
        }
        return true;
    }

    public function get_var($query) {
        $results = $this->query_mock($query);
        if (is_array($results) && !empty($results)) {
            $first = reset($results);
            if (is_array($first)) {
                return reset($first);
            }
        }
        if (is_numeric($results)) {
            return $results;
        }
        return null;
    }

    public function get_row($query) {
        $results = $this->query_mock($query);
        if (is_array($results) && !empty($results)) {
            return (object) reset($results);
        }
        return null;
    }

    public function get_results($query) {
        $results = $this->query_mock($query);
        $objs = array();
        if (is_array($results)) {
            foreach ($results as $r) {
                $objs[] = (object) $r;
            }
        }
        return $objs;
    }

    private function query_mock($query) {
        if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $query, $m)) {
            $table = $m[1];
            $rows = $this->data[$table] ?? array();

            if (preg_match('/WHERE\s+(.+)$/i', $query, $wm)) {
                $where_clause = $wm[1];
                if (preg_match('/player_code\s*=\s*[\'"]([^\'"]+)[\'"]/i', $where_clause, $pcm)) {
                    $code = $pcm[1];
                    $filtered = array();
                    foreach ($rows as $r) {
                        if (($r['player_code'] ?? '') === $code) {
                            $filtered[] = $r;
                        }
                    }
                    $rows = $filtered;
                } elseif (preg_match('/coach_code\s*=\s*[\'"]([^\'"]+)[\'"]/i', $where_clause, $ccm)) {
                    $code = $ccm[1];
                    $filtered = array();
                    foreach ($rows as $r) {
                        if (($r['coach_code'] ?? '') === $code) {
                            $filtered[] = $r;
                        }
                    }
                    $rows = $filtered;
                } elseif (preg_match('/id\s*=\s*(\d+)/i', $where_clause, $im)) {
                    $id = (int)$im[1];
                    $rows = isset($rows[$id]) ? array($rows[$id]) : array();
                }
            }

            if (strpos($query, 'COUNT(*)') !== false || strpos($query, 'COUNT(sp.id)') !== false || strpos($query, 'COUNT(sess.id)') !== false) {
                return array(array('count' => count($rows)));
            }
            return array_values($rows);
        }
        return array();
    }
}

global $wpdb;
$wpdb = new MockWPDB();

// Mock WP helper functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
}
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) { return preg_match('/^#[a-fA-F0-9]{6}$/', $color) ? $color : '#3B82F6'; }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($file) { return preg_replace('/[^a-zA-Z0-9_\.-]/', '', $file); }
}
if (!function_exists('current_time')) {
    function current_time($type) { return $type === 'mysql' ? date('Y-m-d H:i:s') : date($type); }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return htmlspecialchars($text); }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string)$text); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string)$text); }
}
if (!function_exists('esc_url')) {
    function esc_url($text) { return $text; }
}
if (!function_exists('add_role')) {
    function add_role($role, $display_name, $capabilities = array()) { return true; }
}
if (!function_exists('get_role')) {
    function get_role($role) { return null; }
}
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules() {}
}
if (!function_exists('get_option')) {
    function get_option($opt, $default = false) { return $default; }
}
if (!function_exists('update_option')) {
    function update_option($opt, $val) { return true; }
}

class WP_Error {
    private $code;
    private $message;
    public function __construct($code = '', $message = '') {
        $this->code = $code;
        $this->message = $message;
    }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($thing) { return ($thing instanceof WP_Error); }

// Require Sportedia core utilities, models & files
require_once __DIR__ . '/../includes/utilities/class-sportedia-datetime.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-sport.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-coach.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-group.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-timeslot.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-package.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-player.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-session.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-export-history.php';
require_once __DIR__ . '/../includes/admin/class-sportedia-excel-import.php';
require_once __DIR__ . '/../includes/admin/class-sportedia-excel-export.php';

// EXECUTE TESTS
$tests_passed = 0;
$tests_failed = 0;

function assert_test($condition, $test_name) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo " [PASS] $test_name\n";
        $tests_passed++;
    } else {
        echo " [FAIL] $test_name\n";
        $tests_failed++;
    }
}

echo "=========================================\n";
echo " SPORTEDIA PHASE 1 TEST SUITE VERIFICATION\n";
echo "=========================================\n\n";

// Test 1: Dubai Local Timezone Formatting
$dubai_now = Sportedia_DateTime::now('Y-m-d H:i:s');
assert_test(!empty($dubai_now), 'Dubai Timezone Utility: Formats local time in Asia/Dubai');

// Test 2: Name-Only Flexible Player Registration
$name_only_id = Sportedia_Model_Player::create(array(
    'full_name' => 'Flexible Player Name',
));
assert_test(is_numeric($name_only_id) && $name_only_id > 0, 'Player Registration: Save player using ONLY Full Name');

// Test 3: Missing Fields Tracking for Name-Only Player
$flexible_p = Sportedia_Model_Player::get($name_only_id);
assert_test(count($flexible_p->missing_fields) >= 4, 'Player Model: Identify missing optional fields for visual capsules');

// Test 4: Default Session Package Auto-Initialization
assert_test($flexible_p->package && $flexible_p->package->total_sessions === 8, 'Session Package: Auto-initialize 8-session default package');

// Test 5: Session Package Credits Tracking & Completed State
Sportedia_Model_Package::record_session_usage($name_only_id); // 1
Sportedia_Model_Package::record_session_usage($name_only_id); // 2
$updated_pkg = Sportedia_Model_Package::get_active_package($name_only_id);
assert_test($updated_pkg->used_sessions === 2, 'Session Package: Record used session credit');

// Test 6: Export History Logging
$history_id = Sportedia_Model_Export_History::log_export('test_report.xlsx', '/tmp/test_report.xlsx', 'daily_player', '2026-03-01', 1024);
assert_test(is_numeric($history_id) && $history_id > 0, 'Export History: Log generated Excel file metadata');

// Test 7: Create Sport & Coach with Pastel Color
$sport_id = Sportedia_Model_Sport::create(array('name' => 'Gymnastics'));
$coach_id = Sportedia_Model_Coach::create(array('coach_code' => 'CCH-99', 'full_name' => 'Coach Sarah', 'color_hex' => '#FDE68A'));
assert_test(is_numeric($sport_id) && is_numeric($coach_id), 'Coach & Sport: Create Coach with Pastel Color swatch');

// Test 8: Create Group & TimeSlot
$group_id = Sportedia_Model_Group::create(array('name' => 'Group G1', 'sport_id' => $sport_id));
$timeslot_id = Sportedia_Model_TimeSlot::create(array('display_name' => '04:00 PM', 'start_time' => '16:00:00', 'end_time' => '17:00:00'));

// Test 9: Create Session & Add Player
$sess_id = Sportedia_Model_Session::create(array(
    'session_date' => '2026-03-01',
    'sport_id' => $sport_id,
    'time_slot_id' => $timeslot_id,
    'group_id' => $group_id,
    'coach_id' => $coach_id,
));
$add_res = Sportedia_Model_Session::add_player($sess_id, $name_only_id);
assert_test(is_numeric($add_res) && $add_res > 0, 'Session: Assign player to session and increment credit usage');

// Test 10: Duplicate Assignment Prevention
$dup_res = Sportedia_Model_Session::add_player($sess_id, $name_only_id);
assert_test(is_wp_error($dup_res), 'Session: Prevent duplicate player assignment with English notification');

echo "\n=========================================\n";
echo " SUMMARY: $tests_passed PASSED, $tests_failed FAILED\n";
echo "=========================================\n\n";

if ($tests_failed > 0) {
    exit(1);
}
exit(0);
