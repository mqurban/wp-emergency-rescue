<?php
/**
 * Plugin Name: MrQurban Emergency Rescue
 * Description: A must-use plugin to recover from fatal errors by renaming plugins/themes via a secret URL.
 * Version: 1.1.0
 * Author: Muhammad Qurban
 * Author URI: https://mrqurban.com
 * License: GPLv2 or later
 * Text Domain: mrqurban-emergency-rescue
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPER_Emergency_Rescue
{

    private $option_name = 'wper_secret_key';
    private $param_name = 'rescue_key';
    private $dismiss_option = 'wper_notice_dismissed';
    private $log_option = 'wper_activity_logs';

    public function __construct()
    {
        $this->apply_runtime_debug_settings();
        $this->handle_debug_mode();
        $this->check_rescue_mode();

        add_action('admin_init', array($this, 'generate_key_if_missing'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_secret_key'));
        add_action('admin_menu', array($this, 'register_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    /**
     * Enqueue admin stylesheet on all admin pages for manage_options users.
     * The notice banner (.wper-dismiss-link) can appear on any admin page.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public function enqueue_admin_styles($hook)
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_style(
            'mrqurban-emergency-rescue-admin',
            plugins_url('assets/admin.css', __FILE__),
            array(),
            '1.1.0'
        );
    }

    private function handle_debug_mode()
    {
        $secret_key = get_option($this->option_name);
        if (!$secret_key) {
            return;
        }
        $cookie_val = md5($secret_key);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET[$this->param_name]) && sanitize_text_field(wp_unslash($_GET[$this->param_name])) === $secret_key) {
            if (isset($_GET['wper_debug_toggle'])) {
                $toggle = sanitize_key($_GET['wper_debug_toggle']);
                $cookie_name = 'wper_debug_' . $toggle;
                $current = isset($_COOKIE[$cookie_name]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) : '';
                $new_val = ($current === $cookie_val) ? '' : $cookie_val;

                setcookie($cookie_name, $new_val, time() + 3600, '/');
                $_COOKIE[$cookie_name] = $new_val;

                $url = remove_query_arg('wper_debug_toggle');
                header('Location: ' . esc_url_raw($url));
                exit;
            }
        }
    }

    /**
     * If the debug cookie is active, enable PHP error logging at runtime.
     * This ensures developer-level logs are captured even if WP_DEBUG is off in wp-config.php.
     */
    private function apply_runtime_debug_settings()
    {
        $secret_key = get_option($this->option_name);
        if (!$secret_key) {
            return;
        }

        $cookie_val = md5($secret_key);
        $cookie_name = 'wper_debug_log';

        if (isset($_COOKIE[$cookie_name]) && sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) === $cookie_val) {
            @ini_set('log_errors', 'On');
            @ini_set('display_errors', 'Off'); // Ensure we don't break the rescue UI with browser errors.

            $log_file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : ABSPATH . 'wp-content/debug.log';
            @ini_set('error_log', $log_file);
        }
    }

    /**
     * Get the last N bytes of the debug.log file, reversed so newest entries appear first.
     */
    private function get_debug_log_content($max_size = 20480)
    {
        $log_file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : ABSPATH . 'wp-content/debug.log';

        if (!file_exists($log_file)) {
            return sprintf(esc_html__("Debug log file not found at %s. Enable 'Debug Log (File)' and trigger an error to create it.", 'mrqurban-emergency-rescue'), basename($log_file));
        }

        if (!is_readable($log_file)) {
            return esc_html__('Debug log file exists but is not readable.', 'mrqurban-emergency-rescue');
        }

        $fp = fopen($log_file, 'r');
        if (!$fp) {
            return esc_html__('Cannot open log file.', 'mrqurban-emergency-rescue');
        }

        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);

        if ($size === 0) {
            fclose($fp);
            return esc_html__('Debug log file is empty.', 'mrqurban-emergency-rescue');
        }

        $seek = max(0, $size - $max_size);
        fseek($fp, $seek);

        if ($seek > 0) {
            fgets($fp);
        }

        $content = fread($fp, $max_size);
        fclose($fp);

        $lines = explode("\n", $content);
        $lines = array_reverse(array_filter($lines));

        return implode("\n", $lines);
    }

    /**
     * Generate a secret key if one does not already exist.
     * Hooked to admin_init to ensure pluggable functions are loaded.
     */
    public function generate_key_if_missing()
    {
        if (!get_option($this->option_name)) {
            $key = wp_generate_password(32, false);
            update_option($this->option_name, $key);
        }
    }

    /**
     * Register a submenu page under Tools.
     */
    public function register_menu_page()
    {
        add_management_page(
            esc_html__('Emergency Rescue', 'mrqurban-emergency-rescue'),
            esc_html__('Emergency Rescue', 'mrqurban-emergency-rescue'),
            'manage_options',
            'mrqurban-emergency-rescue',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render the settings page in WP Admin.
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $key = get_option($this->option_name);
        $url = home_url('/?' . $this->param_name . '=' . $key);
?>
        <div class="wrap">
            <h1>🚑 <?php esc_html_e('Emergency Rescue Settings', 'mrqurban-emergency-rescue'); ?></h1>

            <div class="card wper-card">
                <h2><?php esc_html_e('Your Emergency Rescue URL', 'mrqurban-emergency-rescue'); ?></h2>
                <p><?php esc_html_e('Use this URL to access the recovery interface if your site crashes and you cannot access the admin panel.', 'mrqurban-emergency-rescue'); ?></p>
                <p>
                    <input type="text" class="large-text code" value="<?php echo esc_url($url); ?>" readonly onclick="this.select();">
                </p>
                <p class="description"><strong><?php esc_html_e('Tip:', 'mrqurban-emergency-rescue'); ?></strong> <?php esc_html_e('Bookmark this URL now.', 'mrqurban-emergency-rescue'); ?></p>
            </div>

            <div class="card wper-card">
                <h2><?php esc_html_e('Custom Configuration', 'mrqurban-emergency-rescue'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('wper_save_settings', 'wper_nonce'); ?>
                    <input type="hidden" name="action" value="wper_save_settings">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="custom_secret_key"><?php esc_html_e('Secret Key', 'mrqurban-emergency-rescue'); ?></label></th>
                            <td>
                                <input name="custom_secret_key" type="text" id="custom_secret_key" value="<?php echo esc_attr($key); ?>" class="regular-text code">
                                <p class="description"><?php esc_html_e('You can change this key to something memorable (e.g., a custom password).', 'mrqurban-emergency-rescue'); ?> <br><strong><?php esc_html_e('Warning:', 'mrqurban-emergency-rescue'); ?></strong> <?php esc_html_e('Changing this invalidates the old Rescue URL.', 'mrqurban-emergency-rescue'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Save Changes', 'mrqurban-emergency-rescue')); ?>
                </form>
            </div>

            <div class="card wper-card">
                <h2>🛡️ <?php esc_html_e('Fail-Safe Mode (Highly Recommended)', 'mrqurban-emergency-rescue'); ?></h2>
                <p><?php esc_html_e('Currently, this is running as a standard plugin. If your site crashes very early during startup, this plugin might also be blocked from loading.', 'mrqurban-emergency-rescue'); ?></p>
                <p><strong><?php esc_html_e('The Solution:', 'mrqurban-emergency-rescue'); ?></strong> <?php esc_html_e('Install the Fail-Safe Loader. This ensures Emergency Rescue loads before everything else, making it 100% reliable even in severe crashes.', 'mrqurban-emergency-rescue'); ?></p>
                
                <?php
        $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
        $loader_file = $mu_dir . '/mrqurban-emergency-rescue-loader.php';
        if (file_exists($loader_file)): ?>
                    <div class="wper-status-badge status-active">✅ <?php esc_html_e('Fail-Safe Mode is Active', 'mrqurban-emergency-rescue'); ?></div>
                <?php
        else: ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('wper_install_mu', 'wper_nonce'); ?>
                        <input type="hidden" name="action" value="wper_install_mu">
                        <p><?php submit_button(esc_html__('Enable Fail-Safe Mode', 'mrqurban-emergency-rescue'), 'primary', 'submit', false); ?></p>
                    </form>
                <?php
        endif; ?>
            </div>

            <div class="card wper-card">
                <h2><?php esc_html_e('Activity Logs', 'mrqurban-emergency-rescue'); ?></h2>
                <div class="wper-logs-toolbar">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="mrqurban-emergency-rescue">
                        <label for="wper_log_limit"><?php esc_html_e('Show last:', 'mrqurban-emergency-rescue'); ?> </label>
                        <select name="limit" id="wper_log_limit" onchange="this.form.submit()">
                            <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View preference only.
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $options = array(10, 25, 50, 100);
        foreach ($options as $opt) {
            echo '<option value="' . esc_attr($opt) . '" ' . selected($limit, $opt, false) . '>' . sprintf(esc_html__('%d entries', 'mrqurban-emergency-rescue'), esc_html($opt)) . '</option>';
        }
?>
                        </select>
                    </form>

                    <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'mrqurban-emergency-rescue'); ?>');"><div class="wper-clear-btn-wrap">
                        <?php wp_nonce_field('wper_clear_logs', 'wper_nonce'); ?>
                        <input type="hidden" name="action" value="wper_clear_logs">
                        <?php submit_button(esc_html__('Clear Logs', 'mrqurban-emergency-rescue'), 'delete wper-clear-btn', 'submit', false); ?>
                    </div></form>
                </div>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th class="wper-col-time"><?php esc_html_e('Time', 'mrqurban-emergency-rescue'); ?></th>
                            <th><?php esc_html_e('Action / Message', 'mrqurban-emergency-rescue'); ?></th>
                            <th class="wper-col-ip"><?php esc_html_e('IP Address', 'mrqurban-emergency-rescue'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
        $logs = $this->get_logs($limit);
        if (empty($logs)): ?>
                            <tr><td colspan="3"><?php esc_html_e('No activity logs found.', 'mrqurban-emergency-rescue'); ?></td></tr>
                        <?php
        else: ?>
                            <?php foreach ($logs as $log):
                $parts = explode(' - ', $log);
                if (count($parts) >= 3) {
                    $time = array_shift($parts);
                    $ip_part = array_pop($parts);
                    $ip = str_replace('IP: ', '', $ip_part);
                    $message = implode(' - ', $parts);
                }
                else {
                    $time = '';
                    $message = $log;
                    $ip = '';
                }
?>
                                <tr>
                                    <td><?php echo esc_html($time); ?></td>
                                    <td><?php echo esc_html($message); ?></td>
                                    <td><?php echo esc_html($ip); ?></td>
                                </tr>
                            <?php
            endforeach; ?>
                        <?php
        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Handle admin actions: notice dismissal, settings save, and log clearing.
     */
    public function handle_admin_actions()
    {
        if (isset($_GET['wper_dismiss']) && check_admin_referer('wper_dismiss_notice')) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'mrqurban-emergency-rescue'));
            }
            update_user_meta(get_current_user_id(), $this->dismiss_option, true);
            wp_safe_redirect(remove_query_arg(array('wper_dismiss', '_wpnonce')));
            exit;
        }

        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'wper_save_settings') {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (!isset($_POST['wper_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wper_nonce'])), 'wper_save_settings')) {
                return;
            }

            if (!empty($_POST['custom_secret_key'])) {
                $new_key = sanitize_text_field(wp_unslash($_POST['custom_secret_key']));
                $new_key = str_replace(' ', '', $new_key);

                if (!empty($new_key)) {
                    update_option($this->option_name, $new_key);
                    add_settings_error('wper_messages', 'wper_saved', esc_html__('Settings Saved. Your Rescue URL has been updated.', 'mrqurban-emergency-rescue'), 'success');
                }
            }
        }

        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'wper_clear_logs') {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (!isset($_POST['wper_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wper_nonce'])), 'wper_clear_logs')) {
                return;
            }

            delete_option($this->log_option);
            add_settings_error('wper_messages', 'wper_logs_cleared', esc_html__('Activity logs have been cleared.', 'mrqurban-emergency-rescue'), 'success');
        }

        if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'wper_install_mu') {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (!isset($_POST['wper_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wper_nonce'])), 'wper_install_mu')) {
                return;
            }

            $this->install_mu_loader();
        }
    }

    /**
     * Helper to install the MU-plugin loader file.
     */
    private function install_mu_loader()
    {
        $mu_dir = WP_CONTENT_DIR . '/mu-plugins';
        $loader_file = $mu_dir . '/mrqurban-emergency-rescue-loader.php';

        if (!is_dir($mu_dir)) {
            if (!wp_mkdir_p($mu_dir)) {
                add_settings_error('wper_messages', 'wper_mu_fail', esc_html__('Could not create /mu-plugins/ directory. Please create it manually via FTP.', 'mrqurban-emergency-rescue'), 'error');
                return;
            }
        }

        $plugin_path = var_export(__FILE__, true);
        $loader_content = "<?php\n" .
            "/**\n" .
            " * Loader for MrQurban Emergency Rescue (Must-Use Plugin)\n" .
            " * Explicitly installed via the plugin settings page.\n" .
            " */\n\n" .
            "if ( file_exists( $plugin_path ) ) {\n" .
            "    require_once $plugin_path;\n" .
            "}\n";

        if (file_put_contents($loader_file, $loader_content)) {
            add_settings_error('wper_messages', 'wper_mu_success', esc_html__('Fail-Safe Mode enabled successfully!', 'mrqurban-emergency-rescue'), 'success');
        }
        else {
            add_settings_error('wper_messages', 'wper_mu_fail', esc_html__('Could not write the loader file. Your hosting might have restricted file permissions.', 'mrqurban-emergency-rescue'), 'error');
        }
    }

    // Log helper functions moved to database options.

    /**
     * Get the most recent log entries from the database.
     */
    private function get_logs($limit = 10)
    {
        $logs = get_option($this->log_option, array());
        if (!is_array($logs)) {
            return array();
        }

        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Show the secret rescue URL in an admin notice banner.
     */
    public function show_secret_key()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_user_meta(get_current_user_id(), $this->dismiss_option, true)) {
            return;
        }

        $key = get_option($this->option_name);
        if ($key) {
            $url = home_url('/?' . $this->param_name . '=' . $key);
            $dismiss_url = wp_nonce_url(add_query_arg('wper_dismiss', '1'), 'wper_dismiss_notice');
            $settings_url = admin_url('tools.php?page=mrqurban-emergency-rescue');

            $mu_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/mu-plugins' : ABSPATH . 'wp-content/mu-plugins';
            $loader_file = $mu_dir . '/mrqurban-emergency-rescue-loader.php';
            $mu_active = file_exists($loader_file);
?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>🚑 <?php esc_html_e('Emergency Rescue is Ready!', 'mrqurban-emergency-rescue'); ?></strong></p>
                <p><?php esc_html_e('1. Save your Secret Rescue URL:', 'mrqurban-emergency-rescue'); ?> <code><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($url); ?></a></code></p>

                <?php if (!$mu_active): ?>
                    <p><strong><?php esc_html_e('2. Enable Fail-Safe Mode:', 'mrqurban-emergency-rescue'); ?></strong> <?php esc_html_e('To ensure recovery works even during severe crashes, please', 'mrqurban-emergency-rescue'); ?> <a href="<?php echo esc_url($settings_url); ?>"><strong><?php esc_html_e('Enable Fail-Safe Mode here', 'mrqurban-emergency-rescue'); ?></strong></a>.</p>
                <?php
            endif; ?>

                <p><a href="<?php echo esc_url($dismiss_url); ?>" class="wper-dismiss-link"><?php esc_html_e('Dismiss this notice permanently', 'mrqurban-emergency-rescue'); ?></a> <?php esc_html_e('(You can always find your URL in Tools > Emergency Rescue)', 'mrqurban-emergency-rescue'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Check if the rescue key is present in the URL and enter rescue mode if valid.
     */
    private function check_rescue_mode()
    {
        if (isset($_GET[$this->param_name])) {
            $key = sanitize_text_field(wp_unslash($_GET[$this->param_name]));
            $stored_key = get_option($this->option_name);

            if ($stored_key && $key === $stored_key) {
                $this->render_rescue_page();
                exit;
            }
        }
    }

    /**
     * Render the standalone rescue interface HTML page.
     */
    private function render_rescue_page()
    {
        $this->handle_actions();

        $theme_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
        if (function_exists('get_theme_root')) {
            $theme_dir = get_theme_root();
        }

        $plugin_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';

?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php esc_html_e('Emergency Rescue', 'mrqurban-emergency-rescue'); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php
        wp_enqueue_style(
            'mrqurban-emergency-rescue-page',
            plugins_url('assets/rescue-page.css', __FILE__),
            array(),
            '1.1.0'
        );
        wp_print_styles('mrqurban-emergency-rescue-page');
?>
        </head>
        <body>
            <div class="container">
                <h1>🚑 <?php esc_html_e('Emergency Rescue', 'mrqurban-emergency-rescue'); ?></h1>
                <p><?php esc_html_e('Welcome to the emergency recovery mode. Here you can selectively disable plugins or themes by renaming their folders.', 'mrqurban-emergency-rescue'); ?></p>

                <div class="wper-action-links">
                    <a href="<?php echo esc_url(admin_url()); ?>" class="btn btn-primary" target="_blank"><?php esc_html_e('Try Loading WP Admin', 'mrqurban-emergency-rescue'); ?> &nearr;</a>
                    <a href="<?php echo esc_url(home_url()); ?>" class="btn btn-secondary" target="_blank"><?php esc_html_e('View Site', 'mrqurban-emergency-rescue'); ?> &nearr;</a>
                </div>

                <div class="wper-info-box">
                    <h3 class="wper-box-title">🔧 <?php esc_html_e('Debug Tools', 'mrqurban-emergency-rescue'); ?></h3>
                    <p><?php esc_html_e('Toggle debugging options for this session:', 'mrqurban-emergency-rescue'); ?></p>
                    <?php
        $secret_key_hash = md5(get_option($this->option_name));
        $debug_log_cookie = isset($_COOKIE['wper_debug_log']) ? sanitize_text_field(wp_unslash($_COOKIE['wper_debug_log'])) : '';
        $debug_log = ($debug_log_cookie === $secret_key_hash);

        // Sanitize current URL for toggle links.
        $current_url = set_url_scheme('http://' . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])));
        $url_log = add_query_arg('wper_debug_toggle', 'log', $current_url);
?>

                    <a href="<?php echo esc_url($url_log); ?>" class="btn <?php echo $debug_log ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $debug_log ? esc_html__('Disable', 'mrqurban-emergency-rescue') : esc_html__('Enable', 'mrqurban-emergency-rescue'); ?> <?php esc_html_e('Debug Log (File)', 'mrqurban-emergency-rescue'); ?>
                    </a>
                </div>

                <?php if ($debug_log): ?>
                <div class="wper-info-box">
                    <h3 class="wper-box-title">📄 <?php esc_html_e('Debug Log Viewer', 'mrqurban-emergency-rescue'); ?></h3>
                    <p><?php printf(esc_html__('Last %s of %s:', 'mrqurban-emergency-rescue'), '20KB', '<code>debug.log</code>'); ?></p>
                    <textarea class="wper-log-textarea" readonly><?php echo esc_textarea($this->get_debug_log_content()); ?></textarea>
                    <p class="wper-log-refresh"><a href="<?php echo esc_url(remove_query_arg('wper_test_error', $current_url)); ?>" class="btn btn-secondary"><?php esc_html_e('Refresh Log', 'mrqurban-emergency-rescue'); ?></a></p>
                </div>
                <?php
        endif; ?>

                <?php if (!empty($_GET['msg'])): ?>
                    <div class="message success"><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['msg']))); ?></div>
                <?php
        endif; ?>

                <?php if (!empty($_GET['error'])): ?>
                    <div class="message error"><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['error']))); ?></div>
                <?php
        endif; ?>

                <h2><?php esc_html_e('Plugins', 'mrqurban-emergency-rescue'); ?></h2>
                <?php $this->list_items($plugin_dir, 'plugin'); ?>

                <h2><?php esc_html_e('Themes', 'mrqurban-emergency-rescue'); ?></h2>
                <?php $this->list_items($theme_dir, 'theme'); ?>

                <div class="footer">
                    <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Rescue key used for authentication.
        $rescue_key_val = isset($_GET[$this->param_name]) ? sanitize_text_field(wp_unslash($_GET[$this->param_name])) : '';
        $refresh_url = add_query_arg($this->param_name, $rescue_key_val, home_url('/'));
?>
                    <p><?php esc_html_e('Generated by Emergency Rescue', 'mrqurban-emergency-rescue'); ?> &bull; <a href="<?php echo esc_url($refresh_url); ?>"><?php esc_html_e('Refresh Page', 'mrqurban-emergency-rescue'); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * List plugins or themes in a directory as an HTML table with enable/disable actions.
     */
    private function list_items($directory, $type)
    {
        if (!is_dir($directory)) {
            echo "<div class='message error'>" . sprintf(esc_html__('Directory not found: %s', 'mrqurban-emergency-rescue'), esc_html($directory)) . "</div>";
            return;
        }

        $items = scandir($directory);
        if (!$items) {
            echo "<p>" . esc_html__('No items found.', 'mrqurban-emergency-rescue') . "</p>";
            return;
        }

        echo '<table><thead><tr><th>' . esc_html__('Name (Folder)', 'mrqurban-emergency-rescue') . '</th><th>' . esc_html__('Status', 'mrqurban-emergency-rescue') . '</th><th>' . esc_html__('Action', 'mrqurban-emergency-rescue') . '</th></tr></thead><tbody>';

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'index.php' || $item === '.DS_Store')
                continue;

            $path = trailingslashit($directory) . $item;
            if (!is_dir($path) && $type === 'theme')
                continue;

            $is_disabled = (substr($item, -4) === '.off');
            $display_name = $is_disabled ? substr($item, 0, -4) : $item;

            echo '<tr>';
            echo '<td><strong>' . esc_html($display_name) . '</strong><br><small class="wper-folder-name">' . esc_html($item) . '</small></td>';
            echo '<td>' . ($is_disabled ? '<span class="status-disabled">' . esc_html__('Disabled', 'mrqurban-emergency-rescue') . '</span>' : '<span class="status-active">' . esc_html__('Active', 'mrqurban-emergency-rescue') . '</span>') . '</td>';
            echo '<td>';

            $new_name = $is_disabled ? $display_name : $item . '.off';
            $action_label = $is_disabled ? esc_html__('Restore (Enable)', 'mrqurban-emergency-rescue') : esc_html__('Disable (Rename)', 'mrqurban-emergency-rescue');
            $btn_class = $is_disabled ? 'btn-primary' : 'btn-danger';

            // Sanitize current URL to build the rename action link.
            $rescue_key_safe = isset($_GET[$this->param_name]) ? sanitize_text_field(wp_unslash($_GET[$this->param_name])) : '';
            $current_url = set_url_scheme('http://' . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])));
            $url = add_query_arg(array(
                $this->param_name => $rescue_key_safe,
                'action' => 'rename',
                'type' => $type,
                'target' => $item,
                'new_name' => $new_name,
            ), $current_url);

            echo '<a href="' . esc_url($url) . '" class="btn ' . esc_attr($btn_class) . '" onclick="return confirm(\'' . esc_js(sprintf(esc_html__('Are you sure you want to %s?', 'mrqurban-emergency-rescue'), esc_html(strtolower($action_label)))) . '\');">' . esc_html($action_label) . '</a>';

            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Handle plugin/theme rename (enable/disable) actions from the rescue interface.
     */
    private function handle_actions()
    {
        if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'rename' && isset($_GET['target']) && isset($_GET['new_name'])) {

            $type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'plugin';
            $target = sanitize_file_name(wp_unslash($_GET['target']));
            $new_name = sanitize_file_name(wp_unslash($_GET['new_name']));

            if ($type === 'theme') {
                $base_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
                if (function_exists('get_theme_root'))
                    $base_dir = get_theme_root();
            }
            else {
                $base_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
            }

            $old_path = trailingslashit($base_dir) . $target;
            $new_path = trailingslashit($base_dir) . $new_name;

            if (!file_exists($old_path)) {
                $this->redirect_with_msg('', esc_html__('Target file does not exist.', 'mrqurban-emergency-rescue'));
            }

            if (file_exists($new_path)) {
                $this->redirect_with_msg('', esc_html__('Destination already exists.', 'mrqurban-emergency-rescue'));
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Standard PHP rename used for emergency recovery without WP_Filesystem dependencies.
            if (rename($old_path, $new_path)) {
                $this->log_change(sprintf("Renamed %s to %s (%s)", $target, $new_name, $type));
                $this->redirect_with_msg(sprintf(esc_html__('Successfully renamed %s to %s', 'mrqurban-emergency-rescue'), $target, $new_name));
            }
            else {
                $this->redirect_with_msg('', esc_html__('Failed to rename. Check file permissions.', 'mrqurban-emergency-rescue'));
            }
        }
    }

    /**
     * Redirect back to the rescue page with an optional success message or error.
     */
    private function redirect_with_msg($msg = '', $error = '')
    {
        $current_url = set_url_scheme('http://' . sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) . esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])));
        $url = remove_query_arg(array('action', 'target', 'new_name', 'type', 'msg', 'error'), $current_url);

        if ($msg)
            $url = add_query_arg('msg', $msg, $url);
        if ($error)
            $url = add_query_arg('error', $error, $url);

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
        }
        else {
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fallback if WP functions not fully loaded.
            header('Location: ' . esc_url_raw($url));
        }
        exit;
    }

    /**
     * Append a timestamped entry to the database activity log.
     */
    private function log_change($message)
    {
        $logs = get_option($this->log_option, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR is server controlled.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $entry = gmdate('Y-m-d H:i:s') . " - " . $message . " - IP: " . $ip;

        $logs[] = $entry;

        // Keep only the last 100 entries to prevent DB bloat.
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        update_option($this->log_option, $logs);
    }
}

new WPER_Emergency_Rescue();
