<?php

//notification.php
//receive notifications from the PayPress server
//include("../../../wp-blog-header.php");
require_once("../../../wp-load.php");

//retrieve account information from PayPress option-page
$pp_sharedSecret = get_option("paypress_sharedsecret");
$pp_merchantCode = get_option("paypress_merchantcode");
$pp_apiKey = get_option("paypress_apikey");

$api_key = $_GET['api_key'];
$hmac = $_GET['hash'];
$transaction_id = $_GET['transaction_id'];

if (!empty($api_key) && !empty($hmac) && !empty($transaction_id)) {
    global $wpdb;
    $transaction = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "paypress_notification WHERE transaction_id = '" . $transaction_id . "';");
//    echo "$transaction->code";
//    echo hash_hmac('sha512', $transaction->code . "|" . $transaction->customer_key . "|" . '2', $pp_sharedSecret);
    if (hash_hmac('sha512', $transaction->code . "|" . $transaction->customer_key . "|" . '2', $pp_sharedSecret) == $hmac) {
        //call ended
        $data = array('status' => 2);
        $where = array('transaction_id' => $transaction_id);
        $wpdb->update($wpdb->prefix . "paypress_notification", $data, $where);
        echo "[accepted]";
        /*
         * Clean database
         * Remove expired codes
         */
        $query = "DELETE FROM $wpdb->prefix" . "paypress_notification WHERE time <= NOW()-7200 AND status <> 2";
        $wpdb->query($query);
    } else if (hash_hmac('sha512', $transaction->code . "|" . $transaction->customer_key . "|" . '1', $pp_sharedSecret) == $hmac) {
        //call started
        $data = array('status' => 1);
        $where = array('transaction_id' => $transaction_id);
        $wpdb->update($wpdb->prefix . "paypress_notification", $data, $where);
        echo "[accepted]";
        /*
         * Clean database
         * Remove expired codes
         */
        $query = "DELETE FROM $wpdb->prefix" . "paypress_notification WHERE time <= NOW()-7200 AND status <> 2";
        $wpdb->query($query);
    } else {
        echo "FALSE";
    }
} else {
//    get_header();
//    echo '<div id="content" class="narrowcolumn">
//    <h2 class="center">Error 404 - Not Found</h2>
//    </div>';
//    get_sidebar();
//    get_footer();
    echo "FALSE";
}
?>
