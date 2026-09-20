<?php
/**
 * Excel Exporter Engine
 * Generates highly structured, professional, multi-sheet English-only Excel workbooks
 * with coach pastel color coding, freeze panes, auto-filters, summary statistics, and landscape print setups.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class Sportedia_Excel_Export {

    public static function generate_and_download_export($report_type = 'complete_database', $filters = array()) {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Remove default initial sheet

        $academy_name = get_option('sportedia_academy_name', 'Sportedia Sports Academy');

        // 1. SUMMARY SHEET (Date -> Time -> Sport -> Group -> Coach -> Players)
        self::build_summary_sheet($spreadsheet, $academy_name, $filters);

        // 2. SESSIONS SHEET
        self::build_sessions_sheet($spreadsheet, $filters);

        // 3. PLAYERS SHEET
        self::build_players_sheet($spreadsheet, $filters);

        // 4. COACHES SHEET
        self::build_coaches_sheet($spreadsheet, $filters);

        // 5. STATISTICS SHEET
        self::build_statistics_sheet($spreadsheet, $filters);

        // Set Active Sheet to Summary
        $spreadsheet->setActiveSheetIndex(0);

        // Clean output buffer before sending Excel binary headers
        if (ob_get_length()) {
            ob_clean();
        }

        $filename = 'Sportedia_Academy_Report_' . date('Y-m-d_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private static function build_summary_sheet($spreadsheet, $academy_name, $filters) {
        global $wpdb;
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Summary');

        $sess_table = Sportedia_Model_Session::get_table_name();
        $sp_table = Sportedia_Model_Session::get_session_players_table_name();
        $p_table = Sportedia_Model_Player::get_table_name();
        $c_table = Sportedia_Model_Coach::get_table_name();
        $s_table = Sportedia_Model_Sport::get_table_name();
        $g_table = Sportedia_Model_Group::get_table_name();
        $ts_table = Sportedia_Model_TimeSlot::get_table_name();

        // Worksheet Title Header
        $sheet->setCellValue('A1', strtoupper($academy_name) . ' - OPERATIONAL PARTICIPATION SUMMARY');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
        $sheet->setCellValue('A2', 'Generated on: ' . date('Y-m-d H:i:s') . ' | Filter Period: ' . ($filters['date_from'] ?? 'All Time'));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

        // Coach Color Legend Area
        $coaches = Sportedia_Model_Coach::get_all(array('status' => 'active'));
        $sheet->setCellValue('A4', 'COACH COLOR LEGEND:');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(10);

        $col = 'B';
        foreach ($coaches as $c) {
            $sheet->setCellValue($col . '4', $c->full_name);
            $clean_hex = ltrim($c->color_hex ?: '3B82F6', '#');
            $sheet->getStyle($col . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $clean_hex);
            $sheet->getStyle($col . '4')->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle($col . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        // Fetch Sessions
        $where = array('1=1');
        $params = array();
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $where[] = "s.session_date BETWEEN %s AND %s";
            $params[] = $filters['date_from'];
            $params[] = $filters['date_to'];
        }
        $where_sql = implode(' AND ', $where);

        $sql = "SELECT s.id, s.session_date, sp.name as sport_name, g.name as group_name,
                       c.full_name as coach_name, c.color_hex as coach_color,
                       ts.display_name as time_slot_name, ts.start_time, ts.end_time
                FROM $sess_table s
                LEFT JOIN $s_table sp ON s.sport_id = sp.id
                LEFT JOIN $g_table g ON s.group_id = g.id
                LEFT JOIN $c_table c ON s.coach_id = c.id
                LEFT JOIN $ts_table ts ON s.time_slot_id = ts.id
                WHERE $where_sql
                ORDER BY s.session_date DESC, ts.start_time ASC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $sessions = $wpdb->get_results($sql);

        $row_idx = 6;

        foreach ($sessions as $sess) {
            // Session Header Banner
            $sheet->setCellValue('A' . $row_idx, 'Date: ' . $sess->session_date . ' | Time: ' . $sess->time_slot_name . ' | Sport: ' . $sess->sport_name . ' | Group: ' . $sess->group_name . ' | Coach: ' . $sess->coach_name);
            $sheet->mergeCells('A' . $row_idx . ':G' . $row_idx);

            $clean_hex = ltrim($sess->coach_color ?: '3B82F6', '#');
            $sheet->getStyle('A' . $row_idx . ':G' . $row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $clean_hex);
            $sheet->getStyle('A' . $row_idx)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $row_idx++;

            // Player Subtable Headers
            $headers = array('Player Code', 'Player Name', 'Age', 'Gender', 'Level', 'Sport', 'Coach');
            $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G');
            foreach ($headers as $idx => $h) {
                $sheet->setCellValue($cols[$idx] . $row_idx, $h);
                $sheet->getStyle($cols[$idx] . $row_idx)->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle($cols[$idx] . $row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
            }
            $row_idx++;

            // Session Players
            $players = Sportedia_Model_Session::get_players($sess->id);
            if (!empty($players)) {
                foreach ($players as $p) {
                    $sheet->setCellValue('A' . $row_idx, $p->player_code);
                    $sheet->setCellValue('B' . $row_idx, $p->full_name);
                    $sheet->setCellValue('C' . $row_idx, $p->age);
                    $sheet->setCellValue('D' . $row_idx, $p->gender);
                    $sheet->setCellValue('E' . $row_idx, $p->level);
                    $sheet->setCellValue('F' . $row_idx, $sess->sport_name);
                    $sheet->setCellValue('G' . $row_idx, $sess->coach_name);
                    $row_idx++;
                }
            } else {
                $sheet->setCellValue('A' . $row_idx, 'No players registered in this session.');
                $sheet->mergeCells('A' . $row_idx . ':G' . $row_idx);
                $sheet->getStyle('A' . $row_idx)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('94A3B8'));
                $row_idx++;
            }

            $row_idx++; // Blank row spacing between sessions
        }

        // Auto column widths
        foreach (range('A', 'G') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    private static function build_sessions_sheet($spreadsheet, $filters) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sessions');

        $headers = array('Session ID', 'Date', 'Day', 'Start Time', 'End Time', 'Time Slot', 'Sport', 'Group', 'Coach', 'Player Count', 'Status');
        $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K');

        foreach ($headers as $idx => $h) {
            $sheet->setCellValue($cols[$idx] . '1', $h);
            $sheet->getStyle($cols[$idx] . '1')->getFont()->setBold(true);
            $sheet->getStyle($cols[$idx] . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        }

        $sessions = Sportedia_Model_Session::get_all($filters);
        $row_idx = 2;

        foreach ($sessions as $s) {
            $sheet->setCellValue('A' . $row_idx, '#' . $s->id);
            $sheet->setCellValue('B' . $row_idx, $s->session_date);
            $sheet->setCellValue('C' . $row_idx, date('l', strtotime($s->session_date)));
            $sheet->setCellValue('D' . $row_idx, $s->start_time);
            $sheet->setCellValue('E' . $row_idx, $s->end_time);
            $sheet->setCellValue('F' . $row_idx, $s->time_slot_name);
            $sheet->setCellValue('G' . $row_idx, $s->sport_name);
            $sheet->setCellValue('H' . $row_idx, $s->group_name);
            $sheet->setCellValue('I' . $row_idx, $s->coach_name);

            // Coach pastel color highlight in Coach column
            $clean_hex = ltrim($s->coach_color ?: '3B82F6', '#');
            $sheet->getStyle('I' . $row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $clean_hex);

            $sheet->setCellValue('J' . $row_idx, $s->player_count);
            $sheet->setCellValue('K' . $row_idx, ucfirst($s->status));
            $row_idx++;
        }

        $sheet->setAutoFilter('A1:K' . ($row_idx - 1));
        $sheet->freezePane('A2');

        foreach (range('A', 'K') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    private static function build_players_sheet($spreadsheet, $filters) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Players');

        $headers = array('Player Code', 'Full Name', 'Gender', 'Date of Birth', 'Age', 'Sport', 'Group', 'Level', 'Status');
        $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I');

        foreach ($headers as $idx => $h) {
            $sheet->setCellValue($cols[$idx] . '1', $h);
            $sheet->getStyle($cols[$idx] . '1')->getFont()->setBold(true);
            $sheet->getStyle($cols[$idx] . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        }

        $players = Sportedia_Model_Player::get_all(array('limit' => 0));
        $row_idx = 2;

        foreach ($players as $p) {
            $sheet->setCellValue('A' . $row_idx, $p->player_code);
            $sheet->setCellValue('B' . $row_idx, $p->full_name);
            $sheet->setCellValue('C' . $row_idx, $p->gender);
            $sheet->setCellValue('D' . $row_idx, $p->date_of_birth ?: '—');
            $sheet->setCellValue('E' . $row_idx, $p->age);
            $sheet->setCellValue('F' . $row_idx, $p->sport_name ?: 'N/A');
            $sheet->setCellValue('G' . $row_idx, $p->group_name ?: 'N/A');
            $sheet->setCellValue('H' . $row_idx, $p->level);
            $sheet->setCellValue('I' . $row_idx, ucfirst($p->status));
            $row_idx++;
        }

        $sheet->setAutoFilter('A1:I' . ($row_idx - 1));
        $sheet->freezePane('A2');

        foreach (range('A', 'I') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    private static function build_coaches_sheet($spreadsheet, $filters) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Coaches');

        $headers = array('Coach Code', 'Full Name', 'Sport', 'Specialization', 'Phone', 'Email', 'Pastel Color', 'Status');
        $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');

        foreach ($headers as $idx => $h) {
            $sheet->setCellValue($cols[$idx] . '1', $h);
            $sheet->getStyle($cols[$idx] . '1')->getFont()->setBold(true);
            $sheet->getStyle($cols[$idx] . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        }

        $coaches = Sportedia_Model_Coach::get_all();
        $row_idx = 2;

        foreach ($coaches as $c) {
            $sheet->setCellValue('A' . $row_idx, $c->coach_code);
            $sheet->setCellValue('B' . $row_idx, $c->full_name);
            $sheet->setCellValue('C' . $row_idx, $c->sport_name ?: 'N/A');
            $sheet->setCellValue('D' . $row_idx, $c->specialization ?: '—');
            $sheet->setCellValue('E' . $row_idx, $c->phone ?: '—');
            $sheet->setCellValue('F' . $row_idx, $c->email ?: '—');
            $sheet->setCellValue('G' . $row_idx, $c->color_hex);

            $clean_hex = ltrim($c->color_hex ?: '3B82F6', '#');
            $sheet->getStyle('G' . $row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $clean_hex);

            $sheet->setCellValue('H' . $row_idx, ucfirst($c->status));
            $row_idx++;
        }

        $sheet->freezePane('A2');

        foreach (range('A', 'H') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    private static function build_statistics_sheet($spreadsheet, $filters) {
        global $wpdb;
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Statistics');

        $sheet->setCellValue('A1', 'SPORTS ACADEMY AGGREGATE STATISTICS');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $p_table = Sportedia_Model_Player::get_table_name();
        $c_table = Sportedia_Model_Coach::get_table_name();
        $s_table = Sportedia_Model_Sport::get_table_name();
        $sess_table = Sportedia_Model_Session::get_table_name();

        $stats = array(
            'Total Registered Players' => $wpdb->get_var("SELECT COUNT(*) FROM $p_table"),
            'Active Players' => $wpdb->get_var("SELECT COUNT(*) FROM $p_table WHERE status = 'active'"),
            'Total Registered Coaches' => $wpdb->get_var("SELECT COUNT(*) FROM $c_table"),
            'Active Sports Categories' => $wpdb->get_var("SELECT COUNT(*) FROM $s_table WHERE status = 'active'"),
            'Total Recorded Sessions' => $wpdb->get_var("SELECT COUNT(*) FROM $sess_table"),
        );

        $row_idx = 3;
        foreach ($stats as $label => $val) {
            $sheet->setCellValue('A' . $row_idx, $label);
            $sheet->setCellValue('B' . $row_idx, $val);
            $sheet->getStyle('A' . $row_idx)->getFont()->setBold(true);
            $row_idx++;
        }

        foreach (range('A', 'B') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    public static function render_page() {
        ?>
        <div class="sportedia-wrap">
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Excel Export Center', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Generate professionally styled multi-sheet Excel workbooks with coach pastel color coding.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-card" style="max-width: 600px;">
                <div class="sportedia-card-header">
                    <h3 class="sportedia-card-title"><?php esc_html_e('Export Filter Criteria', 'sportedia'); ?></h3>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sportedia_export_excel">

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('Export Range', 'sportedia'); ?></label>
                        <select name="export_range" class="sp-select">
                            <option value="all"><?php esc_html_e('Complete Database', 'sportedia'); ?></option>
                            <option value="today"><?php esc_html_e('Today Only', 'sportedia'); ?></option>
                            <option value="week"><?php esc_html_e('Current Week', 'sportedia'); ?></option>
                            <option value="month"><?php esc_html_e('Current Month', 'sportedia'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom Date Range', 'sportedia'); ?></option>
                        </select>
                    </div>

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('Start Date', 'sportedia'); ?></label>
                        <input type="date" name="date_from" class="sp-input" value="<?php echo current_time('Y-m-d'); ?>">
                    </div>

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('End Date', 'sportedia'); ?></label>
                        <input type="date" name="date_to" class="sp-input" value="<?php echo current_time('Y-m-d'); ?>">
                    </div>

                    <button type="submit" class="sp-btn sp-btn-primary" style="width:100%; font-size:15px; padding:12px;"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Download Styled Excel Report (.xlsx)', 'sportedia'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }
}
