<?php
	/*
		Class: BigTree\BaseObject
			A base object class from which most BigTree classes inherit.
			Implements protected properties as read-only properties and getXYZ method calls that accept no parameters as read-only properties.
	*/

	namespace BigTree;

	/**
	 * @property-read array $Array
	 */

	class BaseObject {

		public static $Table = "";

		// Magic method to allow for getBy and allBy methods where you pass in a column name
		static function __callStatic($method, $arguments) {
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
						return false;
					}

					// Second parameter will be whether to return as an array
					if (empty($arguments[1])) {
						$class = get_called_class();
						$record = new $class($record);
					}

					return $record;
				}
			}

			throw new \Exception("Invalid method on ".get_called_class().": $method");
		}

		function __call($method, $arguments) {
			return static::__callStatic($method, $arguments);
		}

		// Get magic methods to allow for Array and ID returns
		function __get($property) {

			// Many inherited objects will have read-only properties
			if (property_exists($this, $property)) {
				return $this->$property;
			}

			// Allow for protected get(Property) methods
			if (method_exists($this, "get".$property)) {
				$this->$property = call_user_func(array($this, "get".$property));

				return $this->$property;
			}

			// Allow easy conversion to the old array format for the new data objects
			if ($property == "Array") {
				$raw_properties = get_object_vars($this);
				$changed_properties = array();

				foreach ($raw_properties as $key => $value) {
					$changed_properties[$this->_camelCaseToUnderscore($key)] = $value;
				}

				return $changed_properties;
			}

			trigger_error("Invalid property on ".get_called_class().": $property", E_USER_ERROR);

			return null;
		}

		// Courtesy of StackOverflow: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		static function _camelCaseToUnderscore($string) {
			preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
			$ret = $matches[0];
			foreach ($ret as &$match) {
				$match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
			}

			return implode("_", $ret);
		}

		/*
			Function: all
				Returns an array of records.

			Parameters:
				sort - Sort direction (optional)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of the calling object types (or arrays).
		*/

		static function all($sort = false, $return_arrays = false) {
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "all" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);

				return array();
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

		function delete() {
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "delete" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);

				return false;
			}

			SQL::delete(static::$Table, $this->ID);
			AuditTrail::track(static::$Table, $this->ID, "deleted");

			return true;
		}

		/*
			Function: inherit
				Copies another object's properties back to this object.

			Parameters:
				object - Another object
		*/

		function inherit($object) {
			$properties = get_object_vars($object);

			foreach ($properties as $key) {
				$this->$key = $object->$key;
			}
		}

		/*
			Function: save
				Reads all object properties and compares them against the related table.
				Saves matching columns to the database and logs the audit trail (if logged in).
		*/

		function save() {
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "save" must be called from a subclass where the static variable $Table has been set.', E_USER_ERROR);

				return false;
			}

			// Get the table description and an array equivalent of all object properties
			$table_description = SQL::describeTable(static::$Table);
			$array_data = $this->Array;
			$sql_data = array();

			foreach ($array_data as $key => $value) {
				// We're not going to update IDs or columns not found in the table
				if ($key != "id" && isset($table_description["columns"][$key])) {
					$sql_data[$key] = $value;
				}
			}

			if (empty($this->ID)) {
				$this->ID = SQL::insert(static::$Table, $sql_data);
				AuditTrail::track(static::$Table, $this->ID, "created");
			} else {
				SQL::update(static::$Table, $this->ID, $sql_data);
				AuditTrail::track(static::$Table, $this->ID, "updated");
			}

			return true;
		}

	}