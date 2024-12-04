<?php
namespace Maksekeskus;

use Exception;
use Httpful\Http;
use Httpful\Request;

class MKException extends Exception {

	protected $raw_content = '';

	function __construct($raw_content, $message, $code = 0, Exception $previous = null) {
		$this->raw_content = $raw_content;
		parent::__construct($message, $code, $previous);
	}

	public function getRawContent() {
		return $this->raw_content;
	}

}

class Maksekeskus
{
    const SIGNATURE_TYPE_1 = 'V1';
    const SIGNATURE_TYPE_2 = 'V2';
    const SIGNATURE_TYPE_MAC = 'MAC';

    /**
     * @var string API base URL
     */
    private $apiUrl;

    /**
     * @var str Payment Gateway base URL
     */
    private $gwUrl;

    /**
     * @var str base URL of static resources
     */
    private $staticsUrl;


    /**
     * @var string Shop ID
     */
    private $shopId;


    /**
     * @var string Publishable Key
     */
    private $publishableKey;


    /**
     * @var string Secret Key
     */
    private $secretKey;

    /**
     * @var string library Version
     */
    private $version = "1.4.5";

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
     * @param string $publishableKey Publishable API Key, NULL if not provided
     * @param string $secretKey Secret API Key, NULL if not provided
     * @param bool $testEnv TRUE if connecting to API in test environment, FALSE otherwise. Default to FALSE.
     * @return void
     */
    public function __construct ($shopId, $publishableKey = NULL, $secretKey = NULL, $testEnv = FALSE)
    {
        $this->setShopId($shopId);
        $this->setPublishableKey($publishableKey);
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
     * @return object
     */

    public function getVersion ()
    {
        return $this->version;
    }

    /**
     * Get URL's of endpoints of current environment (Test vs Live)
     *
     * @return object
     */

    public function getEnvUrls ()
    {
        return (object) $this->envUrls;
    }


    /**
     * Set API base URL
     *
     * @param string $value
     * @return void
     */
    public function setApiUrl ($value)
    {
        $this->apiUrl = $value;
    }


    /**
     * Get API base URL
     *
     * @return string
     */
    public function getApiUrl ()
    {
        return $this->apiUrl;
    }

    /**
     * Set GW base URL
     *
     * @param string $value
     * @return void
     */
    public function setGwUrl ($value)
    {
        $this->gwUrl = $value;
    }


    /**
     * Get GW base URL
     *
     * @return string
     */
    public function getGwUrl ()
    {
        return $this->gwUrl;
    }

    /**
     * Set URL for static resources ( js, images)
     *
     * @param string $value
     * @return void
     */
    public function setStaticsUrl ($value)
    {
        $this->staticsUrl = $value;
    }


    /**
     * Get URL for static resources ( js, images)
     *
     * @return string
     */
    public function getStaticsUrl ()
    {
        return $this->staticsUrl;
    }

    /**
     * Set Shop ID
     *
     * @param string $value
     * @return void
     */
    public function setShopId ($value)
    {
        $this->shopId = $value;
    }


    /**
     * Get Shop ID
     *
     * @return string
     */
    public function getShopId ()
    {
        return $this->shopId;
    }


    /**
     * Set Publishable Key
     *
     * @param string $value
     * @return void
     */
    public function setPublishableKey ($value)
    {
        $this->publishableKey = $value;
    }


    /**
     * Get Publishable Key
     *
     * @return string
     */
    public function getPublishableKey ()
    {
        return $this->publishableKey;
    }


    /**
     * Set Secret Key
     *
     * @param string $value
     * @return void
     */
    public function setSecretKey ($value)
    {
        $this->secretKey = $value;
    }


    /**
     * Get Secret Key
     *
     * @return string
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
     * @throws MKException if unable to extract message data from request
     * @return mixed An object or associative array containing the message data
     */
    public function extractRequestData ($request, $as_object = FALSE)
    {
        if (empty($request['json'])) {
            throw new MKException('', "Unable to extract data from request");
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
     * @param string $json JSON message
     * @param bool $v1 TRUE if REDIRECT, FALSE if API/EMBEDDED. Defaults to FALSE.
     * @return string
     */
    protected function getMacInput ($data, $mac_type)
    {
        if (!is_array($data)) {
            $data = json_decode(is_object($data) ? json_encode($data) : $data, TRUE);
        }

        if ($mac_type == self::SIGNATURE_TYPE_MAC) {
            
            if (version_compare(phpversion(), '5.4.0', '<')) {
                $mac_input = json_encode($data);

                $mac_input = preg_replace_callback('/(?<!\\\\)\\\\u(\w{4})/', function ($matches) {
                    return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                }, $mac_input);
            }
            else {
                $mac_input = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
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
        } catch (MKException $e) {
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
        } catch (MKException $e) {
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
                ->withStrictSSL()
                ->authenticateWith($auth_user, $auth_pass)
                ->send();
        } else if ($method == Http::POST) {
            $response = Request::post($uri)
                ->withStrictSSL()
                ->authenticateWith($auth_user, $auth_pass)
                ->sendsJson()
                ->body(json_encode($body))
                ->send();
        } else if ($method == Http::PUT) {
            $response = Request::put($uri)
                ->withStrictSSL()
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
     * @throws MKException if failed to get shop data
     * @return obj Shop object
     */
    public function getShop ()
    {
        $response = $this->makeGetRequest("/v1/shop");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get shop data. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

   /**
     * Get shop config for e-shop integration 
     *
     * @param string $environment json-encoded key-value pairs describing the e-shop environment
     * @throws MKException if failed to get shop configuration
     * @return obj Shop configuration object
     */
    public function getShopConfig ($environment)
    {
        $response = $this->makeGetRequest("/v1/shop/configuration", $environment);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get shop configuration for the environment. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }



    /**
     * Update shop data
     *
     * @param mixed An object or array containing request body
     * @throws MKException if failed to update shop data
     * @return obj Shop object
     */
    public function updateShop ($request_body)
    {
        $response = $this->makePutRequest("/v1/shop", NULL, $request_body);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get shop data. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }


    /**
     * Create new transaction
     *
     * @param mixed An object or array containing request body
     * @throws MKException if failed to create transaction
     * @return obj Transaction object
     */
    public function createTransaction ($request_body)
    {
        $response = $this->makePostRequest('/v1/transactions', $request_body);

        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not create transaction. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }


    /**
     * Append metadata to Transaction's merchant_data container
     *
     * @param string $transaction_id Transaction ID
     * @param string $params json object, key=merchant_data, {"merchant_data":"my new metadata"}
     * @throws MKException if failed to append metadata
     * 
     */
    public function addTransactionMeta ($transaction_id, $params)
    {
        $response = $this->makePostRequest("/v1/transactions/{$transaction_id}/addMeta", $params);

        if (!in_array($response->code, array(200, 201))) {
            throw new MKException($response->raw_body, 'Could not create payment. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }

        return $response->body;
    }


    /**
     * Get transaction details
     *
     * @param string $transaction_id Transaction ID
     * @throws MKException if failed to get transaction object
     * @return obj Transaction object
     */
    public function getTransaction ($transaction_id)
    {
        $response = $this->makeGetRequest("/v1/transactions/{$transaction_id}");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get transaction. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

    /**
     * Get transaction statement details
     *
     * @param string $transaction_id Transaction ID
     * @throws MKException if failed to get transaction object
     * @return obj TransactionStatement object
     */
    public function getTransactionStatement ($transaction_id)
    {
        $response = $this->makeGetRequest("/v1/transactions/{$transaction_id}/statement");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get transaction statement. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
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
            throw new MKException($response->raw_body, 'Could not create payment token. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }

        return $response->body;
    }



    public function createPayment ($transaction_id, $request_body)
    {
        $response = $this->makePostRequest("/v1/transactions/{$transaction_id}/payments", $request_body);

        if (!in_array($response->code, array(200, 201))) {
            throw new MKException($response->raw_body, 'Could not create payment. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }

        return $response->body;
    }


    public function createRefund ($transaction_id, $request_body)
    {
        $response = $this->makePostRequest("/v1/transactions/{$transaction_id}/refunds", $request_body);

        if (!in_array($response->code, array(200, 201))) {
            throw new MKException($response->raw_body, 'Could not create refund. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }

        return $response->body;
    }


    /**
     * Get refund details
     *
     * @param string $refund_id Refund ID
     * @throws MKException if failed to get refund object
     * @return obj Refund object
     */
    public function getRefund ($refund_id)
    {
        $response = $this->makeGetRequest("/v1/refunds/{$refund_id}");

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get refund. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }


    /**
     * Get a list of a transaction's refunds
     * 
     * This function does not work as the name suggests (same as getRefunds), leaving it here for backwards compatibility
     * New function intended functionality is called getShopRefunds
     *
     * @param string $transaction_id Transaction ID
     * @throws MKException if failed to get refunds list
     * @return array Refund objects
     */
    public function getTransactionRefunds ($transaction_id)
    {
        return $this->getShopRefunds();
    }

    /**
     * Returns list of refunds for shop
     * 
     * Possible parameters (none are required):
     * (string)since, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)until, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)status, possible values: CREATED, SENT, SETTLED, FAILED
     * (string)page, page number
     * (string)per_page, number of items per page
     * 
     * @param mixed An object or array containing request parameters
     * @throws MKException if failed to get payment methods
     * @return array Refund objects
     */
    public function getShopRefunds($request_params = null)
    {
        $response = $this->makeGetRequest("/v1/refunds", $request_params);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get refunds list. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }


    /**
     * Get a list of refunds
     * 
     * Use getShopRefunds instead, leaving this here for backwards compatibility
     *
     * @throws MKException if failed to get refunds list
     * @return array Refund objects
     */
    public function getRefunds ()
    {
        return $this->getShopRefunds();
    }


    /**
     * Get payment methods
     *
     * @param mixed An object or array containing request parameters
     * @throws MKException if failed to get payment methods
     * @return obj An object containing grouped lists of Payment Method objects
     */
    public function getPaymentMethods ($request_params)
    {
        $response = $this->makeGetRequest('/v1/methods', $request_params);

        if (!in_array($response->code, array(200))) {
            throw new MKException($response->raw_body, 'Could not get payment methods. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }

        return $response->body;
    }
    
    
   /**
     * Get carrier-specific destinations for shipments (list of Automated Parcel Machines)
     *
     * @param mixed. An object or array containing request body
     * @throws MKException if failed to retrieve the listing
     * @return obj Shop configuration object
     */
    public function getDestinations ($request_body)
    {
        $response = $this->makePostRequest("/v1/shipments/destinations", $request_body);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not retrieve destinations list. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }




    /**
     * Create new shipments at carrier systems
     *
     * @param mixed An object or array containing request body
     * @throws MKException if failed to create transaction
     * @return obj Transaction object
     */
    public function createShipments ($request_body)
    {
        $response = $this->makePostRequest('/v1/shipments', $request_body);

        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not create shipments. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

    /**
     * get label formats
     *
     * @throws MKException if failed to get label formats list
     * @return array List of label formats
     */
    public function getLabelFormats ()
    {
        $response = $this->makeGetRequest('/v1/shipments/labels/formats');

        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new MKException('Could not get parcel label formats. Response ('.$response->code.'): '.$response->raw_body);
        }
    }

   /**
     * generate parcel labels for shipments registered at carriers
     *
     * @param mixed An object or array containing request body
     * @throws MKException if failed to create transaction
     * @return obj Transaction object
     */
    public function createLabels ($request_body)
    {
        $response = $this->makePostRequest('/v1/shipments/createlabels', $request_body);

        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not generate parcel labels. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

    /**
     * generate shopping cart for SimpleCheckout
     *
     * @param mixed An object or array containing request body
     * @throws MKException if failed to create transaction
     * @return obj Transaction object
     */
    public function createCart ($request_body)
    {
        $response = $this->makePostRequest('/v1/carts', $request_body);

        if (in_array($response->code, array(200, 201))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not generate cart. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

    /**
     * Get Shop fees records (Maksekeskus Service fees)
     *
     * Possible parameters (none are required):
     * (string)since, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)until, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)page, page number
     * (string)per_page, number of items per page
     * 
     * @param mixed An object or array containing request parameters
     * @throws MKException if failed to get payment methods
     * @return obj An object containing grouped lists of Payment Method objects
     */
    public function getShopFees($request_params = null)
    {
        $response = $this->makeGetRequest("/v1/shop/fees", $request_params);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get shop fees. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }

    /**
     * Get Shop Account Statement records (income, fees, vat, payouts)
     *
     * Possible parameters (none are required):
     * (string)since, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)until, yyyy-MM-ddZ, ex. 2014-01-01+02:00 (zone is optional)
     * (string)payout_id, id of the payout
     * (string)page, page number
     * (string)per_page, number of items per page
     * 
     * @param mixed An object or array containing request parameters
     * @throws MKException if failed to get payment methods
     * @return obj An object containing grouped lists of Payment Method objects
     */
    public function getAccountStatement($request_params = null)
    {
        $response = $this->makeGetRequest("/v1/shop/accountstatements", $request_params);

        if (in_array($response->code, array(200))) {
            return $response->body;
        } else {
            throw new MKException($response->raw_body, 'Could not get account statements. Response ('.$response->code.'): '.$response->raw_body, $response->body->code);
        }
    }
}

