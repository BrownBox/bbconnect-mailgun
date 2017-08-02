<?php
class bbconnect_mailgun_connector {
    private $api_key = '';
    private $endpoint = 'https://api.mailgun.net/v3/';
    private $last_error = '';
    private $last_code = '';

    private static $_instance = null;

    private function __construct() {
        $this->api_key = get_option('bbconnect_mailgun_api_key');
        $domain = get_option('bbconnect_mailgun_domain');
        if (empty($domain)) {
            $domain = $_SERVER['HTTP_HOST'];
        }
        $this->endpoint .= $domain.'/';
    }

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Was the last request successful?
     */
    public function is_success() {
        return $this->last_code == 200;
    }

    /**
     * Get error message from last request
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Get status code from last request
     */
    public function get_last_code() {
        return $this->last_code;
    }

    /**
     * Send an email through Mailgun
     * @param string|array $to Recipient email address(es). Multiple addresses can be comma separated or in an array. When comma separated, a single email will be sent with all recipients in the To line. When the array format is used, a separate email will be sent to each address individually.
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     * @return mixed
     */
    public function send_email($to, $subject, $message) {
        do_action('bbconnect_mailgun_send_email_before', $to, $subject, $message);
        $data = array(
                'to' => $to,
                'subject' => $subject,
                'text' => strip_tags($message),
                'html' => $message,
                'from' => get_option('admin_email'),
        );
        if (is_array($to)) { // recipient-variables is required for sending separate emails
            $recipient_variables = array();
            foreach ($to as $id => $email) {
                $recipient_variables[$email] = array('id' => $id);
            }
            $data['recipient-variables'] = json_encode($recipient_variables);
        }
        $result = $this->post('messages', $data);
        if ($this->is_success()) {
            $send_email_form_id = bbconnect_get_send_email_form();
            if (!is_array($to)) {
                $to = explode(',', $to);
            }
            foreach ($to as $email) {
                $firstname = $lastname = '';
                $user = get_user_by('email', $email);
                if ($user instanceof WP_User) {
                    $firstname = $user->user_firstname;
                    $lastname = $user->user_lastname;
                } else {
                    // New contact!
                    $firstname = 'Unknown';
                    $lastname = 'Unknown';
                    $user = new WP_User();
                    $user->user_login = wp_generate_password(12, false);
                    $user->user_email = $email;
                    $user->user_firstname = $firstname;
                    $user->user_lastname = $lastname;
                    $user->user_pass = wp_generate_password();
                    $user->ID = wp_insert_user($user);
                }
                // Insert GF entry
                $_POST = array(); // Hack to allow multiple form submissions via API in single process
                $entry = array(
                        'input_2_3' => $firstname,
                        'input_2_6' => $lastname,
                        'input_3' => $email,
                        'input_4' => $subject,
                        'input_5' => $message,
                        'input_6' => 'mailgun',
                        'input_7' => 'mailgun',
                        'agent_id' => get_current_user_id(),
                );
                GFAPI::submit_form($send_email_form_id, $entry);
            }
        }
        do_action('bbconnect_mailgun_send_email_after', $to, $subject, $message, $result);
        return $result;
    }

    /**
     * Send to Mailgun!
     * @param string $method
     * @param array $data
     * @return array
     */
    private function post($method, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint.$method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, 'api:'.$this->api_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $result = json_decode($response);
        if (is_null($result)) { // Not a JSON response - assume it's just a string
            $message = $response;
            $result = new stdClass();
            $result->message = $message;
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_code = $http_code;
        if ($http_code == 200) {
            $this->last_error = '';
        } elseif ($result->message) {
            $this->last_error = $result->message;
        } else {
            $this->last_error = 'An unknown error occured. Please try again later.';
        }
        curl_close($ch);
        return $result;
    }
}
