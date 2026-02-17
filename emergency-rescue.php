<?php
/**
 * Plugin Name: Emergency Rescue
 * Description: A must-use plugin to recover from fatal errors by renaming plugins/themes via a secret URL.
 * Version: 1.1.0
 * Author: Muhammad Qurban
 * Author URI: https://mqurban.com
 * License: GPLv2 or later
 * Text Domain: emergency-rescue
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPER_Emergency_Rescue {

    private $option_name = 'wper_secret_key';
    private $param_name  = 'rescue_key';
    private $dismiss_option = 'wper_notice_dismissed';

    public function __construct() {
        // Step 0: Handle Debug Mode (Must be first to catch early errors)
        $this->handle_debug_mode();

        // Step 1: Initialize
        // Check for rescue mode immediately (for mu-plugin support)
        $this->check_rescue_mode();
        
        // Step 2: Hooks for normal operation (generating key, showing notice)
        // We use admin_init to ensure all WP functions (like pluggable.php) are loaded
        add_action( 'admin_init', array( $this, 'generate_key_if_missing' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) ); // Handle dismissal and settings save
        add_action( 'admin_notices', array( $this, 'show_secret_key' ) );
        add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
    }

    /**
     * Handle Debug Mode toggles.
     * Sets cookies and applies debug constants/ini settings.
     */
    private function handle_debug_mode() {
        $secret_key = get_option( $this->option_name );
        $cookie_val = md5( $secret_key );

        // Handle Toggles via GET request (Only if rescue key is present and correct)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Rescue key serves as authentication.
        if ( isset( $_GET[ $this->param_name ] ) && sanitize_text_field( wp_unslash( $_GET[ $this->param_name ] ) ) === $secret_key ) {
            if ( isset( $_GET['wper_debug_toggle'] ) ) {
                $toggle = sanitize_key( $_GET['wper_debug_toggle'] );
                $cookie_name = 'wper_debug_' . $toggle;
                
                // Toggle the cookie value (cookie_val or empty)
                $current = isset( $_COOKIE[ $cookie_name ] ) ? $_COOKIE[ $cookie_name ] : '';
                $new_val = ( $current === $cookie_val ) ? '' : $cookie_val;
                
                // Set cookie for 1 hour
                // Note: COOKIEPATH and COOKIE_DOMAIN might not be defined yet in mu-plugins
                setcookie( $cookie_name, $new_val, time() + 3600, '/' );
                $_COOKIE[ $cookie_name ] = $new_val; // Update current request
                
                // Redirect to remove the toggle param
                $url = remove_query_arg( 'wper_debug_toggle' );
                header( "Location: $url" );
                exit;
            }
        }

        // Apply Settings based on Cookies (Check validity)
        $debug_log = isset( $_COOKIE['wper_debug_log'] ) && $_COOKIE['wper_debug_log'] === $cookie_val;

        if ( $debug_log ) {
            // Try to define constants if not already defined
            if ( ! defined( 'WP_DEBUG' ) ) {
                define( 'WP_DEBUG', true );
            }
        }

        if ( $debug_log ) {
            if ( ! defined( 'WP_DEBUG_LOG' ) ) {
                define( 'WP_DEBUG_LOG', true );
            }
            @ini_set( 'log_errors', 1 );
            // Ensure we know where the log goes if WP hasn't set it
            if ( defined( 'WP_CONTENT_DIR' ) ) {
                @ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );
            }
        }
    }

    /**
     * Get the last N bytes of the debug.log file and reverse lines.
     */
    private function get_debug_log_content( $max_size = 20480 ) {
        $log_file = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/debug.log' : ABSPATH . 'wp-content/debug.log';
        
        if ( ! file_exists( $log_file ) ) {
            return sprintf( __( "Debug log file not found at %s. Enable 'Debug Log (File)' and trigger an error to create it.", 'emergency-rescue' ), basename($log_file) );
        }
        
        if ( ! is_readable( $log_file ) ) {
            return __( "Debug log file exists but is not readable.", 'emergency-rescue' );
        }

        $fp = fopen( $log_file, 'r' );
        if ( ! $fp ) return __( "Cannot open log file.", 'emergency-rescue' );
        
        fseek( $fp, 0, SEEK_END );
        $size = ftell( $fp );
        
        if ( $size === 0 ) {
            fclose( $fp );
            return __( "Debug log file is empty.", 'emergency-rescue' );
        }

        $seek = max( 0, $size - $max_size );
        fseek( $fp, $seek );
        
        // Discard first partial line if we seeked
        if ( $seek > 0 ) {
            fgets( $fp ); 
        }

        $content = fread( $fp, $max_size );
        fclose( $fp );
        
        // Reverse lines for better readability (Newest first)
        $lines = explode( "\n", $content );
        $lines = array_reverse( array_filter( $lines ) );
        
        return htmlspecialchars( implode( "\n", $lines ) );
    }

    /**
     * Generate a secret key if one doesn't exist.
     * Hooked to admin_init to ensure random functions are available.
     */
    public function generate_key_if_missing() {
        if ( ! get_option( $this->option_name ) ) {
            // wp_generate_password is in pluggable.php, which is loaded by now
            $key = wp_generate_password( 32, false );
            update_option( $this->option_name, $key );
        }
    }

    /**
     * Register a submenu page under Tools.
     */
    public function register_menu_page() {
        add_management_page(
            __( 'Emergency Rescue', 'emergency-rescue' ),
            __( 'Emergency Rescue', 'emergency-rescue' ),
            'manage_options',
            'emergency-rescue',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the settings page in WP Admin.
     */
    public function render_settings_page() {
        $key = get_option( $this->option_name );
        $url = home_url( '/?' . $this->param_name . '=' . $key );
        ?>
        <div class="wrap">
            <h1>🚑 <?php esc_html_e( 'Emergency Rescue Settings', 'emergency-rescue' ); ?></h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e( 'Your Emergency Rescue URL', 'emergency-rescue' ); ?></h2>
                <p><?php esc_html_e( 'Use this URL to access the recovery interface if your site crashes and you cannot access the admin panel.', 'emergency-rescue' ); ?></p>
                <p>
                    <input type="text" class="large-text code" value="<?php echo esc_url( $url ); ?>" readonly onclick="this.select();">
                </p>
                <p class="description"><strong><?php esc_html_e( 'Tip:', 'emergency-rescue' ); ?></strong> <?php esc_html_e( 'Bookmark this URL now.', 'emergency-rescue' ); ?></p>
            </div>

            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e( 'Custom Configuration', 'emergency-rescue' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'wper_save_settings', 'wper_nonce' ); ?>
                    <input type="hidden" name="action" value="wper_save_settings">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="custom_secret_key"><?php esc_html_e( 'Secret Key', 'emergency-rescue' ); ?></label></th>
                            <td>
                                <input name="custom_secret_key" type="text" id="custom_secret_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text code">
                                <p class="description"><?php esc_html_e( 'You can change this key to something memorable (e.g., a custom password).', 'emergency-rescue' ); ?> <br><strong><?php esc_html_e( 'Warning:', 'emergency-rescue' ); ?></strong> <?php esc_html_e( 'Changing this invalidates the old Rescue URL.', 'emergency-rescue' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Save Changes', 'emergency-rescue' ) ); ?>
                </form>
            </div>

            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e( 'Activity Logs', 'emergency-rescue' ); ?></h2>
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="emergency-rescue">
                        <label for="wper_log_limit"><?php esc_html_e( 'Show last:', 'emergency-rescue' ); ?> </label>
                        <select name="limit" id="wper_log_limit" onchange="this.form.submit()">
                            <?php 
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View preference only.
                            $limit = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 10;
                            $options = array( 10, 25, 50, 100 );
                            foreach ( $options as $opt ) {
                                echo '<option value="' . esc_attr( $opt ) . '" ' . selected( $limit, $opt, false ) . '>' . sprintf( __( '%d entries', 'emergency-rescue' ), esc_html( $opt ) ) . '</option>';
                            }
                            ?>
                        </select>
                    </form>
                    
                    <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs?', 'emergency-rescue' ); ?>');">
                        <?php wp_nonce_field( 'wper_clear_logs', 'wper_nonce' ); ?>
                        <input type="hidden" name="action" value="wper_clear_logs">
                        <?php submit_button( __( 'Clear Logs', 'emergency-rescue' ), 'delete', 'submit', false, array( 'style' => 'margin:0;' ) ); ?>
                    </form>
                </div>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 180px;"><?php esc_html_e( 'Time', 'emergency-rescue' ); ?></th>
                            <th><?php esc_html_e( 'Action / Message', 'emergency-rescue' ); ?></th>
                            <th style="width: 120px;"><?php esc_html_e( 'IP Address', 'emergency-rescue' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $logs = $this->get_logs( $limit );
                        if ( empty( $logs ) ) : ?>
                            <tr><td colspan="3"><?php esc_html_e( 'No activity logs found.', 'emergency-rescue' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $log ) : 
                                // Basic parsing: "DATE TIME - Message - IP: IP"
                                $parts = explode( ' - ', $log );
                                if ( count( $parts ) >= 3 ) {
                                    $time = array_shift( $parts );
                                    $ip_part = array_pop( $parts );
                                    $ip = str_replace( 'IP: ', '', $ip_part );
                                    $message = implode( ' - ', $parts );
                                } else {
                                    $time = '';
                                    $message = $log;
                                    $ip = '';
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $time ); ?></td>
                                    <td><?php echo esc_html( $message ); ?></td>
                                    <td><?php echo esc_html( $ip ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Handle admin actions (Settings save, Notice dismissal).
     */
    public function handle_admin_actions() {
        // Handle Notice Dismissal
        if ( isset( $_GET['wper_dismiss'] ) && check_admin_referer( 'wper_dismiss_notice' ) ) {
            update_user_meta( get_current_user_id(), $this->dismiss_option, true );
            wp_safe_redirect( remove_query_arg( array( 'wper_dismiss', '_wpnonce' ) ) );
            exit;
        }

        // Handle Settings Save
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'wper_save_settings' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            
            if ( ! isset( $_POST['wper_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wper_nonce'] ) ), 'wper_save_settings' ) ) {
                return;
            }

            if ( ! empty( $_POST['custom_secret_key'] ) ) {
                // Sanitize: Allow alphanumeric and common safe chars
                $new_key = sanitize_text_field( wp_unslash( $_POST['custom_secret_key'] ) );
                // Ensure no spaces
                $new_key = str_replace( ' ', '', $new_key );
                
                if ( ! empty( $new_key ) ) {
                    update_option( $this->option_name, $new_key );
                    add_settings_error( 'wper_messages', 'wper_saved', __( 'Settings Saved. Your Rescue URL has been updated.', 'emergency-rescue' ), 'success' );
                }
            }
        }

        // Handle Clear Logs
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'wper_clear_logs' ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            
            if ( ! isset( $_POST['wper_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wper_nonce'] ) ), 'wper_clear_logs' ) ) {
                return;
            }

            $log_file = $this->get_log_file_path();
            if ( file_exists( $log_file ) ) {
                file_put_contents( $log_file, '' );
                add_settings_error( 'wper_messages', 'wper_logs_cleared', __( 'Activity logs have been cleared.', 'emergency-rescue' ), 'success' );
            }
        }
    }

    /**
     * Get the path to the log file.
     */
    private function get_log_file_path() {
        return dirname( __FILE__ ) . '/rescue_log.txt';
    }

    /**
     * Get logs with pagination limit.
     */
    private function get_logs( $limit = 10 ) {
        $log_file = $this->get_log_file_path();
        if ( ! file_exists( $log_file ) ) {
            return array();
        }

        $content = file_get_contents( $log_file );
        if ( empty( $content ) ) {
            return array();
        }

        // Split by newline and remove empty lines
        $lines = array_filter( explode( "\n", $content ) );
        
        // Reverse to show newest first
        $lines = array_reverse( $lines );
        
        // Slice to limit
        return array_slice( $lines, 0, $limit );
    }

    /**
     * Show the secret URL to the admin.
     */
    public function show_secret_key() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if dismissed
        if ( get_user_meta( get_current_user_id(), $this->dismiss_option, true ) ) {
            return;
        }

        $key = get_option( $this->option_name );
        if ( $key ) {
            $url = home_url( '/?' . $this->param_name . '=' . $key );
            $dismiss_url = wp_nonce_url( add_query_arg( 'wper_dismiss', '1' ), 'wper_dismiss_notice' );
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>🚑 <?php esc_html_e( 'Emergency Rescue:', 'emergency-rescue' ); ?></strong> <?php esc_html_e( 'Save this URL to recover your site if it crashes:', 'emergency-rescue' ); ?></p>
                <p><code><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a></code></p>
                <p><a href="<?php echo esc_url( $dismiss_url ); ?>" style="text-decoration:none; font-size: 0.9em;"><?php esc_html_e( 'Dismiss this notice permanently', 'emergency-rescue' ); ?></a> <?php esc_html_e( '(You can always find this in Tools > Emergency Rescue)', 'emergency-rescue' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Check if the rescue key is present in the URL.
     */
    private function check_rescue_mode() {
        if ( isset( $_GET[ $this->param_name ] ) ) {
            // Basic sanitization: Allow standard URL-safe characters
            $key = sanitize_text_field( wp_unslash( $_GET[ $this->param_name ] ) );
            $stored_key = get_option( $this->option_name );

            // If key matches, enter rescue mode
            if ( $stored_key && $key === $stored_key ) {
                $this->render_rescue_page();
                exit; // Stop WordPress from loading further
            }
        }
    }

    /**
     * Render the rescue interface.
     */
    private function render_rescue_page() {
        // Handle any actions (rename/restore) before rendering
        $this->handle_actions();

        // Determine Theme Directory safely (get_theme_root might not be loaded)
        $theme_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
        if ( function_exists( 'get_theme_root' ) ) {
            $theme_dir = get_theme_root();
        }
        
        // Plugin Directory
        $plugin_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php esc_html_e( 'Emergency Rescue', 'emergency-rescue' ); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; color: #3c434a; padding: 20px; line-height: 1.5; }
                .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 5px; }
                h1 { color: #d63638; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                h2 { margin-top: 30px; font-size: 1.3em; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; border: 1px solid #e5e5e5; }
                th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e5e5; }
                th { background: #f9f9f9; font-weight: 600; }
                tr:hover { background: #fafafa; }
                .btn { display: inline-block; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 13px; cursor: pointer; border: 1px solid transparent; }
                .btn-danger { background: #d63638; color: #fff; border-color: #d63638; }
                .btn-danger:hover { background: #b32d2e; border-color: #b32d2e; }
                .btn-primary { background: #2271b1; color: #fff; border-color: #2271b1; }
                .btn-primary:hover { background: #135e96; border-color: #135e96; }
                .btn-secondary { background: #f6f7f7; color: #2c3338; border-color: #dcdcde; }
                .btn-secondary:hover { background: #f0f0f1; border-color: #c3c4c7; }
                .status-active { color: #007017; font-weight: bold; background: #edfaef; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
                .status-disabled { color: #d63638; font-weight: bold; background: #fbeaea; padding: 4px 8px; border-radius: 4px; font-size: 0.9em; }
                .message { padding: 12px; margin-bottom: 20px; border-left: 4px solid; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
                .message.success { border-color: #46b450; background: #fff; }
                .message.error { border-color: #d63638; background: #fff; }
                code { background: #f0f0f1; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
                .footer { margin-top: 40px; font-size: 0.9em; color: #646970; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚑 <?php esc_html_e( 'Emergency Rescue', 'emergency-rescue' ); ?></h1>
                <p><?php esc_html_e( 'Welcome to the emergency recovery mode. Here you can selectively disable plugins or themes by renaming their folders.', 'emergency-rescue' ); ?></p>
                
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo esc_url( admin_url() ); ?>" class="btn btn-primary" target="_blank"><?php esc_html_e( 'Try Loading WP Admin', 'emergency-rescue' ); ?> &nearr;</a>
                    <a href="<?php echo esc_url( home_url() ); ?>" class="btn btn-secondary" target="_blank"><?php esc_html_e( 'View Site', 'emergency-rescue' ); ?> &nearr;</a>
                </div>

                <div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top:0;">🔧 <?php esc_html_e( 'Debug Tools', 'emergency-rescue' ); ?></h3>
                    <p><?php esc_html_e( 'Toggle debugging options for this session:', 'emergency-rescue' ); ?></p>
                    <?php
                    $debug_display = isset( $_COOKIE['wper_debug_display'] ) && $_COOKIE['wper_debug_display'];
                    $debug_log     = isset( $_COOKIE['wper_debug_log'] ) && $_COOKIE['wper_debug_log'];
                    
                    // Build toggle URLs
                    // We must preserve the secret key which is in $_GET
                    $current_url = set_url_scheme( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
                    $url_log     = add_query_arg( 'wper_debug_toggle', 'log', $current_url );
                    ?>
                    
                    <a href="<?php echo esc_url( $url_log ); ?>" class="btn <?php echo $debug_log ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $debug_log ? __( 'Disable', 'emergency-rescue' ) : __( 'Enable', 'emergency-rescue' ); ?> <?php esc_html_e( 'Debug Log (File)', 'emergency-rescue' ); ?>
                    </a>
                </div>

                <?php if ( $debug_log ) : ?>
                <div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <h3 style="margin-top:0;">📄 <?php esc_html_e( 'Debug Log Viewer', 'emergency-rescue' ); ?></h3>
                    <p><?php printf( __( 'Last %s of %s:', 'emergency-rescue' ), '20KB', '<code>debug.log</code>' ); ?></p>
                    <textarea style="width:100%; height: 300px; font-family: monospace; font-size: 12px; background: #f0f0f1; border: 1px solid #ddd; padding: 10px; white-space: pre;" readonly><?php echo $this->get_debug_log_content(); ?></textarea>
                    <p style="text-align: right; margin-top: 5px;"><a href="<?php echo esc_url( remove_query_arg( 'wper_test_error', $current_url ) ); ?>" class="btn btn-secondary"><?php esc_html_e( 'Refresh Log', 'emergency-rescue' ); ?></a></p>
                </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $_GET['msg'] ) ) : ?>
                    <div class="message success"><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ) ); ?></div>
                <?php endif; ?>

                <?php if ( ! empty( $_GET['error'] ) ) : ?>
                    <div class="message error"><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) ); ?></div>
                <?php endif; ?>

                <h2><?php esc_html_e( 'Plugins', 'emergency-rescue' ); ?></h2>
                <?php $this->list_items( $plugin_dir, 'plugin' ); ?>

                <h2><?php esc_html_e( 'Themes', 'emergency-rescue' ); ?></h2>
                <?php $this->list_items( $theme_dir, 'theme' ); ?>
                
                <div class="footer">
                    <p><?php esc_html_e( 'Generated by Emergency Rescue', 'emergency-rescue' ); ?> &bull; <a href="?<?php echo $this->param_name . '=' . esc_attr( $_GET[ $this->param_name ] ); ?>"><?php esc_html_e( 'Refresh Page', 'emergency-rescue' ); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * List items in a directory.
     */
    private function list_items( $directory, $type ) {
        if ( ! is_dir( $directory ) ) {
            echo "<div class='message error'>" . sprintf( __( 'Directory not found: %s', 'emergency-rescue' ), esc_html( $directory ) ) . "</div>";
            return;
        }

        $items = scandir( $directory );
        if ( ! $items ) {
            echo "<p>" . __( 'No items found.', 'emergency-rescue' ) . "</p>";
            return;
        }

        echo '<table><thead><tr><th>' . __( 'Name (Folder)', 'emergency-rescue' ) . '</th><th>' . __( 'Status', 'emergency-rescue' ) . '</th><th>' . __( 'Action', 'emergency-rescue' ) . '</th></tr></thead><tbody>';

        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' || $item === 'index.php' || $item === '.DS_Store' ) continue;
            
            $path = trailingslashit( $directory ) . $item;
            if ( ! is_dir( $path ) && $type === 'theme' ) continue; // Themes must be directories
            
            // Basic detection of disabled items (renamed with .off suffix)
            $is_disabled = ( substr( $item, -4 ) === '.off' );
            $display_name = $is_disabled ? substr( $item, 0, -4 ) : $item;
            
            echo '<tr>';
            echo '<td><strong>' . esc_html( $display_name ) . '</strong><br><small style="color:#666">' . esc_html( $item ) . '</small></td>';
            echo '<td>' . ( $is_disabled ? '<span class="status-disabled">' . __( 'Disabled', 'emergency-rescue' ) . '</span>' : '<span class="status-active">' . __( 'Active', 'emergency-rescue' ) . '</span>' ) . '</td>';
            echo '<td>';
            
            // Calculate new name
            $new_name = $is_disabled ? $display_name : $item . '.off';
            $action_label = $is_disabled ? __( 'Restore (Enable)', 'emergency-rescue' ) : __( 'Disable (Rename)', 'emergency-rescue' );
            $btn_class = $is_disabled ? 'btn-primary' : 'btn-danger';
            
            // Build Action URL
            // We must preserve the secret key
            $current_url = set_url_scheme( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
            $url = add_query_arg( array(
                $this->param_name => sanitize_text_field( wp_unslash( $_GET[ $this->param_name ] ) ), // Keep secret key
                'action'   => 'rename',
                'type'     => $type,
                'target'   => $item,
                'new_name' => $new_name
            ), $current_url );

            echo '<a href="' . esc_url( $url ) . '" class="btn ' . esc_attr( $btn_class ) . '" onclick="return confirm(\'' . esc_js( sprintf( __( 'Are you sure you want to %s?', 'emergency-rescue' ), esc_html( strtolower( $action_label ) ) ) ) . '\');">' . esc_html( $action_label ) . '</a>';
            
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Handle rename actions.
     */
    private function handle_actions() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'rename' && isset( $_GET['target'] ) && isset( $_GET['new_name'] ) ) {
            
            $type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : 'plugin';
            
            // Security: Sanitize filenames strictly to prevent directory traversal
            $target = sanitize_file_name( wp_unslash( $_GET['target'] ) );
            $new_name = sanitize_file_name( wp_unslash( $_GET['new_name'] ) );
            
            // Determine base directory again
            if ( $type === 'theme' ) {
                 $base_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes' : ABSPATH . 'wp-content/themes';
                 if ( function_exists( 'get_theme_root' ) ) $base_dir = get_theme_root();
            } else {
                $base_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
            }

            $old_path = trailingslashit( $base_dir ) . $target;
            $new_path = trailingslashit( $base_dir ) . $new_name;

            // Verify paths exist and are valid
            if ( ! file_exists( $old_path ) ) {
                $this->redirect_with_msg( '', __( 'Target file does not exist.', 'emergency-rescue' ) );
            }

            if ( file_exists( $new_path ) ) {
                $this->redirect_with_msg( '', __( 'Destination already exists.', 'emergency-rescue' ) );
            }

            // Perform Rename
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Standard PHP rename used for emergency recovery without WP_Filesystem dependencies.
            if ( rename( $old_path, $new_path ) ) {
                $this->log_change( sprintf( "Renamed %s to %s (%s)", $target, $new_name, $type ) );
                $this->redirect_with_msg( sprintf( __( 'Successfully renamed %s to %s', 'emergency-rescue' ), $target, $new_name ) );
            } else {
                $this->redirect_with_msg( '', __( 'Failed to rename. Check file permissions.', 'emergency-rescue' ) );
            }
        }
    }

    private function redirect_with_msg( $msg = '', $error = '' ) {
        // We need to preserve the secret key in the URL
        $current_url = set_url_scheme( 'http://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
        
        // Remove action parameters but keep the key
        $url = remove_query_arg( array( 'action', 'target', 'new_name', 'type', 'msg', 'error' ), $current_url );
        
        if ( $msg ) $url = add_query_arg( 'msg', urlencode( $msg ), $url );
        if ( $error ) $url = add_query_arg( 'error', urlencode( $error ), $url );
        
        // Use wp_safe_redirect if available, otherwise fallback (though at plugins_loaded it should be available)
        if ( function_exists( 'wp_safe_redirect' ) ) {
            wp_safe_redirect( $url );
        } else {
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fallback if WP functions not fully loaded.
            header( "Location: $url" );
        }
        exit;
    }

    private function log_change( $message ) {
        $log_file = $this->get_log_file_path();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR is server controlled.
        $entry = gmdate( 'Y-m-d H:i:s' ) . " - " . $message . " - IP: " . sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) . "\n";
        file_put_contents( $log_file, $entry, FILE_APPEND );
    }
}

// Initialize the plugin
new WPER_Emergency_Rescue();
