<?php
/**
 * Admin Views Renderer
 * Handles rendering of all admin pages: Dashboard, Players, Coaches, Sports, Groups, Time Slots, Sessions, Reports, Settings
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Admin_Views {

    public static function render_notices() {
        if (!isset($_GET['msg'])) {
            return;
        }

        $msg = sanitize_text_field($_GET['msg']);
        $err_msg = isset($_GET['err_msg']) ? sanitize_text_field($_GET['err_msg']) : '';

        $notices = array(
            'sport_created' => array('success', __('Sport created successfully.', 'sportedia')),
            'sport_updated' => array('success', __('Sport updated successfully.', 'sportedia')),
            'sport_deleted' => array('success', __('Sport deleted successfully.', 'sportedia')),
            'coach_created' => array('success', __('Coach created successfully.', 'sportedia')),
            'coach_updated' => array('success', __('Coach updated successfully.', 'sportedia')),
            'coach_deleted' => array('success', __('Coach deleted successfully.', 'sportedia')),
            'group_created' => array('success', __('Group created successfully.', 'sportedia')),
            'group_updated' => array('success', __('Group updated successfully.', 'sportedia')),
            'group_deleted' => array('success', __('Group deleted successfully.', 'sportedia')),
            'timeslot_created' => array('success', __('Time slot created successfully.', 'sportedia')),
            'timeslot_updated' => array('success', __('Time slot updated successfully.', 'sportedia')),
            'timeslot_deleted' => array('success', __('Time slot deleted successfully.', 'sportedia')),
            'player_created' => array('success', __('Player created successfully.', 'sportedia')),
            'player_updated' => array('success', __('Player updated successfully.', 'sportedia')),
            'player_deleted' => array('success', __('Player deleted successfully.', 'sportedia')),
            'session_created' => array('success', __('Session created successfully.', 'sportedia')),
            'session_updated' => array('success', __('Session updated successfully.', 'sportedia')),
            'session_duplicated' => array('success', __('Session duplicated successfully.', 'sportedia')),
            'session_deleted' => array('success', __('Session deleted successfully.', 'sportedia')),
            'settings_saved' => array('success', __('Settings saved successfully.', 'sportedia')),
            'error' => array('error', !empty($err_msg) ? urldecode($err_msg) : __('An error occurred. Please try again.', 'sportedia')),
        );

        if (isset($notices[$msg])) {
            $type = $notices[$msg][0];
            $text = $notices[$msg][1];
            echo '<div class="sp-notice sp-notice-' . esc_attr($type) . '">
                    <span>' . esc_html($text) . '</span>
                    <button type="button" class="sp-notice-dismiss" style="margin-left:auto; background:none; border:none; cursor:pointer;">&times;</button>
                  </div>';
        }
    }

    // 1. DASHBOARD PAGE
    public static function render_dashboard() {
        global $wpdb;

        $p_table = Sportedia_Model_Player::get_table_name();
        $c_table = Sportedia_Model_Coach::get_table_name();
        $s_table = Sportedia_Model_Sport::get_table_name();
        $sess_table = Sportedia_Model_Session::get_table_name();
        $sp_table = Sportedia_Model_Session::get_session_players_table_name();

        $total_players = $wpdb->get_var("SELECT COUNT(*) FROM $p_table");
        $active_players = $wpdb->get_var("SELECT COUNT(*) FROM $p_table WHERE status = 'active'");
        $total_coaches = $wpdb->get_var("SELECT COUNT(*) FROM $c_table");
        $active_coaches = $wpdb->get_var("SELECT COUNT(*) FROM $c_table WHERE status = 'active'");
        $total_sports = $wpdb->get_var("SELECT COUNT(*) FROM $s_table WHERE status = 'active'");

        $today = current_time('Y-m-d');
        $today_sessions = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $sess_table WHERE session_date = %s", $today));

        $today_players = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(sp.id) FROM $sp_table sp INNER JOIN $sess_table s ON sp.session_id = s.id WHERE s.session_date = %s", $today
        ));

        $weekly_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $sess_table WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $monthly_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $sess_table WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        // Coach Statistical Table Data
        $coach_stats = $wpdb->get_results(
            "SELECT c.id, c.full_name, c.color_hex, s.name as sport_name,
                    COUNT(DISTINCT sess.id) as total_sessions,
                    COUNT(sp.id) as total_assignments,
                    COUNT(DISTINCT sess.group_id) as active_groups
             FROM $c_table c
             LEFT JOIN $s_table s ON c.sport_id = s.id
             LEFT JOIN $sess_table sess ON c.id = sess.coach_id
             LEFT JOIN $sp_table sp ON sess.id = sp.session_id
             GROUP BY c.id
             ORDER BY c.full_name ASC"
        );

        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Sportedia Dashboard', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Overview of Sports Academy metrics, sessions, coaches, and player participations.', 'sportedia'); ?></p>
                </div>
                <div class="sportedia-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sessions&action=new')); ?>" class="sp-btn sp-btn-primary">+ <?php esc_html_e('New Session', 'sportedia'); ?></a>
                </div>
            </div>

            <!-- Main Metric Cards -->
            <div class="sportedia-grid sportedia-grid-4" style="margin-bottom: 24px;">
                <div class="sportedia-card sportedia-metric-card">
                    <div class="sportedia-metric-icon"><span class="dashicons dashicons-admin-users"></span></div>
                    <div>
                        <div class="sportedia-metric-value"><?php echo esc_html($active_players); ?> / <?php echo esc_html($total_players); ?></div>
                        <div class="sportedia-metric-label"><?php esc_html_e('Active / Total Players', 'sportedia'); ?></div>
                    </div>
                </div>

                <div class="sportedia-card sportedia-metric-card">
                    <div class="sportedia-metric-icon" style="background:#ecfdf5; color:#10b981;"><span class="dashicons dashicons-businessperson"></span></div>
                    <div>
                        <div class="sportedia-metric-value"><?php echo esc_html($active_coaches); ?> / <?php echo esc_html($total_coaches); ?></div>
                        <div class="sportedia-metric-label"><?php esc_html_e('Active / Total Coaches', 'sportedia'); ?></div>
                    </div>
                </div>

                <div class="sportedia-card sportedia-metric-card">
                    <div class="sportedia-metric-icon" style="background:#fef3c7; color:#d97706;"><span class="dashicons dashicons-awards"></span></div>
                    <div>
                        <div class="sportedia-metric-value"><?php echo esc_html($total_sports); ?></div>
                        <div class="sportedia-metric-label"><?php esc_html_e('Active Sports', 'sportedia'); ?></div>
                    </div>
                </div>

                <div class="sportedia-card sportedia-metric-card">
                    <div class="sportedia-metric-icon" style="background:#f3e8ff; color:#9333ea;"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div>
                        <div class="sportedia-metric-value"><?php echo esc_html($today_sessions); ?> (<?php echo esc_html($today_players); ?> <?php esc_html_e('Players', 'sportedia'); ?>)</div>
                        <div class="sportedia-metric-label"><?php esc_html_e("Today's Sessions", 'sportedia'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Secondary Analytics Row -->
            <div class="sportedia-grid sportedia-grid-3" style="margin-bottom: 24px;">
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Sessions Activity', 'sportedia'); ?></h3>
                    </div>
                    <div style="font-size: 14px; line-height: 2;">
                        <div><strong><?php esc_html_e("Today's Sessions:", 'sportedia'); ?></strong> <?php echo esc_html($today_sessions); ?></div>
                        <div><strong><?php esc_html_e('Weekly Sessions (7 Days):', 'sportedia'); ?></strong> <?php echo esc_html($weekly_sessions); ?></div>
                        <div><strong><?php esc_html_e('Monthly Sessions (30 Days):', 'sportedia'); ?></strong> <?php echo esc_html($monthly_sessions); ?></div>
                    </div>
                </div>

                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Quick Export Options', 'sportedia'); ?></h3>
                    </div>
                    <p style="font-size:13px; color:var(--sp-text-muted);"><?php esc_html_e('Generate styled Excel reports with color-coded coaches.', 'sportedia'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-export')); ?>" class="sp-btn sp-btn-secondary sp-btn-sm" style="width:100%; justify-content:center; margin-top:8px;">
                        <span class="dashicons dashicons-download"></span> <?php esc_html_e('Go to Excel Export Hub', 'sportedia'); ?>
                    </a>
                </div>

                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php esc_html_e('Quick Import', 'sportedia'); ?></h3>
                    </div>
                    <p style="font-size:13px; color:var(--sp-text-muted);"><?php esc_html_e('Batch import Players, Coaches, Sports, and Groups from Excel.', 'sportedia'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-import')); ?>" class="sp-btn sp-btn-secondary sp-btn-sm" style="width:100%; justify-content:center; margin-top:8px;">
                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e('Go to Excel Import Center', 'sportedia'); ?>
                    </a>
                </div>
            </div>

            <!-- Coach Statistical Table -->
            <div class="sportedia-card">
                <div class="sportedia-card-header">
                    <h3 class="sportedia-card-title"><?php esc_html_e('Coach Statistics & Participation Overview', 'sportedia'); ?></h3>
                </div>
                <div class="sportedia-table-container">
                    <table class="sportedia-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Primary Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Total Sessions', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Active Groups', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Total Player Assignments', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Avg Players / Session', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($coach_stats)): ?>
                                <?php foreach ($coach_stats as $cs):
                                    $avg = $cs->total_sessions > 0 ? round($cs->total_assignments / $cs->total_sessions, 1) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($cs->color_hex); ?>15; color: #1e293b;">
                                                <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($cs->color_hex); ?>;"></span>
                                                <?php echo esc_html($cs->full_name); ?>
                                            </span>
                                        </td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($cs->sport_name ?: 'N/A'); ?></span></td>
                                        <td><strong><?php echo esc_html($cs->total_sessions); ?></strong></td>
                                        <td><?php echo esc_html($cs->active_groups); ?></td>
                                        <td><strong><?php echo esc_html($cs->total_assignments); ?></strong></td>
                                        <td><span class="sp-badge sp-badge-pending"><?php echo esc_html($avg); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No coach records found.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php
    }

    // 2. SPORTS MANAGEMENT PAGE
    public static function render_sports() {
        $sports = Sportedia_Model_Sport::get_all();
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_sport = $edit_id ? Sportedia_Model_Sport::get($edit_id) : null;
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-awards"></span> <?php esc_html_e('Sports Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Manage academy sports categories and disciplines.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <!-- Add / Edit Form -->
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php echo $edit_sport ? esc_html__('Edit Sport', 'sportedia') : esc_html__('Add New Sport', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_admin_action">
                        <input type="hidden" name="sportedia_action" value="save_sport">
                        <input type="hidden" name="sport_id" value="<?php echo $edit_sport ? esc_attr($edit_sport->id) : 0; ?>">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Sport Name *', 'sportedia'); ?></label>
                            <input type="text" name="name" class="sp-input" required value="<?php echo $edit_sport ? esc_attr($edit_sport->name) : ''; ?>" placeholder="e.g. Swimming, Football">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Description', 'sportedia'); ?></label>
                            <textarea name="description" class="sp-textarea" rows="3"><?php echo $edit_sport ? esc_textarea($edit_sport->description) : ''; ?></textarea>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                            <select name="status" class="sp-select">
                                <option value="active" <?php selected($edit_sport ? $edit_sport->status : 'active', 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                                <option value="inactive" <?php selected($edit_sport ? $edit_sport->status : '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php echo $edit_sport ? esc_html__('Update Sport', 'sportedia') : esc_html__('Save Sport', 'sportedia'); ?></button>
                        <?php if ($edit_sport): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sports')); ?>" class="sp-btn sp-btn-secondary" style="width:100%; margin-top:8px; text-align:center; display:block;"><?php esc_html_e('Cancel Edit', 'sportedia'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="sportedia-table-container">
                    <table class="sportedia-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Description', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sports)): ?>
                                <?php foreach ($sports as $s): ?>
                                    <tr>
                                        <td>#<?php echo esc_html($s->id); ?></td>
                                        <td><strong><span class="sp-badge sp-badge-sport"><?php echo esc_html($s->name); ?></span></strong></td>
                                        <td><?php echo esc_html($s->description ?: '—'); ?></td>
                                        <td><span class="sp-badge sp-badge-<?php echo esc_attr($s->status); ?>"><?php echo esc_html(ucfirst($s->status)); ?></span></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sports&edit=' . $s->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Edit', 'sportedia'); ?></a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                <input type="hidden" name="action" value="sportedia_admin_action">
                                                <input type="hidden" name="sportedia_action" value="delete_sport">
                                                <input type="hidden" name="sport_id" value="<?php echo esc_attr($s->id); ?>">
                                                <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No sports added yet.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // 3. COACH MANAGEMENT PAGE
    public static function render_coaches() {
        $coaches = Sportedia_Model_Coach::get_all();
        $sports = Sportedia_Model_Sport::get_all('active');
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_coach = $edit_id ? Sportedia_Model_Coach::get($edit_id) : null;
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-businessperson"></span> <?php esc_html_e('Coach Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Manage academy coaches, sports specializations, and assigned colors.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <!-- Add / Edit Form -->
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php echo $edit_coach ? esc_html__('Edit Coach', 'sportedia') : esc_html__('Add New Coach', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_admin_action">
                        <input type="hidden" name="sportedia_action" value="save_coach">
                        <input type="hidden" name="coach_id" value="<?php echo $edit_coach ? esc_attr($edit_coach->id) : 0; ?>">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Coach ID / Code', 'sportedia'); ?></label>
                            <input type="text" name="coach_code" class="sp-input" value="<?php echo $edit_coach ? esc_attr($edit_coach->coach_code) : esc_attr(Sportedia_Model_Coach::generate_code()); ?>" required>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Full Name *', 'sportedia'); ?></label>
                            <input type="text" name="full_name" class="sp-input" required value="<?php echo $edit_coach ? esc_attr($edit_coach->full_name) : ''; ?>" placeholder="e.g. John Doe">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Sport', 'sportedia'); ?></label>
                            <select name="sport_id" class="sp-select">
                                <option value=""><?php esc_html_e('-- Select Sport --', 'sportedia'); ?></option>
                                <?php foreach ($sports as $sp): ?>
                                    <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($edit_coach ? $edit_coach->sport_id : '', $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Specialization', 'sportedia'); ?></label>
                            <input type="text" name="specialization" class="sp-input" value="<?php echo $edit_coach ? esc_attr($edit_coach->specialization) : ''; ?>" placeholder="e.g. Swimming Head Coach">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Phone / Contact', 'sportedia'); ?></label>
                            <input type="text" name="phone" class="sp-input" value="<?php echo $edit_coach ? esc_attr($edit_coach->phone) : ''; ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Email', 'sportedia'); ?></label>
                            <input type="email" name="email" class="sp-input" value="<?php echo $edit_coach ? esc_attr($edit_coach->email) : ''; ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Pastel Identification Color', 'sportedia'); ?></label>
                            <input type="color" name="color_hex" value="<?php echo $edit_coach ? esc_attr($edit_coach->color_hex) : '#3B82F6'; ?>" style="height:40px; cursor:pointer;" class="sp-input">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                            <select name="status" class="sp-select">
                                <option value="active" <?php selected($edit_coach ? $edit_coach->status : 'active', 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                                <option value="inactive" <?php selected($edit_coach ? $edit_coach->status : '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Notes', 'sportedia'); ?></label>
                            <textarea name="notes" class="sp-textarea" rows="2"><?php echo $edit_coach ? esc_textarea($edit_coach->notes) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php echo $edit_coach ? esc_html__('Update Coach', 'sportedia') : esc_html__('Save Coach', 'sportedia'); ?></button>
                        <?php if ($edit_coach): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-coaches')); ?>" class="sp-btn sp-btn-secondary" style="width:100%; margin-top:8px; text-align:center; display:block;"><?php esc_html_e('Cancel Edit', 'sportedia'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="sportedia-table-container">
                    <table class="sportedia-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Code', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Specialization', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Contact', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($coaches)): ?>
                                <?php foreach ($coaches as $c): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($c->coach_code); ?></code></td>
                                        <td>
                                            <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($c->color_hex); ?>18; color: #1e293b;">
                                                <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($c->color_hex); ?>;"></span>
                                                <strong><?php echo esc_html($c->full_name); ?></strong>
                                            </span>
                                        </td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($c->sport_name ?: 'N/A'); ?></span></td>
                                        <td><?php echo esc_html($c->specialization ?: '—'); ?></td>
                                        <td>
                                            <?php echo esc_html($c->phone ?: ''); ?>
                                            <?php if ($c->email): ?><br><small style="color:var(--sp-text-muted);"><?php echo esc_html($c->email); ?></small><?php endif; ?>
                                        </td>
                                        <td><span class="sp-badge sp-badge-<?php echo esc_attr($c->status); ?>"><?php echo esc_html(ucfirst($c->status)); ?></span></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-coaches&edit=' . $c->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Edit', 'sportedia'); ?></a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                <input type="hidden" name="action" value="sportedia_admin_action">
                                                <input type="hidden" name="sportedia_action" value="delete_coach">
                                                <input type="hidden" name="coach_id" value="<?php echo esc_attr($c->id); ?>">
                                                <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No coaches registered yet.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // 4. GROUPS MANAGEMENT PAGE
    public static function render_groups() {
        $groups = Sportedia_Model_Group::get_all();
        $sports = Sportedia_Model_Sport::get_all('active');
        $coaches = Sportedia_Model_Coach::get_all(array('status' => 'active'));
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_group = $edit_id ? Sportedia_Model_Group::get($edit_id) : null;
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-category"></span> <?php esc_html_e('Groups Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Organize training groups by sports, levels, and coaches.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php echo $edit_group ? esc_html__('Edit Group', 'sportedia') : esc_html__('Add New Group', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_admin_action">
                        <input type="hidden" name="sportedia_action" value="save_group">
                        <input type="hidden" name="group_id" value="<?php echo $edit_group ? esc_attr($edit_group->id) : 0; ?>">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Group Name *', 'sportedia'); ?></label>
                            <input type="text" name="name" class="sp-input" required value="<?php echo $edit_group ? esc_attr($edit_group->name) : ''; ?>" placeholder="e.g. Group A - Swimming">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Sport *', 'sportedia'); ?></label>
                            <select name="sport_id" class="sp-select" required>
                                <option value=""><?php esc_html_e('-- Select Sport --', 'sportedia'); ?></option>
                                <?php foreach ($sports as $sp): ?>
                                    <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($edit_group ? $edit_group->sport_id : '', $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Assigned Coach', 'sportedia'); ?></label>
                            <select name="coach_id" class="sp-select">
                                <option value=""><?php esc_html_e('-- Select Coach --', 'sportedia'); ?></option>
                                <?php foreach ($coaches as $c): ?>
                                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($edit_group ? $edit_group->coach_id : '', $c->id); ?>><?php echo esc_html($c->full_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Level', 'sportedia'); ?></label>
                            <select name="level" class="sp-select">
                                <option value="Beginner" <?php selected($edit_group ? $edit_group->level : '', 'Beginner'); ?>><?php esc_html_e('Beginner', 'sportedia'); ?></option>
                                <option value="Intermediate" <?php selected($edit_group ? $edit_group->level : '', 'Intermediate'); ?>><?php esc_html_e('Intermediate', 'sportedia'); ?></option>
                                <option value="Advanced" <?php selected($edit_group ? $edit_group->level : '', 'Advanced'); ?>><?php esc_html_e('Advanced', 'sportedia'); ?></option>
                                <option value="Professional" <?php selected($edit_group ? $edit_group->level : '', 'Professional'); ?>><?php esc_html_e('Professional', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Training Schedule Description', 'sportedia'); ?></label>
                            <input type="text" name="training_time" class="sp-input" value="<?php echo $edit_group ? esc_attr($edit_group->training_time) : ''; ?>" placeholder="e.g. Mon / Wed / Fri 04:00 PM">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Max Capacity', 'sportedia'); ?></label>
                            <input type="number" name="max_capacity" class="sp-input" value="<?php echo $edit_group ? esc_attr($edit_group->max_capacity) : 20; ?>" min="1">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                            <select name="status" class="sp-select">
                                <option value="active" <?php selected($edit_group ? $edit_group->status : 'active', 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                                <option value="inactive" <?php selected($edit_group ? $edit_group->status : '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php echo $edit_group ? esc_html__('Update Group', 'sportedia') : esc_html__('Save Group', 'sportedia'); ?></button>
                        <?php if ($edit_group): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-groups')); ?>" class="sp-btn sp-btn-secondary" style="width:100%; margin-top:8px; text-align:center; display:block;"><?php esc_html_e('Cancel Edit', 'sportedia'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="sportedia-table-container">
                    <table class="sportedia-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Group Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Level', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Training Time', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Max Capacity', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($groups)): ?>
                                <?php foreach ($groups as $g): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($g->name); ?></strong></td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($g->sport_name ?: 'N/A'); ?></span></td>
                                        <td><?php echo esc_html($g->coach_name ?: 'Unassigned'); ?></td>
                                        <td><span class="sp-badge sp-badge-level"><?php echo esc_html($g->level ?: 'N/A'); ?></span></td>
                                        <td><?php echo esc_html($g->training_time ?: '—'); ?></td>
                                        <td><?php echo esc_html($g->max_capacity); ?></td>
                                        <td><span class="sp-badge sp-badge-<?php echo esc_attr($g->status); ?>"><?php echo esc_html(ucfirst($g->status)); ?></span></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-groups&edit=' . $g->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Edit', 'sportedia'); ?></a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                <input type="hidden" name="action" value="sportedia_admin_action">
                                                <input type="hidden" name="sportedia_action" value="delete_group">
                                                <input type="hidden" name="group_id" value="<?php echo esc_attr($g->id); ?>">
                                                <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No groups configured yet.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // 5. TIME SLOTS PAGE
    public static function render_timeslots() {
        $timeslots = Sportedia_Model_TimeSlot::get_all();
        $sports = Sportedia_Model_Sport::get_all('active');
        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_ts = $edit_id ? Sportedia_Model_TimeSlot::get($edit_id) : null;
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Time Slots Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Manage operational training session time slots.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php echo $edit_ts ? esc_html__('Edit Time Slot', 'sportedia') : esc_html__('Add New Time Slot', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_admin_action">
                        <input type="hidden" name="sportedia_action" value="save_timeslot">
                        <input type="hidden" name="timeslot_id" value="<?php echo $edit_ts ? esc_attr($edit_ts->id) : 0; ?>">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Display Label *', 'sportedia'); ?></label>
                            <input type="text" name="display_name" class="sp-input" required value="<?php echo $edit_ts ? esc_attr($edit_ts->display_name) : ''; ?>" placeholder="e.g. 02:00 PM - 03:00 PM">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Start Time *', 'sportedia'); ?></label>
                            <input type="time" name="start_time" class="sp-input" required value="<?php echo $edit_ts ? esc_attr($edit_ts->start_time) : '14:00'; ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('End Time *', 'sportedia'); ?></label>
                            <input type="time" name="end_time" class="sp-input" required value="<?php echo $edit_ts ? esc_attr($edit_ts->end_time) : '15:00'; ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Sport Specific (Optional)', 'sportedia'); ?></label>
                            <select name="sport_id" class="sp-select">
                                <option value=""><?php esc_html_e('All Sports', 'sportedia'); ?></option>
                                <?php foreach ($sports as $sp): ?>
                                    <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($edit_ts ? $edit_ts->sport_id : '', $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                            <select name="status" class="sp-select">
                                <option value="active" <?php selected($edit_ts ? $edit_ts->status : 'active', 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                                <option value="inactive" <?php selected($edit_ts ? $edit_ts->status : '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php echo $edit_ts ? esc_html__('Update Time Slot', 'sportedia') : esc_html__('Save Time Slot', 'sportedia'); ?></button>
                        <?php if ($edit_ts): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-time-slots')); ?>" class="sp-btn sp-btn-secondary" style="width:100%; margin-top:8px; text-align:center; display:block;"><?php esc_html_e('Cancel Edit', 'sportedia'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="sportedia-table-container">
                    <table class="sportedia-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Display Name', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Start Time', 'sportedia'); ?></th>
                                <th><?php esc_html_e('End Time', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($timeslots)): ?>
                                <?php foreach ($timeslots as $ts): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($ts->display_name); ?></strong></td>
                                        <td><code><?php echo esc_html($ts->start_time); ?></code></td>
                                        <td><code><?php echo esc_html($ts->end_time); ?></code></td>
                                        <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($ts->sport_name ?: 'All Sports'); ?></span></td>
                                        <td><span class="sp-badge sp-badge-<?php echo esc_attr($ts->status); ?>"><?php echo esc_html(ucfirst($ts->status)); ?></span></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-time-slots&edit=' . $ts->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Edit', 'sportedia'); ?></a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                <input type="hidden" name="action" value="sportedia_admin_action">
                                                <input type="hidden" name="sportedia_action" value="delete_timeslot">
                                                <input type="hidden" name="timeslot_id" value="<?php echo esc_attr($ts->id); ?>">
                                                <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No time slots created yet.', 'sportedia'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // 6. PLAYER MANAGEMENT PAGE
    public static function render_players() {
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit = 15;
        $offset = ($paged - 1) * $limit;

        $args = array(
            'limit' => $limit,
            'offset' => $offset,
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'sport_id' => isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 0,
            'group_id' => isset($_GET['group_id']) ? intval($_GET['group_id']) : 0,
            'level' => isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
        );

        $players = Sportedia_Model_Player::get_all($args);
        $total_players = Sportedia_Model_Player::count_all($args);
        $total_pages = ceil($total_players / $limit);

        $sports = Sportedia_Model_Sport::get_all('active');
        $groups = Sportedia_Model_Group::get_all(array('status' => 'active'));

        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_player = $edit_id ? Sportedia_Model_Player::get($edit_id) : null;
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Player Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Manage academy players, IDs, ages, sports, levels, and group enrollments.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-1-3">
                <!-- Add / Edit Form -->
                <div class="sportedia-card">
                    <div class="sportedia-card-header">
                        <h3 class="sportedia-card-title"><?php echo $edit_player ? esc_html__('Edit Player', 'sportedia') : esc_html__('Add New Player', 'sportedia'); ?></h3>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="sportedia_admin_action">
                        <input type="hidden" name="sportedia_action" value="save_player">
                        <input type="hidden" name="player_id" value="<?php echo $edit_player ? esc_attr($edit_player->id) : 0; ?>">
                        <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Player Code / ID', 'sportedia'); ?></label>
                            <input type="text" name="player_code" class="sp-input" value="<?php echo $edit_player ? esc_attr($edit_player->player_code) : esc_attr(Sportedia_Model_Player::generate_code()); ?>" required>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Full Name *', 'sportedia'); ?></label>
                            <input type="text" name="full_name" class="sp-input" required value="<?php echo $edit_player ? esc_attr($edit_player->full_name) : ''; ?>" placeholder="e.g. Alex Johnson">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Gender', 'sportedia'); ?></label>
                            <select name="gender" class="sp-select">
                                <option value="Male" <?php selected($edit_player ? $edit_player->gender : 'Male', 'Male'); ?>><?php esc_html_e('Male', 'sportedia'); ?></option>
                                <option value="Female" <?php selected($edit_player ? $edit_player->gender : '', 'Female'); ?>><?php esc_html_e('Female', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Date of Birth', 'sportedia'); ?></label>
                            <input type="date" name="date_of_birth" class="sp-input" value="<?php echo $edit_player ? esc_attr($edit_player->date_of_birth) : ''; ?>">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Age', 'sportedia'); ?></label>
                            <input type="number" name="age" class="sp-input" value="<?php echo $edit_player ? esc_attr($edit_player->age) : 10; ?>" min="1">
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Sport', 'sportedia'); ?></label>
                            <select name="sport_id" class="sp-select">
                                <option value=""><?php esc_html_e('-- Select Sport --', 'sportedia'); ?></option>
                                <?php foreach ($sports as $sp): ?>
                                    <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($edit_player ? $edit_player->sport_id : '', $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Group', 'sportedia'); ?></label>
                            <select name="group_id" class="sp-select">
                                <option value=""><?php esc_html_e('-- Select Group --', 'sportedia'); ?></option>
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?php echo esc_attr($g->id); ?>" <?php selected($edit_player ? $edit_player->group_id : '', $g->id); ?>><?php echo esc_html($g->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Level', 'sportedia'); ?></label>
                            <select name="level" class="sp-select">
                                <option value="Beginner" <?php selected($edit_player ? $edit_player->level : 'Beginner', 'Beginner'); ?>><?php esc_html_e('Beginner', 'sportedia'); ?></option>
                                <option value="Intermediate" <?php selected($edit_player ? $edit_player->level : '', 'Intermediate'); ?>><?php esc_html_e('Intermediate', 'sportedia'); ?></option>
                                <option value="Advanced" <?php selected($edit_player ? $edit_player->level : '', 'Advanced'); ?>><?php esc_html_e('Advanced', 'sportedia'); ?></option>
                                <option value="Professional" <?php selected($edit_player ? $edit_player->level : '', 'Professional'); ?>><?php esc_html_e('Professional', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                            <select name="status" class="sp-select">
                                <option value="active" <?php selected($edit_player ? $edit_player->status : 'active', 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                                <option value="inactive" <?php selected($edit_player ? $edit_player->status : '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                            </select>
                        </div>

                        <div class="sp-form-group">
                            <label class="sp-form-label"><?php esc_html_e('Notes', 'sportedia'); ?></label>
                            <textarea name="notes" class="sp-textarea" rows="2"><?php echo $edit_player ? esc_textarea($edit_player->notes) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="sp-btn sp-btn-primary" style="width:100%;"><?php echo $edit_player ? esc_html__('Update Player', 'sportedia') : esc_html__('Save Player', 'sportedia'); ?></button>
                        <?php if ($edit_player): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-players')); ?>" class="sp-btn sp-btn-secondary" style="width:100%; margin-top:8px; text-align:center; display:block;"><?php esc_html_e('Cancel Edit', 'sportedia'); ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Right List Container with Filter Bar -->
                <div>
                    <form method="get" class="sportedia-filter-bar">
                        <input type="hidden" name="page" value="sportedia-players">

                        <input type="text" name="s" class="sp-input" placeholder="Search name or ID..." value="<?php echo esc_attr($args['search']); ?>" style="width: 180px;">

                        <select name="sport_id" class="sp-select" style="width: 150px;">
                            <option value=""><?php esc_html_e('All Sports', 'sportedia'); ?></option>
                            <?php foreach ($sports as $sp): ?>
                                <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($args['sport_id'], $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="level" class="sp-select" style="width: 130px;">
                            <option value=""><?php esc_html_e('All Levels', 'sportedia'); ?></option>
                            <option value="Beginner" <?php selected($args['level'], 'Beginner'); ?>><?php esc_html_e('Beginner', 'sportedia'); ?></option>
                            <option value="Intermediate" <?php selected($args['level'], 'Intermediate'); ?>><?php esc_html_e('Intermediate', 'sportedia'); ?></option>
                            <option value="Advanced" <?php selected($args['level'], 'Advanced'); ?>><?php esc_html_e('Advanced', 'sportedia'); ?></option>
                            <option value="Professional" <?php selected($args['level'], 'Professional'); ?>><?php esc_html_e('Professional', 'sportedia'); ?></option>
                        </select>

                        <select name="status" class="sp-select" style="width: 120px;">
                            <option value=""><?php esc_html_e('All Status', 'sportedia'); ?></option>
                            <option value="active" <?php selected($args['status'], 'active'); ?>><?php esc_html_e('Active', 'sportedia'); ?></option>
                            <option value="inactive" <?php selected($args['status'], 'inactive'); ?>><?php esc_html_e('Inactive', 'sportedia'); ?></option>
                        </select>

                        <button type="submit" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Filter', 'sportedia'); ?></button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-players')); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Clear', 'sportedia'); ?></a>
                    </form>

                    <div class="sportedia-table-container">
                        <table class="sportedia-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Player Code', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Full Name', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Gender', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Age', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Level', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                                    <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($players)): ?>
                                    <?php foreach ($players as $p): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($p->player_code); ?></code></td>
                                            <td><strong><?php echo esc_html($p->full_name); ?></strong></td>
                                            <td><?php echo esc_html($p->gender); ?></td>
                                            <td><?php echo esc_html($p->age); ?> yrs</td>
                                            <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($p->sport_name ?: 'N/A'); ?></span></td>
                                            <td><span class="sp-badge sp-badge-level"><?php echo esc_html($p->level ?: 'N/A'); ?></span></td>
                                            <td><span class="sp-badge sp-badge-<?php echo esc_attr($p->status); ?>"><?php echo esc_html(ucfirst($p->status)); ?></span></td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-players&edit=' . $p->id)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Edit', 'sportedia'); ?></a>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                                    <input type="hidden" name="action" value="sportedia_admin_action">
                                                    <input type="hidden" name="sportedia_action" value="delete_player">
                                                    <input type="hidden" name="player_id" value="<?php echo esc_attr($p->id); ?>">
                                                    <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                                    <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No players found.', 'sportedia'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if ($total_pages > 1): ?>
                            <div class="sportedia-pagination">
                                <span>Showing page <?php echo esc_html($paged); ?> of <?php echo esc_html($total_pages); ?> (Total: <?php echo esc_html($total_players); ?>)</span>
                                <div>
                                    <?php if ($paged > 1): ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm">&laquo; Previous</a>
                                    <?php endif; ?>
                                    <?php if ($paged < $total_pages): ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm">Next &raquo;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // 7. SESSION MANAGEMENT (CORE FEATURE)
    public static function render_sessions() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($action === 'new' || $action === 'edit') {
            self::render_session_form($session_id);
            return;
        }

        // Sessions Overview Table
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit = 15;
        $offset = ($paged - 1) * $limit;

        $args = array(
            'limit' => $limit,
            'offset' => $offset,
            'session_date' => isset($_GET['session_date']) ? sanitize_text_field($_GET['session_date']) : '',
            'sport_id' => isset($_GET['sport_id']) ? intval($_GET['sport_id']) : 0,
            'coach_id' => isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0,
            'time_slot_id' => isset($_GET['time_slot_id']) ? intval($_GET['time_slot_id']) : 0,
            'group_id' => isset($_GET['group_id']) ? intval($_GET['group_id']) : 0,
        );

        $sessions = Sportedia_Model_Session::get_all($args);
        $total_sessions = Sportedia_Model_Session::count_all($args);
        $total_pages = ceil($total_sessions / $limit);

        $sports = Sportedia_Model_Sport::get_all('active');
        $coaches = Sportedia_Model_Coach::get_all(array('status' => 'active'));
        $timeslots = Sportedia_Model_TimeSlot::get_all(array('status' => 'active'));
        $groups = Sportedia_Model_Group::get_all(array('status' => 'active'));
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e('Session Management', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Record player participation by Date, Time Slot, Sport, Group, and Coach.', 'sportedia'); ?></p>
                </div>
                <div class="sportedia-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sessions&action=new')); ?>" class="sp-btn sp-btn-primary">+ <?php esc_html_e('Create New Session', 'sportedia'); ?></a>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="get" class="sportedia-filter-bar">
                <input type="hidden" name="page" value="sportedia-sessions">

                <input type="date" name="session_date" class="sp-input" value="<?php echo esc_attr($args['session_date']); ?>" style="width: 160px;">

                <select name="sport_id" class="sp-select" style="width: 150px;">
                    <option value=""><?php esc_html_e('All Sports', 'sportedia'); ?></option>
                    <?php foreach ($sports as $sp): ?>
                        <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($args['sport_id'], $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="coach_id" class="sp-select" style="width: 150px;">
                    <option value=""><?php esc_html_e('All Coaches', 'sportedia'); ?></option>
                    <?php foreach ($coaches as $c): ?>
                        <option value="<?php echo esc_attr($c->id); ?>" <?php selected($args['coach_id'], $c->id); ?>><?php echo esc_html($c->full_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="time_slot_id" class="sp-select" style="width: 150px;">
                    <option value=""><?php esc_html_e('All Time Slots', 'sportedia'); ?></option>
                    <?php foreach ($timeslots as $ts): ?>
                        <option value="<?php echo esc_attr($ts->id); ?>" <?php selected($args['time_slot_id'], $ts->id); ?>><?php echo esc_html($ts->display_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="group_id" class="sp-select" style="width: 150px;">
                    <option value=""><?php esc_html_e('All Groups', 'sportedia'); ?></option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo esc_attr($g->id); ?>" <?php selected($args['group_id'], $g->id); ?>><?php echo esc_html($g->name); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Apply Filters', 'sportedia'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sessions')); ?>" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Reset', 'sportedia'); ?></a>
            </form>

            <div class="sportedia-table-container">
                <table class="sportedia-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Time Slot', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Group', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Coach', 'sportedia'); ?></th>
                            <th style="text-align:center;"><?php esc_html_e('Players', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Status', 'sportedia'); ?></th>
                            <th><?php esc_html_e('Actions', 'sportedia'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sessions)): ?>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($s->session_date); ?></strong></td>
                                    <td><code><?php echo esc_html($s->time_slot_name); ?></code></td>
                                    <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($s->sport_name); ?></span></td>
                                    <td><?php echo esc_html($s->group_name); ?></td>
                                    <td>
                                        <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($s->coach_color); ?>18; color: #1e293b;">
                                            <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($s->coach_color); ?>;"></span>
                                            <?php echo esc_html($s->coach_name); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="sp-badge sp-badge-active" style="font-size:13px; font-weight:bold;"><?php echo esc_html($s->player_count); ?></span>
                                    </td>
                                    <td><span class="sp-badge sp-badge-<?php echo esc_attr($s->status); ?>"><?php echo esc_html(ucfirst($s->status)); ?></span></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sessions&action=edit&id=' . $s->id)); ?>" class="sp-btn sp-btn-primary sp-btn-sm"><?php esc_html_e('Add/Manage Players', 'sportedia'); ?></a>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                                            <input type="hidden" name="action" value="sportedia_admin_action">
                                            <input type="hidden" name="sportedia_action" value="duplicate_session">
                                            <input type="hidden" name="session_id" value="<?php echo esc_attr($s->id); ?>">
                                            <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                            <button type="submit" class="sp-btn sp-btn-secondary sp-btn-sm"><?php esc_html_e('Duplicate', 'sportedia'); ?></button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" class="sp-confirm-delete">
                                            <input type="hidden" name="action" value="sportedia_admin_action">
                                            <input type="hidden" name="sportedia_action" value="delete_session">
                                            <input type="hidden" name="session_id" value="<?php echo esc_attr($s->id); ?>">
                                            <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>
                                            <button type="submit" class="sp-btn sp-btn-danger sp-btn-sm"><?php esc_html_e('Delete', 'sportedia'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No sessions recorded yet.', 'sportedia'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <div class="sportedia-pagination">
                        <span>Showing page <?php echo esc_html($paged); ?> of <?php echo esc_html($total_pages); ?> (Total: <?php echo esc_html($total_sessions); ?>)</span>
                        <div>
                            <?php if ($paged > 1): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm">&laquo; Previous</a>
                            <?php endif; ?>
                            <?php if ($paged < $total_pages): ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>" class="sp-btn sp-btn-secondary sp-btn-sm">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // 7.1 CORE SESSION WORKFLOW & PLAYER ASSIGNMENT INTERFACE
    private static function render_session_form($session_id = 0) {
        $session = $session_id ? Sportedia_Model_Session::get($session_id) : null;
        $sports = Sportedia_Model_Sport::get_all('active');
        $coaches = Sportedia_Model_Coach::get_all(array('status' => 'active'));
        $timeslots = Sportedia_Model_TimeSlot::get_all(array('status' => 'active'));
        $groups = Sportedia_Model_Group::get_all(array('status' => 'active'));

        $assigned_players = $session_id ? Sportedia_Model_Session::get_players($session_id) : array();
        ?>
        <div class="sportedia-wrap">
            <?php self::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-edit"></span> <?php echo $session ? esc_html__('Manage Operational Session', 'sportedia') : esc_html__('Create New Training Session', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Set up training session details then search and assign players rapidly.', 'sportedia'); ?></p>
                </div>
                <div class="sportedia-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sportedia-sessions')); ?>" class="sp-btn sp-btn-secondary">&laquo; <?php esc_html_e('Back to Sessions', 'sportedia'); ?></a>
                </div>
            </div>

            <div class="sportedia-grid sportedia-grid-3-1">
                <div>
                    <!-- Session Configuration Box -->
                    <div class="sportedia-card" style="margin-bottom: 24px;">
                        <div class="sportedia-card-header">
                            <h3 class="sportedia-card-title"><?php esc_html_e('1. Session Configuration', 'sportedia'); ?></h3>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="sportedia_admin_action">
                            <input type="hidden" name="sportedia_action" value="save_session">
                            <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                            <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                            <div class="sportedia-grid sportedia-grid-3" style="margin-bottom:16px;">
                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Date *', 'sportedia'); ?></label>
                                    <input type="date" name="session_date" class="sp-input" required value="<?php echo $session ? esc_attr($session->session_date) : current_time('Y-m-d'); ?>">
                                </div>

                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Sport *', 'sportedia'); ?></label>
                                    <select name="sport_id" class="sp-select" required>
                                        <option value=""><?php esc_html_e('-- Select Sport --', 'sportedia'); ?></option>
                                        <?php foreach ($sports as $sp): ?>
                                            <option value="<?php echo esc_attr($sp->id); ?>" <?php selected($session ? $session->sport_id : '', $sp->id); ?>><?php echo esc_html($sp->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Time Slot *', 'sportedia'); ?></label>
                                    <select name="time_slot_id" class="sp-select" required>
                                        <option value=""><?php esc_html_e('-- Select Time Slot --', 'sportedia'); ?></option>
                                        <?php foreach ($timeslots as $ts): ?>
                                            <option value="<?php echo esc_attr($ts->id); ?>" <?php selected($session ? $session->time_slot_id : '', $ts->id); ?>><?php echo esc_html($ts->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Group *', 'sportedia'); ?></label>
                                    <select name="group_id" class="sp-select" required>
                                        <option value=""><?php esc_html_e('-- Select Group --', 'sportedia'); ?></option>
                                        <?php foreach ($groups as $g): ?>
                                            <option value="<?php echo esc_attr($g->id); ?>" <?php selected($session ? $session->group_id : '', $g->id); ?>><?php echo esc_html($g->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Coach *', 'sportedia'); ?></label>
                                    <select name="coach_id" class="sp-select" required>
                                        <option value=""><?php esc_html_e('-- Select Coach --', 'sportedia'); ?></option>
                                        <?php foreach ($coaches as $c): ?>
                                            <option value="<?php echo esc_attr($c->id); ?>" <?php selected($session ? $session->coach_id : '', $c->id); ?>><?php echo esc_html($c->full_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="sp-form-group">
                                    <label class="sp-form-label"><?php esc_html_e('Status', 'sportedia'); ?></label>
                                    <select name="status" class="sp-select">
                                        <option value="completed" <?php selected($session ? $session->status : 'completed', 'completed'); ?>><?php esc_html_e('Completed', 'sportedia'); ?></option>
                                        <option value="pending" <?php selected($session ? $session->status : '', 'pending'); ?>><?php esc_html_e('Pending', 'sportedia'); ?></option>
                                        <option value="cancelled" <?php selected($session ? $session->status : '', 'cancelled'); ?>><?php esc_html_e('Cancelled', 'sportedia'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="sp-btn sp-btn-primary"><?php echo $session ? esc_html__('Save Configuration Changes', 'sportedia') : esc_html__('Create Session & Proceed to Add Players', 'sportedia'); ?></button>
                        </form>
                    </div>

                    <?php if ($session_id > 0): ?>
                        <!-- 2. Rapid Player Search & Assignment Area -->
                        <div class="sportedia-card">
                            <div class="sportedia-card-header">
                                <h3 class="sportedia-card-title"><?php esc_html_e('2. Rapid Player Search & One-Click Assignment', 'sportedia'); ?></h3>
                                <span class="sp-badge sp-badge-active" id="sp-assigned-count-badge"><?php echo count($assigned_players); ?> <?php esc_html_e('Players Assigned', 'sportedia'); ?></span>
                            </div>

                            <div id="sp-ajax-notice-container"></div>

                            <div class="sp-search-container" style="margin-bottom: 20px;">
                                <label class="sp-form-label" style="font-size:14px; color:var(--sp-primary);"><?php esc_html_e('Search player by name or Player ID...', 'sportedia'); ?></label>
                                <input type="text" id="sp-player-search-input" class="sp-input" placeholder="Type name (e.g. John) or ID (e.g. PLY-0001)..." style="font-size:15px; padding:12px 16px;">
                                <div id="sp-player-search-results" class="sp-autocomplete-results"></div>
                            </div>

                            <!-- Assigned Players List -->
                            <div class="sportedia-table-container">
                                <table class="sportedia-table" id="sp-assigned-players-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Code', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Player Name', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Gender', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Age', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Sport', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Level', 'sportedia'); ?></th>
                                            <th><?php esc_html_e('Action', 'sportedia'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="sp-assigned-players-tbody">
                                        <?php if (!empty($assigned_players)): ?>
                                            <?php foreach ($assigned_players as $p): ?>
                                                <tr id="sp-player-row-<?php echo esc_attr($p->id); ?>">
                                                    <td><code><?php echo esc_html($p->player_code); ?></code></td>
                                                    <td><strong><?php echo esc_html($p->full_name); ?></strong></td>
                                                    <td><?php echo esc_html($p->gender); ?></td>
                                                    <td><?php echo esc_html($p->age); ?> yrs</td>
                                                    <td><span class="sp-badge sp-badge-sport"><?php echo esc_html($p->sport_name ?: 'N/A'); ?></span></td>
                                                    <td><span class="sp-badge sp-badge-level"><?php echo esc_html($p->level ?: 'N/A'); ?></span></td>
                                                    <td>
                                                        <button type="button" class="sp-btn sp-btn-danger sp-btn-sm sp-remove-player-btn" data-id="<?php echo esc_attr($p->id); ?>"><?php esc_html_e('Remove', 'sportedia'); ?></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="sp-no-players-row"><td colspan="7" style="text-align:center; color:var(--sp-text-muted);"><?php esc_html_e('No players assigned to this session yet. Use the search bar above to add players.', 'sportedia'); ?></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- JavaScript handler for AJAX additions/removals in session -->
                        <script>
                        jQuery(document).ready(function($) {
                            const sessionId = <?php echo intval($session_id); ?>;

                            // Add Player
                            $(document).on('click', '.sp-add-player-btn', function() {
                                const playerId = $(this).data('id');
                                $('#sp-player-search-results').hide();
                                $('#sp-player-search-input').val('');

                                $.ajax({
                                    url: sportediaVars.ajaxurl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                        action: 'sportedia_add_session_player',
                                        nonce: sportediaVars.nonce,
                                        session_id: sessionId,
                                        player_id: playerId
                                    },
                                    success: function(res) {
                                        if (res.success) {
                                            $('#sp-ajax-notice-container').html('<div class="sp-notice sp-notice-success"><span>' + res.data.message + '</span></div>');
                                            renderPlayersTable(res.data.players);
                                        } else {
                                            $('#sp-ajax-notice-container').html('<div class="sp-notice sp-notice-error"><span>' + res.data.message + '</span></div>');
                                        }
                                    }
                                });
                            });

                            // Remove Player
                            $(document).on('click', '.sp-remove-player-btn', function() {
                                const playerId = $(this).data('id');

                                $.ajax({
                                    url: sportediaVars.ajaxurl,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                        action: 'sportedia_remove_session_player',
                                        nonce: sportediaVars.nonce,
                                        session_id: sessionId,
                                        player_id: playerId
                                    },
                                    success: function(res) {
                                        if (res.success) {
                                            $('#sp-ajax-notice-container').html('<div class="sp-notice sp-notice-warning"><span>' + res.data.message + '</span></div>');
                                            renderPlayersTable(res.data.players);
                                        }
                                    }
                                });
                            });

                            function renderPlayersTable(players) {
                                const $tbody = $('#sp-assigned-players-tbody');
                                $tbody.empty();
                                $('#sp-assigned-count-badge').text(players.length + ' Players Assigned');

                                if (players.length === 0) {
                                    $tbody.html('<tr id="sp-no-players-row"><td colspan="7" style="text-align:center; color:var(--sp-text-muted);">No players assigned to this session yet. Use the search bar above to add players.</td></tr>');
                                    return;
                                }

                                $.each(players, function(idx, p) {
                                    const row = `
                                        <tr id="sp-player-row-${p.id}">
                                            <td><code>${p.player_code}</code></td>
                                            <td><strong>${p.full_name}</strong></td>
                                            <td>${p.gender}</td>
                                            <td>${p.age} yrs</td>
                                            <td><span class="sp-badge sp-badge-sport">${p.sport_name || 'N/A'}</span></td>
                                            <td><span class="sp-badge sp-badge-level">${p.level || 'N/A'}</span></td>
                                            <td>
                                                <button type="button" class="sp-btn sp-btn-danger sp-btn-sm sp-remove-player-btn" data-id="${p.id}">Remove</button>
                                            </td>
                                        </tr>
                                    `;
                                    $tbody.append(row);
                                });
                            }
                        });
                        </script>
                    <?php endif; ?>
                </div>

                <!-- Session Metadata Card -->
                <div>
                    <div class="sportedia-card">
                        <div class="sportedia-card-header">
                            <h3 class="sportedia-card-title"><?php esc_html_e('Session Summary', 'sportedia'); ?></h3>
                        </div>
                        <?php if ($session): ?>
                            <div style="font-size:13px; line-height:1.8;">
                                <div><strong><?php esc_html_e('Date:', 'sportedia'); ?></strong> <?php echo esc_html($session->session_date); ?></div>
                                <div><strong><?php esc_html_e('Time:', 'sportedia'); ?></strong> <?php echo esc_html($session->time_slot_name); ?></div>
                                <div><strong><?php esc_html_e('Sport:', 'sportedia'); ?></strong> <?php echo esc_html($session->sport_name); ?></div>
                                <div><strong><?php esc_html_e('Group:', 'sportedia'); ?></strong> <?php echo esc_html($session->group_name); ?></div>
                                <div>
                                    <strong><?php esc_html_e('Coach:', 'sportedia'); ?></strong><br>
                                    <span class="sp-coach-badge" style="background-color: <?php echo esc_attr($session->coach_color); ?>18; color: #1e293b; margin-top:4px;">
                                        <span class="sp-coach-swatch" style="background-color: <?php echo esc_attr($session->coach_color); ?>;"></span>
                                        <?php echo esc_html($session->coach_name); ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="font-size:13px; color:var(--sp-text-muted);"><?php esc_html_e('Fill in session parameters on the left to activate player registration.', 'sportedia'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
