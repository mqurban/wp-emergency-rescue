# 🚑 MrQurban Emergency Rescue

**MrQurban Emergency Rescue** is a lightweight, "Must-Use" (MU) WordPress plugin designed to help you recover from fatal PHP errors (White Screen of Death) that lock you out of the admin panel.

It provides a **Secret Rescue URL** that loads a minimal, fail-safe interface *before* your active plugins, allowing you to selectively disable problematic plugins or themes by renaming their folders.

![License](https://img.shields.io/badge/license-GPLv2-blue.svg) ![Version](https://img.shields.io/badge/version-1.1.0-green.svg)

## 🌟 Features

*   **Fail-Safe Recovery**: Loads before standard plugins to bypass fatal errors.
*   **Secret Access**: Protected by a unique, randomized secret key stored in your database.
*   **One-Click Deactivation**: Instantly disable any plugin or theme by renaming its folder (appends `.off`).
*   **Runtime Debugging**: Activate `WP_DEBUG_LOG` on demand even if it's disabled in `wp-config.php`, and view logs directly in the rescue page.
*   **Customizable Key**: Set your own memorable secret key via the settings menu.
*   **Activity Logging**: Tracks all rescue actions (renaming plugins/themes) with timestamps and IP addresses. Logs can be viewed and cleared from the settings page.
*   **Admin Dashboard Integration**:
    *   Persistent access to your Rescue URL via **Tools > Emergency Rescue**.
    *   Dismissible admin notices to keep your dashboard clean.

## 🚀 Installation (Highly Recommended)

To ensure this plugin works even when your site is crashing, it should run as a **Must-Use (MU) Plugin**.

### Option 1: One-Click Setup (Recommended)
1.  Download the plugin zip file.
2.  Go to **Plugins > Add New** in your WordPress dashboard and upload the zip.
3.  **Activate** the plugin. 
4.  Navigate to **Tools > Emergency Rescue** and click the **"Enable Fail-Safe Mode"** button. This will automatically set up the needed loader in your `mu-plugins` directory.

### Option 2: Manual MU-Plugin Setup
1.  Access your site via **FTP** or your hosting **File Manager**.
2.  Navigate to `wp-content/mu-plugins/`. (Create the folder if it doesn't exist).
3.  Upload the `mrqurban-emergency-rescue.php` file directly into that folder.

*(Note: If installed as a regular plugin without the MU loader, it might not load early enough to catch all fatal errors.)*

## 📖 Usage

### 1. Setup & Configuration
Once installed, log in to your WordPress Admin.
*   You will see a notice containing your **Secret Rescue URL**.
*   Go to **Tools > Emergency Rescue** to:
    *   View/Copy your Rescue URL.
    *   Change your **Secret Key** to something custom (e.g., `?rescue_key=my-secure-password`).

### 2. When Disaster Strikes 💥
If you activate a plugin or theme that crashes your site (Critical Error / White Screen):
1.  **Visit your Secret Rescue URL** (e.g., `https://yoursite.com/?rescue_key=your-secret-key`).
2.  You will see the **Emergency Rescue Interface**.
3.  Find the plugin that caused the crash in the list.
4.  Click **Disable (Rename)**.
    *   This renames the plugin folder (e.g., `elementor` → `elementor.off`).
    *   WordPress will automatically deactivate the plugin because the path changed.
5.  Go back to `https://yoursite.com/wp-admin/` and fix the issue.

### 3. Restoring
Once you have fixed the code or decided to re-enable the plugin:
1.  Visit the Rescue URL again.
2.  Click **Restore (Enable)** next to the disabled plugin.
3.  Go to **Plugins > Installed Plugins** in WordPress and reactivate it.

## 🔒 Security

*   **Secret Key Enforced**: The interface is only accessible if the URL contains the correct `rescue_key`.
*   **Sanitized Inputs**: All file operations are strictly sanitized to prevent directory traversal attacks.
*   **Capability Check**: The settings page in the admin panel is restricted to users with `manage_options` capability (Admins).

## ⚠️ Disclaimer
This tool modifies filesystem paths (renames folders). Use it responsibly. It is designed for emergency recovery when you cannot access the WordPress dashboard.
