<?php
	namespace BigTree;

	use BigTreeCMS;

	class BaseObject {

		static $Table = "";

		// Magic method to allow for getBy and allBy methods where you pass in a column name
		static function __callStatic($method,$arguments) {
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
				$column = static::_camelCaseToUnderscore(substr($method,5));
	
				// Magically create "allBy" methods
				if (substr($method,0,5) == "allBy") {
					$sort = !empty($arguments[1]) ? " ORDER BY ".$arguments[1] : "";
					$records = BigTreeCMS::$DB->fetchAll("SELECT * FROM `".static::$Table."` WHERE `$column` = ? $sort", $value);

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
				if (substr($method,0,5) == "getBy") {

					$record = BigTreeCMS::$DB->fetch("SELECT * FROM `".static::$Table."` WHERE `$column` = ?", $value);
					if (!$record) {
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
		}
		function __call($method,$arguments) { return static::__callStatic($method,$arguments); }

		// Get magic methods to allow for Array and ID returns
		function __get($property) {
			// Many inherited objects will have read-only ID
			if ($property == "ID") {
				return $this->ID;
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
		}

		// Courtesy of StackOverflow: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		static function _camelCaseToUnderscore($string) {
			preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
  			$ret = $matches[0];
  			foreach ($ret as &$match) {
  			  $match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
  			}
  			return implode("_",$ret);
		}

		/*
			Function: all
				Returns an array of callout groups sorted by name.

			Parameters:
				sort - Sort direction (optional)
				return_arrays - Set to true to return arrays rather than objects.

			Returns:
				An array of BigTree\CalloutGroup objects.
		*/

		static function all($sort = false,$return_arrays = false) {
			// Must have a static Table var.
			if (empty(static::$Table)) {
				trigger_error('Method "all" must be called from a subclass where the static variable $Table has been set.',E_ERROR);
				return false;
			}

			$sort = $sort ? " ORDER BY ".$sort : "";
			$records = BigTreeCMS::$DB->fetchAll("SELECT * FROM `".static::$Table."` $sort");

			// Third parameter will be whether to return as an array
			if (!$return_arrays) {
				$class = get_called_class();
				foreach ($records as &$record) {
					$record = new $class($record);
				}
			}

			return $records;
		}

	}