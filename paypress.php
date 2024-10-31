<?php
/*
  Plugin Name: PayPress
  Plugin URI: http://www.paypress.nl
  Description:
  Version: 1.1
  Author: PayPress Developers
  Author URI: http://www.paypress.nl
  License: GPLv2 or later
 */

//start session
if (!session_id()) {
    session_start();
}

require_once dirname(__FILE__) . '/widget.php';

if (is_admin())
    require_once dirname(__FILE__) . '/admin.php';

define('PAYPRESS_TAXONOMY', "paypress_paid");
define('PAYPRESS_PLUGINURL', plugin_dir_url(__FILE__));
define('PAYPRESS_API_URL', "http://api.paypress.nl/api.xml");

require('classes/PayPressPayment.php');

/*
 * Add javascript
 */
add_action('wp_enqueue_scripts', 'paypress_add_javascript');

function paypress_add_javascript() {
    wp_enqueue_script("jquery");
}

/*
 * This code is going to run when the plugin is activated
 */

register_activation_hook(__FILE__, 'paypress_install');

function paypress_install() {
    //insert standard terms
    wp_insert_term('ppp_individueel', PAYPRESS_TAXONOMY);

    //create paypress notification table
    global $wpdb;
    $table_name = $wpdb->prefix . "paypress_notification";
    $sql = "CREATE TABLE " . $table_name . " (
	  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	  time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
          code INT NOT NULL,
          paymenttype VARCHAR(10) NOT NULL,
	  transaction_id INT NOT NULL,
	  customer_key VARCHAR(40) NOT NULL,
          session_id VARCHAR(40) NOT NULL,
	  status TINYINT NOT NULL
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    //add options
    add_option("paypress_merchantcode");
    add_option("paypress_apikey");
    add_option("paypress_sharedsecret");
    add_option("paypress_profilekey");
    add_option("paypress_allowed_post_types");

    //insert original style
    $payment_style = "background: #f2f2f2; text-align: center; margin-bottom: 1em; border: solid 1px #333; padding: 0px;";
    update_option('paypress_original_style', $payment_style);
    update_option('paypress_user_style', $payment_style);
}

/*
 * Create PayPress Taxonomy
 */
add_action('init', 'paypress_create_payment_taxonomy', 0);

function paypress_create_payment_taxonomy() {
    $object_types = get_post_types(array('public' => true, '_builtin' => false));
    $args = array(
        'public' => true,
        'show_ui' => true,
        'hierarchical' => true,
        'rewrite' => false
    );
    register_taxonomy('paypress_paid', $object_types, $args);
}

/*
 * Function to check if user has to pay for the content
 * If yes,
 *      Displays a payment screen
 * If no,
 *      Displays the content
 */
add_filter('the_content', 'paypress_display_content', 1, 1);

function paypress_display_content($content) {
    //register globals
    global $post;
    global $wpdb;
    global $more;

    //get post info
    $paidContent = get_post_meta($post->ID, '_paypress_paidcontent', true);
    $paymentGroup = get_post_meta($post->ID, '_paypress_paymentgroup', true);
    $displayType = get_post_meta($post->ID, '_paypress_display_type', true);


    if ($paidContent != 'true' || empty($paymentGroup)) {
        return $content;
    }

    //check if user has just payed
    if (isset($_POST['paypress_code'], $_POST['paypress_transaction_id'])) {
        $result = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix .
                "paypress_notification WHERE transaction_id = '" .
                $_POST['paypress_transaction_id'] . "' AND code = '" . $_POST['paypress_code'] . "' AND session_id = '" . session_id() . "';");
        if ($result->status == 2) {
            //update number of transactions for this post
            $session_array = get_post_meta($post->ID, '_paypress_transaction_number');
            if (!in_array(session_id(), $session_array)) {
                add_post_meta($post->ID, '_paypress_transaction_number', session_id());
            }

            //check if session variables are arrays
            if (!isset($_SESSION['paypress_paidposts'])) {
                $_SESSION['paypress_paidposts'] = array();
            }
            if (!isset($_SESSION['paypress_paidgroups'])) {
                $_SESSION['paypress_paidgroups'] = array();
            }

            $paidposts = $_SESSION['paypress_paidposts'];
            if (!is_array($paidposts)) {
                $paidposts = array();
            }
            if (!in_array($post->ID, $paidposts)) {
                $paidposts[] = $post->ID;
            }
            $_SESSION['paypress_paidposts'] = $paidposts;
            if (!is_array($_SESSION['paypress_paidgroups'])) {
                $_SESSION['paypress_paidgroups'] = array();
            }

            if ($paymentGroup != 'ppp_individueel') {
                $paidgroups = $_SESSION['paypress_paidgroups'];
                if (!in_array($paymentGroup, $paidgroups)) {
                    $paidgroups[] = $paymentGroup;
                }
                $_SESSION['paypress_paidgroups'] = $paidgroups;
            }
        }
    }

    //check if user has to pay
    if (term_exists($paymentGroup, PAYPRESS_TAXONOMY) &&
            $paidContent == 'true' &&
            get_option('paypress_merchantcode') &&
            get_option('paypress_apikey') &&
            get_option('paypress_sharedsecret') &&
            get_option('paypress_profilekey')) {
        $hasToPay = true;
    } else {
        $hasToPay = false;
    }

    //check if user has already payed, if payment is in session
    if (isset($_SESSION['paypress_paidposts']) && session_id() && $paidContent == 'true') {
        if (in_array($post->ID, $_SESSION['paypress_paidposts']) ||
                (in_array($paymentGroup, $_SESSION['paypress_paidgroups']) && $paymentGroup != 'ppp_individueel')) {
            $hasToPay = false;
        } else {
            $hasToPay = true;
        }
    }

    if (!$hasToPay) {
        return $content;
    }
    
    //user has to pay for the post, get the paymentscreen
    $paymentScreen = paypress_get_payment_screen();
    
    //check if excerpt is called or normal content.
    if ($more == 0) {
        //return content
        if ($displayType == 'nothing') {
            return $paymentScreen;
        } else if ($displayType == 'excerpt') {
            return $content;
        } else {
            return $content;
        }
    }

    if ($displayType == 'nothing') {
        return $paymentScreen;
    }

    if ($displayType == 'excerpt') {
        $location = strpos($content, '<span id="more-' . $post->ID . '"></span>');
        if ($location != false) {
            return substr($content, 0, $location) . "<br />" . html_entity_decode(get_option("paypress_more_text")) . $paymentScreen;
        } else {
            return $content . $paymentScreen;
        }
    }

    if ($displayType == 'all') {
        return $paymentScreen . $content;
    }
}

function paypress_get_payment_screen() {
    global $wpdb;
    global $post;
    //set styles and variables for payment type
    if (strlen(get_option('paypress_user_style')) > 0) {
        $paypress_style = get_option('paypress_user_style');
    } else {
        $paypress_style = get_option('paypress_original_style');
    }
    $paymentMessage = '<p style="display: none; color: green;" class="pp_message2"></p>';

    //get paypress account info
    $merchantCode = get_option("paypress_merchantcode");
    $apiKey = get_option("paypress_apikey");
    $sharedSecret = get_option("paypress_sharedsecret");
    $profileKey = get_option("paypress_profilekey");

    //initialize $return
    $return = '';

    $return .= "<style>.paypress-form { $paypress_style }</style>";

    $return .= '<div class="paypress-form" style="background-image: ' .
            "url('" . PAYPRESS_PLUGINURL . 'images/paypress-flag.png' . "')" .
            '; background-position: right top; background-repeat: no-repeat;">' .
            '<h4 class="paypress_instructions">Betaalinstructies</h4>';

    $payment = new PayPressPayment($merchantCode, $apiKey, $sharedSecret, 'ppc', $profileKey);
    $response = $payment->getPaymentCode();

    if (empty($response->code) || strlen($response->code) == 0) {
        $return .= "Er is een fout opgetreden bij het aanvragen van een betaalcode";
        return $return;
    }

    $query = "INSERT INTO $wpdb->prefix" . "paypress_notification (time,
        code, paymenttype, transaction_id, customer_key, session_id) VALUES 
        (NOW(), '$response->code', 'ppc', '$response->transaction_id',
            '$response->customer_key', '" . session_id() . "');";
    $wpdb->query($query);

    //insert values in session
    $_SESSION['paypress_transid'] = $response->transaction_id;
    $_SESSION['paypress_code'] = $response->code;

    $return .= '<strong>Bel ' . $response->telephone_number . '</strong><br />';
    $return .= 'en toets de volgende code in: <strong>' . $response->code . ' #</strong>';
    $return .= '<form class="paypress_payment_form" id="paypress_payment_form_' . $post->ID . '" action="' . get_permalink() . '" method="post">
            <input class="pp_code" type="hidden" name="paypress_code" value="' . $response->code . '" />
            <input class="pp_transaction_id" type="hidden" name="paypress_transaction_id" value="' . $response->transaction_id . '" />' .
            $paymentMessage .
            '<input class="paypress_paidbutton" id="paypress_paidbutton_' . $post->ID . '" type="submit" value="';
    $return .= 'Klik hier als de betaling is afgerond.';
    $return .= '" />';
    $return .= '</form>';

    $return .= '</div>';

    return $return;
}

/*
 * Add PayPress Statistics Shortcodes
 * 
 */

add_shortcode('PayPress', 'paypress_handle_shortcode');

function paypress_handle_shortcode($args) {
    //register globals
    global $post;
    //check if array key 'stat' exists
    if (array_key_exists('stat', $args)) {
        switch ($args['stat']) {
            case 'counter':
                return count(get_post_meta($post->ID, '_paypress_transaction_number'));
            case 'money':
                /*
                * @TODO Multiplier 0.85 requesten from server
                */
                return "&euro; " . money_format('%n', count(get_post_meta($post->ID, '_paypress_transaction_number')) * 0.85);
        }
    }
}

/*
 * Add AJAX
 */

add_action('wp_head', 'paypress_add_ajax');

function paypress_add_ajax() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            //polling function
            setInterval(function() {
                jQuery.post(
                "<?php echo PAYPRESS_PLUGINURL . 'paypress_poll.php'; ?>",
                {
                    paypress_code: jQuery(".pp_code").val(), paypress_transid: jQuery(".pp_transaction_id").val()
                },
                checkResult
            )
            }, 5000);
            function checkResult(xml) {
                if (jQuery(xml).find("type").text() == "ppc" && jQuery(xml).find("status").text() == 2) {
                    jQuery(".pp_message2").css("color", "green");
                    jQuery(".pp_message2").text("De betaling is ontvangen.");
                    jQuery(".pp_message2").show("slow");
                }
            }
                                                                                                
            //check payment
            jQuery(".paypress_payment_form .paypress_paidbutton").click(function () {
                var hans = false;
                jQuery.ajax({
                    url: "<?php echo PAYPRESS_PLUGINURL . 'paypress_poll.php'; ?>",
                    type: 'POST',
                    datatype: "xml",
                    data: 
                        {
                        paypress_code: jQuery(".pp_code").val(), 
                        paypress_transid: jQuery(".pp_transaction_id").val()
                    },
                    success: handleResult
                });
                function handleResult(xml) {
                    if (jQuery(xml).find("type").text() != "ppc" || jQuery(xml).find("status").text() != 2) {
                        jQuery(".pp_message2").css("color", "red");
                        jQuery(".pp_message2").text("De betaling is nog niet geregistreerd.");
                        jQuery(".pp_message2").show("slow");
                    } else {
                        var id = jQuery(".paypress_paidbutton").parent().attr("id");
                        id = id.replace("paypress_payment_form_", "");
                        jQuery("#paypress_payment_form_" + id).submit();
                    }
                }
                return false;
            });
        });
    </script>

    <?php
}
?>
