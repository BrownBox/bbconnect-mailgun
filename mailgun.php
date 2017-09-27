<?php
/**
 * Plugin Name: Connexions Mailgun
 * Plugin URI: http://connexionscrm.com/
 * Description: Send emails to your contacts via Mailgun (http://mailgun.com/)
 * Version: 0.1.2
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 *
 */
define('BBCONNECT_MAILGUN_DIR', plugin_dir_path(__FILE__));
define('BBCONNECT_MAILGUN_URL', plugin_dir_url(__FILE__));

require_once (BBCONNECT_MAILGUN_DIR.'settings.php');
require_once (BBCONNECT_MAILGUN_DIR.'classes/connector.class.php');

function bbconnect_mailgun_init() {
    if (!defined('BBCONNECT_VER') || version_compare(BBCONNECT_VER, '2.5.6', '<')) {
        add_action('admin_init', 'bbconnect_mailgun_deactivate');
        add_action('admin_notices', 'bbconnect_mailgun_deactivate_notice');
        return;
    }
    if (is_admin()) {
        new BbConnectUpdates(__FILE__, 'BrownBox', 'bbconnect-mailgun');
    }
    $quicklinks_dir = BBCONNECT_MAILGUN_DIR.'quicklinks/';
    bbconnect_quicklinks_recursive_include($quicklinks_dir);
}
add_action('plugins_loaded', 'bbconnect_mailgun_init');

function bbconnect_mailgun_deactivate() {
    deactivate_plugins(plugin_basename(__FILE__));
}

function bbconnect_mailgun_deactivate_notice() {
    echo '<div class="updated"><p><strong>Connexions Mailgun</strong> has been <strong>deactivated</strong> as it requires Connexions (v2.5.6 or higher).</p></div>';
    if (isset($_GET['activate'])) {
        unset($_GET['activate']);
    }
}

add_filter('bbconnect_activity_types', 'bbconnect_mailgun_activity_types');
function bbconnect_mailgun_activity_types($types) {
    $types['mailgun'] = 'Mailgun';
    return $types;
}

add_filter('bbconnect_activity_icon', 'bbconnect_mailgun_activity_icon', 10, 2);
function bbconnect_mailgun_activity_icon($icon, $activity_type) {
    if ($activity_type == 'mailgun') {
        $icon = plugin_dir_url(__FILE__).'images/activity-icon.png';
    }
    return $icon;
}
