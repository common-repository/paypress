<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class PayPressPayment {

    private $merchantCode;
    private $apiKey;
    private $sharedSecret;
    private $profileId;
    private $paymentMethod;
    private $apiUrl = 'http://api.paypress.nl/api';
    private $jsonEnabled = false;

    public function __construct($merchantCode, $apiKey, $sharedSecret, $paymentMethod, $profileId = 0) {
        $this->merchantCode = $merchantCode;
        $this->apiKey = $apiKey;
        $this->sharedSecret = $sharedSecret;
        $this->profileId = $profileId;
        $this->paymentMethod = $paymentMethod;
        $this->jsonEnabled = function_exists('json_decode');
    }
    
    public function getPaymentCode() {
        if($this->jsonEnabled) {
            $url = $this->apiUrl . '.json';
        } else {
            $url = $this->apiUrl . '.xml';
        }
        
        $url .= "?type=retrieve-code&method=" . $this->paymentMethod . "&merchant_code=" . $this->merchantCode . "&ip=" . $_SERVER['REMOTE_ADDR'] . "&request_uri=" . $_SERVER['REQUEST_URI'];
        
        if($this->profileId > 0) {
            $url .= "&profile_id=" . $this->profileId;
        }
        
        $answer = file_get_contents($url);

        if($this->jsonEnabled) {
            $answer = (object) json_decode($answer);
        } else {
            $answer = simplexml_load_string($answer);
        }
        
        if($this->apiKey == $answer->api_key) {
            return $answer;
        } else {
            return (object) array();
        }
        
    }


}

?>
