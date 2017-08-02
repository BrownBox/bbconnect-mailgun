<?php
// @todo
require_once(dirname(__FILE__)."/../../../wp-load.php");

if (isset($_POST['type'])) {
    $hook_date = bbconnect_get_datetime($_POST['fired_at'], new DateTimeZone('GMT')); // MailChimp always sends date as GMT
    $hook_date->setTimezone(bbconnect_get_timezone()); // Convert to local timezone
    $tracking_args = array(
            'type' => 'mailchimp',
            'source' => 'bbconnect-mailchimp',
            'title' => 'MailChimp Webhook Received: ',
            'date' => $hook_date->format('Y-m-d H:i:s'),
    );

    // WARNING!! HACK AHEAD!!
    // When adding a new user, the MC call is triggered before we create the user - so when MC comes back straight away to say they're subscribed, we can't find the user because they don't exist yet.
    // So we delay the lookup for a few seconds to give it a chance to create the user first. This doesn't impact the end user as this script is called by MC.
    // We also delay it if it's a profile update in case there's a change of email at the same time - so that we have time to update the email address before doing the lookup.
    if ($_POST['type'] == 'subscribe' || $_POST['type'] == 'profile') {
        sleep(5);
    }
    // HACK OVER. You can relax now.

    $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
    $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    if (isset($_POST['data']['list_id'])) {
        $list_details = $mailchimp_lists->getList(array('list_id' => $_POST['data']['list_id']));
        $list_details = array_shift($list_details['data']);
    }

    if (!empty($_POST['data']['merges']['EMAIL']) || !empty($_POST['data']['old_email'])) {
        if ($_POST['type'] == 'upemail') {
            $email = $_POST['data']['old_email'];
        } else {
            $email = $_POST['data']['merges']['EMAIL'];
        }

        do_action('bbconnect_mailchimp_before_webhook', $email, $_POST);

        $userobject = get_user_by('email', $email);
        if ($userobject instanceof WP_User) {
            $user_id = $userobject->data->ID;

            $tracking_args['user_id'] = $user_id;
            $tracking_args['email'] = $email;

            switch ($_POST['type']) {
                case 'subscribe':
                    // Update Subscribe meta on user
                    update_user_meta($user_id, 'bbconnect_bbc_subscription', 'true');
                    $tracking_args['title'] .= 'Subscribe';
                    $tracking_args['description'] = '<p>Subscribed to "'.$list_details['name'].'".</p>';
                    break;
                case 'unsubscribe':
                    // Update Subscribe meta on user
                    update_user_meta($user_id, 'bbconnect_bbc_subscription', 'false');
                    $tracking_args['title'] .= 'Unsubscribe';
                    $tracking_args['description'] = '<p>Unsubscribed from "'.$list_details['name'].'".</p>';
                    break;
                case 'cleaned':
                    // Update Subscribe meta on user
                    update_user_meta($user_id, 'bbconnect_bbc_subscription', 'false');
                    $tracking_args['title'] .= 'Cleaned';
                    $reason = $_POST['data']['reason'] == 'hard' ? 'hard bounce' : 'abuse report';
                    $tracking_args['description'] = '<p>Email was forcibly removed from list "'.$list_details['name'].'" due to '.$reason.'.</p>';
                    break;
                case 'upemail':
                    $userobject->data->user_email = $_POST['data']['new_email'];
                    $result = wp_update_user($userobject);
                    $tracking_args['title'] .= 'Email Address Change';
                    $tracking_args['description'] = '<p>Email address changed from '.$_POST['data']['old_email'].' to '.$_POST['data']['new_email'].'.</p>';
                    break;
                case 'profile':
                    $tracking_args['title'] .= 'Profile Update';
                    $tracking_args['description'] = '<p>Updated profile details for "'.$list_details['name'].'".</p>';
                    break;
            }

            if ($type != 'upemail') { // Don't need to run this for a change of email address as we always get a separate profile call at the same time as an email update
                $profile_fields = array(
                        'COUNTRY' => 'bbconnect_address_country_1',
                        'FNAME' => 'first_name',
                        'LNAME' => 'last_name',
                );

                foreach ($profile_fields as $mc_field => $meta_key) {
                    if (!empty($_POST['data']['merges'][$mc_field])) {
                        $meta_value = $_POST['data']['merges'][$mc_field];
                        if ($meta_key == 'bbconnect_address_country_1') {
                            $bbconnect_helper_country = bbconnect_helper_country();
                            $meta_value = array_search($meta_value, $bbconnect_helper_country);
                        }
                        update_user_meta($user_id, $meta_key, $meta_value);
                    }
                }
            }

            bbconnect_track_activity($tracking_args);
        }
    } elseif ($_POST['type'] == 'campaign') {
        do_action('bbconnect_mailchimp_before_webhook', null, $_POST);
        if ($_POST['data']['status'] == 'sent') {
            $start = 0;
            do {
                $params = array(
                        'cid' => $_POST['data']['id'],
                        'opts' => array(
                                'start' => $start,
                                'limit' => 100,
                        ),
                );
                $recipients = $mailchimp->call('reports/sent-to', $params);
                $total = $recipients['total'];
                foreach ($recipients['data'] as $recipient) {
                    $email = $recipient['member']['email'];
                    $userobject = get_user_by('email', $email);
                    if ($userobject instanceof WP_User) {
                        $user_id = $userobject->data->ID;

                        $tracking_args['user_id'] = $user_id;
                        $tracking_args['email'] = $email;
                        $tracking_args['title'] = 'MailChimp Webhook Received: Campaign "'.$_POST['data']['subject'].'"';
                        if ($recipient['status'] == 'sent') {
                            $description = 'was sent successfully';
                        } else {
                            $description = 'could not be sent';
                        }
                        $tracking_args['description'] = '<p>Campaign '.$description.'.</p>';
                        bbconnect_track_activity($tracking_args);
                    }
                }
                $start++;
            } while (($start*100) <= $total);
        }
    }

    do_action('bbconnect_mailchimp_after_webhook', $email, $_POST);
}

echo 'Thanks!';
