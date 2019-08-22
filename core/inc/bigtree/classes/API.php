<?php
	/*
		Class: BigTree\API
			Provides an interface for handling API calls
	*/
	
	namespace BigTree;
	
	use BigTree\Auth\AuthenticatedUser;
	
	class API {

		/** @var AuthenticatedUser $AuthenticatedUser */
		public static $AuthenticatedUser;
		public static $Method;
		/** @var User $User */
		public static $User;
	
		/*
			Function: authenticate
				Authenticates a user based on authenticate header or session state.
				Stops execution and triggers an error if a failure occurs.
		*/
		
		public static function authenticate(): void
		{
			$headers = Utils::getHeaders();
			
			if (!empty($headers["Authorization"])) {
				[, $base64_data] = explode(" ", $headers["Authorization"]);
				[$user, $key] = explode(":", base64_decode($base64_data));
				$key_entry = SQL::fetchSingle("bigtree_users_api_keys", ["user" => $user, "key" => $key]);
				
				if ($key_entry) {
					if ($key_entry["expires"] < time()) {
						static::triggerError("The provided API token has expired.", "token:expired", "authentication");
					} else {
						static::$User = new User($user);
						static::$AuthenticatedUser = Auth::user(static::$User);
						
						// Set Auth state to be this user
						Auth::$ID = static::$AuthenticatedUser->ID;
						Auth::$Level = static::$AuthenticatedUser->Level;
						Auth::$Permissions = static::$AuthenticatedUser->Permissions;
					}
				} else {
					static::triggerError("The provided API token is invalid.", "token:invalid", "authentication");
				}
			} else {
				Auth::initSecurity();
				Auth::authenticate();
				
				if (is_null(Auth::user()->ID)) {
					static::triggerError("No API token was provided.", "token:missing", "authentication");
				} else {
					static::$AuthenticatedUser = Auth::user();
					static::$User = new User(Auth::user()->ID);
				}
			}
		}
		
		/*
			Function: requireMethod
				Requires the passed in HTTP method and triggers an error if an invalid method is called.
			
			Parameters:
				method - Required HTTP Method
		*/
		
		public static function requireMethod(string $method)
		{
			if (strtolower($_SERVER["REQUEST_METHOD"]) !== strtolower($method)) {
				static::triggerError("This API endpoint must be called via $method.", "invalid:method", "method");
			}
			
			static::$Method = strtoupper($method);
		}
		
		/*
			Function: requireParameters
				Requires the passed in parameter names and types and triggers errors for invalid parameters.
				API::requireMethod must be called first
			
			Parameters:
				parameters - An associative array of key => type
		*/
		
		public static function requireParameters(array $parameters): void
		{
			if (empty(static::$Method)) {
				trigger_error(Text::translate("requireMethod must be called prior to requireParameters"), E_USER_ERROR);
			}
			
			if (static::$Method === "POST") {
				$data_source = $_POST;
			} else {
				$data_source = $_GET;
			}
			
			// Allow for the first routed command to be the parameter for single parameter requests
			if (count($parameters) === 1 && static::$Method !== "POST") {
				list($key) = array_keys($parameters);
				
				if (!isset($data_source[$key]) && isset(Router::$Commands[0])) {
					$_GET[$key] = Router::$Commands[0];
				}
			}
			
			foreach ($parameters as $key => $type) {
				$type = strtolower($type);
				
				if (!isset($data_source[$key])) {
					API::triggerError("Missing parameter: '$key'", "parameters:missing", "parameters");
				} else {
					$val = $data_source[$key];
					$error = false;
					
					if (($type === "number" || $type === "int") && !is_numeric($val)) {
						$error = true;
					} elseif ($type === "int" && intval($val) != $val) {
						$error = true;
					} elseif ($type === "string" && !is_string($val)) {
						$error = true;
					} elseif ($type == "array" && !is_array($val)) {
						$error = true;
					} elseif ($type == "string_int" && !is_string($val) && !is_int($val)) {
						$error = true;
					}
					
					if ($error) {
						API::triggerError("Parameter '$key' must be of type '$type'.", "parameters:invalid", "parameters");
					}
				}
			}
		}
			
		/*
			Function: sendResponse
				Sends a success response through the API.
				If no parameters are passed, a simple success response is returned.
		
			Parameters:
				data - An optional array of response data
				message - An optional message
				code - An optional response code
		*/
		
		public static function sendResponse(?array $data = null, ?string $message = null, ?string $code = null,
											?string $next_page = null): void
		{
			$response = [
				"error" => false,
				"success" => true
			];
			
			if (is_array($data)) {
				$response["response"] = $data;
			}
			
			if (!is_null($message)) {
				$response["message"] = $message;
			}
			
			if (!is_null($code)) {
				$response["code"] = $code;
			}
			
			if (!is_null($next_page)) {
				$response["next_page"] = $next_page;
			}
			
			echo JSON::encode($response);
			
			die();
		}
	
		/*
			Function: triggerError
				Sends an error response through the API
		
			Parameters:
				message - An error message (this is translated and should not be relied upon programatically)
				code - An error code (this is consistent across languages)
				category - An optional error category (this is consistent across languages)
		*/
		
		public static function triggerError(string $message, ?string $code = "generic:error",
											?string $category = "generic"): void
		{
			echo JSON::encode([
				"error" => true,
				"success" => false,
				"message" => Text::translate($message),
				"code" => $code,
				"category" => $category
			]);
			
			die();
		}
	}