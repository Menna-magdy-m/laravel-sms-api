<?php

namespace Gr8Shivam\SmsApi;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use Gr8Shivam\SmsApi\Exception\InvalidMethodException;
use Illuminate\Support\Facades\Http;

class SmsApi
{
    private static $client = null;
    private $config = array();
    private $gateway;
    private $request = '';
    public $response = '';
    public $res = '';
    public $responseCode = '';
    private $country_code = null;
    private $wrapperParams=[];

    /**
     * SmsApi constructor.
     */
    public function __construct()
    {
        $this->createClient();
    }

    /**
     * Create new Guzzle Client
     *
     * @return $this
     */
    protected function createClient()
    {
        if (!self::$client) {
            self::$client = new Client;
        }
        return $this;
    }

    /**
     * Set custom gateway
     *
     * @param string $gateway
     * @return $this
     */
    public function gateway($gateway = '')
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * Set custom country code
     *
     * @param string $country_code
     * @return $this
     */
    public function countryCode($country_code = '')
    {
        $this->country_code = $country_code;
        return $this;
    }

    /**
     * Adds wrapper Variables
     *
     * @param array $wrapperVars
     * @return $this
     */
    //Addition
    public function addWrapperParams($wrapperParams)
    {
        $this->wrapperParams = $wrapperParams;
        return $this;
    }

    /**
     * Send message
     *
     * @param $to
     * @param $message
     * @param array $extra_params
     * @param array $extra_headers
     * @return $this
     * @throws InvalidMethodException
     */
    public function sendMessage($to, $message, $extra_params = null, $extra_headers = [],$url =null)
    {
        
        //echo 'sendmessage';
        if ($this->gateway == '') {
            $this->loadDefaultGateway();
        }
        $this->loadCredentialsFromConfig();

        $request_method = isset($this->config['method']) ? $this->config['method'] : 'GET';
        $url = $url?$url:  $this->config['url'];

        $mobile = $this->config['add_code'] ? $this->addCountryCode($to) : $to;
        if (!(isset($this->config['json']) && $this->config['json'])) {
            //Flatten Array if JSON false
            if (is_array($mobile)){
                $mobile = $this->composeBulkMobile($mobile);
            }
        }
        else{
            //Transform to Array if JSON true
            if (!is_array($mobile)){
                $mobile = (isset($this->config['jsonToArray']) ? $this->config['jsonToArray'] : true) ? array($mobile) : $mobile;
                // $mobile = array($mobile);
            }
        }

        $params = $this->config['params']['others'];

        $headers = isset($this->config['headers']) ? $this->config['headers'] : [];

        //Check wrapper for JSON Payload
        $wrapper = isset($this->config['wrapper']) ? $this->config['wrapper'] : NULL;

        $wrapperParams = array_merge($this->wrapperParams,(isset($this->config['wrapperParams']) ? $this->config['wrapperParams'] : []));

        $send_to_param_name = $this->config['params']['send_to_param_name'];
        $msg_param_name = $this->config['params']['msg_param_name'];

        if ($wrapper) {
            $send_vars[$send_to_param_name] = $mobile;
            $send_vars[$msg_param_name] = $message;
        } else {
            $params[$send_to_param_name] = $mobile;
            $params[$msg_param_name] = $message;
        }

        if ($wrapper && $wrapperParams) {
            $send_vars = array_merge($send_vars, $wrapperParams);
        }

        if ($extra_params) {
            $params = array_merge($params, $extra_params);
        }

        if($extra_headers){
            $headers = array_merge($headers, $extra_headers);
        }

        try {
            
            @$response = Http::get($url);
           $body = $response->body();
           //dd($body);
           if(is_numeric($body))
              $this->response =  $body;
           elseif($response->json('Code')){
                $this->response = $response->json('Code');
           }else{
                $this->response = $response->getStatusCode();
           }
//            //Build Request
//            $request = new Request($request_method, $url);
//            //echo $url;
//            if ($request_method == "GET") {
//                
//                $promise = $this->getClient()->sendAsync(
//                    $request,
//                    [
//                        'query' => $params,
//                        'headers' => $headers
//                    ]
//                );
//                dd($params);
//            } elseif ($request_method == "POST") {
//                $payload = $wrapper ? array_merge(array($wrapper => array($send_vars)), $params) : $params;
//
//                if ((isset($this->config['json']) && $this->config['json'])) {
//                    $promise = $this->getClient()->sendAsync(
//                        $request,
//                        [
//                            'json' => $payload,
//                            'headers' => $headers
//                        ]
//                    );
//                } else {
//                    $promise = $this->getClient()->sendAsync(
//                        $request,
//                        [
//                            'query' => $params,
//                            'headers' => $headers
//                        ]
//                    );
//                }
//            } else {
//                throw new InvalidMethodException(
//                    sprintf("Only GET and POST methods allowed.")
//                );
//            }

//            $response = $promise->wait();
//            //
//            $this->response = $response->getBody()->getContents();
            $this->responseCode = $response->getStatusCode();
            //dd($response->getStatusCode());
            
            Log::info('SMS Gateway Response Code: '. $this->responseCode);
            Log::info('SMS Gateway Response Body: \n'. $this->response);
//            $this->res = $this->response;
//            //print_r($this->response);
//            $this->res = $promise->wait()->getBody()->getContents();
//            echo"resss";
            //print_r($this->response);
            
        } catch (RequestException $e) {
            Log::error($e);
            //dd($e);
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->response = Message::bodySummary($response);
                $this->responseCode = $response->getStatusCode();
                //dd($response->getStatusCode());
                Log::error('SMS Gateway Response Code: '. $this->responseCode);
                Log::error('SMS Gateway Response Body: \n'. $this->response);
            }
        }
        return $this;
    }
    public function get_res(){
        return $this->res;
    }
    
    public function check_palance($url =null,$method='GET')
    {
        try {
            @$response = Http::get($url);
           $body = $response->body();
           //dd($response->json('balance'));
           if(is_numeric($body))
              $this->response =  $body;
           elseif($response->json('balance')||$response->json('credits')){
                $this->response = $response->json('balance')?$response->json('balance'):$response->json('credits');
           }else{
                $this->response = '0';
           }
           //dd($body);
           $this->responseCode = $response->status();
           
        } catch (Exception $ex) {
             if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->response = Message::bodySummary($response);
                $this->responseCode = $response->getStatusCode();

                Log::error('SMS Gateway Response Code: '. $this->responseCode);
                Log::error('SMS Gateway Response Body: \n'. $this->response);
            }
        }
        return $this;
       
    }

    /**
     * Load Default Gateway
     *
     * @return $this
     */
    private function loadDefaultGateway()
    {
        $default_acc = config('sms-api.default', null);
        if ($default_acc) {
            $this->gateway = $default_acc;
        }
        return $this;
    }

    /**
     * Load Credentials from the selected Gateway
     *
     * @return $this
     */
    protected function loadCredentialsFromConfig()
    {
        $gateway = $this->gateway;
        $config_name = 'sms-api.' . $gateway;
        $this->config = config($config_name);
        return $this;
    }

    /**
     * Add country code to mobile
     *
     * @param $mobile
     * @return array|string
     */
    private function addCountryCode($mobile)
    {
        if (!$this->country_code) {
            $this->country_code = config('sms-api.country_code', '91');
        }
        if (is_array($mobile)) {
            array_walk($mobile, function (&$value, $key) {
                $value = $this->country_code . $value;
            });
            return $mobile;
        }
        return $this->country_code . $mobile;
    }

    /**
     * For multiple mobiles
     *
     * @param $mobile
     * @return string
     */
    private function composeBulkMobile($mobile)
    {
        return implode(',', $mobile);
    }

    /**
     * Get Client
     *
     * @return GuzzleHttp\Client
     */
    public function getClient()
    {
        return self::$client;
    }

    /**
     * Return Response
     *
     * @return string
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Return Response Code
     *
     * @return string
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }
}
