<?php
/**
 * This is the PHP Cloud Files API.
 *
 * <code>
 *   # Authenticate to Cloud Files.  The default is to automatically try
 *   # to re-authenticate if an authentication token expires.
 *   #
 *   # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 *   #	   file.  This API ships with a newer version obtained directly from
 *   #	   cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 *   #	   call the CF_Authentication instance's 'ssl_use_cabundle()' method.
 *   #
 *   $auth = new CF_Authentication($username, $api_key);
 *   # $auth->ssl_use_cabundle();  # bypass cURL's old CA bundle
 *   $auth->authenticate();
 *
 *   # Establish a connection to the storage system
 *   #
 *   # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 *   #	   file.  This API ships with a newer version obtained directly from
 *   #	   cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 *   #	   call the CF_Connection instance's 'ssl_use_cabundle()' method.
 *   #
 *   $conn = new CF_Connection($auth);
 *   # $conn->ssl_use_cabundle();  # bypass cURL's old CA bundle
 *
 *   # Create a remote Container and storage Object
 *   #
 *   $images = $conn->create_container("photos");
 *   $bday = $images->create_object("first_birthday.jpg");
 *
 *   # Upload content from a local file by streaming it.  Note that we use
 *   # a "float" for the file size to overcome PHP's 32-bit integer limit for
 *   # very large files.
 *   #
 *   $fname = "/home/user/photos/birthdays/birthday1.jpg";  # filename to upload
 *   $size = (float) sprintf("%u", filesize($fname));
 *   $fp = open($fname, "r");
 *   $bday->write($fp, $size);
 *
 *   # Or... use a convenience function instead
 *   #
 *   $bday->load_from_filename("/home/user/photos/birthdays/birthday1.jpg");
 *
 *   # Now, publish the "photos" container to serve the images by CDN.
 *   # Use the "$uri" value to put in your web pages or send the link in an
 *   # email message, etc.
 *   #
 *   $uri = $images->make_public();
 *
 *   # Or... print out the Object's public URI
 *   #
 *   print $bday->public_uri();
 * </code>
 *
 * See the included tests directory for additional sample code.
 *
 * Requres PHP 5.x (for Exceptions and OO syntax) and PHP's cURL module.
 *
 * It uses the supporting "cloudfiles_http.php" module for HTTP(s) support and
 * allows for connection re-use and streaming of content into/out of Cloud Files
 * via PHP's cURL module.
 *
 * See COPYING for license information.
 *
 * @author Eric "EJ" Johnson <ej@racklabs.com>
 * @copyright Copyright (c) 2008, Rackspace US, Inc.
 * @package php-cloudfiles
 */

/**
 */
 
/**
 * Custom Exceptions for the CloudFiles API
 *
 * Requres PHP 5.x (for Exceptions and OO syntax)
 *
 * See COPYING for license information.
 *
 * @author Eric "EJ" Johnson <ej@racklabs.com>
 * @copyright Copyright (c) 2008, Rackspace US, Inc.
 * @package php-cloudfiles-exceptions
 */

/**
 * Custom Exceptions for the CloudFiles API
 * @package php-cloudfiles-exceptions
 */
class SyntaxException extends Exception { }
class AuthenticationException extends Exception { }
class InvalidResponseException extends Exception { }
class NonEmptyContainerException extends Exception { }
class NoSuchObjectException extends Exception { }
class NoSuchContainerException extends Exception { }
class NoSuchAccountException extends Exception { }
class MisMatchedChecksumException extends Exception { }
class IOException extends Exception { }
class CDNNotEnabledException extends Exception { }
class BadContentTypeException extends Exception { }
class InvalidUTF8Exception extends Exception { }
class ConnectionNotOpenException extends Exception { }

/**
 * This is an HTTP client class for Cloud Files.  It uses PHP's cURL module
 * to handle the actual HTTP request/response.  This is NOT a generic HTTP
 * client class and is only used to abstract out the HTTP communication for
 * the PHP Cloud Files API.
 *
 * This module was designed to re-use existing HTTP(S) connections between
 * subsequent operations.  For example, performing multiple PUT operations
 * will re-use the same connection.
 *
 * This modules also provides support for streaming content into and out
 * of Cloud Files.  The majority (all?) of the PHP HTTP client modules expect
 * to read the server's response into a string variable.  This will not work
 * with large files without killing your server.  Methods like,
 * get_object_to_stream() and put_object() take an open filehandle
 * argument for streaming data out of or into Cloud Files.
 *
 * Requres PHP 5.x (for Exceptions and OO syntax)
 *
 * See COPYING for license information.
 *
 * @author Eric "EJ" Johnson <ej@racklabs.com>
 * @copyright Copyright (c) 2008, Rackspace US, Inc.
 * @package php-cloudfiles-http
 */

define("PHP_CF_VERSION", "1.7.10");
define("USER_AGENT", sprintf("PHP-CloudFiles/%s", PHP_CF_VERSION));
define("MAX_HEADER_NAME_LEN", 128);
define("MAX_HEADER_VALUE_LEN", 256);
define("ACCOUNT_CONTAINER_COUNT", "X-Account-Container-Count");
define("ACCOUNT_BYTES_USED", "X-Account-Bytes-Used");
define("CONTAINER_OBJ_COUNT", "X-Container-Object-Count");
define("CONTAINER_BYTES_USED", "X-Container-Bytes-Used");
define("MANIFEST_HEADER", "X-Object-Manifest");
define("METADATA_HEADER_PREFIX", "X-Object-Meta-");
define("CONTENT_HEADER_PREFIX", "Content-");
define("ACCESS_CONTROL_HEADER_PREFIX", "Access-Control-");
define("ORIGIN_HEADER", "Origin");
define("CDN_URI", "X-CDN-URI");
define("CDN_SSL_URI", "X-CDN-SSL-URI");
define("CDN_STREAMING_URI", "X-CDN-Streaming-URI");
define("CDN_ENABLED", "X-CDN-Enabled");
define("CDN_LOG_RETENTION", "X-Log-Retention");
define("CDN_ACL_USER_AGENT", "X-User-Agent-ACL");
define("CDN_ACL_REFERRER", "X-Referrer-ACL");
define("CDN_TTL", "X-TTL");
define("CDNM_URL", "X-CDN-Management-Url");
define("STORAGE_URL", "X-Storage-Url");
define("AUTH_TOKEN", "X-Auth-Token");
define("AUTH_USER_HEADER", "X-Auth-User");
define("AUTH_KEY_HEADER", "X-Auth-Key");
define("AUTH_USER_HEADER_LEGACY", "X-Storage-User");
define("AUTH_KEY_HEADER_LEGACY", "X-Storage-Pass");
define("AUTH_TOKEN_LEGACY", "X-Storage-Token");
define("CDN_EMAIL", "X-Purge-Email");
define("DESTINATION", "Destination");
define("ETAG_HEADER", "ETag");
define("LAST_MODIFIED_HEADER", "Last-Modified");
define("CONTENT_TYPE_HEADER", "Content-Type");
define("CONTENT_LENGTH_HEADER", "Content-Length");
define("USER_AGENT_HEADER", "User-Agent");

/**
 * HTTP/cURL wrapper for Cloud Files
 *
 * This class should not be used directly.  It's only purpose is to abstract
 * out the HTTP communication from the main API.
 *
 * @package php-cloudfiles-http
 */
class CF_Http
{
	private $error_str;
	private $dbug;
	private $cabundle_path;
	private $api_version;

	# Authentication instance variables
	#
	private $storage_url;
	private $cdnm_url;
	private $auth_token;

	# Request/response variables
	#
	private $response_status;
	private $response_reason;
	private $connections;

	# Variables used for content/header callbacks
	#
	private $_user_read_progress_callback_func;
	private $_user_write_progress_callback_func;
	private $_write_callback_type;
	private $_text_list;
	private $_account_container_count;
	private $_account_bytes_used;
	private $_container_object_count;
	private $_container_bytes_used;
	private $_obj_etag;
	private $_obj_last_modified;
	private $_obj_content_type;
	private $_obj_content_length;
	private $_obj_metadata;
	private $_obj_headers;
	private $_obj_manifest;
	private $_obj_write_resource;
	private $_obj_write_string;
	private $_cdn_enabled;
	private $_cdn_ssl_uri;
	private $_cdn_streaming_uri;
	private $_cdn_uri;
	private $_cdn_ttl;
	private $_cdn_log_retention;
	private $_cdn_acl_user_agent;
	private $_cdn_acl_referrer;

	function __construct($api_version)
	{
		$this->dbug = False;
		$this->cabundle_path = NULL;
		$this->api_version = $api_version;
		$this->error_str = NULL;

		$this->storage_url = NULL;
		$this->cdnm_url = NULL;
		$this->auth_token = NULL;

		$this->response_status = NULL;
		$this->response_reason = NULL;

		# Curl connections array - since there is no way to "re-set" the
		# connection paramaters for a cURL handle, we keep an array of
		# the unique use-cases and funnel all of those same type
		# requests through the appropriate curl connection.
		#
		$this->connections = array(
			"GET_CALL"  => NULL, # GET objects/containers/lists
			"PUT_OBJ"   => NULL, # PUT object
			"HEAD"	  => NULL, # HEAD requests
			"PUT_CONT"  => NULL, # PUT container
			"DEL_POST"  => NULL, # DELETE containers/objects, POST objects
			"COPY"	  => null, # COPY objects
		);

		$this->_user_read_progress_callback_func = NULL;
		$this->_user_write_progress_callback_func = NULL;
		$this->_write_callback_type = NULL;
		$this->_text_list = array();
		$this->_return_list = NULL;
		$this->_account_container_count = 0;
		$this->_account_bytes_used = 0;
		$this->_container_object_count = 0;
		$this->_container_bytes_used = 0;
		$this->_obj_write_resource = NULL;
		$this->_obj_write_string = "";
		$this->_obj_etag = NULL;
		$this->_obj_last_modified = NULL;
		$this->_obj_content_type = NULL;
		$this->_obj_content_length = NULL;
		$this->_obj_metadata = array();
		$this->_obj_manifest = NULL;
		$this->_obj_headers = NULL;
		$this->_cdn_enabled = NULL;
		$this->_cdn_ssl_uri = NULL;
		$this->_cdn_streaming_uri = NULL;
		$this->_cdn_uri = NULL;
		$this->_cdn_ttl = NULL;
		$this->_cdn_log_retention = NULL;
		$this->_cdn_acl_user_agent = NULL;
		$this->_cdn_acl_referrer = NULL;

		# The OS list with a PHP without an updated CA File for CURL to
		# connect to SSL Websites. It is the first 3 letters of the PHP_OS
		# variable.
		$OS_CAFILE_NONUPDATED=array(
			"win","dar"
		); 

		if (in_array((strtolower (substr(PHP_OS, 0,3))), $OS_CAFILE_NONUPDATED))
			$this->ssl_use_cabundle();
		
	}

	function ssl_use_cabundle($path=NULL)
	{
		if ($path) {
			$this->cabundle_path = $path;
		} else {
			$this->cabundle_path = dirname(strtr(__FILE__, "\\", "/")) . "/share/cacert.pem";
		}
		if (!file_exists($this->cabundle_path)) {
			throw new IOException("Could not use CA bundle: "
				. $this->cabundle_path);
		}
		return;
	}

	# Uses separate cURL connection to authenticate
	#
	function authenticate($user, $pass, $acct=NULL, $host=NULL)
	{
		$path = array();
		if (isset($acct)){
			$headers = array(
				sprintf("%s: %s", AUTH_USER_HEADER_LEGACY, $user),
				sprintf("%s: %s", AUTH_KEY_HEADER_LEGACY, $pass),
				);
			$path[] = $host;
			$path[] = rawurlencode(sprintf("v%d",$this->api_version));
			$path[] = rawurlencode($acct);
		} else {
			$headers = array(
				sprintf("%s: %s", AUTH_USER_HEADER, $user),
				sprintf("%s: %s", AUTH_KEY_HEADER, $pass),
				);
		$path[] = $host;
		}
		$path[] = "v1.0";
		$url = implode("/", $path);

		$curl_ch = curl_init();
		if (!is_null($this->cabundle_path)) {
			curl_setopt($curl_ch, CURLOPT_SSL_VERIFYPEER, True);
			curl_setopt($curl_ch, CURLOPT_CAINFO, $this->cabundle_path);
		}
		curl_setopt($curl_ch, CURLOPT_VERBOSE, $this->dbug);
		curl_setopt($curl_ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl_ch, CURLOPT_MAXREDIRS, 4);
		curl_setopt($curl_ch, CURLOPT_HEADER, 0);
		curl_setopt($curl_ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl_ch, CURLOPT_USERAGENT, USER_AGENT);
		curl_setopt($curl_ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl_ch, CURLOPT_HEADERFUNCTION,array(&$this,'_auth_hdr_cb'));
		curl_setopt($curl_ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl_ch, CURLOPT_URL, $url);
		curl_exec($curl_ch);
		curl_close($curl_ch);

		return array($this->response_status, $this->response_reason,
			$this->storage_url, $this->cdnm_url, $this->auth_token);
	}

	# (CDN) GET /v1/Account
	#
	function list_cdn_containers($enabled_only)
	{
		$conn_type = "GET_CALL";
		$url_path = $this->_make_path("CDN");

		$this->_write_callback_type = "TEXT_LIST";
		if ($enabled_only)
		{
			$return_code = $this->_send_request($conn_type, $url_path . 
			'/?enabled_only=true');
		}
		else
		{
			$return_code = $this->_send_request($conn_type, $url_path);
		}
		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,array());
		}
		if ($return_code == 401) {
			return array($return_code,"Unauthorized",array());
		}
		if ($return_code == 404) {
			return array($return_code,"Account not found.",array());
		}
		if ($return_code == 204) {
			return array($return_code,"Account has no CDN enabled Containers.",
				array());
		}
		if ($return_code == 200) {
		$this->create_array();
			return array($return_code,$this->response_reason,$this->_text_list);
		}
		$this->error_str = "Unexpected HTTP response: ".$this->response_reason;
		return array($return_code,$this->error_str,array());
	}

	# (CDN) DELETE /v1/Account/Container or /v1/Account/Container/Object
	#
	function purge_from_cdn($path, $email=null)
	{
		if(!$path)
			throw new SyntaxException("Path not set");
		$url_path = $this->_make_path("CDN", NULL, $path);
		if($email)
		{
			$hdrs = array(CDN_EMAIL => $email);
			$return_code = $this->_send_request("DEL_POST",$url_path,$hdrs,"DELETE");
		}
		else
			$return_code = $this->_send_request("DEL_POST",$url_path,null,"DELETE");
		return $return_code;
	}

	# (CDN) POST /v1/Account/Container
	function update_cdn_container($container_name, $ttl=86400, $cdn_log_retention=False,
								  $cdn_acl_user_agent="", $cdn_acl_referrer)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");

		$url_path = $this->_make_path("CDN", $container_name);
		$hdrs = array(
			CDN_ENABLED => "True",
			CDN_TTL => $ttl,
			CDN_LOG_RETENTION => $cdn_log_retention ?  "True" : "False",
			CDN_ACL_USER_AGENT => $cdn_acl_user_agent,
			CDN_ACL_REFERRER => $cdn_acl_referrer,
			);
		$return_code = $this->_send_request("DEL_POST",$url_path,$hdrs,"POST");
		if ($return_code == 401) {
			$this->error_str = "Unauthorized";
			return array($return_code, $this->error_str, NULL);
		}
		if ($return_code == 404) {
			$this->error_str = "Container not found.";
			return array($return_code, $this->error_str, NULL);
		}
		if ($return_code != 202) {
			$this->error_str="Unexpected HTTP response: ".$this->response_reason;
			return array($return_code, $this->error_str, NULL);
		}
		return array($return_code, "Accepted", $this->_cdn_uri, $this->_cdn_ssl_uri);

	}

	# (CDN) PUT /v1/Account/Container
	#
	function add_cdn_container($container_name, $ttl=86400)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");
		
		$url_path = $this->_make_path("CDN", $container_name);
		$hdrs = array(
			CDN_ENABLED => "True",
			CDN_TTL => $ttl,
			);
		$return_code = $this->_send_request("PUT_CONT", $url_path, $hdrs);
		if ($return_code == 401) {
			$this->error_str = "Unauthorized";
			return array($return_code,$this->response_reason,False);
		}
		if (!in_array($return_code, array(201,202))) {
			$this->error_str="Unexpected HTTP response: ".$this->response_reason;
			return array($return_code,$this->response_reason,False);
		}
		return array($return_code,$this->response_reason,$this->_cdn_uri,
					 $this->_cdn_ssl_uri);
	}

	# (CDN) POST /v1/Account/Container
	#
	function remove_cdn_container($container_name)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");
		
		$url_path = $this->_make_path("CDN", $container_name);
		$hdrs = array(CDN_ENABLED => "False");
		$return_code = $this->_send_request("DEL_POST",$url_path,$hdrs,"POST");
		if ($return_code == 401) {
			$this->error_str = "Unauthorized";
			return array($return_code, $this->error_str);
		}
		if ($return_code == 404) {
			$this->error_str = "Container not found.";
			return array($return_code, $this->error_str);
		}
		if ($return_code != 202) {
			$this->error_str="Unexpected HTTP response: ".$this->response_reason;
			return array($return_code, $this->error_str);
		}
		return array($return_code, "Accepted");
	}

	# (CDN) HEAD /v1/Account
	#
	function head_cdn_container($container_name)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");
		
		$conn_type = "HEAD";
		$url_path = $this->_make_path("CDN", $container_name);
		$return_code = $this->_send_request($conn_type, $url_path, NULL, "GET", True);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
		}
		if ($return_code == 401) {
			return array($return_code,"Unauthorized",NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
		}
		if ($return_code == 404) {
			return array($return_code,"Account not found.",NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
		}
		if ($return_code == 204) {
			return array($return_code,$this->response_reason,
				$this->_cdn_enabled, $this->_cdn_ssl_uri,
				$this->_cdn_streaming_uri,
				$this->_cdn_uri, $this->_cdn_ttl,
				$this->_cdn_log_retention,
				$this->_cdn_acl_user_agent,
				$this->_cdn_acl_referrer
				);
		}
		return array($return_code,$this->response_reason,
					 NULL,NULL,NULL,NULL,
					 $this->_cdn_log_retention,
					 $this->_cdn_acl_user_agent,
					 $this->_cdn_acl_referrer,
					 NULL
			);
	}

	# GET /v1/Account
	#
	function list_containers($limit=0, $marker=NULL)
	{
		$conn_type = "GET_CALL";
		$url_path = $this->_make_path();

		$limit = intval($limit);
		$params = array();
		if ($limit > 0) {
			$params[] = "limit=$limit";
		}
		if ($marker) {
			$params[] = "marker=".rawurlencode($marker);
		}
		if (!empty($params)) {
			$url_path .= "?" . implode("&", $params);
		}

		$this->_write_callback_type = "TEXT_LIST";
		$return_code = $this->_send_request($conn_type, $url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,array());
		}
		if ($return_code == 204) {
			return array($return_code, "Account has no containers.", array());
		}
		if ($return_code == 404) {
			$this->error_str = "Invalid account name for authentication token.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 200) {
		$this->create_array();
			return array($return_code, $this->response_reason, $this->_text_list);
		}
		$this->error_str = "Unexpected HTTP response: ".$this->response_reason;
		return array($return_code,$this->error_str,array());
	}

	# GET /v1/Account?format=json
	#
	function list_containers_info($limit=0, $marker=NULL)
	{
		$conn_type = "GET_CALL";
		$url_path = $this->_make_path() . "?format=json";

		$limit = intval($limit);
		$params = array();
		if ($limit > 0) {
			$params[] = "limit=$limit";
		}
		if ($marker) {
			$params[] = "marker=".rawurlencode($marker);
		}
		if (!empty($params)) {
			$url_path .= "&" . implode("&", $params);
		}

		$this->_write_callback_type = "OBJECT_STRING";
		$return_code = $this->_send_request($conn_type, $url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,array());
		}
		if ($return_code == 204) {
			return array($return_code, "Account has no containers.", array());
		}
		if ($return_code == 404) {
			$this->error_str = "Invalid account name for authentication token.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 200) {
			$json_body = json_decode($this->_obj_write_string, True);
			return array($return_code, $this->response_reason, $json_body);
		}
		$this->error_str = "Unexpected HTTP response: ".$this->response_reason;
		return array($return_code,$this->error_str,array());
	}

	# HEAD /v1/Account
	#
	function head_account()
	{
		$conn_type = "HEAD";

		$url_path = $this->_make_path();
		$return_code = $this->_send_request($conn_type,$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,0,0);
		}
		if ($return_code == 404) {
			return array($return_code,"Account not found.",0,0);
		}
		if ($return_code == 204) {
			return array($return_code,$this->response_reason,
				$this->_account_container_count, $this->_account_bytes_used);
		}
		return array($return_code,$this->response_reason,0,0);
	}

	# PUT /v1/Account/Container
	#
	function create_container($container_name)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");

		$url_path = $this->_make_path("STORAGE", $container_name);
		$return_code = $this->_send_request("PUT_CONT",$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return False;
		}
		return $return_code;
	}

	# DELETE /v1/Account/Container
	#
	function delete_container($container_name)
	{
		if ($container_name == "")
			throw new SyntaxException("Container name not set.");

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");

		$url_path = $this->_make_path("STORAGE", $container_name);
		$return_code = $this->_send_request("DEL_POST",$url_path,array(),"DELETE");

		switch ($return_code) {
		case 204:
			break;
		case 0:
			$this->error_str .= ": Failed to obtain valid HTTP response.";;
			break;
		case 409:
			$this->error_str = "Container must be empty prior to removing it.";
			break;
		case 404:
			$this->error_str = "Specified container did not exist to delete.";
			break;
		default:
			$this->error_str = "Unexpected HTTP return code: $return_code.";
		}
		return $return_code;
	}

	# GET /v1/Account/Container
	#
	function list_objects($cname,$limit=0,$marker=NULL,$prefix=NULL,$path=NULL)
	{
		if (!$cname) {
			$this->error_str = "Container name not set.";
			return array(0, $this->error_str, array());
		}

		$url_path = $this->_make_path("STORAGE", $cname);

		$limit = intval($limit);
		$params = array();
		if ($limit > 0) {
			$params[] = "limit=$limit";
		}
		if ($marker) {
			$params[] = "marker=".rawurlencode($marker);
		}
		if ($prefix) {
			$params[] = "prefix=".rawurlencode($prefix);
		}
		if ($path) {
			$params[] = "path=".rawurlencode($path);
		}
		if (!empty($params)) {
			$url_path .= "?" . implode("&", $params);
		}
 
		$conn_type = "GET_CALL";
		$this->_write_callback_type = "TEXT_LIST";
		$return_code = $this->_send_request($conn_type,$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,array());
		}
		if ($return_code == 204) {
			$this->error_str = "Container has no Objects.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 404) {
			$this->error_str = "Container has no Objects.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 200) {
		$this->create_array();	
			return array($return_code,$this->response_reason, $this->_text_list);
		}
		$this->error_str = "Unexpected HTTP response code: $return_code";
		return array(0,$this->error_str,array());
	}

	# GET /v1/Account/Container?format=json
	#
	function get_objects($cname,$limit=0,$marker=NULL,$prefix=NULL,$path=NULL)
	{
		if (!$cname) {
			$this->error_str = "Container name not set.";
			return array(0, $this->error_str, array());
		}

		$url_path = $this->_make_path("STORAGE", $cname);

		$limit = intval($limit);
		$params = array();
		$params[] = "format=json";
		if ($limit > 0) {
			$params[] = "limit=$limit";
		}
		if ($marker) {
			$params[] = "marker=".rawurlencode($marker);
		}
		if ($prefix) {
			$params[] = "prefix=".rawurlencode($prefix);
		}
		if ($path) {
			$params[] = "path=".rawurlencode($path);
		}
		if (!empty($params)) {
			$url_path .= "?" . implode("&", $params);
		}
 
		$conn_type = "GET_CALL";
		$this->_write_callback_type = "OBJECT_STRING";
		$return_code = $this->_send_request($conn_type,$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,array());
		}
		if ($return_code == 204) {
			$this->error_str = "Container has no Objects.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 404) {
			$this->error_str = "Container has no Objects.";
			return array($return_code,$this->error_str,array());
		}
		if ($return_code == 200) {
			$json_body = json_decode($this->_obj_write_string, True);
			return array($return_code,$this->response_reason, $json_body);
		}
		$this->error_str = "Unexpected HTTP response code: $return_code";
		return array(0,$this->error_str,array());
	}


	# HEAD /v1/Account/Container
	#
	function head_container($container_name)
	{

		if ($container_name == "") {
			$this->error_str = "Container name not set.";
			return False;
		}
		
		if ($container_name != "0" and !isset($container_name)) {
			$this->error_str = "Container name not set.";
			return False;
		}
	
		$conn_type = "HEAD";

		$url_path = $this->_make_path("STORAGE", $container_name);
		$return_code = $this->_send_request($conn_type,$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,0,0);
		}
		if ($return_code == 404) {
			return array($return_code,"Container not found.",0,0);
		}
		if ($return_code == 204 || $return_code == 200) {
			return array($return_code,$this->response_reason,
				$this->_container_object_count, $this->_container_bytes_used);
		}
		return array($return_code,$this->response_reason,0,0);
	}

	# GET /v1/Account/Container/Object
	#
	function get_object_to_string(&$obj, $hdrs=array())
	{
		if (!is_object($obj) || get_class($obj) != "CF_Object") {
			throw new SyntaxException(
				"Method argument is not a valid CF_Object.");
		}

		$conn_type = "GET_CALL";

		$url_path = $this->_make_path("STORAGE", $obj->container->name,$obj->name);
		$this->_write_callback_type = "OBJECT_STRING";
		$return_code = $this->_send_request($conn_type,$url_path,$hdrs);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array($return_code0,$this->error_str,NULL);
		}
		if ($return_code == 404) {
			$this->error_str = "Object not found.";
			return array($return_code0,$this->error_str,NULL);
		}
		if (($return_code < 200) || ($return_code > 299
				&& $return_code != 412 && $return_code != 304)) {
			$this->error_str = "Unexpected HTTP return code: $return_code";
			return array($return_code,$this->error_str,NULL);
		}
		return array($return_code,$this->response_reason, $this->_obj_write_string);
	}

	# GET /v1/Account/Container/Object
	#
	function get_object_to_stream(&$obj, &$resource=NULL, $hdrs=array())
	{
		if (!is_object($obj) || get_class($obj) != "CF_Object") {
			throw new SyntaxException(
				"Method argument is not a valid CF_Object.");
		}
		if (!is_resource($resource)) {
			throw new SyntaxException(
				"Resource argument not a valid PHP resource.");
		}

		$conn_type = "GET_CALL";

		$url_path = $this->_make_path("STORAGE", $obj->container->name,$obj->name);
		$this->_obj_write_resource = $resource;
		$this->_write_callback_type = "OBJECT_STREAM";
		$return_code = $this->_send_request($conn_type,$url_path,$hdrs);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array($return_code,$this->error_str);
		}
		if ($return_code == 404) {
			$this->error_str = "Object not found.";
			return array($return_code,$this->error_str);
		}
		if (($return_code < 200) || ($return_code > 299
				&& $return_code != 412 && $return_code != 304)) {
			$this->error_str = "Unexpected HTTP return code: $return_code";
			return array($return_code,$this->error_str);
		}
		return array($return_code,$this->response_reason);
	}

	# PUT /v1/Account/Container/Object
	#
	function put_object(&$obj, &$fp)
	{
		if (!is_object($obj) || get_class($obj) != "CF_Object") {
			throw new SyntaxException(
				"Method argument is not a valid CF_Object.");
		}
		if (!is_resource($fp)) {
			throw new SyntaxException(
				"File pointer argument is not a valid resource.");
		}

		$conn_type = "PUT_OBJ";
		$url_path = $this->_make_path("STORAGE", $obj->container->name,$obj->name);

		$hdrs = $this->_headers($obj);

		$etag = $obj->getETag();
		if (isset($etag)) {
			$hdrs[] = "ETag: " . $etag;
		}
		if (!$obj->content_type) {
			$hdrs[] = "Content-Type: application/octet-stream";
		} else {
			$hdrs[] = "Content-Type: " . $obj->content_type;
		}

		$this->_init($conn_type);
		curl_setopt($this->connections[$conn_type],
				CURLOPT_INFILE, $fp);
		if (!$obj->content_length) {
			# We don''t know the Content-Length, so assumed "chunked" PUT
			#
			curl_setopt($this->connections[$conn_type], CURLOPT_UPLOAD, True);
			$hdrs[] = 'Transfer-Encoding: chunked';
		} else {
			# We know the Content-Length, so use regular transfer
			#
			curl_setopt($this->connections[$conn_type],
					CURLOPT_INFILESIZE, $obj->content_length);
		}
		$return_code = $this->_send_request($conn_type,$url_path,$hdrs);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0,$this->error_str,NULL);
		}
		if ($return_code == 412) {
			$this->error_str = "Missing Content-Type header";
			return array($return_code,$this->error_str,NULL);
		}
		if ($return_code == 422) {
			$this->error_str = "Derived and computed checksums do not match.";
			return array($return_code,$this->error_str,NULL);
		}
		if ($return_code != 201) {
			$this->error_str = "Unexpected HTTP return code: $return_code";
			return array($return_code,$this->error_str,NULL);
		}
		return array($return_code,$this->response_reason,$this->_obj_etag);
	}

	# POST /v1/Account/Container/Object
	#
	function update_object(&$obj)
	{
		if (!is_object($obj) || get_class($obj) != "CF_Object") {
			throw new SyntaxException(
				"Method argument is not a valid CF_Object.");
		}

		# TODO: The is_array check isn't in sync with the error message
		if (!$obj->manifest && !(is_array($obj->metadata) || is_array($obj->headers))) {
			$this->error_str = "Metadata and headers arrays are empty.";
			return 0;
		}

		$url_path = $this->_make_path("STORAGE", $obj->container->name,$obj->name);

		$hdrs = $this->_headers($obj);
		$return_code = $this->_send_request("DEL_POST",$url_path,$hdrs,"POST");
		switch ($return_code) {
		case 202:
			break;
		case 0:
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			$return_code = 0;
			break;
		case 404:
			$this->error_str = "Account, Container, or Object not found.";
			break;
		default:
			$this->error_str = "Unexpected HTTP return code: $return_code";
			break;
		}
		return $return_code;
	}

	# HEAD /v1/Account/Container/Object
	#
	function head_object(&$obj)
	{
		if (!is_object($obj) || get_class($obj) != "CF_Object") {
			throw new SyntaxException(
				"Method argument is not a valid CF_Object.");
		}

		$conn_type = "HEAD";

		$url_path = $this->_make_path("STORAGE", $obj->container->name,$obj->name);
		$return_code = $this->_send_request($conn_type,$url_path);

		if (!$return_code) {
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			return array(0, $this->error_str." ".$this->response_reason,
				NULL, NULL, NULL, NULL, array(), NULL, array());
		}

		if ($return_code == 404) {
			return array($return_code, $this->response_reason,
				NULL, NULL, NULL, NULL, array(), NULL, array());
		}
		if ($return_code == 204 || $return_code == 200) {
			return array($return_code,$this->response_reason,
				$this->_obj_etag,
				$this->_obj_last_modified,
				$this->_obj_content_type,
				$this->_obj_content_length,
				$this->_obj_metadata,
				$this->_obj_manifest,
				$this->_obj_headers);
		}
		$this->error_str = "Unexpected HTTP return code: $return_code";
		return array($return_code, $this->error_str." ".$this->response_reason,
				NULL, NULL, NULL, NULL, array(), NULL, array());
	}

	# COPY /v1/Account/Container/Object
	#
	function copy_object($src_obj_name, $dest_obj_name, $container_name_source, $container_name_target, $metadata=NULL, $headers=NULL)
	{
		if (!$src_obj_name) {
			$this->error_str = "Object name not set.";
			return 0;
		}

		if ($container_name_source == "") {
			$this->error_str = "Container name source not set.";
			return 0;
		}

		if ($container_name_source != "0" and !isset($container_name_source)) {
			$this->error_str = "Container name source not set.";
			return 0;
		}

		if ($container_name_target == "") {
			$this->error_str = "Container name target not set.";
			return 0;
		}

		if ($container_name_target != "0" and !isset($container_name_target)) {
			$this->error_str = "Container name target not set.";
			return 0;
		}

		$conn_type = "COPY";

		$url_path = $this->_make_path("STORAGE", $container_name_source, rawurlencode($src_obj_name));
		$destination = rawurlencode($container_name_target."/".$dest_obj_name);

		$hdrs = self::_process_headers($metadata, $headers);
		$hdrs[DESTINATION] = $destination;

		$return_code = $this->_send_request($conn_type,$url_path,$hdrs,"COPY");
		switch ($return_code) {
		case 201:
			break;
		case 0:
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			$return_code = 0;
			break;
		case 404:
			$this->error_str = "Specified container/object did not exist.";
			break;
		default:
			$this->error_str = "Unexpected HTTP return code: $return_code.";
		}
		return $return_code;
	}

	# DELETE /v1/Account/Container/Object
	#
	function delete_object($container_name, $object_name)
	{
		if ($container_name == "") {
			$this->error_str = "Container name not set.";
			return 0;
		}
		
		if ($container_name != "0" and !isset($container_name)) {
			$this->error_str = "Container name not set.";
			return 0;
		}
		
		if (!$object_name) {
			$this->error_str = "Object name not set.";
			return 0;
		}

		$url_path = $this->_make_path("STORAGE", $container_name,$object_name);
		$return_code = $this->_send_request("DEL_POST",$url_path,NULL,"DELETE");
		switch ($return_code) {
		case 204:
			break;
		case 0:
			$this->error_str .= ": Failed to obtain valid HTTP response.";
			$return_code = 0;
			break;
		case 404:
			$this->error_str = "Specified container did not exist to delete.";
			break;
		default:
			$this->error_str = "Unexpected HTTP return code: $return_code.";
		}
		return $return_code;
	}

	function get_error()
	{
		return $this->error_str;
	}

	function setDebug($bool)
	{
		$this->dbug = $bool;
		foreach ($this->connections as $k => $v) {
			if (!is_null($v)) {
				curl_setopt($this->connections[$k], CURLOPT_VERBOSE, $this->dbug);
			}
		}
	}

	function getCDNMUrl()
	{
		return $this->cdnm_url;
	}

	function getStorageUrl()
	{
		return $this->storage_url;
	}

	function getAuthToken()
	{
		return $this->auth_token;
	}

	function setCFAuth($cfs_auth, $servicenet=False)
	{
		if ($servicenet) {
			$this->storage_url = "https://snet-" . substr($cfs_auth->storage_url, 8);
		} else {
			$this->storage_url = $cfs_auth->storage_url;
		}
		$this->auth_token = $cfs_auth->auth_token;
		$this->cdnm_url = $cfs_auth->cdnm_url;
	}

	function setReadProgressFunc($func_name)
	{
		$this->_user_read_progress_callback_func = $func_name;
	}

	function setWriteProgressFunc($func_name)
	{
		$this->_user_write_progress_callback_func = $func_name;
	}

	private function _header_cb($ch, $header)
	{
		$header_len = strlen($header);

		if (preg_match("/^(HTTP\/1\.[01]) (\d{3}) (.*)/", $header, $matches)) {
			$this->response_status = $matches[2];
			$this->response_reason = $matches[3];
			return $header_len;
		}

		if (strpos($header, ":") === False)
			return $header_len;
		list($name, $value) = explode(":", $header, 2);
		$value = trim($value);

		switch (strtolower($name)) {
		case strtolower(CDN_ENABLED):
			$this->_cdn_enabled = strtolower($value) == "true";
			break;
		case strtolower(CDN_URI):
			$this->_cdn_uri = $value;
			break;
		case strtolower(CDN_SSL_URI):
			$this->_cdn_ssl_uri = $value;
			break;
		case strtolower(CDN_STREAMING_URI):
			$this->_cdn_streaming_uri = $value;
			break;
		case strtolower(CDN_TTL):
			$this->_cdn_ttl = $value;
			break;
		case strtolower(MANIFEST_HEADER):
			$this->_obj_manifest = $value;
			break;
		case strtolower(CDN_LOG_RETENTION):
			$this->_cdn_log_retention = strtolower($value) == "true";
			break;
		case strtolower(CDN_ACL_USER_AGENT):
			$this->_cdn_acl_user_agent = $value;
			break;
		case strtolower(CDN_ACL_REFERRER):
			$this->_cdn_acl_referrer = $value;
			break;
		case strtolower(ACCOUNT_CONTAINER_COUNT):
			$this->_account_container_count = (float)$value+0;
			break;
		case strtolower(ACCOUNT_BYTES_USED):
			$this->_account_bytes_used = (float)$value+0;
			break;
		case strtolower(CONTAINER_OBJ_COUNT):
			$this->_container_object_count = (float)$value+0;
			break;
		case strtolower(CONTAINER_BYTES_USED):
			$this->_container_bytes_used = (float)$value+0;
			break;
		case strtolower(ETAG_HEADER):
			$this->_obj_etag = $value;
			break;
		case strtolower(LAST_MODIFIED_HEADER):
			$this->_obj_last_modified = $value;
			break;
		case strtolower(CONTENT_TYPE_HEADER):
			$this->_obj_content_type = $value;
			break;
		case strtolower(CONTENT_LENGTH_HEADER):
			$this->_obj_content_length = (float)$value+0;
			break;
		case strtolower(ORIGIN_HEADER):
			$this->_obj_headers[ORIGIN_HEADER] = $value;
			break;
		default:
			if (strncasecmp($name, METADATA_HEADER_PREFIX, strlen(METADATA_HEADER_PREFIX)) == 0) {
				$name = substr($name, strlen(METADATA_HEADER_PREFIX));
				$this->_obj_metadata[$name] = $value;
			}
			elseif ((strncasecmp($name, CONTENT_HEADER_PREFIX, strlen(CONTENT_HEADER_PREFIX)) == 0) ||
					(strncasecmp($name, ACCESS_CONTROL_HEADER_PREFIX, strlen(ACCESS_CONTROL_HEADER_PREFIX)) == 0)) {
				$this->_obj_headers[$name] = $value;
			}
		}
		return $header_len;
	}

	private function _read_cb($ch, $fd, $length)
	{
		$data = fread($fd, $length);
		$len = strlen($data);
		if (isset($this->_user_write_progress_callback_func)) {
			call_user_func($this->_user_write_progress_callback_func, $len);
		}
		return $data;
	}

	private function _write_cb($ch, $data)
	{
		$dlen = strlen($data);
		switch ($this->_write_callback_type) {
		case "TEXT_LIST":
		 $this->_return_list = $this->_return_list . $data;
		 //= explode("\n",$data); # keep tab,space
		 //his->_text_list[] = rtrim($data,"\n\r\x0B"); # keep tab,space
			break;
		case "OBJECT_STREAM":
			fwrite($this->_obj_write_resource, $data, $dlen);
			break;
		case "OBJECT_STRING":
			$this->_obj_write_string .= $data;
			break;
		}
		if (isset($this->_user_read_progress_callback_func)) {
			call_user_func($this->_user_read_progress_callback_func, $dlen);
		}
		return $dlen;
	}

	private function _auth_hdr_cb($ch, $header)
	{
		preg_match("/^HTTP\/1\.[01] (\d{3}) (.*)/", $header, $matches);
		if (isset($matches[1])) {
			$this->response_status = $matches[1];
		}
		if (isset($matches[2])) {
			$this->response_reason = $matches[2];
		}
		if (stripos($header, STORAGE_URL) === 0) {
			$this->storage_url = trim(substr($header, strlen(STORAGE_URL)+1));
		}
		if (stripos($header, CDNM_URL) === 0) {
			$this->cdnm_url = trim(substr($header, strlen(CDNM_URL)+1));
		}
		if (stripos($header, AUTH_TOKEN) === 0) {
			$this->auth_token = trim(substr($header, strlen(AUTH_TOKEN)+1));
		}
		if (stripos($header, AUTH_TOKEN_LEGACY) === 0) {
			$this->auth_token = trim(substr($header,strlen(AUTH_TOKEN_LEGACY)+1));
		}
		return strlen($header);
	}

	private function _make_headers($hdrs=NULL)
	{
		$new_headers = array();
		$has_stoken = False;
		$has_uagent = False;
		if (is_array($hdrs)) {
			foreach ($hdrs as $h => $v) {
				if (is_int($h)) {
					list($h, $v) = explode(":", $v, 2);
				}

				if (strncasecmp($h, AUTH_TOKEN, strlen(AUTH_TOKEN)) === 0) {
					$has_stoken = True;
				}
				if (strncasecmp($h, USER_AGENT_HEADER, strlen(USER_AGENT_HEADER)) === 0) {
					$has_uagent = True;
				}
				$new_headers[] = $h . ": " . trim($v);
			}
		}
		if (!$has_stoken) {
			$new_headers[] = AUTH_TOKEN . ": " . $this->auth_token;
		}
		if (!$has_uagent) {
			$new_headers[] = USER_AGENT_HEADER . ": " . USER_AGENT;
		}
		return $new_headers;
	}

	private function _init($conn_type, $force_new=False)
	{
		if (!array_key_exists($conn_type, $this->connections)) {
			$this->error_str = "Invalid CURL_XXX connection type";
			return False;
		}

		if (is_null($this->connections[$conn_type]) || $force_new) {
			$ch = curl_init();
		} else {
			return;
		}

		if ($this->dbug) { curl_setopt($ch, CURLOPT_VERBOSE, 1); }

		if (!is_null($this->cabundle_path)) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, True);
			curl_setopt($ch, CURLOPT_CAINFO, $this->cabundle_path);
		}
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, True);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, '_header_cb'));

		if ($conn_type == "GET_CALL") {
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(&$this, '_write_cb'));
		}

		if ($conn_type == "PUT_OBJ") {
			curl_setopt($ch, CURLOPT_PUT, 1);
			curl_setopt($ch, CURLOPT_READFUNCTION, array(&$this, '_read_cb'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		}
		if ($conn_type == "HEAD") {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "HEAD");
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		if ($conn_type == "PUT_CONT") {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_INFILESIZE, 0);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		if ($conn_type == "DEL_POST") {
			curl_setopt($ch, CURLOPT_NOBODY, 1);
	}
		if ($conn_type == "COPY") {
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		$this->connections[$conn_type] = $ch;
		return;
	}

	private function _reset_callback_vars()
	{
		$this->_text_list = array();
		$this->_return_list = NULL;
		$this->_account_container_count = 0;
		$this->_account_bytes_used = 0;
		$this->_container_object_count = 0;
		$this->_container_bytes_used = 0;
		$this->_obj_etag = NULL;
		$this->_obj_last_modified = NULL;
		$this->_obj_content_type = NULL;
		$this->_obj_content_length = NULL;
		$this->_obj_metadata = array();
		$this->_obj_manifest = NULL;
		$this->_obj_headers = NULL;
		$this->_obj_write_string = "";
		$this->_cdn_streaming_uri = NULL;
		$this->_cdn_enabled = NULL;
		$this->_cdn_ssl_uri = NULL;
		$this->_cdn_uri = NULL;
		$this->_cdn_ttl = NULL;
		$this->response_status = 0;
		$this->response_reason = "";
	}

	private function _make_path($t="STORAGE",$c=NULL,$o=NULL)
	{
		$path = array();
		switch ($t) {
		case "STORAGE":
			$path[] = $this->storage_url; break;
		case "CDN":
			$path[] = $this->cdnm_url; break;
		}
		if ($c == "0")
			$path[] = rawurlencode($c);

		if ($c) {
			$path[] = rawurlencode($c);
		}
		if ($o) {
			# mimic Python''s urllib.quote() feature of a "safe" '/' character
			#
			$path[] = str_replace("%2F","/",rawurlencode($o));
		}
		return implode("/",$path);
	}

	private function _headers(&$obj)
	{
		$hdrs = self::_process_headers($obj->metadata, $obj->headers);
		if ($obj->manifest)
			$hdrs[MANIFEST_HEADER] = $obj->manifest;

		return $hdrs;
	}

	private function _process_headers($metadata=null, $headers=null)
	{
		$rules = array(
			array(
				'prefix' => METADATA_HEADER_PREFIX,
			),
			array(
				'prefix' => '',
				'filter' => array( # key order is important, first match decides
					CONTENT_TYPE_HEADER		  => false,
					CONTENT_LENGTH_HEADER		=> false,
					CONTENT_HEADER_PREFIX		=> true,
					ACCESS_CONTROL_HEADER_PREFIX => true,
					ORIGIN_HEADER				=> true,
				),
			),
		);

		$hdrs = array();
		$argc = func_num_args();
		$argv = func_get_args();
		for ($argi = 0; $argi < $argc; $argi++) {
			if(!is_array($argv[$argi])) continue;

			$rule = $rules[$argi];
			foreach ($argv[$argi] as $k => $v) {
				$k = trim($k);
				$v = trim($v);
				if (strpos($k, ":") !== False) throw new SyntaxException(
					"Header names cannot contain a ':' character.");

				if (array_key_exists('filter', $rule)) {
					$result = null;
					foreach ($rule['filter'] as $p => $f) {
						if (strncasecmp($k, $p, strlen($p)) == 0) {
							$result = $f;
							break;
						}
					}
					if (!$result) throw new SyntaxException(sprintf(
						"Header name %s is not allowed", $k));
				}

				$k = $rule['prefix'] . $k;
				if (strlen($k) > MAX_HEADER_NAME_LEN || strlen($v) > MAX_HEADER_VALUE_LEN)
					throw new SyntaxException(sprintf(
						"Header %s exceeds maximum length: %d/%d",
							$k, strlen($k), strlen($v)));

				$hdrs[$k] = $v;
			}
		}

		return $hdrs;
	}
	
	private function _send_request($conn_type, $url_path, $hdrs=NULL, $method="GET", $force_new=False)
	{
		$this->_init($conn_type, $force_new);
		$this->_reset_callback_vars();
		$headers = $this->_make_headers($hdrs);

		if (gettype($this->connections[$conn_type]) == "unknown type")
			throw new ConnectionNotOpenException (
				"Connection is not open."
				);
		
		switch ($method) {
		case "COPY":
			curl_setopt($this->connections[$conn_type],
				CURLOPT_CUSTOMREQUEST, "COPY");
			break;
		case "DELETE":
			curl_setopt($this->connections[$conn_type],
				CURLOPT_CUSTOMREQUEST, "DELETE");
			break;
		case "POST":
			curl_setopt($this->connections[$conn_type],
				CURLOPT_CUSTOMREQUEST, "POST");
		default:
			break;
		}		

		curl_setopt($this->connections[$conn_type],
					CURLOPT_HTTPHEADER, $headers);

		curl_setopt($this->connections[$conn_type],
			CURLOPT_URL, $url_path);

		if (!curl_exec($this->connections[$conn_type]) && curl_errno($this->connections[$conn_type]) !== 0) {
			$this->error_str = "(curl error: "
				. curl_errno($this->connections[$conn_type]) . ") ";
			$this->error_str .= curl_error($this->connections[$conn_type]);
			return False;
		}
		return curl_getinfo($this->connections[$conn_type], CURLINFO_HTTP_CODE);
	}
	
	function close()
	{
		foreach ($this->connections as $cnx) {
			if (isset($cnx)) {
				curl_close($cnx);
				$this->connections[$cnx] = NULL;
			}
		}
	}
	private function create_array()
	{
	$this->_text_list = explode("\n",rtrim($this->_return_list,"\n\x0B"));
	return True;
	}

}


 
define("DEFAULT_CF_API_VERSION", 1);
define("MAX_CONTAINER_NAME_LEN", 256);
define("MAX_OBJECT_NAME_LEN", 1024);
define("MAX_OBJECT_SIZE", 5*1024*1024*1024+1);
define("US_AUTHURL", "https://auth.api.rackspacecloud.com");
define("UK_AUTHURL", "https://lon.auth.api.rackspacecloud.com");
/**
 * Class for handling Cloud Files Authentication, call it's {@link authenticate()}
 * method to obtain authorized service urls and an authentication token.
 *
 * Example:
 * <code>
 * # Create the authentication instance
 * #
 * $auth = new CF_Authentication("username", "api_key");
 *
 * # NOTE: For UK Customers please specify your AuthURL Manually
 * # There is a Predfined constant to use EX:
 * #
 * # $auth = new CF_Authentication("username, "api_key", NULL, UK_AUTHURL);
 * # Using the UK_AUTHURL keyword will force the api to use the UK AuthUrl.
 * # rather then the US one. The NULL Is passed for legacy purposes and must
 * # be passed to function correctly.
 *
 * # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 * #	   file.  This API ships with a newer version obtained directly from
 * #	   cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 * #	   call the CF_Authentication instance's 'ssl_use_cabundle()' method.
 * #
 * # $auth->ssl_use_cabundle(); # bypass cURL's old CA bundle
 *
 * # Perform authentication request
 * #
 * $auth->authenticate();
 * </code>
 *
 * @package php-cloudfiles
 */
class CF_Authentication
{
	public $dbug;
	public $username;
	public $api_key;
	public $auth_host;
	public $account;

	/**
	 * Instance variables that are set after successful authentication
	 */
	public $storage_url;
	public $cdnm_url;
	public $auth_token;

	/**
	 * Class constructor (PHP 5 syntax)
	 *
	 * @param string $username Mosso username
	 * @param string $api_key Mosso API Access Key
	 * @param string $account  <i>Account name</i>
	 * @param string $auth_host  <i>Authentication service URI</i>
	 */
	function __construct($username=NULL, $api_key=NULL, $account=NULL, $auth_host=US_AUTHURL)
	{

		$this->dbug = False;
		$this->username = $username;
		$this->api_key = $api_key;
		$this->account_name = $account;
		$this->auth_host = $auth_host;

		$this->storage_url = NULL;
		$this->cdnm_url = NULL;
		$this->auth_token = NULL;

		$this->cfs_http = new CF_Http(DEFAULT_CF_API_VERSION);
	}

	/**
	 * Use the Certificate Authority bundle included with this API
	 *
	 * Most versions of PHP with cURL support include an outdated Certificate
	 * Authority (CA) bundle (the file that lists all valid certificate
	 * signing authorities).  The SSL certificates used by the Cloud Files
	 * storage system are perfectly valid but have been created/signed by
	 * a CA not listed in these outdated cURL distributions.
	 *
	 * As a work-around, we've included an updated CA bundle obtained
	 * directly from cURL's web site (http://curl.haxx.se).  You can direct
	 * the API to use this CA bundle by calling this method prior to making
	 * any remote calls.  The best place to use this method is right after
	 * the CF_Authentication instance has been instantiated.
	 *
	 * You can specify your own CA bundle by passing in the full pathname
	 * to the bundle.  You can use the included CA bundle by leaving the
	 * argument blank.
	 *
	 * @param string $path Specify path to CA bundle (default to included)
	 */
	function ssl_use_cabundle($path=NULL)
	{
		$this->cfs_http->ssl_use_cabundle($path);
	}

	/**
	 * Attempt to validate Username/API Access Key
	 *
	 * Attempts to validate credentials with the authentication service.  It
	 * either returns <kbd>True</kbd> or throws an Exception.  Accepts a single
	 * (optional) argument for the storage system API version.
	 *
	 * Example:
	 * <code>
	 * # Create the authentication instance
	 * #
	 * $auth = new CF_Authentication("username", "api_key");
	 *
	 * # Perform authentication request
	 * #
	 * $auth->authenticate();
	 * </code>
	 *
	 * @param string $version API version for Auth service (optional)
	 * @return boolean <kbd>True</kbd> if successfully authenticated
	 * @throws AuthenticationException invalid credentials
	 * @throws InvalidResponseException invalid response
	 */
	function authenticate($version=DEFAULT_CF_API_VERSION)
	{
		list($status,$reason,$surl,$curl,$atoken) = 
				$this->cfs_http->authenticate($this->username, $this->api_key,
				$this->account_name, $this->auth_host);

		if ($status == 401) {
			throw new AuthenticationException("Invalid username or access key.");
		}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Unexpected response (".$status."): ".$reason);
		}

		if (!($surl || $curl) || !$atoken) {
			throw new InvalidResponseException(
				"Expected headers missing from auth service.");
		}
		$this->storage_url = $surl;
		$this->cdnm_url = $curl;
		$this->auth_token = $atoken;
		return True;
	}
	/**
	 * Use Cached Token and Storage URL's rather then grabbing from the Auth System
		 *
		 * Example:
 	 * <code>
		 * #Create an Auth instance
		 * $auth = new CF_Authentication();
		 * #Pass Cached URL's and Token as Args
	 * $auth->load_cached_credentials("auth_token", "storage_url", "cdn_management_url");
		 * </code>
	 * 
	 * @param string $auth_token A Cloud Files Auth Token (Required)
		 * @param string $storage_url The Cloud Files Storage URL (Required)
		 * @param string $cdnm_url CDN Management URL (Required)
		 * @return boolean <kbd>True</kbd> if successful 
	 * @throws SyntaxException If any of the Required Arguments are missing
		 */
	function load_cached_credentials($auth_token, $storage_url, $cdnm_url)
	{
		if(!$storage_url || !$cdnm_url)
		{
				throw new SyntaxException("Missing Required Interface URL's!");
				return False;
		}
		if(!$auth_token)
		{
				throw new SyntaxException("Missing Auth Token!");
				return False;
		}

		$this->storage_url = $storage_url;
		$this->cdnm_url	= $cdnm_url;
		$this->auth_token  = $auth_token;
		return True;
	}
	/**
		 * Grab Cloud Files info to be Cached for later use with the load_cached_credentials method.
		 *
	 * Example:
		 * <code>
		 * #Create an Auth instance
		 * $auth = new CF_Authentication("UserName","API_Key");
		 * $auth->authenticate();
		 * $array = $auth->export_credentials();
		 * </code>
		 * 
	 * @return array of url's and an auth token.
		 */
	function export_credentials()
	{
		$arr = array();
		$arr['storage_url'] = $this->storage_url;
		$arr['cdnm_url']	= $this->cdnm_url;
		$arr['auth_token']  = $this->auth_token;

		return $arr;
	}


	/**
	 * Make sure the CF_Authentication instance has authenticated.
	 *
	 * Ensures that the instance variables necessary to communicate with
	 * Cloud Files have been set from a previous authenticate() call.
	 *
	 * @return boolean <kbd>True</kbd> if successfully authenticated
	 */
	function authenticated()
	{
		if (!($this->storage_url || $this->cdnm_url) || !$this->auth_token) {
			return False;
		}
		return True;
	}

	/**
	 * Toggle debugging - set cURL verbose flag
	 */
	function setDebug($bool)
	{
		$this->dbug = $bool;
		$this->cfs_http->setDebug($bool);
	}
}

/**
 * Class for establishing connections to the Cloud Files storage system.
 * Connection instances are used to communicate with the storage system at
 * the account level; listing and deleting Containers and returning Container
 * instances.
 *
 * Example:
 * <code>
 * # Create the authentication instance
 * #
 * $auth = new CF_Authentication("username", "api_key");
 *
 * # Perform authentication request
 * #
 * $auth->authenticate();
 *
 * # Create a connection to the storage/cdn system(s) and pass in the
 * # validated CF_Authentication instance.
 * #
 * $conn = new CF_Connection($auth);
 *
 * # NOTE: Some versions of cURL include an outdated certificate authority (CA)
 * #	   file.  This API ships with a newer version obtained directly from
 * #	   cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
 * #	   call the CF_Authentication instance's 'ssl_use_cabundle()' method.
 * #
 * # $conn->ssl_use_cabundle(); # bypass cURL's old CA bundle
 * </code>
 *
 * @package php-cloudfiles
 */
class CF_Connection
{
	public $dbug;
	public $cfs_http;
	public $cfs_auth;

	/**
	 * Pass in a previously authenticated CF_Authentication instance.
	 *
	 * Example:
	 * <code>
	 * # Create the authentication instance
	 * #
	 * $auth = new CF_Authentication("username", "api_key");
	 *
	 * # Perform authentication request
	 * #
	 * $auth->authenticate();
	 *
	 * # Create a connection to the storage/cdn system(s) and pass in the
	 * # validated CF_Authentication instance.
	 * #
	 * $conn = new CF_Connection($auth);
	 *
	 * # If you are connecting via Rackspace servers and have access
	 * # to the servicenet network you can set the $servicenet to True
	 * # like this.
	 *
	 * $conn = new CF_Connection($auth, $servicenet=True);
	 *
	 * </code>
	 *
	 * If the environement variable RACKSPACE_SERVICENET is defined it will
	 * force to connect via the servicenet.
	 *
	 * @param obj $cfs_auth previously authenticated CF_Authentication instance
	 * @param boolean $servicenet enable/disable access via Rackspace servicenet.
	 * @throws AuthenticationException not authenticated
	 */
	function __construct($cfs_auth, $servicenet=False)
	{
		if (isset($_ENV['RACKSPACE_SERVICENET']))
			$servicenet=True;
		$this->cfs_http = new CF_Http(DEFAULT_CF_API_VERSION);
		$this->cfs_auth = $cfs_auth;
		if (!$this->cfs_auth->authenticated()) {
			$e = "Need to pass in a previously authenticated ";
			$e .= "CF_Authentication instance.";
			throw new AuthenticationException($e);
		}
		$this->cfs_http->setCFAuth($this->cfs_auth, $servicenet=$servicenet);
		$this->dbug = False;
	}

	/**
	 * Toggle debugging of instance and back-end HTTP module
	 *
	 * @param boolean $bool enable/disable cURL debugging
	 */
	function setDebug($bool)
	{
		$this->dbug = (boolean) $bool;
		$this->cfs_http->setDebug($this->dbug);
	}

	/**
	 * Close a connection
	 *
	 * Example:
	 * <code>
	 *  
	 * $conn->close();
	 * 
	 * </code>
	 *
	 * Will close all current cUrl active connections.
	 * 
	 */
	public function close()
	{
		$this->cfs_http->close();
	}
	
	/**
	 * Cloud Files account information
	 *
	 * Return an array of two floats (since PHP only supports 32-bit integers);
	 * number of containers on the account and total bytes used for the account.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * list($quantity, $bytes) = $conn->get_info();
	 * print "Number of containers: " . $quantity . "\n";
	 * print "Bytes stored in container: " . $bytes . "\n";
	 * </code>
	 *
	 * @return array (number of containers, total bytes stored)
	 * @throws InvalidResponseException unexpected response
	 */
	function get_info()
	{
		list($status, $reason, $container_count, $total_bytes) =
				$this->cfs_http->head_account();
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->get_info();
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return array($container_count, $total_bytes);
	}

	/**
	 * Create a Container
	 *
	 * Given a Container name, return a Container instance, creating a new
	 * remote Container if it does not exit.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->create_container("my photos");
	 * </code>
	 *
	 * @param string $container_name container name
	 * @return CF_Container
	 * @throws SyntaxException invalid name
	 * @throws InvalidResponseException unexpected response
	 */
	function create_container($container_name=NULL)
	{
		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Container name not set.");
		
		if (!isset($container_name) or $container_name == "") 
			throw new SyntaxException("Container name not set.");

		if (strpos($container_name, "/") !== False) {
			$r = "Container name '".$container_name;
			$r .= "' cannot contain a '/' character.";
			throw new SyntaxException($r);
		}
		if (strlen($container_name) > MAX_CONTAINER_NAME_LEN) {
			throw new SyntaxException(sprintf(
				"Container name exeeds %d bytes.",
				MAX_CONTAINER_NAME_LEN));
		}

		$return_code = $this->cfs_http->create_container($container_name);
		if (!$return_code) {
			throw new InvalidResponseException("Invalid response ("
				. $return_code. "): " . $this->cfs_http->get_error());
		}
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->create_container($container_name);
		#}
		if ($return_code != 201 && $return_code != 202) {
			throw new InvalidResponseException(
				"Invalid response (".$return_code."): "
					. $this->cfs_http->get_error());
		}
		return new CF_Container($this->cfs_auth, $this->cfs_http, $container_name);
	}

	/**
	 * Delete a Container
	 *
	 * Given either a Container instance or name, remove the remote Container.
	 * The Container must be empty prior to removing it.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $conn->delete_container("my photos");
	 * </code>
	 *
	 * @param string|obj $container container name or instance
	 * @return boolean <kbd>True</kbd> if successfully deleted
	 * @throws SyntaxException missing proper argument
	 * @throws InvalidResponseException invalid response
	 * @throws NonEmptyContainerException container not empty
	 * @throws NoSuchContainerException remote container does not exist
	 */
	function delete_container($container=NULL)
	{
		$container_name = NULL;
		
		if (is_object($container)) {
			if (get_class($container) == "CF_Container") {
				$container_name = $container->name;
			}
		}
		if (is_string($container)) {
			$container_name = $container;
		}

		if ($container_name != "0" and !isset($container_name))
			throw new SyntaxException("Must specify container object or name.");

		$return_code = $this->cfs_http->delete_container($container_name);

		if (!$return_code) {
			throw new InvalidResponseException("Failed to obtain http response");
		}
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->delete_container($container);
		#}
		if ($return_code == 409) {
			throw new NonEmptyContainerException(
				"Container must be empty prior to removing it.");
		}
		if ($return_code == 404) {
			throw new NoSuchContainerException(
				"Specified container did not exist to delete.");
		}
		if ($return_code != 204) {
			throw new InvalidResponseException(
				"Invalid response (".$return_code."): "
				. $this->cfs_http->get_error());
		}
		return True;
	}

	/**
	 * Return a Container instance
	 *
	 * For the given name, return a Container instance if the remote Container
	 * exists, otherwise throw a Not Found exception.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 * print "Number of Objects: " . $images->count . "\n";
	 * print "Bytes stored in container: " . $images->bytes . "\n";
	 * </code>
	 *
	 * @param string $container_name name of the remote Container
	 * @return container CF_Container instance
	 * @throws NoSuchContainerException thrown if no remote Container
	 * @throws InvalidResponseException unexpected response
	 */
	function get_container($container_name=NULL)
	{
		list($status, $reason, $count, $bytes) =
				$this->cfs_http->head_container($container_name);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->get_container($container_name);
		#}
		if ($status == 404) {
			throw new NoSuchContainerException("Container not found.");
		}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response: ".$this->cfs_http->get_error());
		}
		return new CF_Container($this->cfs_auth, $this->cfs_http,
			$container_name, $count, $bytes);
	}

	/**
	 * Return array of Container instances
	 *
	 * Return an array of CF_Container instances on the account.  The instances
	 * will be fully populated with Container attributes (bytes stored and
	 * Object count)
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $clist = $conn->get_containers();
	 * foreach ($clist as $cont) {
	 *	 print "Container name: " . $cont->name . "\n";
	 *	 print "Number of Objects: " . $cont->count . "\n";
	 *	 print "Bytes stored in container: " . $cont->bytes . "\n";
	 * }
	 * </code>
	 *
	 * @return array An array of CF_Container instances
	 * @throws InvalidResponseException unexpected response
	 */
	function get_containers($limit=0, $marker=NULL)
	{
		list($status, $reason, $container_info) =
				$this->cfs_http->list_containers_info($limit, $marker);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->get_containers();
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response: ".$this->cfs_http->get_error());
		}
		$containers = array();
		foreach ($container_info as $name => $info) {
			$containers[] = new CF_Container($this->cfs_auth, $this->cfs_http,
				$info['name'], $info["count"], $info["bytes"], False);
		}
		return $containers;
	}

	/**
	 * Return list of remote Containers
	 *
	 * Return an array of strings containing the names of all remote Containers.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $container_list = $conn->list_containers();
	 * print_r($container_list);
	 * Array
	 * (
	 *	 [0] => "my photos",
	 *	 [1] => "my docs"
	 * )
	 * </code>
	 *
	 * @param integer $limit restrict results to $limit Containers
	 * @param string $marker return results greater than $marker
	 * @return array list of remote Containers
	 * @throws InvalidResponseException unexpected response
	 */
	function list_containers($limit=0, $marker=NULL)
	{
		list($status, $reason, $containers) =
			$this->cfs_http->list_containers($limit, $marker);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->list_containers($limit, $marker);
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return $containers;
	}

	/**
	 * Return array of information about remote Containers
	 *
	 * Return a nested array structure of Container info.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 *
	 * $container_info = $conn->list_containers_info();
	 * print_r($container_info);
	 * Array
	 * (
	 *	 ["my photos"] =>
	 *		 Array
	 *		 (
	 *			 ["bytes"] => 78,
	 *			 ["count"] => 2
	 *		 )
	 *	 ["docs"] =>
	 *		 Array
	 *		 (
	 *			 ["bytes"] => 37323,
	 *			 ["count"] => 12
	 *		 )
	 * )
	 * </code>
	 *
	 * @param integer $limit restrict results to $limit Containers
	 * @param string $marker return results greater than $marker
	 * @return array nested array structure of Container info
	 * @throws InvalidResponseException unexpected response
	 */
	function list_containers_info($limit=0, $marker=NULL)
	{
		list($status, $reason, $container_info) = 
				$this->cfs_http->list_containers_info($limit, $marker);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->list_containers_info($limit, $marker);
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return $container_info;
	}

	/**
	 * Return list of Containers that have been published to the CDN.
	 *
	 * Return an array of strings containing the names of published Containers.
	 * Note that this function returns the list of any Container that has
	 * ever been CDN-enabled regardless of it's existence in the storage
	 * system.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_containers = $conn->list_public_containers();
	 * print_r($public_containers);
	 * Array
	 * (
	 *	 [0] => "images",
	 *	 [1] => "css",
	 *	 [2] => "javascript"
	 * )
	 * </code>
	 *
	 * @param bool $enabled_only Will list all containers ever CDN enabled if	 * set to false or only currently enabled CDN containers if set to true.	  * Defaults to false.
	 * @return array list of published Container names
	 * @throws InvalidResponseException unexpected response
	 */
	function list_public_containers($enabled_only=False)
	{
		list($status, $reason, $containers) =
				$this->cfs_http->list_cdn_containers($enabled_only);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->list_public_containers();
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return $containers;
	}

	/**
	 * Set a user-supplied callback function to report download progress
	 *
	 * The callback function is used to report incremental progress of a data
	 * download functions (e.g. $container->list_objects(), $obj->read(), etc).
	 * The specified function will be periodically called with the number of
	 * bytes transferred until the entire download is complete.  This callback
	 * function can be useful for implementing "progress bars" for large
	 * downloads.
	 *
	 * The specified callback function should take a single integer parameter.
	 *
	 * <code>
	 * function read_callback($bytes_transferred) {
	 *	 print ">> downloaded " . $bytes_transferred . " bytes.\n";
	 *	 # ... do other things ...
	 *	 return;
	 * }
	 *
	 * $conn = new CF_Connection($auth_obj);
	 * $conn->set_read_progress_function("read_callback");
	 * print_r($conn->list_containers());
	 *
	 * # output would look like this:
	 * #
	 * >> downloaded 10 bytes.
	 * >> downloaded 11 bytes.
	 * Array
	 * (
	 *	  [0] => fuzzy.txt
	 *	  [1] => space name
	 * )
	 * </code>
	 *
	 * @param string $func_name the name of the user callback function
	 */
	function set_read_progress_function($func_name)
	{
		$this->cfs_http->setReadProgressFunc($func_name);
	}

	/**
	 * Set a user-supplied callback function to report upload progress
	 *
	 * The callback function is used to report incremental progress of a data
	 * upload functions (e.g. $obj->write() call).  The specified function will
	 * be periodically called with the number of bytes transferred until the
	 * entire upload is complete.  This callback function can be useful
	 * for implementing "progress bars" for large uploads/downloads.
	 *
	 * The specified callback function should take a single integer parameter.
	 *
	 * <code>
	 * function write_callback($bytes_transferred) {
	 *	 print ">> uploaded " . $bytes_transferred . " bytes.\n";
	 *	 # ... do other things ...
	 *	 return;
	 * }
	 *
	 * $conn = new CF_Connection($auth_obj);
	 * $conn->set_write_progress_function("write_callback");
	 * $container = $conn->create_container("stuff");
	 * $obj = $container->create_object("foo");
	 * $obj->write("The callback function will be called during upload.");
	 *
	 * # output would look like this:
	 * # >> uploaded 51 bytes.
	 * #
	 * </code>
	 *
	 * @param string $func_name the name of the user callback function
	 */
	function set_write_progress_function($func_name)
	{
		$this->cfs_http->setWriteProgressFunc($func_name);
	}

	/**
	 * Use the Certificate Authority bundle included with this API
	 *
	 * Most versions of PHP with cURL support include an outdated Certificate
	 * Authority (CA) bundle (the file that lists all valid certificate
	 * signing authorities).  The SSL certificates used by the Cloud Files
	 * storage system are perfectly valid but have been created/signed by
	 * a CA not listed in these outdated cURL distributions.
	 *
	 * As a work-around, we've included an updated CA bundle obtained
	 * directly from cURL's web site (http://curl.haxx.se).  You can direct
	 * the API to use this CA bundle by calling this method prior to making
	 * any remote calls.  The best place to use this method is right after
	 * the CF_Authentication instance has been instantiated.
	 *
	 * You can specify your own CA bundle by passing in the full pathname
	 * to the bundle.  You can use the included CA bundle by leaving the
	 * argument blank.
	 *
	 * @param string $path Specify path to CA bundle (default to included)
	 */
	function ssl_use_cabundle($path=NULL)
	{
		$this->cfs_http->ssl_use_cabundle($path);
	}

	#private function _re_auth()
	#{
	#	$new_auth = new CF_Authentication(
	#		$this->cfs_auth->username,
	#		$this->cfs_auth->api_key,
	#		$this->cfs_auth->auth_host,
	#		$this->cfs_auth->account);
	#	$new_auth->authenticate();
	#	$this->cfs_auth = $new_auth;
	#	$this->cfs_http->setCFAuth($this->cfs_auth);
	#	return True;
	#}
}

/**
 * Container operations
 *
 * Containers are storage compartments where you put your data (objects).
 * A container is similar to a directory or folder on a conventional filesystem
 * with the exception that they exist in a flat namespace, you can not create
 * containers inside of containers.
 *
 * You also have the option of marking a Container as "public" so that the
 * Objects stored in the Container are publicly available via the CDN.
 *
 * @package php-cloudfiles
 */
class CF_Container
{
	public $cfs_auth;
	public $cfs_http;
	public $name;
	public $object_count;
	public $bytes_used;

	public $cdn_enabled;
	public $cdn_streaming_uri;
	public $cdn_ssl_uri;
	public $cdn_uri;
	public $cdn_ttl;
	public $cdn_log_retention;
	public $cdn_acl_user_agent;
	public $cdn_acl_referrer;

	/**
	 * Class constructor
	 *
	 * Constructor for Container
	 *
	 * @param obj $cfs_auth CF_Authentication instance
	 * @param obj $cfs_http HTTP connection manager
	 * @param string $name name of Container
	 * @param int $count number of Objects stored in this Container
	 * @param int $bytes number of bytes stored in this Container
	 * @throws SyntaxException invalid Container name
	 */
	function __construct(&$cfs_auth, &$cfs_http, $name, $count=0,
		$bytes=0, $docdn=True)
	{
		if (strlen($name) > MAX_CONTAINER_NAME_LEN) {
			throw new SyntaxException("Container name exceeds "
				. "maximum allowed length.");
		}
		if (strpos($name, "/") !== False) {
			throw new SyntaxException(
				"Container names cannot contain a '/' character.");
		}
		$this->cfs_auth = $cfs_auth;
		$this->cfs_http = $cfs_http;
		$this->name = $name;
		$this->object_count = $count;
		$this->bytes_used = $bytes;
		$this->cdn_enabled = NULL;
		$this->cdn_uri = NULL;
		$this->cdn_ssl_uri = NULL;
		$this->cdn_streaming_uri = NULL;
		$this->cdn_ttl = NULL;
		$this->cdn_log_retention = NULL;
		$this->cdn_acl_user_agent = NULL;
		$this->cdn_acl_referrer = NULL;
		if ($this->cfs_http->getCDNMUrl() != NULL && $docdn) {
			$this->_cdn_initialize();
		}
	}

	/**
	 * String representation of Container
	 *
	 * Pretty print the Container instance.
	 *
	 * @return string Container details
	 */
	function __toString()
	{
		$me = sprintf("name: %s, count: %.0f, bytes: %.0f",
			$this->name, $this->object_count, $this->bytes_used);
		if ($this->cfs_http->getCDNMUrl() != NULL) {
			$me .= sprintf(", cdn: %s, cdn uri: %s, cdn ttl: %.0f, logs retention: %s",
				$this->is_public() ? "Yes" : "No",
				$this->cdn_uri, $this->cdn_ttl,
				$this->cdn_log_retention ? "Yes" : "No"
				);

			if ($this->cdn_acl_user_agent != NULL) {
				$me .= ", cdn acl user agent: " . $this->cdn_acl_user_agent;
			}

			if ($this->cdn_acl_referrer != NULL) {
				$me .= ", cdn acl referrer: " . $this->cdn_acl_referrer;
			}
			
			
		}
		return $me;
	}

	/**
	 * Enable Container content to be served via CDN or modify CDN attributes
	 *
	 * Either enable this Container's content to be served via CDN or
	 * adjust its CDN attributes.  This Container will always return the
	 * same CDN-enabled URI each time it is toggled public/private/public.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->create_container("public");
	 *
	 * # CDN-enable the container and set it's TTL for a month
	 * #
	 * $public_container->make_public(86400/2); # 12 hours (86400 seconds/day)
	 * </code>
	 *
	 * @param int $ttl the time in seconds content will be cached in the CDN
	 * @returns string the CDN enabled Container's URI
	 * @throws CDNNotEnabledException CDN functionality not returned during auth
	 * @throws AuthenticationException if auth token is not valid/expired
	 * @throws InvalidResponseException unexpected response
	 */
	function make_public($ttl=86400)
	{
		if ($this->cfs_http->getCDNMUrl() == NULL) {
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		if ($this->cdn_uri != NULL) {
			# previously published, assume we're setting new attributes
			list($status, $reason, $cdn_uri, $cdn_ssl_uri) =
				$this->cfs_http->update_cdn_container($this->name,$ttl,
													  $this->cdn_log_retention,
													  $this->cdn_acl_user_agent,
													  $this->cdn_acl_referrer);
			#if ($status == 401 && $this->_re_auth()) {
			#	return $this->make_public($ttl);
			#}
			if ($status == 404) {
				# this instance _thinks_ the container was published, but the
				# cdn management system thinks otherwise - try again with a PUT
				list($status, $reason, $cdn_uri, $cdn_ssl_uri) =
					$this->cfs_http->add_cdn_container($this->name,$ttl);

			}
		} else {
			# publish it for first time
			list($status, $reason, $cdn_uri, $cdn_ssl_uri) =
				$this->cfs_http->add_cdn_container($this->name,$ttl);
		}
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->make_public($ttl);
		#}
		if (!in_array($status, array(201,202))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_enabled = True;
		$this->cdn_ttl = $ttl;
		$this->cdn_ssl_uri = $cdn_ssl_uri;
		$this->cdn_uri = $cdn_uri;
		$this->cdn_log_retention = False;
		$this->cdn_acl_user_agent = "";
		$this->cdn_acl_referrer = "";
		return $this->cdn_uri;
	}
	/**
	 * Purge Containers objects from CDN Cache.
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 * $container = $conn->get_container("cdn_enabled");
	 * $container->purge_from_cdn("user@domain.com");
	 * # or
	 * $container->purge_from_cdn();
	 * # or 
	 * $container->purge_from_cdn("user1@domain.com,user2@domain.com");
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException if CDN Is not enabled on this connection
	 * @throws InvalidResponseException if the response expected is not returned
	 */
	function purge_from_cdn($email=null)
	{
		if (!$this->cfs_http->getCDNMUrl()) 
		{
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		$status = $this->cfs_http->purge_from_cdn($this->name, $email);
		if ($status < 199 or $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		} 
		return True;
	}
	/**
	 * Enable ACL restriction by User Agent for this container.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # Enable ACL by Referrer
	 * $public_container->acl_referrer("Mozilla");
	 * </code>
	 *
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException CDN functionality not returned during auth
	 * @throws AuthenticationException if auth token is not valid/expired
	 * @throws InvalidResponseException unexpected response
	 */
	function acl_user_agent($cdn_acl_user_agent="") {
		if ($this->cfs_http->getCDNMUrl() == NULL) {
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		list($status,$reason) =
			$this->cfs_http->update_cdn_container($this->name,
												  $this->cdn_ttl,
												  $this->cdn_log_retention,
												  $cdn_acl_user_agent,
												  $this->cdn_acl_referrer
				);
		if (!in_array($status, array(202,404))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_acl_user_agent = $cdn_acl_user_agent;
		return True;
	}

	/**
	 * Enable ACL restriction by referer for this container.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # Enable Referrer
	 * $public_container->acl_referrer("http://www.example.com/gallery.php");
	 * </code>
	 *
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException CDN functionality not returned during auth
	 * @throws AuthenticationException if auth token is not valid/expired
	 * @throws InvalidResponseException unexpected response
	 */
	function acl_referrer($cdn_acl_referrer="") {
		if ($this->cfs_http->getCDNMUrl() == NULL) {
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		list($status,$reason) =
			$this->cfs_http->update_cdn_container($this->name,
												  $this->cdn_ttl,
												  $this->cdn_log_retention,
												  $this->cdn_acl_user_agent,
												  $cdn_acl_referrer
				);
		if (!in_array($status, array(202,404))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_acl_referrer = $cdn_acl_referrer;
		return True;
	}
	
	/**
	 * Enable log retention for this CDN container.
	 *
	 * Enable CDN log retention on the container. If enabled logs will
	 * be periodically (at unpredictable intervals) compressed and
	 * uploaded to a ".CDN_ACCESS_LOGS" container in the form of
	 * "container_name.YYYYMMDDHH-XXXX.gz". Requires CDN be enabled on
	 * the account.
	 * 
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # Enable logs retention.
	 * $public_container->log_retention(True);
	 * </code>
	 *
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException CDN functionality not returned during auth
	 * @throws AuthenticationException if auth token is not valid/expired
	 * @throws InvalidResponseException unexpected response
	 */
	function log_retention($cdn_log_retention=False) {
		if ($this->cfs_http->getCDNMUrl() == NULL) {
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		list($status,$reason) =
			$this->cfs_http->update_cdn_container($this->name,
												  $this->cdn_ttl,
												  $cdn_log_retention,
												  $this->cdn_acl_user_agent,
												  $this->cdn_acl_referrer
				);
		if (!in_array($status, array(202,404))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_log_retention = $cdn_log_retention;
		return True;
	}
	
	/**
	 * Disable the CDN sharing for this container
	 *
	 * Use this method to disallow distribution into the CDN of this Container's
	 * content.
	 *
	 * NOTE: Any content already cached in the CDN will continue to be served
	 *	   from its cache until the TTL expiration transpires.  The default
	 *	   TTL is typically one day, so "privatizing" the Container will take
	 *	   up to 24 hours before the content is purged from the CDN cache.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # Disable CDN accessability
	 * # ... still cached up to a month based on previous example
	 * #
	 * $public_container->make_private();
	 * </code>
	 *
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException CDN functionality not returned during auth
	 * @throws AuthenticationException if auth token is not valid/expired
	 * @throws InvalidResponseException unexpected response
	 */
	function make_private()
	{
		if ($this->cfs_http->getCDNMUrl() == NULL) {
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		list($status,$reason) = $this->cfs_http->remove_cdn_container($this->name);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->make_private();
		#}
		if (!in_array($status, array(202,404))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_enabled = False;
		$this->cdn_ttl = NULL;
		$this->cdn_uri = NULL;
		$this->cdn_ssl_uri = NULL;
		$this->cdn_streaming_uri - NULL;
		$this->cdn_log_retention = NULL;
		$this->cdn_acl_user_agent = NULL;
		$this->cdn_acl_referrer = NULL;
		return True;
	}

	/**
	 * Check if this Container is being publicly served via CDN
	 *
	 * Use this method to determine if the Container's content is currently
	 * available through the CDN.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # Display CDN accessability
	 * #
	 * $public_container->is_public() ? print "Yes" : print "No";
	 * </code>
	 *
	 * @returns boolean True if enabled, False otherwise
	 */
	function is_public()
	{
		return $this->cdn_enabled == True ? True : False;
	}

	/**
	 * Create a new remote storage Object
	 *
	 * Return a new Object instance.  If the remote storage Object exists,
	 * the instance's attributes are populated.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # This creates a local instance of a storage object but only creates
	 * # it in the storage system when the object's write() method is called.
	 * #
	 * $pic = $public_container->create_object("baby.jpg");
	 * </code>
	 *
	 * @param string $obj_name name of storage Object
	 * @return obj CF_Object instance
	 */
	function create_object($obj_name=NULL)
	{
		return new CF_Object($this, $obj_name);
	}

	/**
	 * Return an Object instance for the remote storage Object
	 *
	 * Given a name, return a Object instance representing the
	 * remote storage object.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $public_container = $conn->get_container("public");
	 *
	 * # This call only fetches header information and not the content of
	 * # the storage object.  Use the Object's read() or stream() methods
	 * # to obtain the object's data.
	 * #
	 * $pic = $public_container->get_object("baby.jpg");
	 * </code>
	 *
	 * @param string $obj_name name of storage Object
	 * @return obj CF_Object instance
	 */
	function get_object($obj_name=NULL)
	{
		return new CF_Object($this, $obj_name, True);
	}

	/**
	 * Return a list of Objects
	 *
	 * Return an array of strings listing the Object names in this Container.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $images = $conn->get_container("my photos");
	 *
	 * # Grab the list of all storage objects
	 * #
	 * $all_objects = $images->list_objects();
	 *
	 * # Grab subsets of all storage objects
	 * #
	 * $first_ten = $images->list_objects(10);
	 * 
	 * # Note the use of the previous result's last object name being
	 * # used as the 'marker' parameter to fetch the next 10 objects
	 * #
	 * $next_ten = $images->list_objects(10, $first_ten[count($first_ten)-1]);
	 *
	 * # Grab images starting with "birthday_party" and default limit/marker
	 * # to match all photos with that prefix
	 * #
	 * $prefixed = $images->list_objects(0, NULL, "birthday");
	 *
	 * # Assuming you have created the appropriate directory marker Objects,
	 * # you can traverse your pseudo-hierarchical containers
	 * # with the "path" argument.
	 * #
	 * $animals = $images->list_objects(0,NULL,NULL,"pictures/animals");
	 * $dogs = $images->list_objects(0,NULL,NULL,"pictures/animals/dogs");
	 * </code>
	 *
	 * @param int $limit <i>optional</i> only return $limit names
	 * @param int $marker <i>optional</i> subset of names starting at $marker
	 * @param string $prefix <i>optional</i> Objects whose names begin with $prefix
	 * @param string $path <i>optional</i> only return results under "pathname"
	 * @return array array of strings
	 * @throws InvalidResponseException unexpected response
	 */
	function list_objects($limit=0, $marker=NULL, $prefix=NULL, $path=NULL)
	{
		list($status, $reason, $obj_list) =
			$this->cfs_http->list_objects($this->name, $limit,
				$marker, $prefix, $path);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->list_objects($limit, $marker, $prefix, $path);
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return $obj_list;
	}

	/**
	 * Return an array of Objects
	 *
	 * Return an array of Object instances in this Container.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $images = $conn->get_container("my photos");
	 *
	 * # Grab the list of all storage objects
	 * #
	 * $all_objects = $images->get_objects();
	 *
	 * # Grab subsets of all storage objects
	 * #
	 * $first_ten = $images->get_objects(10);
	 *
	 * # Note the use of the previous result's last object name being
	 * # used as the 'marker' parameter to fetch the next 10 objects
	 * #
	 * $next_ten = $images->list_objects(10, $first_ten[count($first_ten)-1]);
	 *
	 * # Grab images starting with "birthday_party" and default limit/marker
	 * # to match all photos with that prefix
	 * #
	 * $prefixed = $images->get_objects(0, NULL, "birthday");
	 *
	 * # Assuming you have created the appropriate directory marker Objects,
	 * # you can traverse your pseudo-hierarchical containers
	 * # with the "path" argument.
	 * #
	 * $animals = $images->get_objects(0,NULL,NULL,"pictures/animals");
	 * $dogs = $images->get_objects(0,NULL,NULL,"pictures/animals/dogs");
	 * </code>
	 *
	 * @param int $limit <i>optional</i> only return $limit names
	 * @param int $marker <i>optional</i> subset of names starting at $marker
	 * @param string $prefix <i>optional</i> Objects whose names begin with $prefix
	 * @param string $path <i>optional</i> only return results under "pathname"
	 * @return array array of strings
	 * @throws InvalidResponseException unexpected response
	 */
	function get_objects($limit=0, $marker=NULL, $prefix=NULL, $path=NULL)
	{
		list($status, $reason, $obj_array) =
			$this->cfs_http->get_objects($this->name, $limit,
				$marker, $prefix, $path);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->get_objects($limit, $marker, $prefix, $path);
		#}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$objects = array();
		foreach ($obj_array as $obj) {
			$tmp = new CF_Object($this, $obj["name"], False, False);
			$tmp->content_type = $obj["content_type"];
			$tmp->content_length = (float) $obj["bytes"];
			$tmp->set_etag($obj["hash"]);
			$tmp->last_modified = $obj["last_modified"];
			$objects[] = $tmp;
		}
		return $objects;
	}

	/**
	 * Copy a remote storage Object to a target Container
	 *
	 * Given an Object instance or name and a target Container instance or name, copy copies the remote Object
	 * and all associated metadata.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 *
	 * # Copy specific object
	 * #
	 * $images->copy_object_to("disco_dancing.jpg","container_target");
	 * </code>
	 *
	 * @param obj $obj name or instance of Object to copy
	 * @param obj $container_target name or instance of target Container
	 * @param string $dest_obj_name name of target object (optional - uses source name if omitted)
	 * @param array $metadata metadata array for new object (optional)
	 * @param array $headers header fields array for the new object (optional)
	 * @return boolean <kbd>true</kbd> if successfully copied
	 * @throws SyntaxException invalid Object/Container name
	 * @throws NoSuchObjectException remote Object does not exist
	 * @throws InvalidResponseException unexpected response
	 */
	function copy_object_to($obj,$container_target,$dest_obj_name=NULL,$metadata=NULL,$headers=NULL)
	{
		$obj_name = NULL;
		if (is_object($obj)) {
			if (get_class($obj) == "CF_Object") {
				$obj_name = $obj->name;
			}
		}
		if (is_string($obj)) {
			$obj_name = $obj;
		}
		if (!$obj_name) {
			throw new SyntaxException("Object name not set.");
		}

				if ($dest_obj_name === NULL) {
			$dest_obj_name = $obj_name;
				}

		$container_name_target = NULL;
		if (is_object($container_target)) {
			if (get_class($container_target) == "CF_Container") {
				$container_name_target = $container_target->name;
			}
		}
		if (is_string($container_target)) {
			$container_name_target = $container_target;
		}
		if (!$container_name_target) {
			throw new SyntaxException("Container name target not set.");
		}

		$status = $this->cfs_http->copy_object($obj_name,$dest_obj_name,$this->name,$container_name_target,$metadata,$headers);
		if ($status == 404) {
			$m = "Specified object '".$this->name."/".$obj_name;
			$m.= "' did not exist as source to copy from or '".$container_name_target."' did not exist as target to copy to.";
			throw new NoSuchObjectException($m);
		}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return true;
	}

	/**
	 * Copy a remote storage Object from a source Container
	 *
	 * Given an Object instance or name and a source Container instance or name, copy copies the remote Object
	 * and all associated metadata.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 *
	 * # Copy specific object
	 * #
	 * $images->copy_object_from("disco_dancing.jpg","container_source");
	 * </code>
	 *
	 * @param obj $obj name or instance of Object to copy
	 * @param obj $container_source name or instance of source Container
	 * @param string $dest_obj_name name of target object (optional - uses source name if omitted)
	 * @param array $metadata metadata array for new object (optional)
	 * @param array $headers header fields array for the new object (optional)
	 * @return boolean <kbd>true</kbd> if successfully copied
	 * @throws SyntaxException invalid Object/Container name
	 * @throws NoSuchObjectException remote Object does not exist
	 * @throws InvalidResponseException unexpected response
	 */
	function copy_object_from($obj,$container_source,$dest_obj_name=NULL,$metadata=NULL,$headers=NULL)
	{
		$obj_name = NULL;
		if (is_object($obj)) {
			if (get_class($obj) == "CF_Object") {
				$obj_name = $obj->name;
			}
		}
		if (is_string($obj)) {
			$obj_name = $obj;
		}
		if (!$obj_name) {
			throw new SyntaxException("Object name not set.");
		}

				if ($dest_obj_name === NULL) {
			$dest_obj_name = $obj_name;
				}

		$container_name_source = NULL;
		if (is_object($container_source)) {
			if (get_class($container_source) == "CF_Container") {
				$container_name_source = $container_source->name;
			}
		}
		if (is_string($container_source)) {
			$container_name_source = $container_source;
		}
		if (!$container_name_source) {
			throw new SyntaxException("Container name source not set.");
		}

		$status = $this->cfs_http->copy_object($obj_name,$dest_obj_name,$container_name_source,$this->name,$metadata,$headers);
		if ($status == 404) {
			$m = "Specified object '".$container_name_source."/".$obj_name;
			$m.= "' did not exist as source to copy from or '".$this->name."/".$obj_name."' did not exist as target to copy to.";
			throw new NoSuchObjectException($m);
		}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		
		return true;
	}

	/**
	 * Move a remote storage Object to a target Container
	 *
	 * Given an Object instance or name and a target Container instance or name, move copies the remote Object
	 * and all associated metadata and deletes the source Object afterwards
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 *
	 * # Move specific object
	 * #
	 * $images->move_object_to("disco_dancing.jpg","container_target");
	 * </code>
	 *
	 * @param obj $obj name or instance of Object to move
	 * @param obj $container_target name or instance of target Container
	 * @param string $dest_obj_name name of target object (optional - uses source name if omitted)
	 * @param array $metadata metadata array for new object (optional)
	 * @param array $headers header fields array for the new object (optional)
	 * @return boolean <kbd>true</kbd> if successfully moved
	 * @throws SyntaxException invalid Object/Container name
	 * @throws NoSuchObjectException remote Object does not exist
	 * @throws InvalidResponseException unexpected response
	 */
	function move_object_to($obj,$container_target,$dest_obj_name=NULL,$metadata=NULL,$headers=NULL)
	{
		$retVal = false;

		if(self::copy_object_to($obj,$container_target,$dest_obj_name,$metadata,$headers)) {
			$retVal = self::delete_object($obj,$this->name);
		}

		return $retVal;
	}

	/**
	 * Move a remote storage Object from a source Container
	 *
	 * Given an Object instance or name and a source Container instance or name, move copies the remote Object
	 * and all associated metadata and deletes the source Object afterwards
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 *
	 * # Move specific object
	 * #
	 * $images->move_object_from("disco_dancing.jpg","container_target");
	 * </code>
	 *
	 * @param obj $obj name or instance of Object to move
	 * @param obj $container_source name or instance of target Container
	 * @param string $dest_obj_name name of target object (optional - uses source name if omitted)
	 * @param array $metadata metadata array for new object (optional)
	 * @param array $headers header fields array for the new object (optional)
	 * @return boolean <kbd>true</kbd> if successfully moved
	 * @throws SyntaxException invalid Object/Container name
	 * @throws NoSuchObjectException remote Object does not exist
	 * @throws InvalidResponseException unexpected response
	 */
	function move_object_from($obj,$container_source,$dest_obj_name=NULL,$metadata=NULL,$headers=NULL)
	{
		$retVal = false;

		if(self::copy_object_from($obj,$container_source,$dest_obj_name,$metadata,$headers)) {
			$retVal = self::delete_object($obj,$container_source);
		} 	

		return $retVal;
	}

	/**
	 * Delete a remote storage Object
	 *
	 * Given an Object instance or name, permanently remove the remote Object
	 * and all associated metadata.
	 *
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 *
	 * $images = $conn->get_container("my photos");
	 *
	 * # Delete specific object
	 * #
	 * $images->delete_object("disco_dancing.jpg");
	 * </code>
	 *
	 * @param obj $obj name or instance of Object to delete
	 * @param obj $container name or instance of Container in which the object resides (optional)
	 * @return boolean <kbd>True</kbd> if successfully removed
	 * @throws SyntaxException invalid Object name
	 * @throws NoSuchObjectException remote Object does not exist
	 * @throws InvalidResponseException unexpected response
	 */
	function delete_object($obj,$container=NULL)
	{
		$obj_name = NULL;
		if (is_object($obj)) {
			if (get_class($obj) == "CF_Object") {
				$obj_name = $obj->name;
			}
		}
		if (is_string($obj)) {
			$obj_name = $obj;
		}
		if (!$obj_name) {
			throw new SyntaxException("Object name not set.");
		}

		$container_name = NULL;

		if($container === NULL) {
			$container_name = $this->name;
		}
		else {
			if (is_object($container)) {
				if (get_class($container) == "CF_Container") {
					$container_name = $container->name;
				}
			}
			if (is_string($container)) {
				$container_name = $container;
			}
			if (!$container_name) {
				throw new SyntaxException("Container name source not set.");
			}
		}

		$status = $this->cfs_http->delete_object($container_name, $obj_name);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->delete_object($obj);
		#}
		if ($status == 404) {
			$m = "Specified object '".$container_name."/".$obj_name;
			$m.= "' did not exist to delete.";
			throw new NoSuchObjectException($m);
		}
		if ($status != 204) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		return True;
	}

	/**
	 * Helper function to create "path" elements for a given Object name
	 *
	 * Given an Object whos name contains '/' path separators, this function
	 * will create the "directory marker" Objects of one byte with the
	 * Content-Type of "application/directory".
	 *
	 * It assumes the last element of the full path is the "real" Object
	 * and does NOT create a remote storage Object for that last element.
	 */
	function create_paths($path_name)
	{
		if ($path_name[0] == '/') {
			$path_name = mb_substr($path_name, 0, 1);
		}
		$elements = explode('/', $path_name, -1);
		$build_path = "";
		foreach ($elements as $idx => $val) {
			if (!$build_path) {
				$build_path = $val;
			} else {
				$build_path .= "/" . $val;
			}
			$obj = new CF_Object($this, $build_path);
			$obj->content_type = "application/directory";
			$obj->write(".", 1);
		}
	}

	/**
	 * Internal method to grab CDN/Container info if appropriate to do so
	 *
	 * @throws InvalidResponseException unexpected response
	 */
	private function _cdn_initialize()
	{
		list($status, $reason, $cdn_enabled, $cdn_ssl_uri, $cdn_streaming_uri, $cdn_uri, $cdn_ttl,
			 $cdn_log_retention, $cdn_acl_user_agent, $cdn_acl_referrer) =
			$this->cfs_http->head_cdn_container($this->name);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->_cdn_initialize();
		#}
		if (!in_array($status, array(204,404))) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->cfs_http->get_error());
		}
		$this->cdn_enabled = $cdn_enabled;
		$this->cdn_streaming_uri = $cdn_streaming_uri;
		$this->cdn_ssl_uri = $cdn_ssl_uri;
		$this->cdn_uri = $cdn_uri;
		$this->cdn_ttl = $cdn_ttl;
		$this->cdn_log_retention = $cdn_log_retention;
		$this->cdn_acl_user_agent = $cdn_acl_user_agent;
		$this->cdn_acl_referrer = $cdn_acl_referrer;
	}

	#private function _re_auth()
	#{
	#	$new_auth = new CF_Authentication(
	#		$this->cfs_auth->username,
	#		$this->cfs_auth->api_key,
	#		$this->cfs_auth->auth_host,
	#		$this->cfs_auth->account);
	#	$new_auth->authenticate();
	#	$this->cfs_auth = $new_auth;
	#	$this->cfs_http->setCFAuth($this->cfs_auth);
	#	return True;
	#}
}


/**
 * Object operations
 *
 * An Object is analogous to a file on a conventional filesystem. You can
 * read data from, or write data to your Objects. You can also associate 
 * arbitrary metadata with them.
 *
 * @package php-cloudfiles
 */
class CF_Object
{
	public $container;
	public $name;
	public $last_modified;
	public $content_type;
	public $content_length;
	public $metadata;
	public $headers;
	public $manifest;
	private $etag;

	/**
	 * Class constructor
	 *
	 * @param obj $container CF_Container instance
	 * @param string $name name of Object
	 * @param boolean $force_exists if set, throw an error if Object doesn't exist
	 */
	function __construct(&$container, $name, $force_exists=False, $dohead=True)
	{
		if ($name[0] == "/") {
			$r = "Object name '".$name;
			$r .= "' cannot contain begin with a '/' character.";
			throw new SyntaxException($r);
		}
		if (strlen($name) > MAX_OBJECT_NAME_LEN) {
			throw new SyntaxException("Object name exceeds "
				. "maximum allowed length.");
		}
		$this->container = $container;
		$this->name = $name;
		$this->etag = NULL;
		$this->_etag_override = False;
		$this->last_modified = NULL;
		$this->content_type = NULL;
		$this->content_length = 0;
		$this->metadata = array();
		$this->headers = array();
		$this->manifest = NULL;
		if ($dohead) {
			if (!$this->_initialize() && $force_exists) {
				throw new NoSuchObjectException("No such object '".$name."'");
			}
		}
	}

	/**
	 * String representation of Object
	 *
	 * Pretty print the Object's location and name
	 *
	 * @return string Object information
	 */
	function __toString()
	{
		return $this->container->name . "/" . $this->name;
	}

	/**
	 * Internal check to get the proper mimetype.
	 *
	 * This function would go over the available PHP methods to get
	 * the MIME type.
	 *
	 * By default it will try to use the PHP fileinfo library which is
	 * available from PHP 5.3 or as an PECL extension
	 * (http://pecl.php.net/package/Fileinfo).
	 *
	 * It will get the magic file by default from the system wide file
	 * which is usually available in /usr/share/magic on Unix or try
	 * to use the file specified in the source directory of the API
	 * (share directory).
	 *
	 * if fileinfo is not available it will try to use the internal
	 * mime_content_type function.
	 * 
	 * @param string $handle name of file or buffer to guess the type from
	 * @return boolean <kbd>True</kbd> if successful
	 * @throws BadContentTypeException
	 */
	function _guess_content_type($handle) {
		if ($this->content_type)
			return;
			
		if (function_exists("finfo_open")) {
			$local_magic = dirname(strtr(__FILE__, "\\", "/")) . "/share/magic";
			$finfo = @finfo_open(FILEINFO_MIME, $local_magic);

			if (!$finfo) 
				$finfo = @finfo_open(FILEINFO_MIME);
				
			if ($finfo) {

				if (is_file((string)$handle))
					$ct = @finfo_file($finfo, $handle);
				else 
					$ct = @finfo_buffer($finfo, $handle);

				/* PHP 5.3 fileinfo display extra information like
				   charset so we remove everything after the ; since
				   we are not into that stuff */
				if ($ct) {
					$extra_content_type_info = strpos($ct, "; ");
					if ($extra_content_type_info)
						$ct = substr($ct, 0, $extra_content_type_info);
				}

				if ($ct && $ct != 'application/octet-stream')
					$this->content_type = $ct;

				@finfo_close($finfo);
			}
		}

		if (!$this->content_type && (string)is_file($handle) && function_exists("mime_content_type")) {
			$this->content_type = @mime_content_type($handle);
		}

		if (!$this->content_type) {
			throw new BadContentTypeException("Required Content-Type not set");
		}
		return True;
	}
	
	/**
	 * String representation of the Object's public URI
	 *
	 * A string representing the Object's public URI assuming that it's
	 * parent Container is CDN-enabled.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * # Print out the Object's CDN URI (if it has one) in an HTML img-tag
	 * #
	 * print "<img src='$pic->public_uri()' />\n";
	 * </code>
	 *
	 * @return string Object's public URI or NULL
	 */
	function public_uri()
	{
		if ($this->container->cdn_enabled) {
			return $this->container->cdn_uri . "/" . $this->name;
		}
		return NULL;
	}

	   /**
	 * String representation of the Object's public SSL URI
	 *
	 * A string representing the Object's public SSL URI assuming that it's
	 * parent Container is CDN-enabled.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * # Print out the Object's CDN SSL URI (if it has one) in an HTML img-tag
	 * #
	 * print "<img src='$pic->public_ssl_uri()' />\n";
	 * </code>
	 *
	 * @return string Object's public SSL URI or NULL
	 */
	function public_ssl_uri()
	{
		if ($this->container->cdn_enabled) {
			return $this->container->cdn_ssl_uri . "/" . $this->name;
		}
		return NULL;
	}
	/**
	 * String representation of the Object's public Streaming URI
	 *
	 * A string representing the Object's public Streaming URI assuming that it's
	 * parent Container is CDN-enabled.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * # Print out the Object's CDN Streaming URI (if it has one) in an HTML img-tag
	 * #
	 * print "<img src='$pic->public_streaming_uri()' />\n";
	 * </code>
	 *
	 * @return string Object's public Streaming URI or NULL
	 */
	function public_streaming_uri()
	{
		if ($this->container->cdn_enabled) {
			return $this->container->cdn_streaming_uri . "/" . $this->name;
		}
		return NULL;
	}

	/**
	 * Read the remote Object's data
	 *
	 * Returns the Object's data.  This is useful for smaller Objects such
	 * as images or office documents.  Object's with larger content should use
	 * the stream() method below.
	 *
	 * Pass in $hdrs array to set specific custom HTTP headers such as
	 * If-Match, If-None-Match, If-Modified-Since, Range, etc.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 * $data = $doc->read(); # read image content into a string variable
	 * print $data;
	 *
	 * # Or see stream() below for a different example.
	 * #
	 * </code>
	 *
	 * @param array $hdrs user-defined headers (Range, If-Match, etc.)
	 * @return string Object's data
	 * @throws InvalidResponseException unexpected response
	 */
	function read($hdrs=array())
	{
		list($status, $reason, $data) =
			$this->container->cfs_http->get_object_to_string($this, $hdrs);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->read($hdrs);
		#}
		if (($status < 200) || ($status > 299
				&& $status != 412 && $status != 304)) {
			throw new InvalidResponseException("Invalid response (".$status."): "
				. $this->container->cfs_http->get_error());
		}
		return $data;
	}

	/**
	 * Streaming read of Object's data
	 *
	 * Given an open PHP resource (see PHP's fopen() method), fetch the Object's
	 * data and write it to the open resource handle.  This is useful for
	 * streaming an Object's content to the browser (videos, images) or for
	 * fetching content to a local file.
	 *
	 * Pass in $hdrs array to set specific custom HTTP headers such as
	 * If-Match, If-None-Match, If-Modified-Since, Range, etc.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * # Assuming this is a web script to display the README to the
	 * # user's browser:
	 * #
	 * <?php
	 * // grab README from storage system
	 * //
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * // Hand it back to user's browser with appropriate content-type
	 * //
	 * header("Content-Type: " . $doc->content_type);
	 * $output = fopen("php://output", "w");
	 * $doc->stream($output); # stream object content to PHP's output buffer
	 * fclose($output);
	 * ?>
	 *
	 * # See read() above for a more simple example.
	 * #
	 * </code>
	 *
	 * @param resource $fp open resource for writing data to
	 * @param array $hdrs user-defined headers (Range, If-Match, etc.)
	 * @return string Object's data
	 * @throws InvalidResponseException unexpected response
	 */
	function stream(&$fp, $hdrs=array())
	{
		list($status, $reason) = 
				$this->container->cfs_http->get_object_to_stream($this,$fp,$hdrs);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->stream($fp, $hdrs);
		#}
		if (($status < 200) || ($status > 299
				&& $status != 412 && $status != 304)) {
			throw new InvalidResponseException("Invalid response (".$status."): "
				.$reason);
		}
		return True;
	}

	/**
	 * Store new Object metadata
	 *
	 * Write's an Object's metadata to the remote Object.  This will overwrite
	 * an prior Object metadata.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * # Define new metadata for the object
	 * #
	 * $doc->metadata = array(
	 *	 "Author" => "EJ",
	 *	 "Subject" => "How to use the PHP tests",
	 *	 "Version" => "1.2.2"
	 * );
	 *
	 * # Define additional headers for the object
	 * #
	 * $doc->headers = array(
	 *	 "Content-Disposition" => "attachment",
	 * );
	 *
	 * # Push the new metadata up to the storage system
	 * #
	 * $doc->sync_metadata();
	 * </code>
	 *
	 * @return boolean <kbd>True</kbd> if successful, <kbd>False</kbd> otherwise
	 * @throws InvalidResponseException unexpected response
	 */
	function sync_metadata()
	{
		if (!empty($this->metadata) || !empty($this->headers) || $this->manifest) {
			$status = $this->container->cfs_http->update_object($this);
			#if ($status == 401 && $this->_re_auth()) {
			#	return $this->sync_metadata();
			#}
			if ($status != 202) {
				throw new InvalidResponseException("Invalid response ("
					.$status."): ".$this->container->cfs_http->get_error());
			}
			return True;
		}
		return False;
	}
	/**
	 * Store new Object manifest
	 *
	 * Write's an Object's manifest to the remote Object.  This will overwrite
	 * an prior Object manifest.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * # Define new manifest for the object
	 * #
	 * $doc->manifest = "container/prefix";
	 *
	 * # Push the new manifest up to the storage system
	 * #
	 * $doc->sync_manifest();
	 * </code>
	 *
	 * @return boolean <kbd>True</kbd> if successful, <kbd>False</kbd> otherwise
	 * @throws InvalidResponseException unexpected response
	 */

	function sync_manifest()
	{
		return $this->sync_metadata();
	}
	/**
	 * Upload Object's data to Cloud Files
	 *
	 * Write data to the remote Object.  The $data argument can either be a
	 * PHP resource open for reading (see PHP's fopen() method) or an in-memory
	 * variable.  If passing in a PHP resource, you must also include the $bytes
	 * parameter.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * # Upload placeholder text in my README
	 * #
	 * $doc->write("This is just placeholder text for now...");
	 * </code>
	 *
	 * @param string|resource $data string or open resource
	 * @param float $bytes amount of data to upload (required for resources)
	 * @param boolean $verify generate, send, and compare MD5 checksums
	 * @return boolean <kbd>True</kbd> when data uploaded successfully
	 * @throws SyntaxException missing required parameters
	 * @throws BadContentTypeException if no Content-Type was/could be set
	 * @throws MisMatchedChecksumException $verify is set and checksums unequal
	 * @throws InvalidResponseException unexpected response
	 */
	function write($data=NULL, $bytes=0, $verify=True)
	{
		if (!$data && !is_string($data)) {
			throw new SyntaxException("Missing data source.");
		}
		if ($bytes > MAX_OBJECT_SIZE) {
			throw new SyntaxException("Bytes exceeds maximum object size.");
		}
		if ($verify) {
			if (!$this->_etag_override) {
				$this->etag = $this->compute_md5sum($data);
			}
		} else {
			$this->etag = NULL;
		}

		$close_fh = False;
		if (!is_resource($data)) {
			# A hack to treat string data as a file handle.  php://memory feels
			# like a better option, but it seems to break on Windows so use
			# a temporary file instead.
			#
			$fp = fopen("php://temp", "wb+");
			#$fp = fopen("php://memory", "wb+");
			fwrite($fp, $data, strlen($data));
			rewind($fp);
			$close_fh = True;
			$this->content_length = (float) strlen($data);
			if ($this->content_length > MAX_OBJECT_SIZE) {
				throw new SyntaxException("Data exceeds maximum object size");
			}
			$ct_data = substr($data, 0, 64);
		} else {
			$this->content_length = $bytes;
			$fp = $data;
			$ct_data = fread($data, 64);
			rewind($data);
		}

		$this->_guess_content_type($ct_data);

		list($status, $reason, $etag) =
				$this->container->cfs_http->put_object($this, $fp);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->write($data, $bytes, $verify);
		#}
		if ($status == 412) {
			if ($close_fh) { fclose($fp); }
			throw new SyntaxException("Missing Content-Type header");
		}
		if ($status == 422) {
			if ($close_fh) { fclose($fp); }
			throw new MisMatchedChecksumException(
				"Supplied and computed checksums do not match.");
		}
		if ($status != 201) {
			if ($close_fh) { fclose($fp); }
			throw new InvalidResponseException("Invalid response (".$status."): "
				. $this->container->cfs_http->get_error());
		}
		if (!$verify) {
			$this->etag = $etag;
		}
		if ($close_fh) { fclose($fp); }
		return True;
	}

	/**
	 * Upload Object data from local filename
	 *
	 * This is a convenience function to upload the data from a local file.  A
	 * True value for $verify will cause the method to compute the Object's MD5
	 * checksum prior to uploading.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * # Upload my local README's content
	 * #
	 * $doc->load_from_filename("/home/ej/cloudfiles/readme");
	 * </code>
	 *
	 * @param string $filename full path to local file
	 * @param boolean $verify enable local/remote MD5 checksum validation
	 * @return boolean <kbd>True</kbd> if data uploaded successfully
	 * @throws SyntaxException missing required parameters
	 * @throws BadContentTypeException if no Content-Type was/could be set
	 * @throws MisMatchedChecksumException $verify is set and checksums unequal
	 * @throws InvalidResponseException unexpected response
	 * @throws IOException error opening file
	 */
	function load_from_filename($filename, $verify=True)
	{
		$fp = @fopen($filename, "r");
		if (!$fp) {
			throw new IOException("Could not open file for reading: ".$filename);
		}

		clearstatcache();
		
		$size = (float) sprintf("%u", filesize($filename));
		if ($size > MAX_OBJECT_SIZE) {
			throw new SyntaxException("File size exceeds maximum object size.");
		}

		$this->_guess_content_type($filename);
		
		$this->write($fp, $size, $verify);
		fclose($fp);
		return True;
	}

	/**
	 * Save Object's data to local filename
	 *
	 * Given a local filename, the Object's data will be written to the newly
	 * created file.
	 *
	 * Example:
	 * <code>
	 * # ... authentication/connection/container code excluded
	 * # ... see previous examples
	 *
	 * # Whoops!  I deleted my local README, let me download/save it
	 * #
	 * $my_docs = $conn->get_container("documents");
	 * $doc = $my_docs->get_object("README");
	 *
	 * $doc->save_to_filename("/home/ej/cloudfiles/readme.restored");
	 * </code>
	 *
	 * @param string $filename name of local file to write data to
	 * @return boolean <kbd>True</kbd> if successful
	 * @throws IOException error opening file
	 * @throws InvalidResponseException unexpected response
	 */
	function save_to_filename($filename)
	{
		$fp = @fopen($filename, "wb");
		if (!$fp) {
			throw new IOException("Could not open file for writing: ".$filename);
		}
		$result = $this->stream($fp);
		fclose($fp);
		return $result;
	}
	   /**
	 * Purge this Object from CDN Cache.
	 * Example:
	 * <code>
	 * # ... authentication code excluded (see previous examples) ...
	 * #
	 * $conn = new CF_Authentication($auth);
	 * $container = $conn->get_container("cdn_enabled");
	 * $obj = $container->get_object("object");
	 * $obj->purge_from_cdn("user@domain.com");
	 * # or
	 * $obj->purge_from_cdn();
	 * # or 
	 * $obj->purge_from_cdn("user1@domain.com,user2@domain.com");
	 * @returns boolean True if successful
	 * @throws CDNNotEnabledException if CDN Is not enabled on this connection
	 * @throws InvalidResponseException if the response expected is not returned
	 */
	function purge_from_cdn($email=null)
	{
		if (!$this->container->cfs_http->getCDNMUrl())
		{
			throw new CDNNotEnabledException(
				"Authentication response did not indicate CDN availability");
		}
		$status = $this->container->cfs_http->purge_from_cdn($this->container->name . "/" . $this->name, $email);
		if ($status < 199 or $status > 299) {
			throw new InvalidResponseException(
				"Invalid response (".$status."): ".$this->container->cfs_http->get_error());
		}
		return True;
	}

	/**
	 * Set Object's MD5 checksum
	 *
	 * Manually set the Object's ETag.  Including the ETag is mandatory for
	 * Cloud Files to perform end-to-end verification.  Omitting the ETag forces
	 * the user to handle any data integrity checks.
	 *
	 * @param string $etag MD5 checksum hexidecimal string
	 */
	function set_etag($etag)
	{
		$this->etag = $etag;
		$this->_etag_override = True;
	}

	/**
	 * Object's MD5 checksum
	 *
	 * Accessor method for reading Object's private ETag attribute.
	 *
	 * @return string MD5 checksum hexidecimal string
	 */
	function getETag()
	{
		return $this->etag;
	}

	/**
	 * Compute the MD5 checksum
	 *
	 * Calculate the MD5 checksum on either a PHP resource or data.  The argument
	 * may either be a local filename, open resource for reading, or a string.
	 *
	 * <b>WARNING:</b> if you are uploading a big file over a stream
	 * it could get very slow to compute the md5 you probably want to
	 * set the $verify parameter to False in the write() method and
	 * compute yourself the md5 before if you have it.
	 *
	 * @param filename|obj|string $data filename, open resource, or string
	 * @return string MD5 checksum hexidecimal string
	 */
	function compute_md5sum(&$data)
	{

		if (function_exists("hash_init") && is_resource($data)) {
			$ctx = hash_init('md5');
			while (!feof($data)) {
				$buffer = fgets($data, 65536);
				hash_update($ctx, $buffer);
			}
			$md5 = hash_final($ctx, false);
			rewind($data);
		} elseif ((string)is_file($data)) {
			$md5 = md5_file($data);
		} else {
			$md5 = md5($data);
		}
		return $md5;
	}

	/**
	 * PRIVATE: fetch information about the remote Object if it exists
	 */
	private function _initialize()
	{
		list($status, $reason, $etag, $last_modified, $content_type,
			$content_length, $metadata, $manifest, $headers) =
				$this->container->cfs_http->head_object($this);
		#if ($status == 401 && $this->_re_auth()) {
		#	return $this->_initialize();
		#}
		if ($status == 404) {
			return False;
		}
		if ($status < 200 || $status > 299) {
			throw new InvalidResponseException("Invalid response (".$status."): "
				. $this->container->cfs_http->get_error());
		}
		$this->etag = $etag;
		$this->last_modified = $last_modified;
		$this->content_type = $content_type;
		$this->content_length = $content_length;
		$this->metadata = $metadata;
		$this->headers = $headers;
		$this->manifest = $manifest;
		return True;
	}

	#private function _re_auth()
	#{
	#	$new_auth = new CF_Authentication(
	#		$this->cfs_auth->username,
	#		$this->cfs_auth->api_key,
	#		$this->cfs_auth->auth_host,
	#		$this->cfs_auth->account);
	#	$new_auth->authenticate();
	#	$this->container->cfs_auth = $new_auth;
	#	$this->container->cfs_http->setCFAuth($this->cfs_auth);
	#	return True;
	#}
}

?>