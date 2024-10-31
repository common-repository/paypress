<?php

require_once("../../../wp-load.php");

if (!empty($_POST['paypress_code']) && !empty($_POST['paypress_transid'])) {
    global $wpdb;

    $session_id = session_id();

    $transaction_id = $_POST['paypress_transid'];
    $code = $_POST['paypress_code'];

    $result = null;

    $query = "SELECT * FROM $wpdb->prefix" . "paypress_notification" . " WHERE
                transaction_id = '" . $transaction_id . "' AND
                    code = '" . $code . "' AND session_id = '" . $session_id . "' AND time >= NOW()-900;";
    $result = $wpdb->get_row($query);

    if ($result != null) {
        header("Content-type: text/xml");

        if ($result->paymenttype == 'ppc') {
            if ($result->status == 2) {
                $status = 2;
                $type = 'ppc';
            } else {
                $status = 0;
                $type = 'ppc';
            }
        }
    } else {
        $status = "No Database result";
        $type = "No type";
    }
} else {
    $status = "Invalid Parameters";
    $type = "No type";
}

echo <<<XML
<?xml version="1.0" encoding="utf-8"?>
<response>
    <status>{$status}</status>
    <type>{$type}</type>
</response>
XML;
?>