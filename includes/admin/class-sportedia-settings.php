<?php
/**
 * Settings Page Renderer
 *
 * @package Sportedia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sportedia_Settings {

    public static function render_page() {
        $academy_name = get_option('sportedia_academy_name', 'Sportedia Sports Academy');
        $date_format = get_option('sportedia_date_format', 'Y-m-d');
        $time_format = get_option('sportedia_time_format', 'h:i A');
        ?>
        <div class="sportedia-wrap">
            <?php Sportedia_Admin_Views::render_notices(); ?>
            <div class="sportedia-header">
                <div>
                    <h1 class="sportedia-title"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Sportedia Settings', 'sportedia'); ?></h1>
                    <p class="sportedia-subtitle"><?php esc_html_e('Configure academy details, date/time formats, and default system behavior.', 'sportedia'); ?></p>
                </div>
            </div>

            <div class="sportedia-card" style="max-width: 600px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sportedia_admin_action">
                    <input type="hidden" name="sportedia_action" value="save_settings">
                    <?php wp_nonce_field('sportedia_action_nonce', 'sportedia_nonce'); ?>

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('Sports Academy Name', 'sportedia'); ?></label>
                        <input type="text" name="academy_name" class="sp-input" value="<?php echo esc_attr($academy_name); ?>" required>
                    </div>

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('Display Date Format', 'sportedia'); ?></label>
                        <select name="date_format" class="sp-select">
                            <option value="Y-m-d" <?php selected($date_format, 'Y-m-d'); ?>>YYYY-MM-DD (e.g. <?php echo date('Y-m-d'); ?>)</option>
                            <option value="d/m/Y" <?php selected($date_format, 'd/m/Y'); ?>>DD/MM/YYYY (e.g. <?php echo date('d/m/Y'); ?>)</option>
                            <option value="m/d/Y" <?php selected($date_format, 'm/d/Y'); ?>>MM/DD/YYYY (e.g. <?php echo date('m/d/Y'); ?>)</option>
                            <option value="F j, Y" <?php selected($date_format, 'F j, Y'); ?>>Month Day, Year (e.g. <?php echo date('F j, Y'); ?>)</option>
                        </select>
                    </div>

                    <div class="sp-form-group">
                        <label class="sp-form-label"><?php esc_html_e('Display Time Format', 'sportedia'); ?></label>
                        <select name="time_format" class="sp-select">
                            <option value="h:i A" <?php selected($time_format, 'h:i A'); ?>>12-Hour AM/PM (e.g. <?php echo date('h:i A'); ?>)</option>
                            <option value="H:i" <?php selected($time_format, 'H:i'); ?>>24-Hour Military (e.g. <?php echo date('H:i'); ?>)</option>
                        </select>
                    </div>

                    <button type="submit" class="sp-btn sp-btn-primary" style="margin-top:10px; width:100%;"><?php esc_html_e('Save Settings', 'sportedia'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }
}
