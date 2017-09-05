<?php

class SagePayConnector {
    private $sessionkey;
    private $vendor;
    private $mode = 'test';
    private $apiurllive = 'https://pi-live.sagepay.com/api/v1/';
    private $apiurltest = 'https://pi-test.sagepay.com/api/v1/';
    private $jsurllive = 'https://pi-live.sagepay.com/api/v1/js/sagepay.js';
    private $jsurltest = 'https://pi-test.sagepay.com/api/v1/js/sagepay.js';
    
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

       return json_decode( $response );
    }
}