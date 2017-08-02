<?php
add_filter('bbconnect_options_tabs', 'bbconnect_mailgun_options');
function bbconnect_mailgun_options($navigation) {
    $navigation['bbconnect_mailgun_settings'] = array(
            'title' => __('Mailgun', 'bbconnect'),
            'subs' => false,
    );
    return $navigation;
}

function bbconnect_mailgun_settings() {
    return array(
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailgun_api_key',
                            'name' => __('API Key', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => true,
                                    'public' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailgun_domain',
                            'name' => __('Domain', 'bbconnect'),
                            'help' => 'Must match the configured domain within Mailgun. If left blank will use the current site domain, i.e. '.$_SERVER['HTTP_HOST'],
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => false,
                                    'public' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailgun_test_email',
                            'name' => __('Send test email now', 'bbconnect'),
                            'help' => 'If ticked, the system will attempt to send a test email to your email address',
                            'options' => array(
                                    'field_type' => 'checkbox',
                                    'req' => false,
                                    'public' => false,
                            ),
                    ),
            ),
    );
}

add_action('bbconnect_options_save_ext', 'bbconnect_mailgun_save_settings');
function bbconnect_mailgun_save_settings() {
    if (isset($_POST['_bbc_option']['bbconnect_mailgun_api_key']) && $_POST['_bbc_option']['bbconnect_mailgun_test_email'] == 'true') {
        // Update settings before sending test
        update_option('bbconnect_mailgun_api_key', $_POST['_bbc_option']['bbconnect_mailgun_api_key']);
        update_option('bbconnect_mailgun_domain', $_POST['_bbc_option']['bbconnect_mailgun_domain']);

        // Send test email
        $connector = bbconnect_mailgun_connector::get_instance();
        $user = wp_get_current_user();
        $connector->send_email($user->user_email, 'Connexions Mailgun Test Message', '<p>Your test was successful. Welcome to Mailgun!</p>');
        if ($connector->is_success()) {
            echo '<div class="notice notice-success"><p>Test successful!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Sending failed with the following error: '.$connector->get_last_error().' ('.$connector->get_last_code().')</p></div>';
        }

        // Uncheck test option
        $_POST['_bbc_option']['bbconnect_mailgun_test_email'] = 'false';
    }
}
