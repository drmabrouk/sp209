<?php
/**
 * Excel Exporter Engine
 * Generates highly structured, professional, multi-sheet English-only Excel workbooks
 * with coach pastel color coding, freeze panes, auto-filters, summary statistics, export history logging,
 * and landscape print setups.
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class Sportedia_Excel_Export {

    public static function generate_and_download_export($report_type = 'complete_database', $filters = array()) {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Remove default initial sheet

        $academy_name = get_option('sportedia_academy_name', 'Sportedia Sports Academy');

        // 1. SUMMARY SHEET
        self::build_summary_sheet($spreadsheet, $academy_name, $filters);

        // 2. SESSIONS SHEET
        self::build_sessions_sheet($spreadsheet, $filters);

        // 3. PLAYERS SHEET
        self::build_players_sheet($spreadsheet, $filters);

        // 4. COACHES SHEET
        self::build_coaches_sheet($spreadsheet, $filters);

        // 5. STATISTICS SHEET
        self::build_statistics_sheet($spreadsheet, $filters);

        // 6. EXPORT INFORMATION SHEET
        self::build_export_info_sheet($spreadsheet, $academy_name, $report_type, $filters);

        // Set Active Sheet to Summary
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Sportedia_Report_' . date('Y-m-d_His') . '.xlsx';

        // Save to protected uploads directory for Export History
        $file_path = '';
        $file_size = 0;
        if (function_exists('wp_upload_dir')) {
            $upload = wp_upload_dir();
            $dir = $upload['basedir'] . '/sportedia-exports';
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            $file_path = $dir . '/' . $filename;
            $writer = new Xlsx($spreadsheet);
            $writer->save($file_path);
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;

            // Log Export History
            $date_range = (!empty($filters['date_from']) ? $filters['date_from'] : 'All') . ' to ' . (!empty($filters['date_to']) ? $filters['date_to'] : 'All');
            Sportedia_Model_Export_History::log_export($filename, $file_path, $report_type, $date_range, $file_size);
        }

        // Send output to browser for immediate download
        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Pragma: public');

        if (!empty($file_path) && file_exists($file_path)) {
            readfile($file_path);
        } else {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }
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
        $sheet->setCellValue('A2', 'Dubai Local Date: ' . Sportedia_DateTime::now('Y-m-d H:i:s') . ' | Period: ' . ($filters['date_from'] ?? 'All Time'));
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
                       ts.display_name as time_slot_name
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
                    $sheet->setCellValue('C' . $row_idx, $p->age ?: '—');
                    $sheet->setCellValue('D' . $row_idx, $p->gender ?: '—');
                    $sheet->setCellValue('E' . $row_idx, $p->level ?: '—');
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

            $row_idx++; // Blank row spacing
        }

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

            $clean_hex = ltrim($s->coach_color ?: '3B82F6', '#');
            $sheet->getStyle('I' . $row_idx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $clean_hex);

            $sheet->setCellValue('J' . $row_idx, $s->player_count);
            $sheet->setCellValue('K' . $row_idx, ucfirst($s->status));
            $row_idx++;
        }

        $sheet->setAutoFilter('A1:K' . max(2, $row_idx - 1));
        $sheet->freezePane('A2');

        foreach (range('A', 'K') as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }

    private static function build_players_sheet($spreadsheet, $filters) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Players');

        $headers = array('Player Code', 'Full Name', 'Gender', 'Date of Birth', 'Age', 'Sport', 'Group', 'Level', 'Package Status', 'Sessions Used / Total');
        $cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');

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
            $sheet->setCellValue('C' . $row_idx, $p->gender ?: '—');
            $sheet->setCellValue('D' . $row_idx, $p->date_of_birth ?: '—');
            $sheet->setCellValue('E' . $row_idx, $p->age ?: '—');
            $sheet->setCellValue('F' . $row_idx, $p->sport_name ?: 'N/A');
            $sheet->setCellValue('G' . $row_idx, $p->group_name ?: 'N/A');
            $sheet->setCellValue('H' . $row_idx, $p->level ?: '—');

            $pkg = $p->package;
            $sheet->setCellValue('I' . $row_idx, ucfirst($pkg ? $pkg->status : 'Active'));
            $sheet->setCellValue('J' . $row_idx, $pkg ? ($pkg->used_sessions . ' / ' . $pkg->total_sessions) : '0 / 8');

            $row_idx++;
        }

        $sheet->setAutoFilter('A1:J' . max(2, $row_idx - 1));
        $sheet->freezePane('A2');

        foreach (range('A', 'J') as $col_letter) {
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

    private static function build_export_info_sheet($spreadsheet, $academy_name, $report_type, $filters) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Export Information');

        $sheet->setCellValue('A1', 'EXPORT METADATA & AUDIT INFO');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $info = array(
            'Academy Name' => $academy_name,
            'Report Type' => $report_type,
            'Date Range' => ($filters['date_from'] ?? 'All') . ' to ' . ($filters['date_to'] ?? 'All'),
            'Timezone' => 'Asia/Dubai (UAE Local Time)',
            'Generation Timestamp' => Sportedia_DateTime::now('Y-m-d H:i:s'),
            'Generated By' => function_exists('wp_get_current_user') && wp_get_current_user()->display_name ? wp_get_current_user()->display_name : 'System Administrator',
            'Language' => 'English (100%)',
        );

        $row_idx = 3;
        foreach ($info as $label => $val) {
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
        $history = Sportedia_Model_Export_History::get_all();
        ?>
        <div class="sportedia-wrap">
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Excel Export & History Hub', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Generate styled Excel reports and manage historical export file archives.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Generate New Export', 'sportedia'); ?></h3>
                    </div>
                    <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_export_excel">

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Start Date', 'sportedia'); ?></label>
                            <input type="date" name="date_from" class="sp-input" value="<?php echo Sportedia_DateTime::now('Y-m-d'); ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('End Date', 'sportedia'); ?></label>
                            <input type="date" name="date_to" class="sp-input" value="<?php echo Sportedia_DateTime::now('Y-m-d'); ?>">
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%; font-size:14px; padding:10px;"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Download Excel (.xlsx)', 'sportedia'); ?></button>
                    </form>
                </div>

                <!-- Export History Table -->
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Export File History Archive', 'sportedia'); ?></h3>
                    </div>
                    <div class="sportedia-table-container">
                        <table class="sportedia-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('File Name', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Generated (Dubai Time)', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Size', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Generated By', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($history)): ?>
                                    <?php foreach ($history as $h): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($h->file_name); ?></code></td>
                                            <td><?php echo esc_html($h->generation_time); ?></td>
                                            <td><?php echo esc_html(round($h->file_size / 1024, 1)); ?> KB</td>
                                            <td><?php echo esc_html($h->generated_by); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin-post.php?action=sportedia_download_export_history&id=' . $h->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Download', 'sportedia'); ?></a>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                    <input type="hidden" name="action" value="sportedia_admin_action">
                                                    <input type="hidden" name="sportedia_action" value="delete_export_history">
                                                    <input type="hidden" name="history_id" value="<?php echo esc_attr($h->id); ?>">
                                                    <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                    <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No historical export records logged yet.', 'sportedia'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
