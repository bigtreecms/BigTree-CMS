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
			Function: getSettingsCacheObject
				Returns a cache object (in array form) for a given setting array or ID
		
			Parameters:
				setting - Either a setting array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getSettingsCacheObject($setting): array
		{
			if (!is_array($setting)) {
				$setting = DB::get("settings", $setting);
			}
			
			if ($setting["locked"]) {
				if (Auth::user()->Level > 1) {
					$access_level = "p";
				} else {
					$access_level = null;
				}
			} else {
				$access_level = Auth::user()->Level ? "p" : null;
			}
			
			$record = [
				"id" => $setting["id"],
				"title" => $setting["name"],
				"locked" => $setting["locked"],
				"value" => null,
				"access_level" => $access_level
			];
			
			if ($access_level) {
				$value_data = SQL::fetch("SELECT encrypted, value FROM bigtree_settings WHERE id = ?", $setting["id"]);
				
				if ($value_data) {
					if ($value_data["encrypted"]) {
						$record["value"] = Text::translate("-- Encrypted --");
					} else {
						$value = json_decode($value_data["value"], true);
						
						if (is_array($value)) {
							$record["value"] = Text::translate("-- Array --");
						} else {
							$record["value"] = Text::trimLength(Text::htmlEncode(strip_tags(Link::decode($value))), 100);
						}
					}
				}
			}
			
			return $record;
		}
		
		/*
			Function: requireLevel
				Requires the authenticated user be a given user level or higher.
			
			Parameters:
				level - User level
		*/
		
		public static function requireLevel(int $level): void
		{
			$level_strings = [
				1 => "Administrator",
				2 => "Developer"
			];
			
			if (Auth::user()->Level < $level) {
				static::triggerError("The minimum user level to call this endpoint is: ".$level_strings[$level],
									 "invalid:level", "permissions");
			}
		}
		
		/*
			Function: requireMethod
				Requires the passed in HTTP method and triggers an error if an invalid method is called.
			
			Parameters:
				method - Required HTTP Method
		*/
		
		public static function requireMethod(string $method): void
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
					$_GET[$key] = $data_source[$key] = Router::$Commands[0];
				}
			}
			
			foreach ($parameters as $key => $type) {
				if (!isset($data_source[$key])) {
					API::triggerError("Missing parameter: '$key'", "parameters:missing", "parameters");
				}
			}
			
			static::validateParameters($parameters);
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
		
		/*
			Function: validateParameters
				Validates parameters are either empty or of the specified types.
				API::requireMethod must be called first
			
			Parameters:
				parameters - An associative array of key => type
		*/
		
		public static function validateParameters(array $parameters): void
		{
			if (empty(static::$Method)) {
				trigger_error(Text::translate("requireMethod must be called prior to validateParameters"), E_USER_ERROR);
			}
			
			if (static::$Method === "POST") {
				$data_source = &$_POST;
			} else {
				$data_source = &$_GET;
			}
			
			foreach ($parameters as $key => $type) {
				$type = strtolower($type);
				$val = $data_source[$key];
				$error = false;
				
				// Make sure strict types for empty data
				if (empty($val)) {
					if ($type === "number" || $type === "int") {
						$data_source[$key] = 0;
					} elseif ($type === "string") {
						$data_source[$key] = "";
					} elseif ($type === "array") {
						$data_source[$key] = [];
					} elseif ($type === "string_int") {
						$data_source[$key] = "";
					} elseif ($type === "bool") {
						$data_source[$key] = false;
					}
					
					continue;
				}
				
				// For non-empty data, enforce user input types, cast floats and ints
				if ($type === "number") {
					if (!is_numeric($val)) {
						$error = true;
					} else {
						$data_source[$key] = (float) $val;
					}
				} elseif ($type === "int") {
					if (intval($val) != $val) {
						$error = true;
					} else {
						$data_source[$key] = intval($val);
					}
				} elseif ($type === "string" && !is_string($val)) {
					$error = true;
				} elseif ($type == "array" && !is_array($val)) {
					$error = true;
				} elseif ($type == "string_int" && !is_string($val) && !is_int($val)) {
					$error = true;
				} elseif ($type == "bool") {
					$data_source[$key] = !empty($data_source[$key]);
				}
				
				if ($error) {
					API::triggerError("Parameter '$key' must be of type '$type'.", "parameters:invalid", "parameters");
				}
			}
		}
		
	}