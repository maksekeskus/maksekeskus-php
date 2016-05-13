<?php
require __DIR__ . '/vendor/autoload.php';
use Httpful\Http;
use Httpful\Request;

class Maksekeskus
{
    const SIGNATURE_TYPE_1 = 'V1';
    const SIGNATURE_TYPE_2 = 'V2';
    const SIGNATURE_TYPE_MAC = 'MAC';

    /**
     * @var str API base URL
     */
    private $apiUrl;


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
     * @param bool $testEnv TRUE if connecting to API in test environment, FALSE otherwise. Default to FALSE.
     * @return void
     */
    public function __construct ($shopId, $publishableKey = NULL, $secretKey = NULL, $testEnv = FALSE)
    {
        $this->setShopId($shopId);
        $this->setPublishableKey($publishableKey);
        $this->setSecretKey($secretKey);

        if ($testEnv) {
            $this->setApiUrl('https://api-test.maksekeskus.ee');
        } else {
            $this->setApiUrl('https://api.maksekeskus.ee');
        }
    }


    /**
     * Set API base URL
     *
     * @param str $value
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
        return $this->apiUrl;
    }


    /**
     * Set Shop ID
     *
     * @param str $value
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


    /**
     * Extract message data from request
     *
     * @param array $request Request data (ie. $_REQUEST)
     * @param bool $as_object Whether to return the message as an object, defaults to FALSE
     * @throws Exception if unable to extract message data from request
     * @return mixed An object or associative array containing the message data
     */
    public function extractRequestData ($request, $as_object = FALSE)
    {
        if (empty($request['json'])) {
            throw new Exception("Unable to extract data from request");
        }

        return json_decode($request['json'], !$as_object);
    }


    /**
     * Extracts the signature type from request data
     *
     * @deprecated Verify message authenticity via MAC instead.
     * @param array $request Associative array of request data
     * @return string Returns the signature type, NULL if not present
     */
    public function extractRequestSignatureType ($request)
    {
        $data = $this->extractRequestData($request);

        if (!empty($data['signature'])) {
            if (empty($data['transaction'])) {
                return self::SIGNATURE_TYPE_1;
            } else {
                return self::SIGNATURE_TYPE_2;
            }
        }

        return null;
    }


    /**
     * Extracts the signature value from request data
     *
     * @deprecated Verify message authenticity via MAC instead.
     * @param array $request Associative array of request data
     * @return string Returns the signature, NULL if not present
     */
    public function extractRequestSignature ($request)
    {
        $data = $this->extractRequestData($request);

        if (!empty($data['signature'])) {
            return $data['signature'];
        }

        return null;
    }


    /**
     * Extracts the MAC value from request data
     *
     * @param array $request Associative array of request data
     * @return string Returns the extracted MAC value, NULL if not present
     */
    public function extractRequestMac ($request)
    {
        if (!empty($request['mac'])) {
            return $request['mac'];
        }

        return null;
    }


    /**
     * Create a MAC hash for the given string
     *
     * @param string $string Input string
     * @return string MAC value
     */
    protected function createMacHash ($string)
    {
        return strtoupper(hash('sha512', $string . $this->getSecretKey()));
    }


    /**
     * Prepares the input string for MAC calculation depending on integration type
     *
     * @param str $json JSON message
     * @param bool $v1 TRUE if REDIRECT, FALSE if API/EMBEDDED. Defaults to FALSE.
     * @return str
     */
    protected function getMacInput ($data, $mac_type)
    {
        if (!is_array($data)) {
            $data = json_decode(is_object($data) ? json_encode($data) : $data, TRUE);
        }

        if ($mac_type == self::SIGNATURE_TYPE_MAC) {
            $mac_input = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            if ($mac_type == self::SIGNATURE_TYPE_2) {
               $use_parts = array('amount', 'currency', 'reference', 'transaction', 'status');
            } else {
                $use_parts = array('paymentId', 'amount', 'status');
            }

            $mac_input = '';
            foreach ($use_parts as $part) {
                $mac_input .= (is_bool($data[$part]) ? ($data[$part] ? 'true' : 'false') : (string) $data[$part]);
            }
        }

        return $mac_input;
    }


    /**
     * Compose a signature
     *
     * @deprecated Used only for testing. Verify message authenticity via MAC instead.
     * @param mixed $data Transaction data
     * @param string $signature_type V1 or V2
     * @return string Signature
     */
    public function composeSignature ($data, $signature_type)
    {
        $mac_input = $this->getMacInput($data, $signature_type);

        return $this->createMacHash($mac_input);
    }


    /**
     * Compose a signature for the Embedded Payments snippet
     *
     * @param mixed $amount Transaction amount
     * @param string $currency Transaction currency
     * @param string $reference An optional reference value
     * @return string Signature
     */
    public function composeEmbeddedSignature ($amount, $currency, $reference = NULL)
    {
        $mac_input = (string)$amount . (string)$currency . (string)$reference;

        return $this->createMacHash($mac_input);
    }


    public function composeMac ($data)
    {
        $mac_input = $this->getMacInput($data, self::SIGNATURE_TYPE_MAC);

        return $this->createMacHash($mac_input);
    }


    public function explainSignature ($data, $signature_type)
    {
        $input = $this->getMacInput($data, $signature_type);

        return 'UPPERCASE(HEX(SHA512('.$input.')))';
    }


    public function explainMac ($data)
    {
        $input = $this->getMacInput($data, self::SIGNATURE_TYPE_MAC);

        return 'UPPERCASE(HEX(SHA512('.$input.')))';
    }


    /**
     * Verify the MAC of the received request
     *
     * @param array $request Associative array of request data
     * @return bool TRUE if MAC verification was successful, FALSE otherwise
     */
    public function verifyMac ($request)
    {
        try {
            $received = $this->extractRequestMac($request);
            $expected = $this->composeMac($this->extractRequestData($request));

            return ($received == $expected);
        } catch (Exception $e) {
            return FALSE;
        }
    }


    /**
     * Verify the signature of the received request
     *
     * @deprecated Verify message authenticity via MAC instead.
     * @param array $request Associative array of request data
     * @return bool TRUE if signature verification was successful, FALSE otherwise
     */
    public function verifySignature ($request)
    {
        try {
            $received = $this->extractRequestSignature($request);
            $expected = $this->composeSignature($this->extractRequestData($request), $this->extractRequestSignatureType($request));

            return ($received == $expected);
        } catch (Exception $e) {
            return FALSE;
        }
    }


    /**
     * Send a GET request to an API endpoint
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return obj Response object
     */
    public function makeGetRequest ($endpoint, $params = NULL)
    {
        return $this->makeApiRequest(Http::GET, $endpoint, $params);
    }


    /**
     * Send a POST request to an API endpoint
     *
     * @param string $endpoint API endpoint
     * @param string $body Request body
     * @return obj Response object
     */
    public function makePostRequest ($endpoint, $body = NULL)
    {
        return $this->makeApiRequest(Http::POST, $endpoint, NULL, $body);
    }


    /**
     * Send a GET request to an API endpoint
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $body Request body
     * @return obj Response object
     */
    public function makePutRequest ($endpoint, $params = NULL, $body = NULL)
    {
        return $this->makeApiRequest(Http::PUT, $endpoint, $params, $body);
    }


    /**
     * Send a request to an API endpoint
     *
     * @param string $method Request method (Http::GET, Http::POST or Http::PUT)
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $body Request body
     * @return obj Response object
     */
    protected function makeApiRequest ($method, $endpoint, $params = NULL, $body = NULL)
    {
        $uri = $this->apiUrl . $endpoint;

        if (isset($params) AND count($params)) {
            $uri .= '?'.http_build_query($params);
        }

        $auth_user = $this->getShopId();
        $auth_pass = $this->getSecretKey();

        if ($method == Http::GET) {
            $response = Request::get($uri)
                ->authenticateWith($auth_user, $auth_pass)
                ->send();
        } else if ($method == Http::POST) {
            $response = Request::post($uri)
                ->authenticateWith($auth_user, $auth_pass)
                ->sendsJson()
                ->body(json_encode($body))
                ->send();
        } else if ($method == Http::PUT) {
            $response = Request::put($uri)
                ->authenticateWith($auth_user, $auth_pass)
                ->sendsJson()
                ->body(json_encode($body))
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
     * Get shop data
     *
     * @throws Exception if failed to get shop data
     * @return obj Shop object
     */
    public function getShop ()
    {
        $response = $this->makeGetRequest("/v1/shop");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get shop data. Response ('.$response->code.'): '.$response->raw_body);
        }
    }

   /**
     * Get shop config for e-shop integration 
     *
     * @param string $environment json-encoded key-value pairs describing the e-shop environment
     * @throws Exception if failed to get shop configuration
     * @return obj Shop configuration object
     */
    public function getShopConfig ($environment)
    {
        $response = $this->makeGetRequest("/v1/shop/configuration", $environment);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get shop configuration for the environment. Response ('.$response->code.'): '.$response->raw_body);
        }
    }



    /**
     * Update shop data
     *
     * @param mixed An object or array containing request body
     * @throws Exception if failed to update shop data
     * @return obj Shop object
     */
    public function updateShop ($request_body)
    {
        $response = $this->makePutRequest("/v1/shop", NULL, $request_body);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get shop data. Response ('.$response->code.'): '.$response->raw_body);
        }
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
        $response = $this->makePostRequest('/v1/transactions', $request_body);

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
        $response = $this->makeGetRequest("/v1/transactions/{$transaction_id}");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get transaction. Response ('.$response->code.'): '.$response->raw_body);
        }
    }


    /**
     * Get transactions list
     *
     * @param array $params Associative array of query parameters
     * @return obj Transactions list
     */
    public function getTransactions ($params = array())
    {
        $request_params = array();

        if (!empty($params['since'])) {
            $request_params['since'] = $params['since'];
        }

        if (!empty($params['until'])) {
            $request_params['until'] = $params['until'];
        }

        if (!empty($params['completed_since'])) {
            $request_params['completed_since'] = $params['completed_since'];
        }

        if (!empty($params['completed_until'])) {
            $request_params['completed_until'] = $params['completed_until'];
        }

        if (!empty($params['refunded_since'])) {
            $request_params['refunded_since'] = $params['refunded_since'];
        }

        if (!empty($params['refunded_until'])) {
            $request_params['refunded_until'] = $params['refunded_until'];
        }

        if (!empty($params['status'])) {
            $request_params['status'] = is_array($params['status']) ? join(',', $params['status']) : $params['status'];
        }

        if (!empty($params['page'])) {
            $request_params['page'] = (int) $params['page'];
        }

        if (!empty($params['per_page'])) {
            $request_params['per_page'] = (int) $params['per_page'];
        }

        return $this->makeGetRequest("/v1/transactions", $request_params)->body;
    }


    public function createToken ($request_body)
    {
        $response = $this->makePostRequest('/v1/tokens', $request_body);

        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create payment token. Response ('.$response->code.'): '.$response->raw_body);
        }

        return $response->body;
    }


    /**
     * Get token by email or cookie ID
    *
    * @param string $request_params Request parameters
    * @throws Exception if failed to get token object
    * @return obj Token object
    */
    public function getToken ($request_params)
    {
        $response = $this->makeGetRequest('/v1/tokens', $request_params);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get token. Response ('.$response->code.'): '.$response->raw_body);
        }
    }


    public function createPayment ($transaction_id, $request_body)
    {
        $response = $this->makePostRequest("/v1/transactions/{$transaction_id}/payments", $request_body);

        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create payment. Response ('.$response->code.'): '.$response->raw_body);
        }

        return $response->body;
    }


    public function createRefund ($transaction_id, $request_body)
    {
        $response = $this->makePostRequest("/v1/transactions/{$transaction_id}/refunds", $request_body);

        if (!in_array($response->code, array(200, 201))) {
            throw new Exception('Could not create refund. Response ('.$response->code.'): '.$response->raw_body);
        }

        return $response->body;
    }


    /**
     * Get refund details
     *
     * @param string $refund_id Refund ID
     * @throws Exception if failed to get refund object
     * @return obj Refund object
     */
    public function getRefund ($refund_id)
    {
        $response = $this->makeGetRequest("/v1/refunds/{$refund_id}");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get refund. Response ('.$response->code.'): '.$response->raw_body);
        }
    }


    /**
     * Get a list of a transaction's refunds
     *
     * @param string $transaction_id Transaction ID
     * @throws Exception if failed to get refunds list
     * @return array Refund objects
     */
    public function getTransactionRefunds ($transaction_id)
    {
        $response = $this->makeGetRequest("/v1/refunds");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new Exception('Could not get transaction refunds list. Response ('.$response->code.'): '.$response->raw_body);
        }
    }


    /**
     * Get a list of refunds
     *
     * @throws Exception if failed to get refunds list
     * @return array Refund objects
     */
    public function getRefunds ()
    {
        $response = $this->makeGetRequest("/v1/refunds");

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
        $response = $this->makeGetRequest('/v1/methods', $request_params);

        if (!in_array($response->code, array(200))) {
            throw new Exception('Could not get payment methods. Response ('.$response->code.'): '.$response->raw_body);
        }

        return $response->body;
    }
}

