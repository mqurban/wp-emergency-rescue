=== WP Emergency Rescue ===
Contributors: mqurban
Tags: recovery, debug, white screen of death, fatal error, troubleshooting, emergency
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover from fatal errors and White Screen of Death (WSOD) by disabling plugins or themes via a secret rescue URL.

== Description ==

**WP Emergency Rescue** is a lightweight, life-saving plugin designed to help you recover your WordPress site when you are locked out of the admin panel due to a fatal error, "Critical Error," or the dreaded White Screen of Death (WSOD).

It provides a **Secret Rescue URL** that loads a minimal, fail-safe interface *before* your active plugins, allowing you to selectively disable problematic plugins or themes by renaming their folders without needing FTP access.

### 🌟 Key Features

*   **Fail-Safe Recovery**: Loads early to bypass fatal errors caused by other plugins.
*   **Secret Access**: Protected by a unique, randomized secret key.
*   **One-Click Deactivation**: Instantly disable any plugin or theme.
*   **Debug Tools**: Enable `WP_DEBUG_LOG` on the fly and view the log file directly in the rescue interface.
*   **Secure**: Uses a secret key and verifies permissions.

### ⚠️ Important Usage Note

While this plugin works as a standard plugin, it is **highly recommended** to install it as a **Must-Use (MU) Plugin** to ensure it loads before any other plugin that might be causing a crash.

== Installation ==

### Standard Installation (Good)
1.  Upload the `wp-emergency-rescue.php` file to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Tools > Emergency Rescue** to get your Secret URL.

### Must-Use (MU) Installation (Best - Recommended)
To ensure the plugin works even when your site is crashing early:
1.  Upload the `wp-emergency-rescue.php` file to `/wp-content/mu-plugins/`.
2.  If the `mu-plugins` folder does not exist, create it inside `wp-content`.
3.  The plugin is automatically active. Access your dashboard to see the notice with your Secret URL.

== Frequently Asked Questions ==

= How do I find my Secret Rescue URL? =
Go to **Tools > Emergency Rescue** in your WordPress dashboard. It is also displayed in an admin notice upon installation.

= What if I forgot my Secret URL and can't access the admin? =
You can find the secret key in your database in the `wp_options` table under the option name `wper_secret_key`. Alternatively, you can define a constant in your `wp-config.php` (feature coming soon) or check the file code if you have FTP access.

= Does this plugin delete my files? =
No. It simply renames the plugin or theme folder by appending `.off` (e.g., `plugin-name` becomes `plugin-name.off`). This forces WordPress to deactivate it. You can restore it from the same interface.

== Screenshots ==

1. **Rescue Interface** - The fail-safe screen where you can disable plugins and view debug logs.
2. **Disable Plugins** - Disable Plugins by just clicking the deactivate.
3. **Disable Theme** - Disable theme if the error is in the theme, your website will fallback to default theme. Make sure you have at least one extra theme.
4. **Admin Interface Guide** - The admin settings page under Tools -> Emergency Rescue.

== Changelog ==

= 1.1.0 =
*   Added Debug Log Viewer to the rescue interface.
*   Added ability to enable `WP_DEBUG_LOG` via a secure cookie.
*   Improved security and sanitization.
*   Added internationalization support.

= 1.0.0 =
*   Initial release.
