<?php

/**
 * Manages the Pi integration with Opayo.
 * Allows the form to create a request for a session key and authorise
 * payments. Pi integration ensures that card details are passed
 * directly to the payment vendor and no details are stored on the server.
 */
class SagePayConnector {
    private $sessionkey;
    private $vendor;
    private $mode = 'test';
    /**
     * The static class reference to the Opayo endpoints
     * @var string
     */
    private $paymentAPI = null;
    
    public function __construct( $vendor = null, $sessionkey = null )
    {
        $this->setSessionKey( $sessionkey );
        $this->vendor = $vendor;
    }
    
    public function setSessionKey( $sessionkey )
    {
        $this->sessionkey = base64_encode( $sessionkey );
    }
    
    public function setVendor( $vendor )
    {
        $this->vendor = $vendor;
    }

    public function setMode( $mode )
    {
        if ( 'test' === $mode ) {
            $this->paymentAPI = 'OpayoTest';
            return;
        }
        if ( 'live' === $mode ) {
            $this->paymentAPI = 'OpayoLive';
            return;
        }
        throw new Exception("The mode of the connector could not be set with $mode.");
    }
    
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     * Return the main Opayo API URL
     * @return string
     */    
    public function getAPIUrl()
    {
        $this->paymentAPI::getAPIUrl();
    }
    
    /**
     * Retrieve the Opayo 3D Secure callback URL
     * https://developer-eu.elavon.com/docs/opayo/3d-secure-authentication#step-3
     * @return string
     */
    public function get3DSUrl()
    {
        return $this->paymentAPI::getAPISecureCallbackURL();
    }
    
    /**
     * Get the Javascript URL for the API.
     * @return string
     */
    public function getJSUrl()
    {
        return $this->paymentAPI::getJSUrl();
    }
    
    public function getMerchantKey()
    {
        return $this->sageRequest( $this->paymentAPI::getAPIUrl() . 'merchant-session-keys' , '{ "vendorName": "' . $this->vendor . '" }' );
    }

    public function sendTransaction( $txdetails )
    {
        return $this->sageRequest( $this->paymentAPI::getAPIUrl() . 'transactions' , $txdetails );
    }
    
    /**
     * Get the 3D secure FALLBACK response from the provider
     * @param string $transactionID The ID of the transaction being authorised
     * @param string $paRes This is a Base64 encoded, encrypted message sent
     * back by the issuing bank to your TermURL at the end of the 3D Secure
     * authentication process
     * @return string JSON {"status": "authenticated"} on success
     */
    public function get3DAuth( $transactionID, $paRes )
    {
        $transactionurl = preg_replace('{\{transactionId\}}', $transactionID, $this->get3DSUrl()) . '/3d-secure';
        
        return $this->sageRequest( $transactionurl, '{"paRes":"' . $paRes . '"}');
    }
    
    /**
     * Get the 3D secure CHALLENGE response from the provider
     * @param string $transactionID The ID of the transaction being authorised
     * @param string $cres Challenge result - this is the authentication result.
     */
    public function get3DAuthCallback( $transactionID, $cres )
    {
        $transactionurl = preg_replace('{\{transactionId\}}', $transactionID, $this->get3DSUrl()) . '/3d-secure-challenge';
        $request = new stdClass();
        $request->cRes = $cres;
        
        return $this->sageRequest( $transactionurl, '{"cRes":"' . $cres . '"}' );
    }
    
    /**
     * Send a request to SagePay using curl
     * @param string $endpoint The URL endpoint for the request
     * @param string $data JSON structured data
     * @return string JSON response
     */
    private function sageRequest( $endpoint, $data )
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
            "Authorization: Basic {$this->sessionkey}",
            "Cache-Control: no-cache",
            "Content-Type: application/json"
         ),
        ));
        
       $response = curl_exec($curl);
       $err = curl_error($curl);

       curl_close($curl);
       $return = json_decode( $response );
       $return->http_response = curl_getinfo($curl, CURLINFO_HTTP_CODE);

       return $return;
    }
}

/**
 * Abstract class for use with the SagepayConnector
 * Manages the Opayo endpoints and returns the relevant endpoints.
 */
abstract class PaymentEndpoint {
    static protected $APIUrl = '';
    static protected $JSUrl = '';
    static protected $APISecureCallback = '';
    
    static public function getAPIUrl(): string
    {
        return static::$APIUrl;
    }
    
    static public function getJSUrl(): string
    {
        return static::$JSUrl;
    }
    
    static public function getAPISecureCallbackURL(): string
    {
        return static::$APISecureCallback;
    }
}

/**
 * Live - and active - API endpoints. Use this for production.
 */
class OpayoLive extends PaymentEndpoint {
    static protected $APIUrl = 'https://live.opayo.eu.elavon.com/api/v1/';
    static protected $JSUrl = 'https://live.opayo.eu.elavon.com/api/v1/js/sagepay.js';
    static protected $APISecureCallback = 'https://live.opayo.eu.elavon.com/api/v1/transactions/{transactionId}';
}

/**
 * Test - or sandbox - API endpoints. This is used for testing the Opayo
 * implementation.
 */
class OpayoTest extends PaymentEndpoint {
    static protected $APIUrl = 'https://sandbox.opayo.eu.elavon.com/api/v1/';
    static protected $JSUrl = 'https://sandbox.opayo.eu.elavon.com/api/v1/js/sagepay.js';
    static protected $APISecureCallback = 'https://sandbox.opayo.eu.elavon.com/api/v1/js/sagepay.js';
}
