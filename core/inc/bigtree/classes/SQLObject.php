<?php
	/*
		Class: BigTree\SQLObject
			An interface for working with the MySQL DB store.
			Implements protected properties as read-only properties and getXYZ method calls that accept no parameters as read-only properties.
	*/
	
	namespace BigTree;
	
	use Exception;
	
	/**
	 * @property-read array $Array
	 */
	
	class SQLObject extends BaseObject
	{
		
		public static $Table = "";
		
		// Magic method to allow for getBy and allBy methods where you pass in a column name
		public static function __callStatic($method, $arguments)
		{
			// Magic methods only work for sub classes with table set
			if (static::$Table) {
				
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
					$sort = !empty($arguments[1]) ? " ORDER BY ".$arguments[1] : "";
					$records = SQL::fetchAll("SELECT * FROM `".static::$Table."` WHERE `$column` = ? $sort", $value);
					
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
					
					$record = SQL::fetch("SELECT * FROM `".static::$Table."` WHERE `$column` = ?", $value);
					
					if (empty($record)) {
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
				sort - Sort column/direction (optional)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of the calling object types (or arrays).
		*/
		
		public static function all(?string $sort = null, bool $return_arrays = false): array
		{
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "all" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);
				
				return [];
			}
			
			$sort = $sort ? " ORDER BY ".$sort : "";
			$records = SQL::fetchAll("SELECT * FROM `".static::$Table."` $sort");
			
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
			if (empty(static::$Table)) {
				trigger_error('Method "delete" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);
				
				return null;
			}
			
			SQL::delete(static::$Table, $this->ID);
			AuditTrail::track(static::$Table, $this->ID, "delete", "deleted");
			
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
			if (empty(static::$Table)) {
				trigger_error('Method "exists" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);
				
				return null;
			}
			
			if (is_null($id)) {
				return false;
			}
			
			return SQL::exists(static::$Table, $id);
		}
		
		/*
			Function: save
				Reads all object properties and compares them against the related table.
				Saves matching columns to the database and logs the audit trail (if logged in).
		*/
		
		public function save(): ?bool
		{
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "save" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);
				
				return null;
			}
			
			// Get the table description and an array equivalent of all object properties
			$table_description = SQL::describeTable(static::$Table);
			$array_data = $this->Array;
			$sql_data = [];
			
			foreach ($array_data as $key => $value) {
				// We're not going to update IDs or columns not found in the table
				if ($key != "id" && isset($table_description["columns"][$key])) {
					$sql_data[$key] = $value;
				}
			}
			
			if (empty($this->ID)) {
				$this->ID = SQL::insert(static::$Table, $sql_data);
				AuditTrail::track(static::$Table, $this->ID, "add", "created");
			} else {
				SQL::update(static::$Table, $this->ID, $sql_data);
				AuditTrail::track(static::$Table, $this->ID, "update", "updated");
			}
			
			return true;
		}
		
	}
	