<?php

/*
 * Add admin menu pages
 */
add_action('admin_menu', 'paypress_add_admin_pages', 0);

function paypress_add_admin_pages() {
    add_menu_page("PayPress", "PayPress", "manage_options", "paypress-settings", 'paypress_settings', PAYPRESS_PLUGINURL . 'images/paypress-16x16.png');
    add_submenu_page('paypress-settings', __('Groepen', 'PayPress'), __('Groepen', 'PayPress'), 'manage_options', 'paypress-groups', 'paypress_groups');
}

/*
 * Add admin warning box
 */
add_action('admin_notices', 'paypress_admin_warning');

function paypress_admin_warning() {
    if (!get_option('paypress_merchantcode') ||
            !get_option('paypress_profilekey') ||
            !get_option('paypress_sharedsecret') ||
            !get_option('paypress_apikey')) {

        echo "<div id='paypress-warning' class='updated fade'><p><strong>" .
        __('PayPress is bijna klaar om te gebruiken.') . "</strong> " .
        sprintf(__('Je moet <a href="' . admin_url() . 'admin.php?page=paypress-settings' . '">de plugin koppelen aan je PayPress-account</a> om de plugin te kunnen gebruiken.'), "admin.php?page=paypress-settings") . "</p></div>";
    }
}

/*
 * Add admin JavaScript
 */
add_action('admin_enqueue_scripts', 'paypress_add_admin_javascript');

function paypress_add_admin_javascript() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('admin-pp', plugin_dir_url(__FILE__) . 'js/paypress_admin.js');
}

/*
 * Add posts columns
 */
add_filter('manage_posts_columns', 'paypress_add_posts_custom_columns');
add_filter('manage_pages_columns', 'paypress_add_posts_custom_columns');

function paypress_add_posts_custom_columns($defaults) {
    $defaults['paypress'] = __('PayPress');
    return $defaults;
}

/*
 * Manage posts columns
 * Add information to it
 */
add_action('manage_posts_custom_column', 'paypress_manage_custom_column', 10, 2);
add_action('manage_pages_custom_column', 'paypress_manage_custom_column', 10, 2);

function paypress_manage_custom_column($column_name, $post_id) {
    if ($column_name == 'paypress') {
        global $_paypress_paidcontent;
        global $_paypress_paymentgroup;
        global $_paypress_displaytype;

        $_paypress_paidcontent = get_post_meta($post_id, '_paypress_paidcontent', true);
        $_paypress_paymentgroup = get_post_meta($post_id, '_paypress_paymentgroup', true);
        $_paypress_displaytype = get_post_meta($post_id, '_paypress_display_type', true);

        if ($_paypress_paidcontent == 'true') {
            echo "Betaald";
            if ($_paypress_paymentgroup == "ppp_individueel") {
                echo " - (PayPerPost)";
            } else {
                $type = substr($_paypress_paymentgroup, 0, 4) == 'ppp_' ? 'PayPerPost' : 'PayPerMinute';
                echo " - " . substr($_paypress_paymentgroup, 4) . " ($type)";
            }
        } else {
            echo "Gratis";
        }
    }
}

/*
 * @TODO Add Bulk Action function
 */

/*
 * PayPress Settings page
 */

function paypress_settings() {
    //check if post values exists for easy-install
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paypress_my_account'])) {
        if (!empty($_POST['paypress_account_username']) &&
                !empty($_POST['paypress_account_password']) &&
                get_option('paypress_profilekey') == false) {
            $username = $_POST['paypress_account_username'];
            $password = $_POST['paypress_account_password'];
            //send username and password to paypress-server
            $paypress_account = file_get_contents(PAYPRESS_API_URL . "?type=retrieve-keys&merchant_code=&emailaddress=$username&password=$password");
            if (!$paypress_account) {
                echo "<p>Kon geen verbinding maken met PayPress, probeer het later nog eens.</p>";
                exit;
            }
            $paypress_account = new SimpleXMLElement($paypress_account);
            if ($paypress_account->success == 0) {
                $error[] = "Er is een fout opgetreden bij het registreren van de plugin.";
                $error[] = $paypress_account->message;
            } else {
                //get merchant and api key
                if ($paypress_account->success == 1) {
                    $merchant_code = (string) $paypress_account[0]->merchant_code;
                    $api_key = (string) $paypress_account->api_key;
                }
                //get sharedsecret
                $paypress_account = file_get_contents(PAYPRESS_API_URL . "?type=retrieve-shared-secret&merchant_code=&emailaddress=$username&password=$password");
                $paypress_account = new SimpleXMLElement($paypress_account);
                if ($paypress_account->success == 1) {
                    $shared_secret = (string) $paypress_account->key;
                }
                //get profilekey
                $url = get_bloginfo('wpurl');
                $paypress_account = file_get_contents(PAYPRESS_API_URL . "?type=register-profile&merchant_code=$merchant_code&url=$url");
                $paypress_account = new SimpleXMLElement($paypress_account);
                if (isset($paypress_account->profile_id)) {
                    $profile_key = (string) $paypress_account->profile_id;
                }
                //save merchant, api, shared key to database
                if (!empty($merchant_code) && !empty($api_key) && !empty($shared_secret) && !empty($profile_key)) {
                    update_option('paypress_merchantcode', $merchant_code);
                    update_option('paypress_apikey', $api_key);
                    update_option('paypress_sharedsecret', $shared_secret);
                    update_option('paypress_profilekey', $profile_key);
                } else {
                    $error[] = "Er is een fout opgetreden bij het registreren van de plugin.";
                }
                //remove username and password from memory
                unset($username);
                unset($password);
            }
        } else {
            delete_option('paypress_profilekey');
        }
    }

    //check if user wants to remove paypress-account options
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paypress_remove_account'])) {
        delete_option('paypress_profilekey');
        delete_option('paypress_apikey');
        delete_option('paypress_sharedsecret');
        delete_option('paypress_merchantcode');
    }

    //save paypress-settings
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paypress_save_settings'])) {
        update_option('paypress_more_text', htmlspecialchars($_POST['paypress_more_text']));
        update_option('paypress_user_style', $_POST['paypress_user_style']);
    }

    //reset paypress style settings
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paypress_reset_style'])) {
        update_option('paypress_user_style', get_option('paypress_original_style'));
    }

    paypress_create_layout_header_and_sidebar("Koppeling PayPress-account");

    if (!empty($error)) {
        foreach ($error as $err) {
            echo '<p style="color: red;">' . $err . '</p>';
        }
    }

    //check if plugin is already activated with PayPress-account
    //create settings
    if (get_option("paypress_profilekey")) {
        $display_none = '';
        $account_info = 'style="display: none;"';
        $button_description = "Deze plugin is gekoppeld met je PayPress-account.";
        $display = 'style="display: none"';
    } else {
        $display_none = 'style="display: none;"';
        $account_info = '';
        $button_description = "De plugin zal automatisch worden gekoppeld met je PayPress-account.";
        $display = "";
    }

    $plugin_url = PAYPRESS_PLUGINURL;

    echo '<form action="" name="paypress_account_settings" method="post">';
    echo <<<AFFAIRE
        <table class="form-table">
            <p>Om content te verkopen met PayPress moet je jouw PayPress-account
                koppelen aan deze WordPress website.</p>
                <p {$display}>Heb je al een PayPress-account?</p>
                <p {$display}>Koppel hieronder jouw PayPress-account door jouw e-mailadres en wachtwoord in te vullen. Klik daarna op “Koppel mijn PayPress-account”.</p>
            <tbody>
                <tr>
                <th scope="row">Koppeling met PayPress-account</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">Koppeling met PayPress-account</legend>
                    </fieldset>
                    <p class="paypress_account" {$account_info}>
                        Log in bij PayPress&nbsp;&nbsp;
                        <span class="description">Log in met je PayPress email-adres en wachtwoord om je unieke betaalcodes op te halen</span>
                    </p>
                    <p class="paypress_account" {$account_info}>
                        <input type="text" name="paypress_account_username" value="" />
                        <br/><span class="description">PayPress email-adres</span>
                        <br/>
                        <input type="password" name="paypress_account_password" value="" />
                        <br/><span class="description">PayPress wachtwoord<br /><a href="http://my.paypress.nl/wachtwoord-vergeten/">Ik ben mijn wachtwoord vergeten.</a></span>
                    </p>
                    <p class="paypress_account_verified" {$display_none}>
                        <img src="{$plugin_url}images/success.gif" />&nbsp;Gekoppeld met je PayPress-account.
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <p {$display}>
        Om transacties plaats te laten vinden heb je een PayPress-account nodig.</p>
        <p {$display}>Heb je deze nog niet?
        <a target="_blank" href="http://my.paypress.nl/registreer/">Registreer je dan hier voor een PayPress-account &raquo;</a>
        </p>
        <p class="submit">
            <input {$display} type="submit" name="paypress_my_account" id="submit" class="button-primary" value="Koppel mijn PayPress-account" />
            <input {$display_none} type="submit" name="paypress_remove_account" id="submit" class="button-secondary" value="Ontkoppel mijn PayPress-account" />
        </p>
AFFAIRE;
    echo '</form>';

    paypress_create_layout_footer();

    //start second box

    if ($more = get_option('paypress_more_text')) {
        $more_option = $more;
    } else {
        $more_option = "";
    }

    $user_style = get_option('paypress_user_style');

    paypress_create_layout_header("Instellingen");

    echo <<<AFFAIRE
        <form action="" name="paypress_settings" method="post">
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">Tekst voor de more-tag</th>
                    <td>
                        <input style="width: 300px;" type="text" name="paypress_more_text" value="{$more_option}" />
                        <br /><span class="description">Deze tekst wordt getoond i.p.v. een 'more'-button. Je kunt HTML-code gebruiken.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Stijl van het PayPress-betaalscherm</th>
                    <td>
                        <textarea name="paypress_user_style" cols="40" rows="4">{$user_style}</textarea>
                        <br /><span class="description">
                             Het is mogelijk om de stijl van het PayPress-betaalscherm aan te passen met CSS.
                        </span>
                        <br />
                        <input type="submit" name="paypress_reset_style" value="Reset" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="paypress_save_settings" id="submit" class="button-primary" value="Instellingen Opslaan" />
            </p>
        </form>
AFFAIRE;

    paypress_create_layout_footer();
}

/*
 * PayPress Toplevel page
 */

function paypress_toplevel() {
    global $wpdb;
    //get information from database
    $query = "SELECT * FROM " . $wpdb->prefix . "paypress_notification WHERE status = '2'";
    $result = $wpdb->get_results($query);

    //save values in variables
    $callCount = count($result);
    $lastPayment = $result[$callCount - 1]->time;

    //print
    paypress_create_layout_header_and_sidebar("PayPress");
    $content = '<p>Aantal betalingen: ' . $callCount . '<br/>Laatste betaling: ' . $lastPayment . '</p>';
    echo $content;
    paypress_create_layout_footer();
}

/*
 * PayPress Groups page
 */

function paypress_groups() {
    //check if user wants to delete a group
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['delete_group'])) {
        $term_to_delete = get_term_by('id', $_GET['delete_group'], 'paypress_paid');
        if ($term_to_delete != false) {
            wp_delete_term($term_to_delete->term_id, 'paypress_paid');
        } else {
            
        }
    }

    //check if user wants to add a group, if post values are set
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['paypress_group_name'])) {
        if (!empty($_POST['paypress_group_name'])) {
            $group = htmlspecialchars("ppp_" . $_POST['paypress_group_name']);
            $result = wp_insert_term($group, PAYPRESS_TAXONOMY, array('description' => $group));
        } else {
            echo "Fout bij toevoegen van groep.";
        }
    }

    paypress_create_layout_header_and_sidebar("PayPress Groepen");

    $groups = get_terms('paypress_paid', array('hide_empty' => false));
    $url = $_SERVER['PHP_SELF'] . '?page=paypress-groups';

    //print groups
    if (count($groups) <= 0) {
        echo "<p>Geen groepen.</p>";
    } else {

        foreach ($groups as $group) {
            if ($group->name != 'ppp_individueel') {

                echo '<p>' . substr($group->name, 4) . ' - <a href="' . $url . '&delete_group=' . $group->term_id . '">Verwijder Groep</a><br/>';
                echo '</p>';
            }
        }
    }

    echo <<<AFFAIRE
    <h4>Nieuwe Groep toevoegen</h4>
        <p>
            <form action="" method="post">Type: 
        </p>
        <p>
        &nbsp;&nbsp;Naam: <input type="text" name="paypress_group_name" />
        <input type="submit" name="submit" value="Toevoegen" />
        </form>
        </p>
AFFAIRE;

    paypress_create_layout_footer();
}

/*
 * Create admin header
 * PayPress layout
 */

function paypress_create_layout_header($title) {
    $mainTitle = $title;

    echo <<<AFFAIRE
   <div class="wrap">
        <div id="poststuff" class="metabox-holder has-right-sidebar">
AFFAIRE;

    echo <<<AFFAIRE
            <div id="post-body">
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                    
                        <div class="postbox">
                            <h3 style="cursor: default;"><span style="cursor: default">{$title}</span></h3>
                            <div class="inside">
AFFAIRE;
}

/*
 * Create admin header and sidebar
 * PayPress layout
 */

function paypress_create_layout_header_and_sidebar($title) {
//    $mainContent = '<p>Aantal betalingen: ' . $callCount . '<br/>Laatste betaling: ' . $lastPayment . '</p>';
    $mainTitle = $title;

    echo <<<AFFAIRE
   <div class="wrap">
        <div id="poststuff" class="metabox-holder has-right-sidebar">
            <div class="inner-sidebar" style="width: 280px;">
AFFAIRE;

    global $wpdb;
    //get information from database
    $query = "SELECT * FROM " . $wpdb->prefix . "paypress_notification WHERE status = '2'";
    $result = $wpdb->get_results($query);

    //save values in variables
    $callCount = count($result);
    $lastPayment = $result[$callCount - 1]->time;

    $sideContent = "Aantal transacties : $callCount<br />Laatste transactie: $lastPayment";

    paypress_create_info_box("PayPress Transacties", $sideContent);

    $sideContent = 'Voor ondersteuning met de PayPress-plugin
        gaat u naar <a href="http://www.paypress.nl/">www.paypress.nl</a>';

    paypress_create_info_box("Hulp nodig met PayPress?", $sideContent);

    paypress_create_info_box("PayPress Nieuws", paypress_get_rss_feed());

    echo <<<AFFAIRE
            </div>
AFFAIRE;
    //end header

    echo <<<AFFAIRE
            <div id="post-body">
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                    
                        <div class="postbox">
                            <h3 style="cursor: default;"><span style="cursor: default">{$title}</span></h3>
                            <div class="inside">
AFFAIRE;
}

/*
 * Create admin footer
 * PayPress layout
 */

function paypress_create_layout_footer() {

    echo <<<AFFAIRE
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
AFFAIRE;
}

/*
 * Create admin info box
 * PayPress Layout
 */

function paypress_create_info_box($title, $content) {

    echo <<<AFFAIRE
        <div class="postbox">
            <h3 style="cursor: default;"><span style="cursor: default;">{$title}</span></h3>
            <div class="inside">
                {$content}
            </div>
        </div>
AFFAIRE;
}

/*
 * Create meta box
 * PayPress settings per post
 */

add_action('add_meta_boxes', 'paypress_create_meta_box', 0);

function paypress_create_meta_box() {
    $selected_post_types = get_post_types();
    foreach ($selected_post_types as $post_type) {
        add_meta_box("paypress-meta-box", "PayPress", "paypress_meta_box", $post_type, "side", "high");
    }
}

function paypress_meta_box($post) {
    $warning = false;
    if (!get_option('paypress_merchantcode') ||
            !get_option('paypress_profilekey') ||
            !get_option('paypress_sharedsecret') ||
            !get_option('paypress_apikey')) {
        $warning = '<a style="color:red;" href="' . admin_url() . 'admin.php?page=paypress-settings' . '">Deze instellingen zijn pas actief als een PayPress-account gekoppeld is &raquo;</a>';
    }
    //get post paypress values
    $paypress_paidcontent = get_post_meta($post->ID, "_paypress_paidcontent", true);
    $paypress_payment_group = get_post_meta($post->ID, "_paypress_paymentgroup", true);
    $paypress_display_type = get_post_meta($post->ID, "_paypress_display_type", true);

    $paidcontent = $paypress_paidcontent == 'true' ? 'checked="checked"' : '';

    $none = selected($paypress_display_type, "none", false);
    $excerpt = selected($paypress_display_type, "excerpt", false);
    $all = selected($paypress_display_type, "all", false);

    $terms = get_terms('paypress_paid', array('hide_empty' => false));

    if ($warning) {
        echo '<p style="color: red;">' . $warning . '</p>';
    }

    echo <<<AFFAIRE
    <p>
        <input class="paypress_paid" type="checkbox" {$paidcontent} name="paypress_paidcontent" value="true" /> Betaalde Content
    </p>
    <p class="paypress_options">
        
AFFAIRE;
    echo 'Behoort tot Groep: <br /><br /><select name="paypress_paymentgroup">';
    echo '<option selected="selected" value="geengroep">Geen Groep</option>';
    if (count($terms) <= 0) {
        echo '<option selected="selected" value="geengroepen">Geen Groepen</option>';
    } else {
        foreach ($terms as $term) {
            if (substr($term->name, 0, 4) == 'ppp_') {
                $type = "(PayPerPost)";
            } else {
                $type = "(Geen type)";
            }
            if ($paypress_payment_group == $term->name) {
                if ($term->name != "ppp_individueel") {
                    echo '<option selected="selected" value="' . $term->name . '">' . substr($term->name, 4) . " " . $type . '</option>';
                }
            } else {
                if ($term->name != "ppp_individueel") {
                    echo '<option value="' . $term->name . '">' . substr($term->name, 4) . " " . $type . '</option>';
                }
            }
        }
    }
    echo '</select></p>';

    echo <<<AFFAIRE
    <p class="paypress_options">
        Wat laat je vooraf zien?
        <br /><br />
        <select name="paypress_display_type">
            <option {$none} value="nothing">Niets</option>
            <option {$excerpt} value="excerpt">Samenvatting (Tot aan 'more'-tag)</option>
            <option {$all} value="all">Alles (Donatie)</option>
        </select>
    </p>
AFFAIRE;
}

/*
 * PayPress save data from metabox
 */

add_action('save_post', 'paypress_save_meta');

function paypress_save_meta($post_id) {
    //skip autosaving
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    //set validEntries
    $validEntries = array(
        'display_type' => array(
            'nothing', 'excerpt', 'all'
        )
    );

    if (isset($_POST['paypress_paidcontent']) && $_POST['paypress_paidcontent'] == 'true' && in_array($_POST['paypress_display_type'], $validEntries['display_type'])) {
        if ($_POST['paypress_paymentgroup'] == 'geengroep') {
            $paymentgroup = 'ppp_individueel';
        } else {
            $paymentgroup = $_POST['paypress_paymentgroup'];
        }

        update_post_meta($post_id, "_paypress_paidcontent", 'true');
        update_post_meta($post_id, "_paypress_paymentgroup", $paymentgroup);
        update_post_meta($post_id, "_paypress_display_type", htmlspecialchars($_POST['paypress_display_type']));

        wp_set_object_terms($post_id, $paymentgroup, PAYPRESS_TAXONOMY, false);
    } else {
        if (isset($_POST['paypress_paymentgroup'])) {
            if ($_POST['paypress_paymentgroup'] == 'geengroep') {
                $paymentgroup = 'ppp_individueel';
            } else {
                $paymentgroup = $_POST['paypress_paymentgroup'];
            }
        } else {
            $paymentgroup = "";
        }

        update_post_meta($post_id, "_paypress_paidcontent", 'false');
        update_post_meta($post_id, "_paypress_paymentgroup", $paymentgroup);
        if (isset($_POST['paypress_display_type'])) {
            update_post_meta($post_id, "_paypress_display_type", htmlspecialchars($_POST['paypress_display_type']));
        } else {
            update_post_meta($post_id, "_paypress_display_type", "");
        }

        wp_set_object_terms($post_id, $paymentgroup, PAYPRESS_TAXONOMY, false);
    }
}

/*
 * Get RSS feed from PayPress.nl
 */

function paypress_get_rss_feed() {
    $rss_feed = file_get_contents("http://www.paypress.nl/category/paypress-plugin/feed/rss");
    $sideContent = "";

    if (!$rss_feed) {
        $sideContent = "Kan berichten niet laden.";
        return $sideContent;
    }

    $rss = new SimpleXMLElement($rss_feed);

    foreach ($rss->channel->item as $r) {
        $sideContent .= '<p><a target="_blank" href="' . $r->link . '">';
        $sideContent .= $r->title;
        $sideContent .= "</a><br /><br />";
        $sideContent .= substr($r->description, 0, 50) . "...</p>";
    }

    return $sideContent;
}

?>
