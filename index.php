<?php

    // 3DS v2 form documentation here:
    // https://developer-eu.elavon.com/docs/opayo/3d-secure-authentication
    
    // Test card details
    // https://www.opayo.co.uk/support/15/36/test-card-details-for-your-test-transactions#:~:text=Card%20Details%20%20%20Card%20%20%20Card,%20%20E%20%2016%20more%20rows%20
    // https://www.elavon.co.uk/resource-center/help-with-your-solutions/opayo/getting-started/testing-your-account/test-your-opayo-integration.html#step3

    // Handy post for 3DS browser data
    // https://www.mikesimagination.net/journal/jul-19/strong-customer-authentication-sagepay-direct-v400

/**
## Pi drop-in script

https://sandbox.opayo.eu.elavon.com/api/v1/js/sagepay.js
https://live.opayo.eu.elavon.com/api/v1/js/sagepay.js


## Pi session keys
https://sandbox.opayo.eu.elavon.com/api/v1/merchant-session-keys
https://live.opayo.eu.elavon.com/api/v1/merchant-session-keys


## PI 3D Secure Transaction
Submit the transaction request
Submit your transaction registration POST to:
https://sandbox.opayo.eu.elavon.com/api/v1/transactions
For live transactions, submit your transaction registration POST to:
https://live.opayo.eu.elavon.com/api/v1/transactions


## PI Opayo 3D Secure callback API
For test transactions, submit your 3D Secure authentication result to:
https://sandbox.opayo.eu.elavon.com/api/v1/transactions/{transactionId}/3d-secure-challenge
For live transactions, submit your 3D Secure authentication result to:
https://live.opayo.eu.elavon.com/api/v1/transactions/{transactionId}/3d-secure-challenge
 */

if (!file_exists('settings.php')) {
    die ('Payment gateway is not configured.');
}
require_once 'settings.php';
require_once 'sagepayconnector.class.php';

// Prevents JavaScript XSS attacks aimed at stealing the session ID
ini_set('session.cookie_httponly', 1);
// Prevents passing the session ID through URLs
ini_set('session.use_only_cookies', 1);
// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 1);

session_start();

define('ACTION_START', 0);
define('ACTION_CHALLENGE', 1);
define('ACTION_FALLBACK',2);
define('ACTION_SUCCESS', 254);
define('ACTION_FAILED', 255);

/**
 * Write the 3DS session data to a text file for processing in an iFrame
 * @param string $url
 * @param string $creq
 * @param string $session
 */
function writeTransactionFile($action, $url, $creq, $session)
{
    $fh = fopen('paymentkey.' . $session . '.dat', 'w');
    if ($fh) {
        $data = new stdClass();
        $data->action = $action;
        $data->url = $url;
        $data->creq = $creq;
        $data->session = $session;
        
        fwrite($fh, json_encode($data));
//        fwrite($fh, $action . PHP_EOL);
//        fwrite($fh, $url . PHP_EOL);
//        fwrite($fh, $creq . PHP_EOL);
//        fwrite($fh, $session . PHP_EOL);
        fclose($fh);
    } else {
        die ('ERROR: Unable to write to key store. Please contact Real Training for support.');
    }
}

/**
 * Report that the transaction has successfully completed to the screen.
 * This immediately ends the script.
 */
function successfulTransaction()
{
    echo 'Your payment has been successfully authorised. Thank you.';
    echo '</div></body></html>';
    exit;
}

/**
 * Report that the transaction has failed to the screen and report the error.
 * This immediately ends the script.
 * @global stdClass $errors
 * @global stdClass $txfail
 */
function failedTransaction()
{
    global $errors;
    global $txfail;
    
    echo '<div class="error danger">';
    echo '<h3>There was a problem with your payment</h3>';
    foreach ($errors as $error) {
        echo "<p>$error->description</p>";
    }
    if (isset($txfail)) {
        echo "<p>$txfail->description</p>";
    }
    echo '</div></body></html>';
    exit;
}
/**
 * Handle the 3D Secure fallback request
 * @global SagePayConnector $sagepay
 * @global int $action Status of the action to trigger
 */
function manage3DSResponse()
{
    global $sagepay, $action;
    if (isset($_POST['MD'])) {
        $session = trim($_POST['MD']);
        $finalresponse = $sagepay->get3DAuth($session, $_POST['PaRes']);
        if ( 'Authenticated' === $finalresponse->status ) {
            $action = ACTION_SUCCESS;
        } else {
            $action = ACTION_FAILED;
        }
    } elseif (isset($_POST['cres'])) {
        // Full 3D secure response
        $response = $_POST['cres'];
        $txID = (base64_decode($_POST['threeDSSessionData']));
        $finalresponse = $sagepay->get3DAuthCallback( $txID, $response);
        
         if ( '0000' === $finalresponse->statusCode ) {
            $action = ACTION_SUCCESS;
        } else {
            $action = ACTION_FAILED;
        }
    }
}

$sagepay = new SagePayConnector( $vendorname, $sessionkey );
$sagepay->setMode( $mode );

$cardresponse = new stdClass();
$errors = array();
$success = null;
$action = ACTION_START;

if (isset($_POST['PaRes']) && isset($_POST['MD'])) {
    manage3DSResponse();
} elseif (isset($_POST['cres']) && isset($_POST['threeDSSessionData'])) {
    manage3DSResponse();
} else {
    $cardidentifier = filter_input( INPUT_POST, 'card-identifier' );    
}

if ( isset($cardidentifier) ) {
    
    // Build 3D Secure Object
    $scaObj = new stdClass();
    if (isset($_POST['BrowserJavascriptEnabled'])) {
        $scaObj->browserJavascriptEnabled = filter_var(1,FILTER_VALIDATE_BOOLEAN);
        $scaObj->browserJavaEnabled = filter_var($_POST['BrowserJavaEnabled'],FILTER_VALIDATE_BOOLEAN);
        $scaObj->browserColorDepth = $_POST['BrowserColorDepth'];
        $scaObj->browserScreenHeight = $_POST['BrowserScreenHeight'];
        $scaObj->browserScreenWidth = $_POST['BrowserScreenWidth'];
        $scaObj->browserTZ = $_POST['BrowserTZ'];
        $scaObj->browserLanguage = $_POST['BrowserLanguage'];
} else {
        $scaObj->browserJavascriptEnabled = 0;
        $scaObj->browserLanguage = 'en-GB';
}

$scaObj->browserAcceptHeader = !empty($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : 'text/html, application/xhtml+xml, application/xml;q=0.9, */*;q=0.8';
$scaObj->browserUserAgent = $_SERVER['HTTP_USER_AGENT'];
$scaObj->notificationURL = $termurl;
$scaObj->browserIP = $_SERVER['REMOTE_ADDR'];
$scaObj->transType = 'GoodsAndServicePurchase';
$scaObj->challengeWindowSize = 'ExtraLarge';
// End 3DS object
    
    $payment_options = new stdClass();
//    $payment_options->apply3DSecure = 'Force';
//    $payment_optinos->applyAvsCvcCheck = 'Force';
    $payment_options->transactionType = 'Payment';
    $payment_options->paymentMethod = new stdClass();
    $payment_options->paymentMethod->card = new stdClass();
//    $payment_options->paymentMethod->card->merchantSessionKey = getMerchantKey( $sessionkey )->merchantSessionKey;
    $payment_options->paymentMethod->card->merchantSessionKey = filter_input ( INPUT_POST, 'sessionKey' );
    $payment_options->paymentMethod->card->cardIdentifier = $cardidentifier;
//    $payment_options->paymentMethod->card->save = false;
    $payment_options->vendorTxCode = filter_input( INPUT_POST, 'invoiceNumber' ) . '-' . date('Y-m-d-H-i');
    $payment_options->amount = filter_input( INPUT_POST, 'invoiceValue' ) * 100;
    $payment_options->description = 'Real Training course booking';
    $payment_options->currency = 'GBP';
    $payment_options->customerFirstName = filter_input( INPUT_POST, 'customerFirstName' );
    $payment_options->customerLastName = filter_input( INPUT_POST, 'customerLastName' );
    $payment_options->billingAddress = new stdClass();
    $payment_options->billingAddress->address1 = filter_input( INPUT_POST, 'payeeAdd1' );
    $payment_options->billingAddress->address2 = filter_input( INPUT_POST, 'payeeAdd2' );
    $payment_options->billingAddress->address3 = filter_input( INPUT_POST, 'payeeAdd3' );
    $payment_options->billingAddress->city = filter_input( INPUT_POST, 'payeeCity' );
    $postcode = filter_input( INPUT_POST, 'payeePostcode' );
    if ( '' == $postcode ) {
        $postcode = "12345";
    }
    $payment_options->billingAddress->postalCode = $postcode;
    $state = filter_input( INPUT_POST, 'payeeState' );
    if ('' == $state) {
        $payment_options->billingState = $state;
    }
    $payment_options->billingAddress->country = filter_input( INPUT_POST, 'payeeCountry' );
    $payment_options->entryMethod = 'Ecommerce';
    $payment_options->strongCustomerAuthentication = $scaObj;
    
    $cardresponse = $sagepay->sendTransaction( json_encode( $payment_options ) );
    
    if ($cardresponse->http_response > 199 && $cardresponse->http_response < 300) {
        if ( property_exists($cardresponse, 'statusCode') && "2021" === $cardresponse->statusCode ) {
            // Challenge authentication
            $action = ACTION_CHALLENGE;
        } elseif ( property_exists($cardresponse, 'statusCode') && "2007" === $cardresponse->statusCode ) {
            // Fallback outcome
            $action = ACTION_FALLBACK;

        } elseif ( property_exists($cardresponse, 'statusCode') && "0000" !== $cardresponse->statusCode) {
            // We're still not successful. There's something wrong with the transaction
            $txfail = new stdClass();
            $txfail->description = $cardresponse->statusDetail;
            $txfail->code = $cardresponse->statusCode;
            $errors[] = $txfail;
            $action = ACTION_FAILED;
        } else {
            // We've been successful
            // We should probably record that TX data for backup purposes somewhere.
            // Also, show that it's been successful and remove the form.
            $success = true;
            $action = ACTION_SUCCESS;
        }
    } else {
        if ( property_exists( $cardresponse, 'errors')) {
            $errors = $cardresponse->errors;
        }
        $error = new stdClass();
        if (isset($cardresponse->statusCode)) {
            $error->description = "$cardresponse->statusCode: $cardresponse->statusDetail";
        }
        elseif (isset($cardresponse->description)) {
            $error->description = "$cardresponse->code: $cardresponse->description";
        }
        $errors[] = $error;
    }
}

$merchantkey = $sagepay->getMerchantKey();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Real Training payment gateway</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/bootstrap.min.css">
        <link rel="stylesheet" href="css/font-awesome.min.css">
        <script src="<?php echo $sagepay->getJSUrl(); ?>"></script>

    </head>
    <body>

<div class="container">
    <?php
    if (ACTION_FALLBACK !== $action) {
    ?>
    <h1><img alt="Real Training" src="data:image/png;base64,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"> payment gateway</h1>
    <?php } ?>
    
    <?php if ( ACTION_SUCCESS === $action ) {
        successfulTransaction();
    }
    
    if ( ACTION_FALLBACK === $action) {
        $_SESSION['cxID'] = $cardresponse->transactionId;
        writeTransactionFile($action, $cardresponse->acsUrl, $cardresponse->paReq, $cardresponse->transactionId);
        echo "<iframe src=\"3dsform.php\" name=\"3Diframe\" width=600 height=400></iframe>";
        echo '</div></body>';
        exit;
    }
    if ( ACTION_CHALLENGE === $action ) {
        $_SESSION['cxID'] = $cardresponse->transactionId;
        writeTransactionFile($action, $cardresponse->acsUrl, trim($cardresponse->cReq), $cardresponse->transactionId);
        echo "<iframe src=\"3dsform.php\" name=\"3Diframe\" width=600 height=400></iframe>";
        echo '</div></body>';
        exit;
    }
    if ( count( $errors ) || ACTION_FAILED === $action ) {
        failedTransaction();
    }

    ?>
    <p class="page-description">Please use this page to pay for your invoice by card. We require the invoice number and the total value you are paying (including VAT).</p>
        <div class="row payment-form">
            <div class="col-xs-12 col-md-6">
                <form role="form" id="payment-form" method="POST">
                    <input type="hidden" name="sessionKey" value="<?php echo $merchantkey->merchantSessionKey;?>">

                <!-- CREDIT CARD FORM STARTS HERE -->
                <div class="panel panel-default credit-card-box">
                    <div class="panel-heading" >
                        <div class="row display-tr" >
                            <h3 class="panel-title display-td" >Invoice Details</h3>
                            <div class="display-td" >                            
                            </div>
                        </div>                    
                    </div>
                    <div class="panel-body">                    
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="invoiceNumber">Invoice number</label>
                                        <input type="text" class="form-control" name="invoiceNumber" required />
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="invoiceValue">Invoice value (£)</label>
                                        <input type="number" class="form-control" name="invoiceValue" required placeholder="eg. 1234.56" step="0.01" min=""0.01" />
                                        Price <em>including</em> VAT
                                    </div>
                                </div>                        
                            </div>
                    </div>
                    <div class="panel-heading" >
                        <div class="row display-tr" >
                            <h3 class="panel-title display-td" >Cardholder Details</h3>
                            <div class="display-td" >                            
                                <!--<img class="img-responsive pull-right" src="http://i76.imgup.net/accepted_c22e0.png">-->
                            </div>
                        </div>                    
                    </div>
                    <div class="panel-body">                    
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="customerFirstName">First name(s)</label>
                                        <input type="text" class="form-control" name="customerFirstName" value="" required>
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="customerLastName">Surname</label>
                                        <input type="text" class="form-control" name="customerLastName" value="" required>
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeAdd1">Address 1</label>
                                        <input type="text" class="form-control" name="payeeAdd1" value="" maxlength="50" required>
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeAdd2">Address 2</label>
                                        <input type="text" class="form-control" name="payeeAdd2" value="" maxlength="50">
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeAdd3">Address 3</label>
                                        <input type="text" class="form-control" name="payeeAdd3" value="" maxlength="50">
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeCity">City</label>
                                        <input type="text" class="form-control" name="payeeCity"  value="" required >
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeePostcode">Postcode</label>
                                        <input type="text" class="form-control" name="payeePostcode" value="" required >
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeCountry">Country</label>
                                        <select id="countries" class="form-control payeeCountry" name="payeeCountry" required >
                                            <option value="GB">United Kingdom</option>
                                            <option value="AF">Afghanistan</option>
                                            <option value="AX">Aland Islands</option>
                                            <option value="AL">Albania</option>
                                            <option value="DZ">Algeria</option>
                                            <option value="AS">American Samoa</option>
                                            <option value="AD">Andorra</option>
                                            <option value="AO">Angola</option>
                                            <option value="AI">Anguilla</option>
                                            <option value="AQ">Antarctica</option>
                                            <option value="AG">Antigua and Barbuda</option>
                                            <option value="AR">Argentina</option>
                                            <option value="AM">Armenia</option>
                                            <option value="AW">Aruba</option>
                                            <option value="AU">Australia</option>
                                            <option value="AT">Austria</option>
                                            <option value="AZ">Azerbaijan</option>
                                            <option value="BS">Bahamas</option>
                                            <option value="BH">Bahrain</option>
                                            <option value="BD">Bangladesh</option>
                                            <option value="BB">Barbados</option>
                                            <option value="BY">Belarus</option>
                                            <option value="BE">Belgium</option>
                                            <option value="BZ">Belize</option>
                                            <option value="BJ">Benin</option>
                                            <option value="BM">Bermuda</option>
                                            <option value="BT">Bhutan</option>
                                            <option value="BO">Bolivia</option>
                                            <option value="BA">Bosnia and Herzegovina</option>
                                            <option value="BW">Botswana</option>
                                            <option value="BV">Bouvet Island</option>
                                            <option value="BR">Brazil</option>
                                            <option value="IO">British Indian Ocean Territory</option>
                                            <option value="BN">Brunei Darussalam</option>
                                            <option value="BG">Bulgaria</option>
                                            <option value="BF">Burkina Faso</option>
                                            <option value="BI">Burundi</option>
                                            <option value="KH">Cambodia</option>
                                            <option value="CM">Cameroon</option>
                                            <option value="CA">Canada</option>
                                            <option value="CV">Cape Verde</option>
                                            <option value="KY">Cayman Islands</option>
                                            <option value="CF">Central African Republic</option>
                                            <option value="TD">Chad</option>
                                            <option value="CL">Chile</option>
                                            <option value="CN">China</option>
                                            <option value="CX">Christmas Island</option>
                                            <option value="CC">Cocos (Keeling) Islands</option>
                                            <option value="CO">Colombia</option>
                                            <option value="KM">Comoros</option>
                                            <option value="CG">Congo</option>
                                            <option value="CD">Congo, The Democratic Republic of The</option>
                                            <option value="CK">Cook Islands</option>
                                            <option value="CR">Costa Rica</option>
                                            <option value="CI">Cote D'ivoire</option>
                                            <option value="HR">Croatia</option>
                                            <option value="CU">Cuba</option>
                                            <option value="CY">Cyprus</option>
                                            <option value="CZ">Czechia</option>
                                            <option value="DK">Denmark</option>
                                            <option value="DJ">Djibouti</option>
                                            <option value="DM">Dominica</option>
                                            <option value="DO">Dominican Republic</option>
                                            <option value="EC">Ecuador</option>
                                            <option value="EG">Egypt</option>
                                            <option value="SV">El Salvador</option>
                                            <option value="GQ">Equatorial Guinea</option>
                                            <option value="ER">Eritrea</option>
                                            <option value="EE">Estonia</option>
                                            <option value="ET">Ethiopia</option>
                                            <option value="FK">Falkland Islands (Malvinas)</option>
                                            <option value="FO">Faroe Islands</option>
                                            <option value="FJ">Fiji</option>
                                            <option value="FI">Finland</option>
                                            <option value="FR">France</option>
                                            <option value="GF">French Guiana</option>
                                            <option value="PF">French Polynesia</option>
                                            <option value="TF">French Southern Territories</option>
                                            <option value="GA">Gabon</option>
                                            <option value="GM">Gambia</option>
                                            <option value="GE">Georgia</option>
                                            <option value="DE">Germany</option>
                                            <option value="GH">Ghana</option>
                                            <option value="GI">Gibraltar</option>
                                            <option value="GR">Greece</option>
                                            <option value="GL">Greenland</option>
                                            <option value="GD">Grenada</option>
                                            <option value="GP">Guadeloupe</option>
                                            <option value="GU">Guam</option>
                                            <option value="GT">Guatemala</option>
                                            <option value="GG">Guernsey</option>
                                            <option value="GN">Guinea</option>
                                            <option value="GW">Guinea-bissau</option>
                                            <option value="GY">Guyana</option>
                                            <option value="HT">Haiti</option>
                                            <option value="HM">Heard Island and Mcdonald Islands</option>
                                            <option value="VA">Holy See (Vatican City State)</option>
                                            <option value="HN">Honduras</option>
                                            <option value="HK">Hong Kong</option>
                                            <option value="HU">Hungary</option>
                                            <option value="IS">Iceland</option>
                                            <option value="IN">India</option>
                                            <option value="ID">Indonesia</option>
                                            <option value="IR">Iran, Islamic Republic of</option>
                                            <option value="IQ">Iraq</option>
                                            <option value="IE">Ireland</option>
                                            <option value="IM">Isle of Man</option>
                                            <option value="IL">Israel</option>
                                            <option value="IT">Italy</option>
                                            <option value="JM">Jamaica</option>
                                            <option value="JP">Japan</option>
                                            <option value="JE">Jersey</option>
                                            <option value="JO">Jordan</option>
                                            <option value="KZ">Kazakhstan</option>
                                            <option value="KE">Kenya</option>
                                            <option value="KI">Kiribati</option>
                                            <option value="KP">Korea, Democratic People's Republic of</option>
                                            <option value="KR">Korea, Republic of</option>
                                            <option value="KW">Kuwait</option>
                                            <option value="KG">Kyrgyzstan</option>
                                            <option value="LA">Lao People's Democratic Republic</option>
                                            <option value="LV">Latvia</option>
                                            <option value="LB">Lebanon</option>
                                            <option value="LS">Lesotho</option>
                                            <option value="LR">Liberia</option>
                                            <option value="LY">Libyan Arab Jamahiriya</option>
                                            <option value="LI">Liechtenstein</option>
                                            <option value="LT">Lithuania</option>
                                            <option value="LU">Luxembourg</option>
                                            <option value="MO">Macao</option>
                                            <option value="MK">Macedonia, The Former Yugoslav Republic of</option>
                                            <option value="MG">Madagascar</option>
                                            <option value="MW">Malawi</option>
                                            <option value="MY">Malaysia</option>
                                            <option value="MV">Maldives</option>
                                            <option value="ML">Mali</option>
                                            <option value="MT">Malta</option>
                                            <option value="MH">Marshall Islands</option>
                                            <option value="MQ">Martinique</option>
                                            <option value="MR">Mauritania</option>
                                            <option value="MU">Mauritius</option>
                                            <option value="YT">Mayotte</option>
                                            <option value="MX">Mexico</option>
                                            <option value="FM">Micronesia, Federated States of</option>
                                            <option value="MD">Moldova, Republic of</option>
                                            <option value="MC">Monaco</option>
                                            <option value="MN">Mongolia</option>
                                            <option value="ME">Montenegro</option>
                                            <option value="MS">Montserrat</option>
                                            <option value="MA">Morocco</option>
                                            <option value="MZ">Mozambique</option>
                                            <option value="MM">Myanmar</option>
                                            <option value="NA">Namibia</option>
                                            <option value="NR">Nauru</option>
                                            <option value="NP">Nepal</option>
                                            <option value="NL">Netherlands</option>
                                            <option value="AN">Netherlands Antilles</option>
                                            <option value="NC">New Caledonia</option>
                                            <option value="NZ">New Zealand</option>
                                            <option value="NI">Nicaragua</option>
                                            <option value="NE">Niger</option>
                                            <option value="NG">Nigeria</option>
                                            <option value="NU">Niue</option>
                                            <option value="NF">Norfolk Island</option>
                                            <option value="MP">Northern Mariana Islands</option>
                                            <option value="NO">Norway</option>
                                            <option value="OM">Oman</option>
                                            <option value="PK">Pakistan</option>
                                            <option value="PW">Palau</option>
                                            <option value="PS">Palestinian Territory, Occupied</option>
                                            <option value="PA">Panama</option>
                                            <option value="PG">Papua New Guinea</option>
                                            <option value="PY">Paraguay</option>
                                            <option value="PE">Peru</option>
                                            <option value="PH">Philippines</option>
                                            <option value="PN">Pitcairn</option>
                                            <option value="PL">Poland</option>
                                            <option value="PT">Portugal</option>
                                            <option value="PR">Puerto Rico</option>
                                            <option value="QA">Qatar</option>
                                            <option value="RE">Reunion</option>
                                            <option value="RO">Romania</option>
                                            <option value="RU">Russian Federation</option>
                                            <option value="RW">Rwanda</option>
                                            <option value="SH">Saint Helena</option>
                                            <option value="KN">Saint Kitts and Nevis</option>
                                            <option value="LC">Saint Lucia</option>
                                            <option value="PM">Saint Pierre and Miquelon</option>
                                            <option value="VC">Saint Vincent and The Grenadines</option>
                                            <option value="WS">Samoa</option>
                                            <option value="SM">San Marino</option>
                                            <option value="ST">Sao Tome and Principe</option>
                                            <option value="SA">Saudi Arabia</option>
                                            <option value="SN">Senegal</option>
                                            <option value="RS">Serbia</option>
                                            <option value="SC">Seychelles</option>
                                            <option value="SL">Sierra Leone</option>
                                            <option value="SG">Singapore</option>
                                            <option value="SK">Slovakia</option>
                                            <option value="SI">Slovenia</option>
                                            <option value="SB">Solomon Islands</option>
                                            <option value="SO">Somalia</option>
                                            <option value="ZA">South Africa</option>
                                            <option value="GS">South Georgia and The South Sandwich Islands</option>
                                            <option value="ES">Spain</option>
                                            <option value="LK">Sri Lanka</option>
                                            <option value="SD">Sudan</option>
                                            <option value="SR">Suriname</option>
                                            <option value="SJ">Svalbard and Jan Mayen</option>
                                            <option value="SZ">Swaziland</option>
                                            <option value="SE">Sweden</option>
                                            <option value="CH">Switzerland</option>
                                            <option value="SY">Syrian Arab Republic</option>
                                            <option value="TW">Taiwan, Province of China</option>
                                            <option value="TJ">Tajikistan</option>
                                            <option value="TZ">Tanzania, United Republic of</option>
                                            <option value="TH">Thailand</option>
                                            <option value="TL">Timor-leste</option>
                                            <option value="TG">Togo</option>
                                            <option value="TK">Tokelau</option>
                                            <option value="TO">Tonga</option>
                                            <option value="TT">Trinidad and Tobago</option>
                                            <option value="TN">Tunisia</option>
                                            <option value="TR">Turkey</option>
                                            <option value="TM">Turkmenistan</option>
                                            <option value="TC">Turks and Caicos Islands</option>
                                            <option value="TV">Tuvalu</option>
                                            <option value="UG">Uganda</option>
                                            <option value="UA">Ukraine</option>
                                            <option value="AE">United Arab Emirates</option>
                                            <option value="US">United States</option>
                                            <option value="UM">United States Minor Outlying Islands</option>
                                            <option value="UY">Uruguay</option>
                                            <option value="UZ">Uzbekistan</option>
                                            <option value="VU">Vanuatu</option>
                                            <option value="VE">Venezuela</option>
                                            <option value="VN">Viet Nam</option>
                                            <option value="VG">Virgin Islands, British</option>
                                            <option value="VI">Virgin Islands, U.S.</option>
                                            <option value="WF">Wallis and Futuna</option>
                                            <option value="EH">Western Sahara</option>
                                            <option value="YE">Yemen</option>
                                            <option value="ZM">Zambia</option>
                                            <option value="ZW">Zimbabwe</option>
                                        </select>
                                        <!--<input type="text" value="GB" />-->
                                    </div>
                                </div>                        
                            </div>
                            <div class="row state-row" style="display:none;">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeCity">State</label>
                                        <select type="text" class="form-control payeeState" name="payeeState" >
                                            <option value="">Please select a state</option>
                                            <option value="AL">Alabama</option>
                                            <option value="AK">Alaska</option>
                                            <option value="AZ">Arizona</option>
                                            <option value="AR">Arkansas</option>
                                            <option value="CA">California</option>
                                            <option value="CO">Colorado</option>
                                            <option value="CT">Connecticut</option>
                                            <option value="DE">Delaware</option>
                                            <option value="FL">Florida</option>
                                            <option value="GA">Georgia</option>
                                            <option value="HI">Hawaii</option>
                                            <option value="ID">Idaho</option>
                                            <option value="IL">Illinois</option>
                                            <option value="IN">Indiana</option>
                                            <option value="IA">Iowa</option>
                                            <option value="KS">Kansas</option>
                                            <option value="KY">Kentucky</option>
                                            <option value="LA">Louisiana</option>
                                            <option value="ME">Maine</option>
                                            <option value="MD">Maryland</option>
                                            <option value="MA">Massachusetts</option>
                                            <option value="MI">Michigan</option>
                                            <option value="MN">Minnesota</option>
                                            <option value="MS">Mississippi</option>
                                            <option value="MO">Missouri</option>
                                            <option value="MT">Montana</option>
                                            <option value="NE">Nebraska</option>
                                            <option value="NV">Nevada</option>
                                            <option value="NH">New Hampshire</option>
                                            <option value="NJ">New Jersey</option>
                                            <option value="NM">New Mexico</option>
                                            <option value="NY">New York</option>
                                            <option value="NC">North Carolina</option>
                                            <option value="ND">North Dakota</option>
                                            <option value="OH">Ohio</option>
                                            <option value="OK">Oklahoma</option>
                                            <option value="OR">Oregon</option>
                                            <option value="PA">Pennsylvania</option>
                                            <option value="RI">Rhode Island</option>
                                            <option value="SC">South Carolina</option>
                                            <option value="SD">South Dakota</option>
                                            <option value="TN">Tennessee</option>
                                            <option value="TX">Texas</option>
                                            <option value="UT">Utah</option>
                                            <option value="VT">Vermont</option>
                                            <option value="VA">Virginia</option>
                                            <option value="WA">Washington</option>
                                            <option value="WV">West Virginia</option>
                                            <option value="WI">Wisconsin</option>
                                            <option value="WY">Wyoming</option>
                                        </select>
                                    </div>
                                </div>                        
                            </div>

                    </div>
                    <div class="panel-heading" >
                        <div class="row display-tr" >
                            <h3 class="panel-title display-td" >Payment Details</h3>
                            <div class="display-td" >                            
                                <img class="img-responsive pull-right" src="img/accepted_cards.png">
                            </div>
                        </div>                    
                    </div>
                    <div class="panel-body">
                        <div id="sp-container"></div>
                        <div id="submit-container">
                            <input class="btn btn-primary" type="submit" value="Make payment">
                        </div>      
                    </div>
                </div>            
                <!-- CREDIT CARD FORM ENDS HERE -->

                </form>

            </div>            

        </div>
    </div>

    <script src="js/bootstrap.min.js"></script> 
    <script>
       (function() {
           // State checking
           var state = document.getElementsByClassName('payeeState');
           var stateRow = document.getElementsByClassName('state-row');
           var countrySelect = document.getElementsByClassName('payeeCountry');
           countrySelect[0].addEventListener("change", function(e) {
               if ('US' === e.srcElement.value) {
                   state[0].setAttribute('required', '');
                   stateRow[0].removeAttribute('style');
               } else {
                   state[0].removeAttribute('required');
                   state[0].value = '';
                   stateRow[0].setAttribute('style', 'display: none;');
               }
           });

            // We have to check that SagePay has loaded correctly to render the payment form. Otherwise we'll remove the whole form.
            if (typeof sagepayCheckout === "function") { 
                sagepayCheckout ({ merchantSessionKey: '<?php echo $merchantkey->merchantSessionKey;?>'}).form('#payment-form');
                
                var paymentform = document.getElementById('payment-form');
                        //has javascript
                        var a = document.createElement("INPUT");
                        a.setAttribute("type", "hidden");
                        a.setAttribute("value", "1");
                        a.setAttribute("name", "BrowserJavascriptEnabled");
                        paymentform.appendChild(a);

                //java?
                        var b = document.createElement("INPUT");
                        b.setAttribute("type", "hidden");
                        b.setAttribute("name", "BrowserJavaEnabled");
                        if(navigator.javaEnabled()){
                                b.setAttribute("value","1" );
                        }
                        else{
                                b.setAttribute("value", "0");
                        }
                        paymentform.appendChild(b);

                //BrowserColorDepth
                        var c = document.createElement("INPUT");
                        c.setAttribute("type", "hidden");
                        c.setAttribute("value", window.screen.colorDepth);
                        c.setAttribute("name", "BrowserColorDepth");
                        paymentform.appendChild(c);

                //BrowserScreenHeight
                        var d = document.createElement("INPUT");
                        d.setAttribute("type", "hidden");
                        d.setAttribute("value", window.screen.height);
                        d.setAttribute("name", "BrowserScreenHeight");
                        paymentform.appendChild(d);

                //BrowserScreenWidth
                        var e = document.createElement("INPUT");
                        e.setAttribute("type", "hidden");
                        e.setAttribute("value", window.screen.width);
                        e.setAttribute("name", "BrowserScreenWidth");
                        paymentform.appendChild(e);

                //BrowserTZ
                        var tzoffset = new Date().getTimezoneOffset();
                        var f = document.createElement("INPUT");
                        f.setAttribute("type", "hidden");
                        f.setAttribute("value", tzoffset);
                        f.setAttribute("name", "BrowserTZ");
                        paymentform.appendChild(f);

                //BrowserLanguage
                var g = document.createElement("INPUT");
                        g.setAttribute("type", "hidden");
                        g.setAttribute("value", window.navigator.language);
                        g.setAttribute("name", "BrowserLanguage");
                        paymentform.appendChild(g);

            } else {
                // SagePay hasn't loaded
                // We need to display an error on the form so that it's clear it cannot be completed.
                var pageDescription = document.getElementsByClassName('page-description');
                pageDescription[0].innerHTML =  "We're sorry, but we cannot take payments at this time. Please try again later.";
                var paymentForm = document.getElementsByClassName('payment-form');
                paymentForm[0].parentNode.removeChild(paymentForm[0]);
            }
            
        })();
    </script>
    </body>
</html>
