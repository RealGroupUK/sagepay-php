<?php

class SagePayConnector {
    private $sessionkey;
    private $vendor;
    private $mode = 'test';
    private $apiurllive = 'https://pi-live.sagepay.com/api/v1/';
    private $apiurltest = 'https://pi-test.sagepay.com/api/v1/';
    private $jsurllive = 'https://pi-live.sagepay.com/api/v1/js/sagepay.js';
    private $jsurltest = 'https://pi-test.sagepay.com/api/v1/js/sagepay.js';
    private $apisecurecallbacklive = 'https://pi-live.sagepay.com/api/v1/transactions/{transactionId}';
    private $apisecurecallbacktest = 'https://pi-test.sagepay.com/api/v1/transactions/{transactionId}';
    
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
            $this->mode = 'test';
        }
        if ( 'live' === $mode ) {
            $this->mode = 'live';
        }
    }
    
    public function getMode()
    {
        return $this->mode;
    }
    
    public function getAPIUrl()
    {
        if ( 'live' === $this->mode ) {
            return $this->apiurllive;
        } else {
            return $this->apiurltest;
        }
    }
    
    /**
     * Retrieve the Opayo 3D Secure callback
     * https://developer-eu.elavon.com/docs/opayo/3d-secure-authentication#step-3
     * @return string
     */
    public function get3DSUrl()
    {
        if ( 'live' === $this->mode ) {
            return $this->apisecurecallbacklive;
        } else {
            return $this->apisecurecallbacktest;
        }
    }
    
    public function getJSUrl()
    {
        if ( 'live' === $this->mode ) {
            return $this->jsurllive;
        } else {
            return $this->jsurltest;
        }
    }
    
    public function getMerchantKey()
    {
        return $this->sageRequest( $this->getAPIUrl() . 'merchant-session-keys' , '{ "vendorName": "' . $this->vendor . '" }' );
    }

    public function sendTransaction( $txdetails )
    {
        return $this->sageRequest( $this->getAPIUrl() . 'transactions' , $txdetails );
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
