<?php

use Httpful\Http;
use Httpful\Request;

class Maksekeskus
{
    const AUTH_LEVEL_1 = 1;
    const AUTH_LEVEL_2 = 2;
    
    /**
     * @var str API base URL
     */
    private $apiUrl = 'https://api.maksekeskus.ee/v1';
    
    
    /**
     * @var str Shop ID
     */
    private $shopId;
    
    
    /**
     * @var str Publishable Key
     */
    private $publishableKey;
    
    
    /**
     * @var str Secret Key
     */
    private $secretKey;
    
    
    /**
     * Response object of the last API request
     *
     * @var obj
     */
    private $lastApiResponse;
    
    
    /**
     * API client constructor
     *
     * @param str $shopId Shop ID
     * @param str $publishableKey Publishable API Key, NULL if not provided
     * @param str $$secretKey Secret API Key, NULL if not provided
     *
     * @return void
     */
    public function __construct ($shopId, $publishableKey = NULL, $secretKey = NULL)
    {
        $this->setShopId($shopId);
        $this->setPublishableKey($publishableKey);
        $this->setSecretKey($secretKey);
    }
    
    
    /**
     * Set API base URL
     *
     * @param str $value
     *
     * @return void
     */
    public function setApiUrl ($value)
    {
        $this->apiUrl = $value;
    }
    
    
    /**
     * Get API base URL
     *
     * @return str
     */
    public function getApiUrl ()
    {
        return $this->shopId;
    }
    
    
    /**
     * Set Shop ID
     *
     * @param str $value
     *
     * @return void
     */
    public function setShopId ($value)
    {
        $this->shopId = $value;
    }
    
    
    /**
     * Get Shop ID
     *
     * @return str
     */
    public function getShopId ()
    {
        return $this->shopId;
    }
    
    
    /**
     * Set Publishable Key
     *
     * @param str $value
     *
     * @return void
     */
    public function setPublishableKey ($value)
    {
        $this->publishableKey = $value;
    }
    
    
    /**
     * Get Publishable Key
     *
     * @return str
     */
    public function getPublishableKey ()
    {
        return $this->publishableKey;
    }
    
    
    /**
     * Set Secret Key
     *
     * @param str $value
     *
     * @return void
     */
    public function setSecretKey ($value)
    {
        $this->secretKey = $value;
    }
    
    
    /**
     * Get Secret Key
     *
     * @return str
     */
    public function getSecretKey ()
    {
        return $this->secretKey;
    }
    
    
    public function getSignature ($amount, $currency, $reference = NULL)
    {
        $str = (string)$amount . (string)$currency . (string)$reference;
        
        return $this->createSignatureHash($str);
    }
    
    
    private function createSignatureHash ($string)
    {
        return strtoupper(hash('sha512', $string . $this->getSecretKey()));
    }
    
    
    public function explainJsonSignature ($json, $v1 = FALSE)
    {
        $json_array = json_decode($json, TRUE);
        
        if ($v1) {
            $use_parts = array('paymentId', 'amount', 'status');
        } else {
            $use_parts = array('amount', 'currency', 'reference', 'transaction', 'status');
        }
        
        $signature_input = '';
        
        foreach ($use_parts as $part) {
            $signature_input .= (string) $json_array[$part];
        }
        
        return 'UPPERCASE(SHA512("'.$signature_input.$this->getSecretKey().'")';
    }
    
    
    public function composeJsonSignature ($json, $v1 = FALSE)
    {
        $json_array = json_decode($json, TRUE);
        
        if ($v1) {
            $use_parts = array('paymentId', 'amount', 'status');
        } else {
            $use_parts = array('amount', 'currency', 'reference', 'transaction', 'status');
        }
        
        $signature_input = '';
        
        foreach ($use_parts as $part) {
            $signature_input .= (string) $json_array[$part];
        }
        
        return $this->createSignatureHash($signature_input);
    }
    
    
    public function verifyJsonSignature ($json, $v1 = FALSE)
    {
        $json_array = json_decode($json, TRUE);
        
        return ($json_array['signature'] == $this->composeJsonSignature($json, $v1));
    }
    
    
    private function makeApiRequest ($method, $endpoint, $auth_level = self::AUTH_LEVEL_1, $params = NULL, $body = NULL)
    {
        $uri = $this->apiUrl.'/v1'.$endpoint;
        
        if (isset($params) AND count($params)) {
            $uri .= '?'.http_build_query($params);
        }
        
        if ($auth_level == 1) {
            $auth_user = $this->getPublishableKey();
            $auth_pass = 'x';
        } else {
            $auth_user = $this->getShopId();
            $auth_pass = $this->getSecretKey();
        }
        
        if ($method == Http::GET) {
            $response = Request::get($uri)
                ->authenticateWith($auth_user, $auth_pass)
               // ->expectsJson()
                ->send();
        } else if ($method == Http::POST) {
            $response = Request::post($uri)
                ->authenticateWith($auth_user, $auth_pass)
                ->sendsJson()
                ->body(json_encode($body))
                //->expectsJson()
                ->send();
        }
        
        $this->lastApiResponse = $response;
        
        return $response;
    }
    
    
    /**
     * Returns the Response object of the last API request
     *
     * @return obj
     */
    public function getLastApiResponse ()
    {
        return $this->lastApiResponse;
    }
    
    
    /**
     * Create new transaction
     *
     * @param mixed An object or array containing request body
     * @throws Exception if failed to create transaction
     * @return obj Transaction object
     */
    public function createTransaction ($request_body)
    {
        $response = $this->makeApiRequest(Http::POST, '/transactions', self::AUTH_LEVEL_2, NULL, $request_body);
        
        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new Exception('Could not create transaction. Response ('.$response->code.'): '.$response->raw_body);
        }
    }
    
    
    /**
     * Get transaction details
    *
    * @param string $transaction_id Transaction ID
    * @throws Exception if failed to get transaction object
    * @return obj Transaction object
    */
    public function getTransaction ($transaction_id)
    {
        $response = $this->makeApiRequest(Http::GET, '/transactions/'.$transaction_id, self::AUTH_LEVEL_2);
        
        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get transaction. Response ('.$response->code.'): '.$response->raw_body);
        }
    }
    
    
    public function getTransactionList ($since = null, $status = null)
    {
        $params = array();
        
        if (isset($since)) {
            $params['since'] = $since;
        }
        
        if (isset($status)) {
            $params['status'] = is_array($status) ? join(',', $status) : $status;
        }
        
        return $this->makeApiRequest(Http::GET, "/transactions", self::AUTH_LEVEL_2, $params)->body;
    }
    
    
    public function createToken ($request_body)
    {
        $response = $this->makeApiRequest(Http::POST, '/tokens', self::AUTH_LEVEL_1, NULL, $request_body);
        
        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create payment token. Response ('.$response->code.'): '.$response->raw_body);
        }
        
        return $response->body;
    }
    
    
    public function createPayment ($transaction_id, $request_body)
    {
        $response = $this->makeApiRequest(Http::POST, "/transactions/{$transaction_id}/payments", self::AUTH_LEVEL_2, NULL, $request_body);
        
        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create payment. Response ('.$response->code.'): '.$response->raw_body);
        }
        
        return $response->body;
    }
    
    
    public function createRefund ($transaction_id, $request_body)
    {
        $response = $this->makeApiRequest(Http::POST, "/transactions/{$transaction_id}/refunds", self::AUTH_LEVEL_2, NULL, $request_body);
        
        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create refund. Response ('.$response->code.'): '.$response->raw_body);
        }
        
        return $response->body;
    }
    
    
    /**
     * Get refund details
    *
    * @param string $transaction_id Transaction ID
    * @param string $refund_id Refund ID
    * @throws Exception if failed to get refund object
    * @return obj Refund object
    */
    public function getRefund ($transaction_id, $refund_id)
    {
        $response = $this->makeApiRequest(Http::GET, '/transactions/'.$transaction_id.'/refunds/'.$refund_id, self::AUTH_LEVEL_2);
        
        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get refund. Response ('.$response->code.'): '.$response->raw_body);
        }
    }
    
    
    public function getRefundList ($transaction_id)
    {
        $response = $this->makeApiRequest(Http::GET, '/transactions/'.$transaction_id.'/refunds', self::AUTH_LEVEL_2);
        
        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get refunds list. Response ('.$response->code.'): '.$response->raw_body);
        }
    }
    
    
    /**
     * Get payment methods
     *
     * @param mixed An object or array containing request parameters
     * @throws Exception if failed to get payment methods
     * @return obj An object containing grouped lists of Payment Method objects
     */
    public function getPaymentMethods ($request_params)
    {
        $response = $this->makeApiRequest(Http::GET, '/methods', self::AUTH_LEVEL_1, $request_params);
        
        if (!in_array($response->code, array(200))) {
            throw new Exception('Could not get payment methods. Response ('.$response->code.'): '.$response->raw_body);
        }
        
        return $response->body;
    }
}

