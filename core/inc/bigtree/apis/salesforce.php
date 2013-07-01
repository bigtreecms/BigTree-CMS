<?
	/*
		Class: BigTreeSalesforceAPI
	*/
	
	class BigTreeSalesforceAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = false;
		var $Settings = array();
		var $Cache = true;

		/*
			Constructor:
				Sets up the Salesforce API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the Salesforce API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-salesforce-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-salesforce-api", 
					"name" => "Salesforce API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Salesforce";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->redirect_uri = str_replace("http://","https://",ADMIN_ROOT)."developer/services/salesforce/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
				$this->OAuthClient->authorization = "Authorization: Bearer ".$this->Settings["token"];
			}
			
			// Init Client
			$this->OAuthClient->Initialize();

			// Setup Endpoints
			$this->URL = $this->Settings["instance"]."/services/data/v28.0/";
		}
		
		/*
			Function: call
				Calls the Salesforce API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The Salesforce API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.
				second_try - If we failed the first one with a refresh token, we try a second time then fail out (defaults to false)

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$options = array(),$second_try = false) {
			global $cms;
			if ($method != "GET") {
				return $this->callUncached($endpoint,$params,$method);				
			}

			if (!$this->Connected) {
				throw new Exception("The Salesforce API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.salesforce",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Salesforce normally.
					return json_decode(json_encode($record));
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.salesforce",$cache_key,$response);
				}
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				// May need a refreshed token.
				if (!$second_try && $this->Errors[0]->errorCode == "INVALID_SESSION_ID") {
					// Try to refresh the token.
					if ($this->refreshToken()) {
						// Call again.
						return $this->call($endpoint,$params,$method,$options,true);
					}
				}
				return false;
			}
		}

		/*
			Function: callUncached
				Calls the Salesforce API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Salesforce API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.
				second_try - If we failed the first one with a refresh token, we try a second time then fail out (defaults to false)

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$options = array(),$second_try = false) {
			if (!$this->Connected) {
				throw new Exception("The Salesforce API is not connected.");
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				// May need a refreshed token.
				if (!$second_try && $this->Errors[0]->errorCode == "INVALID_SESSION_ID") {
					// Try to refresh the token.
					if ($this->refreshToken()) {
						// Call again.
						return $this->callUncached($endpoint,$params,$method,$options,true);
					}
				}
				return false;
			}
		}

		/*
			Function: cURL
				Calls an authenticated API call via cURL instead of the OAuthClient library.
				This is needed for some calls where the library hangs.

			Parameters:
				endpoint - The endpoint to hit
				method - The HTTP method to use
				content - The content to send
				json - Whether the content is JSON or not, defaults to false

			Returns:
				Response body.
		*/

		function cURL($endpoint,$method,$content,$json = false) {
			$headers = array("Authorization: Bearer ".$this->Settings["token"]);
			if ($json) {
				$headers[] = "Content-type: application/json";
			}

			return BigTree::cURL($this->URL.$endpoint,$content,array(
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_HTTPHEADER => $headers
			));
		}

		/*
			Function: getObject
				Returns a Salesforce object for the given name.

			Parameters:
				name - The object's name.

			Returns:
				A BigTreeSalesforceObject object.
		*/

		function getObject($name) {
			$response = $this->call("sobjects/$name/");
			if (!isset($response->objectDescribe)) {
				return false;
			}
			return new BigTreeSalesforceObject($response->objectDescribe,$this);
		}

		/*
			Function: getObjects
				Returns all the available Salesforce objects in your account.

			Returns:
				An array of BigTreeSalesforceObject objects.
		*/

		function getObjects() {
			$response = $this->call("sobjects/");
			if (!isset($response->sobjects)) {
				return false;
			}
			$objects = array();
			foreach ($response->sobjects as $object) {
				$objects[] = new BigTreeSalesforceObject($object,$this);
			}
			return $objects;
		}

		/*
			Function: refreshToken
				Refreshes the authorization token.
		*/

		function refreshToken() {
			$response = BigTree::cURL("https://login.salesforce.com/services/oauth2/token",array(
				"grant_type" => "refresh_token",
				"client_id" => $this->Settings["key"],
				"client_secret" => $this->Settings["secret"],
				"refresh_token" => $this->Settings["refresh_token"]
			));
			$response = json_decode($response,true);
			if ($response["access_token"]) {
				// Update saved settings
				$this->Settings["token"] = $response["access_token"];
				$this->Settings["instance"] = $response["instance_url"];
				$this->Settings["scope"] = $response["scope"];
				$this->Settings["signature"] = $response["signature"];
				$admin = new BigTreeAdmin;
				$admin->updateSettingValue("bigtree-internal-salesforce-api",$this->Settings);

				// Update local token
				$this->OAuthClient->access_token = $this->Settings["token"];
				return true;
			}
			return false;
		}
	}

	/*
		Class: BigTreeSalesforceObject
			Emulates most of the methods from BigTreeModule for a Salesforce object type.
	*/

	class BigTreeSalesforceObject {

		protected $API;
		protected $QueryFieldNames;

		/*
			Constructor:
				Creates a new BigTreeSalesforceObject

			Parameters:
				object - Salesforce data
				api - Reference to BigTreeSalesforceAPI class instance
		*/

		function __construct($object,&$api) {
			$this->Activateable = $object->activateable;
			$this->API = $api;
			$this->Createable = $object->createable;
			$this->Deleteable = $object->deleteable;
			$this->Label = $object->label;
			$this->LabelPlural = $object->labelPlural;
			$this->Layoutable = $object->layoutable;
			$this->Mergeable = $object->mergeable;
			$this->Name = $object->name;
			$this->Queryable = $object->queryable;
			$this->Replicateable = $object->replicateable;
			$this->Retrieveable = $object->retrieveable;
			$this->Searchable = $object->searchable;
			$this->Triggerable = $object->triggerable;
			$this->Undeleteable = $object->undeleteable;
			$this->Updateable = $object->updateable;
			
			// Find out the fields of this object.
			$response = $this->API->call("sobjects/".$this->Name."/describe/");
			if (!isset($response->name)) {
				return false;
			} 
			$fields = array();
			$field_names = array();
			foreach ($response->fields as $f) {
				$field = new stdClass;
				if (count($f->picklistValues)) {
					$field->AvailableValues = array();
					foreach ($f->picklistValues as $v) {
						$value = new stdClass;
						if ($v->active) {
							$value->Value = $v->value;
							$value->Label = $v->label;
							$value->IsDefault = $v->defaultValue;
						}
						$field->AvailableValues[] = $value;
					}
				}
				$field->DefaultValue = $f->defaultValue;
				$field->Label = $f->label;
				$field->Length = $f->length;
				$field->Name = $f->name;
				$field->Type = $f->type;
				$fields[] = $field;
				$field_names[] = $f->name;
			}
			$this->Fields = $fields;
			$this->QueryFieldNames = implode(",",$field_names);
		}

		/*
			Function: add
				Adds a record to Salesforce.
			
			Parameters:
				keys - An array of column names to add
				vals - An array of values for each of the columns

			Returns:
				A BigTreeSalesforceRecord object if successful.
		*/

		function add($keys,$vals) {
			$record = array();
			foreach ($keys as $key) {
				$record[$key] = current($vals);
				next($vals);
			}
			$response = json_decode($this->API->cURL("sobjects/".$this->Name."/","POST",json_encode($record),true));
			// Look for a response ID.
			if (isset($response->id)) {
				// Setup a dumb record and build it ourselves
				$r = new stdClass;
				foreach ($this->Fields as $field) {
					$field_name = $field->Name;
					$r->$field_name = isset($record[$field_name]) ? $record[$field_name] : "";
				}
				$r = new BigTreeSalesforceRecord($r,$this->API);
				$r->ID = $response->id;
				$r->Type = $this->Name;
				$r->CreatedAt = $r->UpdatedAt = date("Y-m-d H:i:s");
				return $r;				
			} else {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
		}

		/*
			Function: delete
				Deletes a record from Salesforce.

			Returns:
				true if successful.
		*/

		function delete($id) {
			// We're going to straight up cURL because the outh class is bogus and hangs on this call.
			$response = $this->API->cURL("sobjects/".$this->Type."/$id","DELETE");
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}

		/*
			Function: get
				Returns a single record with the given ID.

			Parameters:
				id - The "Id" field of the record in Salesforce

			Returns:
				A BigTreeSalesforceRecord object
		*/

		function get($id) {
			$response = $this->API->call("sobjects/".$this->Name."/$id");
			if (!$response) {
				return false;
			}
			return new BigTreeSalesforceRecord($response,$this->API);
		}

		/*
			Function: getAll
				Returns all records of this object type.

			Parameters:
				order - The sort order (in SOSQL syntax, defaults to "Id ASC")
			
			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/

		function getAll($order = "Id ASC") {
			return $this->query("SELECT ".$this->QueryFieldNames." FROM ".$this->Name." ORDER BY $order");
		}

		/*
			Function: getMatching
				Returns records that match the key/value pairs.

			Parameters:
				fields - Either a single field key or an array of field keys (if you pass an array you must pass an array for values as well)
				values - Either a signle field value or an array of field values (if you pass an array you must pass an array for fields as well)
				order - The sort order (in SOSQL syntax, defaults to "Id ASC")
				limit - Max number of records to return, defaults to all
			
			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/

		function getMatching($fields,$values,$order = "Id ASC",$limit = false) {
			if (!is_array($fields)) {
				$where = "$fields = '".sqlescape($values)."'";
			} else {
				$x = 0;
				while ($x < count($fields)) {
					$where[] = $fields[$x]." = '".sqlescape($values[$x])."'";
					$x++;
				}
				$where = implode(" AND ",$where);
			}
			if ($where) {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." WHERE $where ORDER BY $order";
			} else {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." ORDER BY $order";
			}
			if ($limit) {
				$query .= " LIMIT $limit";
			}
			return $this->query($query);
		}

		/*
			Function: getPage
				Returns a page of records.
			
			Parameters:
				page - The page to return.
				order - The sort order (in SOSQL syntax, defaults to "Id ASC")
				perpage - The number of results per page (defaults to 15)
				where - Optional SOSQL WHERE conditions
			
			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/
		
		function getPage($page = 1,$order = "id ASC",$perpage = 15,$where = false) {
			// Don't try for page 0
			if ($page < 1) {
				$page = 1;
			}
			
			if ($where) {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." WHERE $where ORDER BY $order LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			} else {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." ORDER BY $order LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			}
			return $this->query($query);
		}

		/*
			Function: query
				Perform a SOQL query.

			Parameters:
				query - A SOQL query string

			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/

		function query($query) {
			$response = $this->API->call("query/?q=".urlencode($query));
			if (!isset($response->records)) {
				return false;
			}
			$records = array();
			foreach ($response->records as $record) {
				$records[] = new BigTreeSalesforceRecord($record,$this->API);
			}
			return $records;
		}

		/*
			Function: search
				Returns an array of records from Salesforce that match the search query.
			
			Parameters:
				query - A string to search for.
				order - The SOQL sort order (defaults to "Id ASC")
				limit - Max number of records to return, defaults to all
			
			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/
		
		function search($query,$order = "Id ASC",$limit = false) {
			$where = array();
			$searchable_types = array("string","picklist","textarea","phone","url");
			foreach ($this->Fields as $field) {
				if (in_array($field->Type,$searchable_types) && $field->Name != "Description") {
					$where[] = $field->Name." LIKE '%".addslashes($query)."%'";
				}
			}
			if (!count($where)) {
				return false;
			}
			$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." WHERE ".implode(" OR ",$where)." ORDER BY $order";
			if ($limit) {
				$query .= " LIMIT $limit";
			}
			return $this->query($query);
		}

		/*
			Function: update
				Updates an entry in Salesforce.
			
			Parameters:
				id - The Id of the entry.
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
		*/
		
		function update($id,$fields,$values) {
			$record = array();
			if (is_array($fields)) {
				foreach ($fields as $key) {
					$record[$key] = current($values);
					next($values);
				}
			} else {
				$record[$fields] = $values;
			}
			$response = $this->API->cURL("sobjects/".$this->Name."/$id","PATCH",json_encode($record),true);
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}
	}

	/*
		Class: BigTreeSalesforceRecord
	*/

	class BigTreeSalesforceRecord {

		protected $API;

		/*
			Constructor:
				Creates a new BigTreeSalesforceRecord object.

			Parameters:
				record - Salesforce data
				api - Reference to BigTreeSalesforceAPI class instance
		*/

		function __construct($record,&$api) {
			$this->API = $api;
			// Save this ahead of time to keep things alphabetized.
			$this->Columns = $record;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($record->CreatedDate));
			$this->CreatedBy = $record->CreatedById;
			$this->ID = $record->Id;
			$this->Type = $record->attributes->type;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($record->LastModifiedDate));
			$this->UpdatedBy = $record->LastModifiedById;
			// Remove a bunch of columns we can't modify
			unset($record->attributes);
			unset($this->Columns->CreatedById);
			unset($this->Columns->CreatedDate);
			unset($this->Columns->Id);
			unset($this->Columns->IsDeleted);
			unset($this->Columns->JigsawCompanyId);
			unset($this->Columns->LastActivityDate);
			unset($this->Columns->LastModifiedDate);
			unset($this->Columns->LastModifiedById);
			unset($this->Columns->LastReferencedDate);
			unset($this->Columns->LastViewedDate);
			unset($this->Columns->MasterRecordId);
			unset($this->Columns->SystemModstamp);
		}

		/*
			Function: delete
				Deletes the record from Salesforce.

			Returns:
				true if successful.
		*/

		function delete() {
			// We're going to straight up cURL because the outh class is bogus and hangs on this call.
			$response = $this->API->cURL("sobjects/".$this->Type."/".$this->ID,"DELETE");
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}

		/*
			Function: save
				Saves changes made to the Columns property of this object back to Salesforce.
		*/

		function save() {
			$response = $this->API->cURL("sobjects/".$this->Type."/".$this->ID,"PATCH",json_encode($this->Columns),true);
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}

		/*
			Function: update
				Updates this entry in Salesforce.
			
			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
		*/
		
		function update($fields,$values) {
			$record = array();
			if (is_array($fields)) {
				foreach ($fields as $key) {
					$record[$key] = current($values);
					next($values);
				}
			} else {
				$record[$fields] = $values;
			}
			$response = $this->API->cURL("sobjects/".$this->Type."/".$this->ID,"PATCH",json_encode($record),true);
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}
	}
?>