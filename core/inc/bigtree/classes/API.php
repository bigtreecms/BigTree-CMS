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
		
		private static $UsingKey = false;
	
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
				$key_entry = SQL::fetch("SELECT * FROM bigtree_users_api_keys WHERE user = ? AND key = ?", $user, $key);
				
				if ($key_entry) {
					if ($key_entry["expires"] < time()) {
						static::triggerError("The provided API token has expired.", "token:expired", "authentication");
					} else {
						static::$User = new User($user);
						static::$AuthenticatedUser = Auth::user(static::$User);
						static::$UsingKey = true;
						
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
			Function: getCalloutsCacheObject
				Returns a cache object (in array form) for a given callout array or ID
			
			Parameters:
				callout - Either a callout array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getCalloutsCacheObject($callout): array
		{
			if (!is_array($callout)) {
				$callout = DB::get("callouts", $callout);
			}
			
			return [
				"id" => $callout["id"],
				"name" => $callout["name"],
				"fields" => $callout["fields"],
				"level" => $callout["level"]
			];
		}
		
		/*
			Function: getExtensionsCacheObject
				Returns a cache object (in array form) for a given extension array or ID
			
			Parameters:
				extension - Either a extension array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getExtensionsCacheObject($extension): array
		{
			if (!is_array($extension)) {
				$extension = DB::get("extensions", $extension);
			}
			
			return [
				"id" => $extension["id"],
				"name" => $extension["name"]
			];
		}
		
		/*
			Function: getFeedsCacheObject
				Returns a cache object (in array form) for a given feed array or ID
			
			Parameters:
				feed - Either a feed array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getFeedsCacheObject($feed): array
		{
			if (!is_array($feed)) {
				$feed = DB::get("feeds", $feed);
			}
			
			$type = "";
			
			if ($feed["type"] == "custom") {
				$type = Text::translate("Custom");
			} elseif ($feed["type"] == "json") {
				$type = "JSON";
			} elseif ($feed["type"] == "rss") {
				$type = "RSS 0.91";
			} elseif ($feed["type"] == "rss2") {
				$type = "RSS 2.0";
			} elseif ($feed["type"] == "atom") {
				$type = "ATOM";
			}
			
			$url = WWW_ROOT."feeds/".$feed["route"]."/";
			
			return [
				"id" => $feed["id"],
				"name" => $feed["name"],
				"url" => '<a href="'.$url.'" target="_blank">'.$url.'</a>',
				"type" => $type
			];
		}
		
		/*
			Function: getFieldTypesCacheObject
				Returns a cache object (in array form) for a given field type array or ID
			
			Parameters:
				field_type - Either a field type array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getFieldTypesCacheObject($field_type): array
		{
			if (!is_array($field_type)) {
				$field_type = DB::get("field-types", $field_type);
			}
			
			return [
				"id" => $field_type["id"],
				"name" => $field_type["name"]
			];
		}
		
		/*
			Function: getModulesCacheObject
				Returns a cache object (in array form) for a given module array or ID
			
			Parameters:
				module - Either a module array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getModulesCacheObject($module): array
		{
			$module = new Module($module);
			$actions = [];
			
			foreach ($module->Actions as $action) {
				$actions[] = [
					"id" => $action->ID,
					"in_nav" => $action->InNav,
					"name" => $action->Name,
					"interface" => $action->Interface,
					"position" => intval($action->Position),
					"route" => $action->Route
				];
			}
			
			return [
				"id" => $module->ID,
				"group" => $module->Group,
				"name" => $module->Name,
				"position" => intval($module->Position),
				"actions" => $actions,
				"route" => $module->Route,
				"access_level" => $module->UserAccessLevel
			];
		}
		
		/*
			Function: getModuleGroupsCacheObject
				Returns a cache object (in array form) for a given module group array or ID
			
			Parameters:
				module - Either a module group array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getModuleGroupsCacheObject($group): array
		{
			$group = new ModuleGroup($group);
			
			return [
				"id" => $group->ID,
				"name" => $group->Name,
				"position" => intval($group->Position)
			];
		}
		
		/*
			Function: getPagesCacheObject
				Returns a cache object (in array form) for a given page ID (or pending ID)
			
			Parameters:
				page - A page ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getPagesCacheObject($page): array
		{
			$draft = Page::getPageDraft($page);
			
			if (!$draft) {
				return null;
			}
			
			$record = [
				"id" => $draft->ID,
				"parent" => $draft->Parent,
				"nav_title" => $draft->NavigationTitle,
				"path" => $draft->Path,
				"template" => $draft->Template,
				"archived" => $draft->Archived,
				"in_nav" => $draft->InNav,
				"position" => intval($draft->Position),
				"max_age" => $draft->MaxAge ?: 365,
				"age" => ceil((time() - strtotime($draft->UpdatedAt)) / 24 / 60 / 60),
				"expires" => null,
				"seo_score" => $draft->SEOScore,
				"seo_recommendations" => $draft->SEORecommendations,
				"access_level" => $draft->UserAccessLevel
			];
			
			if ($draft->ExpireAt) {
				$record["expires"] = date(Router::$Config["date_format"] ?: "m/d/Y", strtotime($draft->ExpireAt));
			}
			
			if ($draft->ChangesApplied) {
				$record["status"] = "changed";
			} elseif (strtotime($draft->PublishAt) > time()) {
				$record["status"] = "scheduled";
			} elseif ($draft->ExpireAt != "" && strtotime($draft->ExpireAt) < time()) {
				$record["status"] = "expired";
			} else {
				$record["status"] = "published";
			}
			
			return $record;
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
			
			$type = FieldType::referenceByID($setting["type"]);
			
			$record = [
				"id" => $setting["id"],
				"name" => $setting["name"],
				"locked" => $setting["locked"],
				"value" => null,
				"type" => $type ? $type["name"] : $setting["type"],
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
			Function: getTemplatesCacheObject
				Returns a cache object (in array form) for a given template array or ID
			
			Parameters:
				template - Either a template array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getTemplatesCacheObject($template): array
		{
			if (!is_array($template)) {
				$template = DB::get("templates", $template);
			}
			
			return [
				"id" => $template["id"],
				"name" => $template["name"],
				"routed" => $template["routed"],
				"fields" => $template["fields"],
				"position" => intval($template["position"]),
				"level" => $template["level"]
			];
		}
		
		/*
			Function: getUsersCacheObject
				Returns a cache object (in array form) for a given user array or ID
			
			Parameters:
				user - Either a user array or ID
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getUsersCacheObject($user): array
		{
			static $user_level = null;
			
			if (is_null($user_level)) {
				$user_level = Auth::user()->Level;
			}
			
			if (!is_array($user)) {
				$user = SQL::fetch("SELECT id, name, email, company, level FROM bigtree_users WHERE id = ?", $user);
			}
			
			if (!$user_level) {
				$user["access_level"] = null;
			} elseif ($user_level < $user["level"]) {
				$user["access_level"] = null;
			} else {
				$user["access_level" ] = "p";
			}
			
			return $user;
		}
		
		/*
			Function: getViewCacheObject
				Returns a cache object (in array form) for a given view record array
			
			Parameters:
				record - A module view cache record array
		
			Returns:
				An array of data for storage in the IndexedDB cache
		*/
		
		public static function getViewCacheObject($record): ?array
		{
			static $views = null;
			
			if (is_null($views)) {
				$modules = DB::getAll("modules");

				foreach ($modules as $module) {
					foreach ($module["interfaces"] as $interface) {
						if ($interface["type"] == "view") {
							$interface["module"] = new Module($module["id"]);
							$views[$interface["id"]] = $interface;
						}
					}
				}
			}
			
			if ($view = $views[$record["view"]]) {
				$record["access_level"] = Auth::user()->getCachedAccessLevel($view["module"], $record);
				$record["id"] = $record["view"]."-".$record["entry"];
				
				return $record;
			} else {
				return null;
			}
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
				csrf_verified - Whether to verify CSRF token on POST calls (defaults to true)
		*/
		
		public static function requireMethod(string $method, bool $csrf_verified = true): void
		{
			if (strtolower($_SERVER["REQUEST_METHOD"]) !== strtolower($method)) {
				static::triggerError("This API endpoint must be called via $method.", "invalid:method", "method");
			}
			
			if ($csrf_verified && strtolower($method) === "post" && !static::$UsingKey) {
				CSRF::verify(true);
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