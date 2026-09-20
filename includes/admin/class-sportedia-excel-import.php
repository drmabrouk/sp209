<?php
/**
 * Excel Importer Engine
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

class Sportedia_Excel_Import {

    public static function process_import_file($file_path, $entity_type, $is_dry_run = true) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Import file does not exist.', 'sportedia'));
        }

        try {
            $spreadsheet = IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
        } catch (Exception $e) {
            return new WP_Error('excel_parse_error', sprintf(__('Error parsing Excel file: %s', 'sportedia'), $e->getMessage()));
        }

        if (empty($rows) || count($rows) < 2) {
            return new WP_Error('empty_file', __('Excel file is empty or missing data rows.', 'sportedia'));
        }

        $header_row = array_shift($rows); // First row as header
        $headers = array_map(function($val) {
            return strtolower(trim(str_replace(' ', '_', $val)));
        }, $header_row);

        $valid_rows = array();
        $invalid_rows = array();
        $duplicate_rows = array();

        $row_number = 1; // Header was row 1, data starts at row 2

        foreach ($rows as $row) {
            $row_number++;
            $data = array();
            $col_idx = 0;
            foreach ($headers as $col_letter => $col_name) {
                if (!empty($col_name)) {
                    $data[$col_name] = isset($row[$col_letter]) ? trim($row[$col_letter]) : '';
                }
            }

            // Skip completely empty rows
            if (empty(array_filter($data))) {
                continue;
            }

            $validation = self::validate_row($data, $entity_type, $row_number);

            if ($validation['status'] === 'duplicate') {
                $duplicate_rows[] = $validation;
            } elseif ($validation['status'] === 'invalid') {
                $invalid_rows[] = $validation;
            } else {
                $valid_rows[] = $validation;
                if (!$is_dry_run) {
                    self::import_record($validation['data'], $entity_type);
                }
            }
        }

        return array(
            'total_rows' => count($valid_rows) + count($invalid_rows) + count($duplicate_rows),
            'valid_count' => count($valid_rows),
            'invalid_count' => count($invalid_rows),
            'duplicate_count' => count($duplicate_rows),
            'valid_rows' => $valid_rows,
            'invalid_rows' => $invalid_rows,
            'duplicate_rows' => $duplicate_rows,
            'is_dry_run' => $is_dry_run,
        );
    }

    private static function validate_row($data, $entity_type, $row_number) {
        global $wpdb;

        switch ($entity_type) {
            case 'players':
                $name = $data['full_name'] ?? ($data['player_name'] ?? ($data['name'] ?? ''));
                if (empty($name)) {
                    return array(
                        'row' => $row_number,
                        'status' => 'invalid',
                        'field' => 'full_name',
                        'error' => __('Player full name is required.', 'sportedia'),
                        'suggestion' => __('Provide a valid full name in the "Full Name" column.', 'sportedia'),
                        'data' => $data
                    );
                }

                $code = $data['player_code'] ?? ($data['player_id'] ?? '');
                if (!empty($code)) {
                    $p_table = Sportedia_Model_Player::get_table_name();
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $p_table WHERE player_code = %s", $code));
                    if ($exists) {
                        return array(
                            'row' => $row_number,
                            'status' => 'duplicate',
                            'field' => 'player_code',
                            'error' => sprintf(__('Player Code "%s" already exists.', 'sportedia'), $code),
                            'suggestion' => __('Leave code blank to auto-generate or use a unique player code.', 'sportedia'),
                            'data' => $data
                        );
                    }
                }

                return array('row' => $row_number, 'status' => 'valid', 'data' => $data);

            case 'coaches':
                $name = $data['full_name'] ?? ($data['coach_name'] ?? ($data['name'] ?? ''));
                if (empty($name)) {
                    return array(
                        'row' => $row_number,
                        'status' => 'invalid',
                        'field' => 'full_name',
                        'error' => __('Coach full name is required.', 'sportedia'),
                        'suggestion' => __('Provide a valid full name in the "Full Name" column.', 'sportedia'),
                        'data' => $data
                    );
                }

                $code = $data['coach_code'] ?? ($data['coach_id'] ?? '');
                if (!empty($code)) {
                    $c_table = Sportedia_Model_Coach::get_table_name();
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $c_table WHERE coach_code = %s", $code));
                    if ($exists) {
                        return array(
                            'row' => $row_number,
                            'status' => 'duplicate',
                            'field' => 'coach_code',
                            'error' => sprintf(__('Coach Code "%s" already exists.', 'sportedia'), $code),
                            'suggestion' => __('Leave code blank to auto-generate or use a unique coach code.', 'sportedia'),
                            'data' => $data
                        );
                    }
                }

                return array('row' => $row_number, 'status' => 'valid', 'data' => $data);

            case 'sports':
                $name = $data['name'] ?? ($data['sport_name'] ?? '');
                if (empty($name)) {
                    return array(
                        'row' => $row_number,
                        'status' => 'invalid',
                        'field' => 'name',
                        'error' => __('Sport name is required.', 'sportedia'),
                        'suggestion' => __('Enter a valid sport name.', 'sportedia'),
                        'data' => $data
                    );
                }
                return array('row' => $row_number, 'status' => 'valid', 'data' => $data);

            case 'groups':
                $name = $data['name'] ?? ($data['group_name'] ?? '');
                if (empty($name)) {
                    return array(
                        'row' => $row_number,
                        'status' => 'invalid',
                        'field' => 'name',
                        'error' => __('Group name is required.', 'sportedia'),
                        'suggestion' => __('Enter a valid group name.', 'sportedia'),
                        'data' => $data
                    );
                }
                return array('row' => $row_number, 'status' => 'valid', 'data' => $data);

            default:
                return array('row' => $row_number, 'status' => 'valid', 'data' => $data);
        }
    }

    private static function import_record($data, $entity_type) {
        switch ($entity_type) {
            case 'players':
                Sportedia_Model_Player::create(array(
                    'player_code' => $data['player_code'] ?? ($data['player_id'] ?? ''),
                    'full_name' => $data['full_name'] ?? ($data['player_name'] ?? ($data['name'] ?? '')),
                    'gender' => $data['gender'] ?? 'Male',
                    'date_of_birth' => $data['date_of_birth'] ?? ($data['dob'] ?? null),
                    'age' => !empty($data['age']) ? intval($data['age']) : 0,
                    'level' => $data['level'] ?? 'Beginner',
                    'status' => 'active',
                    'notes' => $data['notes'] ?? '',
                ));
                break;

            case 'coaches':
                Sportedia_Model_Coach::create(array(
                    'coach_code' => $data['coach_code'] ?? ($data['coach_id'] ?? ''),
                    'full_name' => $data['full_name'] ?? ($data['coach_name'] ?? ($data['name'] ?? '')),
                    'specialization' => $data['specialization'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'email' => $data['email'] ?? '',
                    'color_hex' => $data['color_hex'] ?? '#3B82F6',
                    'status' => 'active',
                    'notes' => $data['notes'] ?? '',
                ));
                break;

            case 'sports':
                Sportedia_Model_Sport::create(array(
                    'name' => $data['name'] ?? ($data['sport_name'] ?? ''),
                    'description' => $data['description'] ?? '',
                    'status' => 'active',
                ));
                break;

            case 'groups':
                // Check or create sport ID if provided
                $sport_id = 1;
                $sports = Sportedia_Model_Sport::get_all('active');
                if (!empty($sports)) {
                    $sport_id = $sports[0]->id;
                }

                Sportedia_Model_Group::create(array(
                    'name' => $data['name'] ?? ($data['group_name'] ?? ''),
                    'sport_id' => $sport_id,
                    'level' => $data['level'] ?? 'Beginner',
                    'max_capacity' => !empty($data['max_capacity']) ? intval($data['max_capacity']) : 20,
                    'status' => 'active',
                ));
                break;
        }
    }

    public static function render_page() {
        $import_result = null;

        if (isset($_POST['sportedia_action']) && $_POST['sportedia_action'] === 'process_import') {
            check_admin_referer('sportedia_action_nonce', 'sportedia_nonce');

            if (!empty($_FILES['import_file']['tmp_name'])) {
                $entity_type = sanitize_text_field($_POST['entity_type']);
                $is_dry_run = isset($_POST['is_dry_run']) && $_POST['is_dry_run'] === '1';

                $import_result = self::process_import_file($_FILES['import_file']['tmp_name'], $entity_type, $is_dry_run);
            }
        }
        ?>
        <div class="sportedia-wrap">
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Excel Import Center', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Batch import Players, Coaches, Sports, and Groups from Excel files with pre-validation.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <!-- Import Form Card -->
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Import Settings', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="">
                        <input type="hidden" name="sportedia_action" value="process_import">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Select Data Type to Import *', 'sportedia'); ?></label>
                            <select name="entity_type" class="sp-select" required>
                                <option value="players"><?php esc_html_e('Players', 'sportedia'); ?></option>
                                <option value="coaches"><?php esc_html_e('Coaches', 'sportedia'); ?></option>
                                <option value="sports"><?php esc_html_e('Sports', 'sportedia'); ?></option>
                                <option value="groups"><?php esc_html_e('Groups', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Excel / CSV File (.xlsx, .xls, .csv) *', 'sportedia'); ?></label>
                            <input type="file" name="import_file" accept=".xlsx, .xls, .csv" required class="sp-input">
                        </div>

                        <div class="sp-form-group">
                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                <input type="checkbox" name="is_dry_run" value="1" checked>
                                <?php esc_html_e(' Dry-run Preview (Validate file without modifying database)', 'sportedia'); ?>
                            </label>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php esc_html_e('Upload & Validate File', 'sportedia'); ?></button>
                    </form>
                </div>

                <!-- Preview / Results Report Card -->
                <div>
                    <?php if ($import_result && !is_wp_error($import_result)): ?>
                        <div class="sportedia-card" style="margin-bottom:20px;">
                            <div class="sportedia-card-header">
                                <h3 class="sportedia-card-title"><?php esc_html_e('Import Validation Report Summary', 'sportedia'); ?></h3>
                                <span class="sp-badge <?php echo $import_result['is_dry_run'] ? 'sp-badge-pending' : 'sp-badge-active'; ?>">
                                    <?php echo $import_result['is_dry_run'] ? esc_html__('DRY RUN PREVIEW MODE', 'sportedia') : esc_html__('FINAL IMPORT EXECUTED', 'sportedia'); ?>
                                </span>
                            </div>

                            <div class="sportedia-grid sportedia-grid-4" style="margin-bottom:20px;">
                                <div style="background:#f8fafc; padding:12px; border-radius:6px; text-align:center;">
                                    <div style="font-size:22px; font-weight:bold;"><?php echo esc_html($import_result['total_rows']); ?></div>
                                    <div style="font-size:11px; color:var(--sp-text-muted);"><?php esc_html_e('Total Rows', 'sportedia'); ?></div>
                                </div>
                                <div style="background:#ecfdf5; padding:12px; border-radius:6px; text-align:center;">
                                    <div style="font-size:22px; font-weight:bold; color:#059669;"><?php echo esc_html($import_result['valid_count']); ?></div>
                                    <div style="font-size:11px; color:#047857;"><?php esc_html_e('Valid Rows', 'sportedia'); ?></div>
                                </div>
                                <div style="background:#fef3c7; padding:12px; border-radius:6px; text-align:center;">
                                    <div style="font-size:22px; font-weight:bold; color:#d97706;"><?php echo esc_html($import_result['duplicate_count']); ?></div>
                                    <div style="font-size:11px; color:#b45309;"><?php esc_html_e('Duplicate Rows', 'sportedia'); ?></div>
                                </div>
                                <div style="background:#fef2f2; padding:12px; border-radius:6px; text-align:center;">
                                    <div style="font-size:22px; font-weight:bold; color:#dc2626;"><?php echo esc_html($import_result['invalid_count']); ?></div>
                                    <div style="font-size:11px; color:#b91c1c;"><?php esc_html_e('Invalid Rows', 'sportedia'); ?></div>
                                </div>
                            </div>

                            <!-- Detailed Error / Duplicate Table -->
                            <?php if (!empty($import_result['invalid_rows']) || !empty($import_result['duplicate_rows'])): ?>
                                <h4 style="margin:16px 0 8px 0; font-size:14px;"><?php esc_html_e('Validation Issues & Recommendations', 'sportedia'); ?></h4>
                                <div class="sportedia-table-container">
                                    <table class="sportedia-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Row', 'sportedia'); ?></th>
                                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                                <th><?php esc_html_e('Field', 'sportedia'); ?></th>
                                                <th><?php esc_html_e('Error Message', 'sportedia'); ?></th>
                                                <th><?php esc_html_e('Suggested Correction', 'sportedia'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_merge($import_result['duplicate_rows'], $import_result['invalid_rows']) as $issue): ?>
                                                <tr>
                                                    <td><code>Row #<?php echo esc_html($issue['row']); ?></code></td>
                                                    <td><span class="sp-badge sp-badge-<?php echo $issue['status'] === 'duplicate' ? 'pending' : 'cancelled'; ?>"><?php echo esc_html(ucfirst($issue['status'])); ?></span></td>
                                                    <td><code><?php echo esc_html($issue['field']); ?></code></td>
                                                    <td style="color:#b91c1c;"><?php echo esc_html($issue['error']); ?></td>
                                                    <td style="color:var(--sp-text-muted);"><?php echo esc_html($issue['suggestion']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif (is_wp_error($import_result)): ?>
                        <div class="sp-notice sp-notice-error"><span><?php echo esc_html($import_result->get_error_message()); ?></span></div>
                    <?php else: ?>
                        <div class="sportedia-card">
                            <div class="sportedia-card-header">
                                <h3 class="sportedia-card-title"><?php esc_html_e('Excel File Structure Guidelines', 'sportedia'); ?></h3>
                            </div>
                            <p style="font-size:13px; line-height:1.6; color:var(--sp-text-muted);">
                                <?php esc_html_e('Ensure your uploaded spreadsheet includes a header row on row 1. Recommended column headers:', 'sportedia'); ?>
                            </p>
                            <ul style="font-size:13px; line-height:1.8; color:var(--sp-text-main); margin-left:20px; list-style-type:disc;">
                                <li><strong>Players:</strong> Full Name, Player Code, Gender, Date of Birth, Age, Level, Notes</li>
                                <li><strong>Coaches:</strong> Full Name, Coach Code, Specialization, Phone, Email, Color Hex, Notes</li>
                                <li><strong>Sports:</strong> Name, Description</li>
                                <li><strong>Groups:</strong> Name, Level, Max Capacity</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
