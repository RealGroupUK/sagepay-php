<?php
if (!file_exists('settings.php')) {
    die ('Payment gateway is not configured.');
}
require_once 'settings.php';
require_once 'sagepayconnector.class.php';

$sagepay = new SagePayConnector( $vendorname, $sessionkey );
$sagepay->setMode( $mode );

$cardresponse = new stdClass();
$errors = array();
$success = null;

$cardidentifier = filter_input( INPUT_POST, 'card-identifier' );

if ( $cardidentifier ) {
    
    $payment_options = new stdClass();
    $payment_options->transactionType = 'Payment';
    $payment_options->paymentMethod = new stdClass();
    $payment_options->paymentMethod->card = new stdClass();
//    $payment_options->paymentMethod->card->merchantSessionKey = getMerchantKey( $sessionkey )->merchantSessionKey;
    $payment_options->paymentMethod->card->merchantSessionKey = filter_input ( INPUT_POST, 'sessionKey' );
    $payment_options->paymentMethod->card->cardIdentifier = $cardidentifier;
//    $payment_options->paymentMethod->card->save = false;
    $payment_options->vendorTxCode = filter_input( INPUT_POST, 'invoiceNumber' );
    $payment_options->amount = filter_input( INPUT_POST, 'invoiceValue' ) * 100;
    $payment_options->description = 'Real Training course booking';
    $payment_options->currency = 'GBP';
    $payment_options->customerFirstName = filter_input( INPUT_POST, 'customerFirstName' );
    $payment_options->customerLastName = filter_input( INPUT_POST, 'customerLastName' );
    $payment_options->billingAddress = new stdClass();
    $payment_options->billingAddress->address1 = filter_input( INPUT_POST, 'payeeAdd1' );
    $payment_options->billingAddress->address2 = filter_input( INPUT_POST, 'payeeAdd2' );
    $payment_options->billingAddress->city = filter_input( INPUT_POST, 'payeeCity' );
    $payment_options->billingAddress->postalCode = filter_input( INPUT_POST, 'payeePostcode' );
    $payment_options->billingAddress->country = filter_input( INPUT_POST, 'payeeCountry' );
    $payment_options->entryMethod = 'Ecommerce';
    
    $cardresponse = $sagepay->sendTransaction( json_encode( $payment_options ) );
    
    if ( property_exists( $cardresponse, 'errors' ) ) {
        $errors = $cardresponse->errors;
    } elseif ( "0000" !== $cardresponse->statusCode) {
        // We're still not successful. There's something wrong with the transaction
        $txfail = new stdClass();
        $txfail->description = $cardresponse->statusDetail;
        $txfail->code = $cardresponse->statusCode;
        $errors[] = $txfail;
    } else {
        // We've been successful
        // We should probably record that TX data for backup purposes somewhere.
        // Also, show that it's been successful and remove the form.
        $success = true;
    }
}

$merchantkey = $sagepay->getMerchantKey();

?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Real Training payment gateway</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/bootstrap.min.css">
        <link rel="stylesheet" href="css/font-awesome.min.css">
        <script src="<?php echo $sagepay->getJSUrl(); ?>"></script>

    </head>
    <body>

<div class="container">
    
    <h1><img alt="Real Training" src="data:image/png;base64,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"> payment gateway</h1>
    <?php if ( true === $success ) {
        echo 'Your payment has been successfully authorised. Thank you.';
        echo '</div></body>';
        exit;
    }
    ?>
    <p class="page-description">Please use this page to pay for your invoice by card. We require the invoice number and the total value you are paying (including VAT).</p>
    <?php
    if ( count( $errors ) ) {
        echo '<div class="error danger">';
        echo '<h3>There was a problem with your payment</h3>';
        foreach ($errors as $error) {
            echo "<p>$error->description</p>";
        }
        echo '</div>';
    }
    ?>
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
                                        <input type="number" class="form-control" name="invoiceValue" required placeholder="eg. 1234.56" step="0.01" />
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
                                        <input type="text" class="form-control" name="payeeAdd1" value=""  required>
                                    </div>
                                </div>                        
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="payeeAdd2">Address 2</label>
                                        <input type="text" class="form-control" name="payeeAdd2" value="" >
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
                                        <select id="countries" class="form-control" name="payeeCountry" required >
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

            // We have to check that SagePay has loaded correctly to render the payment form. Otherwise we'll remove the whole form.
            if (typeof sagepayCheckout === "function") { 
                sagepayCheckout ({ merchantSessionKey: '<?php echo $merchantkey->merchantSessionKey;?>'}).form('#payment-form');
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