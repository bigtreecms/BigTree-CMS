<?
	/*
		Class: BigTreeSalesforceAPI
			Salesforce API class that implements a BigTree-esque module API over Salesforce.
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));

	class BigTreeSalesforceAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://login.salesforce.com/services/oauth2/authorize";
		var $EndpointURL = "";
		var $OAuthVersion = "2.0";
		var $RequestType = "header";
		var $TokenURL = "https://login.salesforce.com/services/oauth2/token";

		/*
			Constructor:
				Sets up the Salesforce API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-salesforce-api","Salesforce API","org.bigtreecms.api.salesforce",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/salesforce/return/";
		
			// Change things if we're in the test environment.
			if ($this->Settings["test_environment"]) {
				$this->AuthorizeURL = str_ireplace("login.","test.",$this->AuthorizeURL);
				$this->TokenURL = str_replace("login.","test.",$this->TokenURL);
			}
			
			// Get a new access token for this session.
			$this->Connected = false;
			if ($this->Settings["refresh_token"]) {
				$response = json_decode(BigTree::cURL($this->TokenURL,array(
					"grant_type" => "refresh_token",
					"client_id" => $this->Settings["key"],
					"client_secret" => $this->Settings["secret"],
					"refresh_token" => $this->Settings["refresh_token"]
				)),true);
				if ($response["access_token"]) {
					$this->InstanceURL = $response["instance_url"];
					$this->EndpointURL = $this->InstanceURL."/services/data/v28.0/";
					$this->Settings["token"] = $response["access_token"];
					$this->Connected = true;
				}
			}
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
	}

	/*
		Class: BigTreeSalesforceObject
			An object that emulates most of the methods from BigTreeModule for a Salesforce object type.
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
			$this->Creatable = $object->createable;
			$this->Deletable = $object->deletable;
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
			$this->Undeletable = $object->undeletable;
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
			$response = $this->API->callUncached("sobjects/".$this->Name."/",json_encode($record),"POST");
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
				$this->API->Errors[] = (!is_array($response)) ? json_decode($response) : $response;
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
			$response = $this->API->callUncached("sobjects/".$this->Name."/$id",false,"DELETE");
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

		function getAll($order = "Id ASC",$full_response = false) {
			return $this->query("SELECT ".$this->QueryFieldNames." FROM ".$this->Name." ORDER BY $order",$full_response);
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

		function getMatching($fields,$values,$order = "Id ASC",$limit = false,$full_response = false) {
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
			return $this->query($query,$full_response);
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
		
		function getPage($page = 1,$order = "id ASC",$perpage = 15,$where = false,$full_response = false) {
			// Don't try for page 0
			if ($page < 1) {
				$page = 1;
			}
			
			if ($where) {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." WHERE $where ORDER BY $order LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			} else {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." ORDER BY $order LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			}
			return $this->query($query,$full_response);
		}

		/*
			Function: query
				Perform a SOQL query.

			Parameters:
				query - A SOQL query string

			Returns:
				An array of BigTreeSalesforceRecord objects.
		*/

		function query($query,$full_response = false) {
			
			if (strpos($query, "query/") > -1) {
				$response = $this->API->call($query);
			} else {
				$response = $this->API->call("query/?q=".urlencode($query));
			}
			
			if (!isset($response->records)) {
				return false;
			}
			$records = array();
			foreach ($response->records as $record) {
				$records[] = new BigTreeSalesforceRecord($record,$this->API);
			}
			$response->records = $records;
			return $full_response ? $response : $records;
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
		
		function search($query,$order = "Id ASC",$limit = false,$full_response = false) {
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
			return $this->query($query,$full_response);
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
			$response = $this->API->callUncached("sobjects/".$this->Name."/$id",json_encode($record),"PATCH");
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
			A Salesforce object that contains information about and methods you can perform on a record.
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
			//unset($this->Columns->IsDeleted);
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
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,false,"DELETE");
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
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,json_encode($this->Columns),"PATCH");
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
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,json_encode($record),"PATCH");
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}
			return true;
		}
	}
?>