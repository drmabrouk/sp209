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
        // Table matching
        if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $query, $m)) {
            $table = $m[1];
            $rows = $this->data[$table] ?? array();

            // Handle WHERE conditions simplified for testing
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

// Require Sportedia models & files
require_once __DIR__ . '/../includes/models/class-sportedia-model-sport.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-coach.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-group.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-timeslot.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-player.php';
require_once __DIR__ . '/../includes/models/class-sportedia-model-session.php';
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
echo " SPORTEDIA PLUGIN TEST SUITE VERIFICATION\n";
echo "=========================================\n\n";

// Test 1: Create Sport
$sport_id = Sportedia_Model_Sport::create(array(
    'name' => 'Swimming',
    'description' => 'Olympic Swimming Pool Lessons',
    'status' => 'active',
));
assert_test(is_numeric($sport_id) && $sport_id > 0, 'Sport Model: Create Swimming Sport');

// Test 2: Create Coach with Pastel Color
$coach_id = Sportedia_Model_Coach::create(array(
    'coach_code' => 'CCH-0001',
    'full_name' => 'Michael Phelps',
    'sport_id' => $sport_id,
    'color_hex' => '#93C5FD', // Pastel Blue
    'status' => 'active',
));
assert_test(is_numeric($coach_id) && $coach_id > 0, 'Coach Model: Create Coach with Pastel Color');

// Test 3: Create Group
$group_id = Sportedia_Model_Group::create(array(
    'name' => 'Pro Swimmers A',
    'sport_id' => $sport_id,
    'coach_id' => $coach_id,
    'level' => 'Advanced',
    'max_capacity' => 15,
));
assert_test(is_numeric($group_id) && $group_id > 0, 'Group Model: Create Group');

// Test 4: Create Time Slot
$timeslot_id = Sportedia_Model_TimeSlot::create(array(
    'display_name' => '02:00 PM - 03:00 PM',
    'start_time' => '14:00:00',
    'end_time' => '15:00:00',
));
assert_test(is_numeric($timeslot_id) && $timeslot_id > 0, 'TimeSlot Model: Create Time Slot');

// Test 5: Create Player & Age Calculation
$player_id = Sportedia_Model_Player::create(array(
    'player_code' => 'PLY-0001',
    'full_name' => 'John Smith',
    'gender' => 'Male',
    'date_of_birth' => '2012-05-15',
    'sport_id' => $sport_id,
    'group_id' => $group_id,
    'level' => 'Advanced',
));
assert_test(is_numeric($player_id) && $player_id > 0, 'Player Model: Create Player');

$calculated_age = Sportedia_Model_Player::calculate_age('2012-05-15');
assert_test($calculated_age > 10, 'Player Model: Auto-calculate Age from DOB');

// Test 6: Create Session
$session_id = Sportedia_Model_Session::create(array(
    'session_date' => '2026-03-01',
    'sport_id' => $sport_id,
    'time_slot_id' => $timeslot_id,
    'group_id' => $group_id,
    'coach_id' => $coach_id,
    'status' => 'completed',
));
assert_test(is_numeric($session_id) && $session_id > 0, 'Session Model: Create Operational Session');

// Test 7: Add Player to Session
$add_p_res = Sportedia_Model_Session::add_player($session_id, $player_id);
assert_test(is_numeric($add_p_res) && $add_p_res > 0, 'Session Model: Add Player to Session');

// Test 8: Duplicate Assignment Prevention
$dup_p_res = Sportedia_Model_Session::add_player($session_id, $player_id);
assert_test(is_wp_error($dup_p_res), 'Session Model: Prevent Duplicate Player Assignment with Notification');

// Test 9: Create Sample Excel for Import Validation
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$test_excel_path = __DIR__ . '/test_import.xlsx';
$ss = new Spreadsheet();
$s_sheet = $ss->getActiveSheet();
$s_sheet->setCellValue('A1', 'Full Name');
$s_sheet->setCellValue('B1', 'Player Code');
$s_sheet->setCellValue('C1', 'Gender');
$s_sheet->setCellValue('D1', 'Age');

$s_sheet->setCellValue('A2', 'Alice Cooper');
$s_sheet->setCellValue('B2', 'PLY-0002');
$s_sheet->setCellValue('C2', 'Female');
$s_sheet->setCellValue('D2', '12');

// Duplicate row to test duplicate validator
$s_sheet->setCellValue('A3', 'John Smith');
$s_sheet->setCellValue('B3', 'PLY-0001'); // Existing code
$s_sheet->setCellValue('C3', 'Male');
$s_sheet->setCellValue('D3', '14');

$writer = new Xlsx($ss);
$writer->save($test_excel_path);

$import_report = Sportedia_Excel_Import::process_import_file($test_excel_path, 'players', true);
assert_test(
    is_array($import_report) &&
    $import_report['valid_count'] === 1 &&
    $import_report['duplicate_count'] === 1,
    'Excel Import: Validate rows, detect duplicate codes, and generate validation report'
);

// Clean up sample import file
if (file_exists($test_excel_path)) {
    unlink($test_excel_path);
}

echo "\n=========================================\n";
echo " SUMMARY: $tests_passed PASSED, $tests_failed FAILED\n";
echo "=========================================\n\n";

if ($tests_failed > 0) {
    exit(1);
}
exit(0);
