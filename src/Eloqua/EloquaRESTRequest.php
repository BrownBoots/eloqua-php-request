<?php
namespace Eloqua;
/**
 * REST client for Eloqua's API.
 */
class EloquaRESTRequest
{
    private $ch;
    protected $baseUrl;
    public $responseInfo;

	/**
	 * @var null Used for our singleton pattern
	 */
	protected static $instance = null;
	/**
	 * @param $companySiteName
	 * @param $user
	 * @param $pass
	 * @param $APIversion integer
	 */
	public function __construct($companySiteName, $user, $pass, $APIversion = 2)
	{
		// basic authentication credentials
		$credentials = $companySiteName . '\\' . $user . ':' . $pass;

		$this->apiVersion = $APIversion;

		// initialize the cURL resource
		$this->ch = curl_init();

		// set cURL and credential options
		curl_setopt($this->ch, CURLOPT_USERPWD, $credentials); 

		// set headers
		$headers = array('Content-type: application/json');
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

		// return transfer as string
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
	}

	/**
	 * Fetches standard REST url based on the /id request.
	 * http://topliners.eloqua.com/community/code_it/blog/2012/11/30/using-the-eloqua-api--determining-endpoint-urls-logineloquacom
	 * @return mixed|string
	 */
	public function getBaseUrl()
	{
		if(isset($this->baseUrl) === false) {
			if(!($this->baseUrl = $this->fetchIdUrl())) {
				// timeout? try again...
				sleep(2);
				if(!($this->baseUrl = $this->fetchIdUrl())) {
					// fallback and just guess at this url...
					$this->baseUrl = 'https://secure.eloqua.com/API/REST/' . (int)$this->apiVersion . '.0/';
				}
			}
		}

		return $this->baseUrl;
	}

	/**
	 * @return mixed|null
	 */
	protected function fetchIdUrl()
	{
		// set the full URL for the request
		curl_setopt($this->ch, CURLOPT_URL,  'https://login.eloqua.com/id');
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

		// execute the request
		$response = curl_exec($this->ch);
		if($v = json_decode($response)) {
			// set the base URL for the API endpoint
			// based on version
			if (isset($v->urls->apis->rest->standard)) {

				return str_replace('{version}', $this->apiVersion . '.0', $v->urls->apis->rest->standard);
			}
		}

		// something went wrong! handle upstream
		return null;
	}

	/**
	 * Using the singleton will help save resources and prevent multiple auth/ID requests (a point of rate limiting to be concerned with)
	 *
	 * @param $companySiteName
	 * @param $user
	 * @param $pass
	 * @param $APIVersion
	 * @return EloquaRequest|null
	 */
	public static function singleton($companySiteName, $user, $pass, $APIVersion = 2)
	{
		if(isset(self::$instance) === false) {
			self::$instance = new EloquaRESTRequest($companySiteName, $user, $pass, $APIVersion);
		}

		return self::$instance;
	}

	public function __destruct()
	{
		curl_close($this->ch);
	}

	public function get($url)
	{
		return $this->executeRequest($url, 'GET');
	}

	public function post($url, $data)
	{
		return $this->executeRequest($url, 'POST', $data);
	}

	public function put($url, $data)
	{
		return $this->executeRequest($url, 'PUT', $data);
	}

	public function delete($url)
	{
		return $this->executeRequest($url, 'DELETE');	
	}
	
	public function executeRequest($url, $method, $data=null)
	{

		$uri = $this->getBaseUrl() . '/' . trim($url, "/");

		// set the full URL for the request
		curl_setopt($this->ch, CURLOPT_URL, $uri);

		switch ($method) {
			case 'GET':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
				break;
			case 'POST':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
				break;
			case 'PUT':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($data));
				break;
			case 'DELETE':
				curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			default:
				break;
		}

        // execute the request
        $response = curl_exec($this->ch);

        // store the response info including the HTTP status
        // 400 and 500 status codes indicate an error
        $this->responseInfo = curl_getinfo($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400)
        {            
            trigger_error('Eloqua API Request failed: '.print_r($this->responseInfo, true), E_USER_WARNING);
        }
        
        // todo : add support in constructor for contentType {xml, json}	
        return json_decode($response);
	}
}


