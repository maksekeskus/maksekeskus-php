<?php

namespace MakeCommerce;

class Maksekeskus
{
    const GET = 'get';
    const POST = 'post';
    const PUT = 'put';

    /**
     * @var string API base URL
     */
    private $apiUrl;

    /**
     * @var str base URL of static resources
     */
    private $staticsUrl;

    /**
     * @var string Shop ID
     */
    private $shopId;

    /**
     * @var string Secret Key
     */
    private $secretKey;

    /**
     * @var string library Version
     */
    private $version = "2.0.0";

    /**
     * Response object of the last API request
     *
     * @var object
     */
    private $lastApiResponse;

    /**
     * Urls of endpoints of current Environment Key
     * @var array
     */
    private $envUrls;

    /**
     * API client constructor
     *
     * @param string $shopId Shop ID
     * @param string $secretKey Secret API Key, NULL if not provided
     * @param bool $testEnv TRUE if connecting to API in test environment, FALSE otherwise. Default to FALSE.
     * @return void
     */
    public function __construct($shopId, $secretKey, $testEnv = FALSE)
    {
        $this->setShopId($shopId);
        $this->setSecretKey($secretKey);

        if ($testEnv) {
            $this->setApiUrl('https://api.test.maksekeskus.ee');
            $this->envUrls = array(
                    'apiUrl' => 'https://api.test.maksekeskus.ee',
                    'checkoutjsUrl' => 'https://payment.test.maksekeskus.ee/checkout/dist/',
                    'gatewayUrl' => 'https://payment.test.maksekeskus.ee/pay/1/signed.html',
                    'merchantUrl' => 'https://merchant.test.maksekeskus.ee/',
                    'staticsUrl' => 'https://static-test.maksekeskus.ee/'
                 );
        } else {
            $this->setApiUrl('https://api.maksekeskus.ee');
            $this->envUrls = array(
                    'apiUrl' => 'https://api.maksekeskus.ee',
                    'checkoutjsUrl' => 'https://payment.maksekeskus.ee/checkout/dist/',
                    'gatewayUrl' => 'https://payment.maksekeskus.ee/pay/1/signed.html',
                    'merchantUrl' => 'https://merchant.maksekeskus.ee/',
                    'staticsUrl' => 'https://static.maksekeskus.ee/'
                 );
        }
    }

    /**
     * Get version of this library
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get URL's of endpoints of current environment (Test vs Live)
     *
     * @return object
     */
    public function getEnvUrls()
    {
        return (object) $this->envUrls;
    }

    /**
     * Set API base URL
     *
     * @param string $value
     * @return void
     */
    public function setApiUrl($value)
    {
        $this->apiUrl = $value;
    }

    /**
     * Get API base URL
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * Set URL for static resources (js, images)
     *
     * @param string $value
     * @return void
     */
    public function setStaticsUrl($value)
    {
        $this->staticsUrl = $value;
    }

    /**
     * Get URL for static resources ( js, images)
     *
     * @return string
     */
    public function getStaticsUrl()
    {
        return $this->staticsUrl;
    }

    /**
     * Set Shop ID
     *
     * @param string $value
     * @return void
     */
    public function setShopId($value)
    {
        $this->shopId = $value;
    }

    /**
     * Get Shop ID
     *
     * @return string
     */
    public function getShopId()
    {
        return $this->shopId;
    }

    /**
     * Set Secret Key
     *
     * @param string $value
     * @return void
     */
    public function setSecretKey($value)
    {
        $this->secretKey = $value;
    }

    /**
     * Get Secret Key
     *
     * @return string
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Send a request to an API endpoint
     *
     * @param $method Request method (GET, POST or PUT)
     * @param $endpoint API endpoint
     * @param null $params Request parameters
     * @param null $body Request body
     * @return mixed Response object
     * @throws MKResponseException Response status was not 200 or 201
     */
    private function makeApiRequest($method, $endpoint, $params = NULL, $body = NULL)
    {
        $uri = $this->apiUrl . $endpoint;

        if (isset($params) AND count($params)) {
            $uri .= '?'.http_build_query($params);
        }

        $auth_user = $this->getShopId();
        $auth_pass = $this->getSecretKey();

        switch($method) {
            case self::POST:
                $response = Request::post($uri)
                    ->withStrictSSL()
                    ->authenticateWith($auth_user, $auth_pass)
                    ->sendsJson()
                    ->body(json_encode($body))
                    ->send();
                break;
            case self::PUT:
                $response = Request::put($uri)
                    ->withStrictSSL()
                    ->authenticateWith($auth_user, $auth_pass)
                    ->sendsJson()
                    ->body(json_encode($body))
                    ->send();
                break;
            default: // also self::GET:
                $response = Request::get($uri)
                    ->withStrictSSL()
                    ->authenticateWith($auth_user, $auth_pass)
                    ->send();
                break;
        }

        $this->lastApiResponse = $response;

        if (!in_array($response->code, array(200, 201))) {
            throw new MKResponseException($response->body->message, $response->body->code);
        }
        return $response;
    }

    /**
     * Returns the Response object of the last API request
     *
     * @return obj
     */
    public function getLastApiResponse()
    {
        return $this->lastApiResponse;
    }

    /**
     * Get shop data.
     *
     * @return mixed Shop data
     * @throws MKResponseException Unable to get shop
     */
    public function getShop()
    {
        $response = $this->makeAPIRequest(self::GET, "/v1/shop");
        return $response->body;
    }

   /**
     * Get shop config for e-shop integration.
     *
     * @param $environment json-encoded key-value pairs describing the e-shop environment
     * @return mixed Shop configuration object
     * @throws MKResponseException if failed to get shop configuration
     */
    public function getShopConfig($environment)
    {
        $response = $this->makeApiRequest(self::GET, "/v1/shop/configuration", $environment);
        return $response->body;
    }

    /**
     * Update shop data
     *
     * @param $request_body if failed to get shop configuration
     * @return mixed Shop object
     * @throws MKResponseException if failed to update shop data
     */
    public function updateShop($request_body)
    {
        $response = $this->makeApiRequest(self::PUT, "/v1/shop", NULL, $request_body);
        return $response->body;
    }

    /**
     * Create new transaction.
     *
     * @param $request_body An object or array containing request body
     * @return mixed if failed to create transaction
     * @throws MKResponseException if failed to create transaction
     */
    public function createTransaction($request_body)
    {
        $response = $this->makeApiRequest(self::POST, '/v1/transactions', NULL, $request_body);
        return $response->body;
    }

    /**
     * Append metadata to Transaction's merchant_data container.
     *
     * @param $transaction_id Transaction ID
     * @param $params json object, key=merchant_data, {"merchant_data":"my new metadata"}
     * @return mixed
     * @throws MKResponseException if failed to append metadata
     */
    public function addTransactionMeta($transaction_id, $params)
    {
        $response = $this->makeApiRequest(self::POST,"/v1/transactions/{$transaction_id}/addMeta", NULL, $params);
        return $response->body;
    }

    /**
     * Get transaction details.
     *
     * @param $transaction_id Transaction ID
     * @return mixed Transaction object
     * @throws MKResponseException if failed to get transaction object
     */
    public function getTransaction($transaction_id)
    {
        $response = $this->makeApiRequest(self::GET, "/v1/transactions/{$transaction_id}");
        return $response->body;
    }

    /**
     * Get transaction statement details
     *
     * @param $transaction_id Transaction ID
     * @return mixed TransactionStatement object
     * @throws MKResponseException if failed to get transaction object
     */
    public function getTransactionStatement($transaction_id)
    {
        $response = $this->makeApiRequest(self::GET, "/v1/transactions/{$transaction_id}/statement");
        return $response->body;
    }

    /**
     * Get transactions list
     *
     * @param array $params Associative array of query parameters
     * @return obj Transactions list
     */
    public function getTransactions($params = array())
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

        return $this->makeApiRequest(self::GET, "/v1/transactions", $request_params)->body;
        // TODO: Exception handling
    }

    /**
     * Create token.
     *
     * @param $request_body
     * @return mixed
     * @throws MKResponseException
     */
    public function createToken($request_body)
    {
        $response = $this->makeApiRequest(self::POST, '/v1/tokens', NULL, $request_body);
        return $response->body;
    }

    /**
     * Create payment.
     *
     * @param $transaction_id
     * @param $request_body
     * @return mixed
     * @throws MKResponseException
     */
    public function createPayment($transaction_id, $request_body)
    {
        $response = $this->makeApiRequest(self::POST, "/v1/transactions/{$transaction_id}/payments", NULL, $request_body);
        return $response->body;
    }

    /**
     * Create refund.
     *
     * @param $transaction_id
     * @param $request_body
     * @return mixed
     * @throws MKResponseException
     */
    public function createRefund($transaction_id, $request_body)
    {
        $response = $this->makeApiRequest(self::POST, "/v1/transactions/{$transaction_id}/refunds", NULL, $request_body);
        return $response->body;
    }

    /**
     * Get refund details.
     *
     * @param $refund_id Refund ID
     * @return mixed Refund object
     * @throws MKResponseException if failed to get refund object
     */
    public function getRefund($refund_id)
    {
        $response = $this->makeApiRequest(self::GET, "/v1/refunds/{$refund_id}");
        return $response->body;
    }

    /**
     * Get a list of a transaction's refunds
     *
     * @param $transaction_id Transaction ID
     * @return mixed Refund objects
     * @throws MKResponseException if failed to get refunds list
     */
    public function getTransactionRefunds($transaction_id)
    {
        $response = $this->makeApiRequest(self::GET, '/v1/refunds');
        return $response->body;
    }

    /**
     * Get a list of refunds
     *
     * @return mixed Refund objects
     * @throws MKResponseException if failed to get refunds list
     */
    public function getRefunds()
    {
        $response = $this->makeApiRequest('/v1/refunds');
        return $response->body;
    }

    /**
     * Get payment methods
     *
     * @param $request_params An object or array containing request parameters
     * @return mixed An object containing grouped lists of Payment Method objects
     * @throws MKResponseException if failed to get payment methods
     */
    public function getPaymentMethods($request_params)
    {
        $response = $this->makeApiRequest(self::GET, '/v1/methods', $request_params);
        return $response->body;
    }

   /**
     * Get carrier-specific destinations for shipments (list of Automated Parcel Machines)
     *
     * @param $request_body An object or array containing request body
     * @return mixed Shop configuration object
     * @throws MKResponseException if failed to retrieve the listing
     */
    public function getDestinations($request_body)
    {
        $response = $this->makeApiRequest(self::POST'/v1/shipments/destinations', NULL, $request_body);
        return $response->body;
    }

    /**
     * Create new shipments at carrier systems
     *
     * @param $request_body An object or array containing request body
     * @return mixed Transaction object
     * @throws MKResponseException if failed to create transaction
     */
    public function createShipments($request_body)
    {
        $response = $this->makeApiRequest(self::POST, '/v1/shipments', NULL, $request_body);
        return $response->body;
    }

    /**
     * get label formats
     *
     * @return mixed List of label formats
     * @throws MKResponseException if failed to get label formats list
     */
    public function getLabelFormats()
    {
        $response = $this->makeApiRequest(self::GET, '/v1/shipments/labels/formats');
        return $response->body;
    }

   /**
     * generate parcel labels for shipments registered at carriers
     *
     * @param $request_body An object or array containing request body
     * @return mixed Transaction object
     * @throws MKResponseException if failed to create transaction
     */
    public function createLabels($request_body)
    {
        $response = $this->makeApiRequest(self::POST, '/v1/shipments/createlabels', NULL, $request_body);
        return $response->body;
    }

    /**
     * generate shopping cart for SimpleCheckout
     *
     * @param $request_body An object or array containing request body
     * @return mixed ransaction object
     * @throws MKResponseException if failed to create transaction
     */
    public function createCart($request_body)
    {
        $response = $this->makeApiRequest(self::POST, '/v1/carts', NULL, $request_body);
        return $response->body;
    }
}
