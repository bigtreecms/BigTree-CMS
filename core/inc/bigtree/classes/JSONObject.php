<?php
	/*
		Class: BigTree\JSONObject
			An interface for working with the JSON DB store.
			Implements protected properties as read-only properties and getXYZ method calls that accept no parameters as read-only properties.
	*/
	
	namespace BigTree;
	
	use Exception;
	
	/**
	 * @property-read array $Array
	 * @property-read string $ID
	 */
	
	class JSONObject extends BaseObject
	{
		
		public static $Store = "";
		
		// Magic method to allow for getBy and allBy methods where you pass in a column name
		public static function __callStatic($method, $arguments)
		{
			// Magic methods only work for sub classes with table set
			if (static::$Store) {
				
				// Allow full objects to be passed in as the matching value
				if (is_object($arguments[0])) {
					$value = $arguments[0]->ID;
				} elseif (is_array($arguments[0])) {
					$value = $arguments[0]["id"];
				} else {
					$value = $arguments[0];
				}
				
				// Get column to match against
				$column = static::_camelCaseToUnderscore(substr($method, 5));
				
				// Magically create "allBy" methods
				if (substr($method, 0, 5) == "allBy") {
					$records = DB::getAll(static::$Store, !empty($arguments[1]) ? $arguments[1] : null);
					
					foreach ($records as $index => $entry) {
						if ($entry[$column] != $value) {
							unset($records[$index]);
						}
					}
					
					// Third parameter will be whether to return as an array
					if (empty($arguments[2])) {
						$class = get_called_class();
						
						foreach ($records as &$record) {
							$record = new $class($record);
						}
					}
					
					return $records;
				}
				
				// Magically create "getBy" methods
				if (substr($method, 0, 5) == "getBy") {
					$records = DB::getAll(static::$Store);
					$record = null;
					
					foreach ($records as $entry) {
						if ($entry[$column] == $value) {
							$record = $entry;
						}
					}
					
					if (is_null($record)) {
						return null;
					}
					
					// Second parameter will be whether to return as an array
					if (empty($arguments[1])) {
						$class = get_called_class();
						$record = new $class($record);
					}
					
					return $record;
				}
			}
			
			throw new Exception("Invalid method on ".get_called_class().": $method");
		}
		
		/*
			Function: all
				Returns an array of records.

			Parameters:
				sort_column - Column to sort entries by (optional)
				sort_direction - Sort direction (ASC or DESC, defaults to ASC)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of the calling object types (or arrays).
		*/
		
		public static function all(?string $sort_column = null, string $sort_direction = "ASC",
								   bool $return_arrays = false): array
		{
			// Must have a static Table var.
			if (empty(static::$Store)) {
				trigger_error('Method "all" must be called from a subclass where the static variable $Store has been set.', E_USER_ERROR);
				
				return [];
			}
			
			$records = DB::getAll(static::$Store, $sort_column ?: null, $sort_direction);
			
			// Third parameter will be whether to return as an array
			if (!$return_arrays) {
				$class = get_called_class();
				
				foreach ($records as &$record) {
					$record = new $class($record);
				}
			}
			
			return $records;
		}
		
		/*
			Function: delete
				Deletes the database record for the calling object and records the action in the audit trail (if logged in).
		*/
		
		public function delete(): ?bool
		{
			// Must have a static Table var.
			if (empty(static::$Store)) {
				trigger_error('Method "delete" must be called from a subclass where the static variable $Store has been set.', E_USER_ERROR);
				
				return null;
			}
			
			DB::delete(static::$Store, $this->ID);
			AuditTrail::track("config:".static::$Store, $this->ID, "deleted");
			
			return true;
		}
		
		/*
			Function: exists
				Determines whether an object exists for a given id.

			Parameters:
				id - The object ID to check for.

			Returns:
				true if the setting exists, otherwise false.
		*/
		
		public static function exists(?string $id): ?bool
		{
			// Must have a static Table var.
			if (empty(static::$Store)) {
				trigger_error('Method "exists" must be called from a subclass where the static variable $Store has been set.', E_USER_ERROR);
				
				return null;
			}
			
			if (is_null($id)) {
				return false;
			}
			
			return DB::exists(static::$Store, $id);
		}
		
		/*
			Function: save
				Reads all object properties and compares them against the related table.
				Saves matching columns to the database and logs the audit trail (if logged in).
		*/
		
		public function save(): ?bool
		{
			// Must have a static Table var.
			if (empty(static::$Store)) {
				trigger_error('Method "save" must be called from a subclass where the static variable $Store has been set.', E_USER_ERROR);
				
				return null;
			}
			
			// Get the table description and an array equivalent of all object properties
			$array_data = $this->Array;
			$insert_data = [];
			
			foreach ($array_data as $key => $value) {
				// We're not going to update IDs or columns not found in the table
				if ($key != "id") {
					$insert_data[$key] = $value;
				}
			}
			
			if (empty($this->ID) || !DB::exists(static::$Store, $this->ID)) {
				$this->ID = DB::insert(static::$Store, $insert_data);
				AuditTrail::track("config:".static::$Store, $this->ID, "created");
			} else {
				DB::update(static::$Store, $this->ID, $insert_data);
				AuditTrail::track("config:".static::$Store, $this->ID, "updated");
			}
			
			return true;
		}
		
	}
	