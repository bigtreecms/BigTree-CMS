<?php
	/*
		Class: BigTree\Salesforce\Object
			An object that emulates most of the methods from BigTreeModule for a Salesforce object type.
	*/
	
	namespace BigTree\Salesforce;
	
	use stdClass;
	
	class Object {
		
		/** @var \BigTree\Salesforce\API */
		protected $API;
		
		public $Activateable;
		public $Creatable;
		public $Deletable;
		public $Fields;
		public $Label;
		public $LabelPlural;
		public $Layoutable;
		public $Mergeable;
		public $Name;
		public $Queryable;
		public $QueryFieldNames;
		public $Replicateable;
		public $Retrieveable;
		public $Searchable;
		public $Triggerable;
		public $Undeletable;
		public $Updateable;
		
		/*
			Constructor:
				Creates a new BigTree\Salesforce\Object

			Parameters:
				object - Salesforce data
				api - Reference to BigTree\Salesforce\API class instance
		*/
		
		function __construct(stdClass $object, API &$api) {
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
				return;
			}
			
			$fields = [];
			$field_names = [];
			
			foreach ($response->fields as $f) {
				$field = new stdClass;
				
				if (count($f->picklistValues)) {
					$field->AvailableValues = [];
					
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
			$this->QueryFieldNames = implode(",", $field_names);
		}
		
		/*
			Function: add
				Adds a record to Salesforce.

			Parameters:
				keys - An array of column names to add (if this is a key/value array vals can be ignored)
				vals - An array of values for each of the columns

			Returns:
				A BigTree\Salesforce\Record object if successful.
		*/
		
		function add(array $keys, ?array $vals = null): ?Record {
			if (is_null($vals)) {
				$data = $keys;
			} else {
				$data = array_combine($keys, $vals);
			}
			
			$response = $this->API->callUncached("sobjects/".$this->Name."/", json_encode($data), "POST");
			
			// Look for a response ID.
			if (isset($response->id)) {
				// Setup a dumb record and build it ourselves
				$record_data = new stdClass;
				
				foreach ($this->Fields as $field) {
					$field_name = $field->Name;
					$record_data->$field_name = isset($record[$field_name]) ? $record[$field_name] : "";
				}
				
				$record = new Record($record_data, $this->API);
				$record->ID = $response->id;
				$record->Type = $this->Name;
				$record->CreatedAt = $record->UpdatedAt = date("Y-m-d H:i:s");
				
				return $record;
			} else {
				$this->API->Errors[] = (!is_array($response)) ? json_decode($response) : $response;
				
				return null;
			}
		}
		
		/*
			Function: delete
				Deletes a record from Salesforce.

			Returns:
				true if successful.
		*/
		
		function delete(string $id): bool {
			$response = $this->API->callUncached("sobjects/".$this->Name."/$id", false, "DELETE");
			
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
				A BigTree\Salesforce\Record object
		*/
		
		function get(string $id): ?Record {
			$response = $this->API->call("sobjects/".$this->Name."/$id");
			
			if (!$response) {
				return null;
			}
			
			return new Record($response, $this->API);
		}
		
		/*
			Function: getAll
				Returns all records of this object type.

			Parameters:
				order - The sort order (in SOSQL syntax, defaults to "Id ASC")

			Returns:
				An array of BigTree\Salesforce\Record objects.
		*/
		
		function getAll(string $order = "Id ASC"): ?array {
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
				An array of BigTree\Salesforce\Record objects.
		*/
		
		function getMatching($fields, $values, string $order = "Id ASC", ?int $limit = null): ?array {
			if (!is_array($fields)) {
				$where = "$fields = '".addslashes($values)."'";
			} else {
				$x = 0;
				$where = [];
				
				while ($x < count($fields)) {
					$where[] = $fields[$x]." = '".addslashes($values[$x])."'";
					$x++;
				}
				
				$where = implode(" AND ", $where);
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
				An array of BigTree\Salesforce\Record objects.
		*/
		
		function getPage(int $page = 1, string $order = "Id ASC", int $perpage = 15, ?string $where = null): ?array {
			// Don't try for page 0
			if ($page < 1) {
				$page = 1;
			}
			
			if ($where) {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." 
						  WHERE $where ORDER BY $order 
						  LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			} else {
				$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." 
						  ORDER BY $order LIMIT $perpage OFFSET ".(($page - 1) * $perpage);
			}
			
			return $this->query($query);
		}
		
		/*
			Function: query
				Perform a SOQL query.

			Parameters:
				query - A SOQL query string
				full_response - Whether to return the actual response object (true) or an array of records (false)

			Returns:
				An array of BigTree\Salesforce\Record objects.
		*/
		
		function query(string $query, bool $full_response = false) {
			if (strpos($query, "query/") > -1) {
				$response = $this->API->call($query);
			} else {
				$response = $this->API->call("query/?q=".urlencode($query));
			}
			
			if (!isset($response->records)) {
				return null;
			}
			
			$records = [];
			
			foreach ($response->records as $record) {
				$records[] = new Record($record, $this->API);
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
				An array of BigTree\Salesforce\Record objects.
		*/
		
		function search(string $query, string $order = "Id ASC", ?int $limit = null): ?array {
			$where = [];
			$searchable_types = ["string", "picklist", "textarea", "phone", "url"];
			
			foreach ($this->Fields as $field) {
				if (in_array($field->Type, $searchable_types) && $field->Name != "Description") {
					$where[] = $field->Name." LIKE '%".addslashes($query)."%'";
				}
			}
			
			if (!count($where)) {
				return null;
			}
			
			$query = "SELECT ".$this->QueryFieldNames." FROM ".$this->Name." WHERE ".implode(" OR ", $where)." ORDER BY $order";
			
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
		
		function update(string $id, $fields, $values): bool {
			if (is_array($fields)) {
				$record = array_combine($fields, $values);
			} else {
				$record = [$fields => $values];
			}
			
			$response = $this->API->callUncached("sobjects/".$this->Name."/$id", json_encode($record), "PATCH");
			
			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				
				return false;
			}
			
			return true;
		}
		
	}
