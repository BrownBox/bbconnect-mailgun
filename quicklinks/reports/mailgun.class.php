<?php
/**
 * Mailgun quicklink
 * @author markparnell
 */
class reports_mailgun_quicklink extends bb_form_quicklink {
    public function __construct() {
        parent::__construct();
        $this->title = 'Send Email';
    }

    protected function form_contents(array $user_ids = array(), array $args = array()) {
        echo '<div class="modal-row">';
        if (count($user_ids) > 1000) {
            echo '    <p>You are attempting to send an email to '.count($user_ids).' contacts; however Mailgun restricts batch sending to a maximum of 1000 recipients. Please refine your search criteria or use a smaller page size and try again.</p>';
            echo '</div>';
            // @todo work out some way of hiding submit button
            return;
        }
        echo '    <p>You are sending an email to '.count($user_ids).' contacts.</p>';
        echo '</div>';
        echo '<div class="modal-row">';
        echo '    <label for="subject" class="full-width">Subject</label><br>';
        echo '    <input type="text" name="subject" id="subject"><br>';
        echo '</div>';
        echo '<div class="modal-row">';
        echo '    <label for="message">Message</label>';
        echo '    <textarea id="message" name="message" rows="20"></textarea>'; // @todo would be nice if this was a WP Editor
        echo '</div>';
        foreach ($user_ids as $user_id) {
            echo '<input type="hidden" name="recipients['.$user_id.']" value="'.$user_id.'">';
        }
    }

    public static function post_submission() {
        extract($_POST);
        if (empty($subject) || empty($message)) {
            echo 'All fields are required.';
            die();
        }

        // Send email
        $emails = array();
        foreach ($recipients as $user_id) {
            $user = new WP_User($user_id);
            $emails[$user_id] = $user->user_email;
        }

        $connector = bbconnect_mailgun_connector::get_instance();
        $connector->send_email($emails, $subject, $message);
        if (!$connector->is_success()) {
            echo 'An error occured while attempted to send your email: '.$connector->get_last_error().'.';
            die();
        }

        return true;
    }
}
