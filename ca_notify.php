<?php

//https://developer.paypal.com/docs/classic/ipn/ht_ipn/
// STEP 1: read POST data
// Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
// Instead, read raw POST data from the input stream.
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);


$myPost = array();
foreach ($raw_post_array as $keyval) {
  $keyval = explode ('=', $keyval);
  if (count($keyval) == 2)
    $myPost[$keyval[0]] = urldecode($keyval[1]);
}
// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
$req = 'cmd=_notify-validate';
if (function_exists('get_magic_quotes_gpc')) {
  $get_magic_quotes_exists = true;
}
foreach ($myPost as $key => $value) {
  if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
    $value = urlencode(stripslashes($value));
  } else {
     $value = urlencode($value);
  }
  $req .= "&$key=$value";
  if ($key == 'custom') {
    $custom = $value;
  }
  if ($key == 'test_ipn') {
    $testIpn = $value;
  }
  if ($key == 'payment_status') {
    $paymentStatus = $value;
  }
  if ($key == 'ipn_track_id') {
    $ipnTrackId = $value;
  }
}


// Step 2: POST IPN data back to PayPal to validate

if ($testIpn == '1') {
    $postBackUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
} else {
    $postBackUrl = 'https://www.paypal.com/cgi-bin/webscr';    
}


$ch = curl_init($postBackUrl);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
//http://php.net/manual/en/function.curl-setopt.php
//these are about verifying PayPal's certificate?
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
// In wamp-like environments that do not come bundled with root authority certificates,
// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set
// the directory path of the certificate as shown below:
// curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/cacert.pem');
if ( !($res = curl_exec($ch)) ) {
 
  curl_close($ch);
  exit;
}
curl_close($ch);



if (strcmp ($res, "VERIFIED") == 0) {
  // The IPN is verified, process it
  processCompletedOrder($custom, $paymentStatus, $ipnTrackId);   
  
} else if (strcmp ($res, "INVALID") == 0) {
  // IPN invalid, log for manual investigation
}

function processCompletedOrder($orderRef, $paymentStatus, $ipnTrackId) {
    include('ca_database.php');
    $db = new  ClientAreaDB();  
    $sessionIdOfOrder = $db->markOrderStatus($orderRef, $paymentStatus, $ipnTrackId);
    if ($sessionIdOfOrder !== null) {      
        session_id($sessionIdOfOrder);
        session_start();
        //TODO IF the ipn takes a while they could have started a new order and then we'll clear it..
        // not sure how else to do this.
        $_SESSION['basket'] = array();
    }


    
    //TODO 
    mail('justinwyllie@hotmail.co.uk', 'Confirmed Order on Web Site', 'The confirmed order ref is: ' . $orderRef);
}

?>