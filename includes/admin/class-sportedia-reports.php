<?php
/**
 * Reports Engine & Renderer
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Reports {

    public static function get_report_data($report_type, $filters = array()) {
        global $wpdb;

        $sess_table = Sportedia_Model_Session::get_table_name();
        $sp_table = Sportedia_Model_Session::get_session_players_table_name();
        $p_table = Sportedia_Model_Player::get_table_name();
        $c_table = Sportedia_Model_Coach::get_table_name();
        $s_table = Sportedia_Model_Sport::get_table_name();
        $g_table = Sportedia_Model_Group::get_table_name();
        $ts_table = Sportedia_Model_TimeSlot::get_table_name();

        $date_from = !empty($filters['date_from']) ? sanitize_text_field($filters['date_from']) : current_time('Y-m-d');
        $date_to = !empty($filters['date_to']) ? sanitize_text_field($filters['date_to']) : $date_from;
        $sport_id = !empty($filters['sport_id']) ? intval($filters['sport_id']) : 0;
        $coach_id = !empty($filters['coach_id']) ? intval($filters['coach_id']) : 0;
        $group_id = !empty($filters['group_id']) ? intval($filters['group_id']) : 0;

        switch ($report_type) {
            case 'daily_player':
            case 'player_participation':
                $where = array("sess.session_date BETWEEN %s AND %s");
                $params = array($date_from, $date_to);

                if ($sport_id) { $where[] = "sess.sport_id = %d"; $params[] = $sport_id; }
                if ($coach_id) { $where[] = "sess.coach_id = %d"; $params[] = $coach_id; }
                if ($group_id) { $where[] = "sess.group_id = %d"; $params[] = $group_id; }

                $where_sql = implode(' AND ', $where);
                $sql = "SELECT p.player_code, p.full_name, p.age, p.gender, p.level,
                               s.name as sport_name, g.name as group_name, c.full_name as coach_name,
                               c.color_hex as coach_color,
                               sess.session_date, ts.display_name as time_slot_name
                        FROM $sp_table sp
                        INNER JOIN $p_table p ON sp.player_id = p.id
                        INNER JOIN $sess_table sess ON sp.session_id = sess.id
                        LEFT JOIN $s_table s ON sess.sport_id = s.id
                        LEFT JOIN $g_table g ON sess.group_id = g.id
                        LEFT JOIN $c_table c ON sess.coach_id = c.id
                        LEFT JOIN $ts_table ts ON sess.time_slot_id = ts.id
                        WHERE $where_sql
                        ORDER BY sess.session_date DESC, ts.start_time ASC, p.full_name ASC";

                return $wpdb->get_results($wpdb->prepare($sql, $params));

            case 'daily_coach':
            case 'coach_report':
                $where = array("sess.session_date BETWEEN %s AND %s");
                $params = array($date_from, $date_to);

                if ($sport_id) { $where[] = "sess.sport_id = %d"; $params[] = $sport_id; }
                if ($coach_id) { $where[] = "sess.coach_id = %d"; $params[] = $coach_id; }

                $where_sql = implode(' AND ', $where);
                $sql = "SELECT c.id, c.coach_code, c.full_name as coach_name, c.color_hex, s.name as sport_name,
                               COUNT(DISTINCT sess.id) as session_count,
                               COUNT(sp.id) as total_players
                        FROM $c_table c
                        INNER JOIN $sess_table sess ON c.id = sess.coach_id
                        LEFT JOIN $s_table s ON c.sport_id = s.id
                        LEFT JOIN $sp_table sp ON sess.id = sp.session_id
                        WHERE $where_sql
                        GROUP BY c.id
                        ORDER BY total_players DESC";

                return $wpdb->get_results($wpdb->prepare($sql, $params));

            case 'daily_session':
            case 'weekly_report':
            case 'monthly_report':
                $where = array("sess.session_date BETWEEN %s AND %s");
                $params = array($date_from, $date_to);

                if ($sport_id) { $where[] = "sess.sport_id = %d"; $params[] = $sport_id; }
                if ($coach_id) { $where[] = "sess.coach_id = %d"; $params[] = $coach_id; }
                if ($group_id) { $where[] = "sess.group_id = %d"; $params[] = $group_id; }

                $where_sql = implode(' AND ', $where);
                $sql = "SELECT sess.id, sess.session_date, sess.status,
                               s.name as sport_name, g.name as group_name,
                               c.full_name as coach_name, c.color_hex as coach_color,
                               ts.display_name as time_slot_name,
                               COUNT(sp.id) as player_count
                        FROM $sess_table sess
                        LEFT JOIN $s_table s ON sess.sport_id = s.id
                        LEFT JOIN $g_table g ON sess.group_id = g.id
                        LEFT JOIN $c_table c ON sess.coach_id = c.id
                        LEFT JOIN $ts_table ts ON sess.time_slot_id = ts.id
                        LEFT JOIN $sp_table sp ON sess.id = sp.session_id
                        WHERE $where_sql
                        GROUP BY sess.id
                        ORDER BY sess.session_date DESC, ts.start_time ASC";

                return $wpdb->get_results($wpdb->prepare($sql, $params));

            case 'sport_report':
                $sql = "SELECT s.id, s.name as sport_name,
                               (SELECT COUNT(*) FROM $p_table WHERE sport_id = s.id) as player_count,
                               (SELECT COUNT(*) FROM $c_table WHERE sport_id = s.id) as coach_count,
                               (SELECT COUNT(*) FROM $g_table WHERE sport_id = s.id) as group_count,
                               (SELECT COUNT(*) FROM $sess_table WHERE sport_id = s.id) as session_count
                        FROM $s_table s
                        ORDER BY s.name ASC";
                return $wpdb->get_results($sql);

            case 'group_report':
                $sql = "SELECT g.id, g.name as group_name, g.level, g.max_capacity,
                               s.name as sport_name, c.full_name as coach_name,
                               (SELECT COUNT(*) FROM $p_table WHERE group_id = g.id) as enrolled_players,
                               (SELECT COUNT(*) FROM $sess_table WHERE group_id = g.id) as session_count
                        FROM $g_table g
                        LEFT JOIN $s_table s ON g.sport_id = s.id
                        LEFT JOIN $c_table c ON g.coach_id = c.id
                        ORDER BY g.name ASC";
                return $wpdb->get_results($sql);

            default:
                return array();
        }
    }

    public static function render_page() {
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'daily_player';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : current_time('Y-m-d');
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : $date_from;
        $sport_id = isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 0;
        $coach_id = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

        $filters = array(
            'date_from' => $date_from,
            'date_to' => $date_to,
            'sport_id' => $sport_id,
            'coach_id' => $coach_id,
            'group_id' => $group_id,
        );

        $results = self::get_report_data($report_type, $filters);

        $sports = Sportedia_Model_Sport::get_all('active');
        $coaches = Sportedia_Model_Coach::get_all(array('status' => 'active'));
        $groups = Sportedia_Model_Group::get_all(array('status' => 'active'));

        $export_url = admin_url('admin-post.php?action=sportedia_export_excel&report_type=' . $report_type . '&' . http_build_query($filters));
        ?>
        <div class="sportedia-wrap">
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Reports Hub', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Generate operational reports for player participations, coach loads, and sports statistics.', 'sportedia'); ?></p>
                </div>
                <div class="sportedia-header-actions">
                    <a href="<?php echo esc_url($export_url); ?>" class="sp-btn sp-btn-primary"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Current Report to Excel', 'sportedia'); ?></a>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="get" class="sportedia-filter-bar">
                <input type="hidden" name="page" value="sportedia-reports">

                <select name="report_type" class="sp-select" style="width: 200px; font-weight:600;">
                    <option value="daily_player" <?php selected($report_type, 'daily_player'); ?>><?php esc_html_e('Daily Player Report', 'sportedia'); ?></option>
                    <option value="daily_coach" <?php selected($report_type, 'daily_coach'); ?>><?php esc_html_e('Daily Coach Report', 'sportedia'); ?></option>
                    <option value="daily_session" <?php selected($report_type, 'daily_session'); ?>><?php esc_html_e('Daily Session Report', 'sportedia'); ?></option>
                    <option value="weekly_report" <?php selected($report_type, 'weekly_report'); ?>><?php esc_html_e('Weekly Report', 'sportedia'); ?></option>
                    <option value="monthly_report" <?php selected($report_type, 'monthly_report'); ?>><?php esc_html_e('Monthly Report', 'sportedia'); ?></option>
                    <option value="player_participation" <?php selected($report_type, 'player_participation'); ?>><?php esc_html_e('Player Participation Report', 'sportedia'); ?></option>
                    <option value="coach_report" <?php selected($report_type, 'coach_report'); ?>><?php esc_html_e('Coach Report', 'sportedia'); ?></option>
                    <option value="sport_report" <?php selected($report_type, 'sport_report'); ?>><?php esc_html_e('Sport Report', 'sportedia'); ?></option>
                    <option value="group_report" <?php selected($report_type, 'group_report'); ?>><?php esc_html_e('Group Report', 'sportedia'); ?></option>
                </select>

                <input type="date" name="date_from" class="sp-input" value="<?php echo esc_attr($date_from); ?>" style="width: 150px;">
                <input type="date" name="date_to" class="sp-input" value="<?php echo esc_attr($date_to); ?>" style="width: 150px;">

                <select name="sport_id" class="sp-select" style="width: 140px;">
                    <option value=""><?php esc_html_e('All Sports', 'sportedia'); ?></option>
                    <?php foreach ($sports as $sp): ?>
                        <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($sport_id, $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="coach_id" class="sp-select" style="width: 140px;">
                    <option value=""><?php esc_html_e('All Coaches', 'sportedia'); ?></option>
                    <?php foreach ($coaches as $c): ?>
                        <option value="<?php echo esc_attr($c->id); ?>" <?php selected($coach_id, $c->id); ?>><?php echo esc_html($c->full_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Generate Report', 'sportedia'); ?></button>
            </form>

            <div class="sportedia-table-container">
                <table class="sportedia-table">
                    <?php if ($report_type === 'daily_player' || $report_type === 'player_participation'): ?>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Time Slot', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Player Code', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Player Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Age/Gender', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Group', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($r->session_date); ?></strong></td>
                                        <td><code><?php echo esc_html($r->time_slot_name); ?></code></td>
                                        <td><code><?php echo esc_html($r->player_code); ?></code></td>
                                        <td><strong><?php echo esc_html($r->full_name); ?></strong></td>
                                        <td><?php echo esc_html($r->age); ?> yrs (<?php echo esc_html($r->gender); ?>)</td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($r->sport_name); ?></span></td>
                                        <td><?php echo esc_html($r->group_name); ?></td>
                                        <td>
                                            <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($r->coach_color); ?>18; color: #1e293b;">
                                                <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($r->coach_color); ?>;"></span>
                                                <?php echo esc_html($r->coach_name); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No player participation records found for the selected filter criteria.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif ($report_type === 'daily_coach' || $report_type === 'coach_report'): ?>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Coach Code', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sessions Conducted', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Total Player Attendance', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Avg Players / Session', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $r):
                                    $avg = $r->session_count > 0 ? round($r->total_players / $r->session_count, 1) : 0;
                                ?>
                                    <tr>
                                        <td><code><?php echo esc_html($r->coach_code); ?></code></td>
                                        <td>
                                            <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($r->color_hex); ?>18; color: #1e293b;">
                                                <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($r->color_hex); ?>;"></span>
                                                <strong><?php echo esc_html($r->coach_name); ?></strong>
                                            </span>
                                        </td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($r->sport_name); ?></span></td>
                                        <td><strong><?php echo esc_html($r->session_count); ?></strong></td>
                                        <td><strong><?php echo esc_html($r->total_players); ?></strong></td>
                                        <td><span class="sp-badge sp-badge-pending"><?php echo esc_html($avg); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No coach records found for selected dates.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif (in_array($report_type, array('daily_session', 'weekly_report', 'monthly_report'))): ?>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Time Slot', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Group', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Player Count', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($r->session_date); ?></strong></td>
                                        <td><code><?php echo esc_html($r->time_slot_name); ?></code></td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($r->sport_name); ?></span></td>
                                        <td><?php echo esc_html($r->group_name); ?></td>
                                        <td>
                                            <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($r->coach_color); ?>18; color: #1e293b;">
                                                <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($r->coach_color); ?>;"></span>
                                                <?php echo esc_html($r->coach_name); ?>
                                            </span>
                                        </td>
                                        <td><span class="sp-badge sp-badge-active" style="font-weight:bold; font-size:13px;"><?php echo esc_html($r->player_count); ?></span></td>
                                        <td><span class="sp-badge sp-badge-<?php echo esc_attr($r->status); ?>"><?php echo esc_html(ucfirst($r->status)); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No session records found.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif ($report_type === 'sport_report'): ?>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Sport Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Registered Players', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Assigned Coaches', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Groups', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Total Sessions Conducted', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><strong><span class="sp-badge sp-badge-sport"><?php echo esc_html($r->sport_name); ?></span></strong></td>
                                        <td><?php echo esc_html($r->player_count); ?></td>
                                        <td><?php echo esc_html($r->coach_count); ?></td>
                                        <td><?php echo esc_html($r->group_count); ?></td>
                                        <td><strong><?php echo esc_html($r->session_count); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>

                    <?php elseif ($report_type === 'group_report'): ?>
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Group Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Level', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Enrolled Players / Max', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Total Sessions', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($r->group_name); ?></strong></td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($r->sport_name); ?></span></td>
                                        <td><?php echo esc_html($r->coach_name ?: 'Unassigned'); ?></td>
                                        <td><span class="sp-badge sp-badge-level"><?php echo esc_html($r->level); ?></span></td>
                                        <td><?php echo esc_html($r->enrolled_players); ?> / <?php echo esc_html($r->max_capacity); ?></td>
                                        <td><strong><?php echo esc_html($r->session_count); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php
    }
}
