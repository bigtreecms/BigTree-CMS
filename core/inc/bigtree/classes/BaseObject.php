<?php
	/*
		Class: BigTree\BaseObject
			Implements a bunch of magic methods that other classes inherit.
	*/
	
	namespace BigTree;
	
	/**
	 * @property-read array $Array
	 * @property-read string $ID
	 */
	
	class BaseObject
	{
		
		public function __call($method, $arguments)
		{
			return static::__callStatic($method, $arguments);
		}
		
		// Get magic methods to allow for Array and ID returns
		public function __get($property)
		{
			// Many inherited objects will have read-only properties
			if (property_exists($this, $property)) {
				return $this->$property;
			}
			
			// Allow for protected get(Property) methods
			if (method_exists($this, "get".$property)) {
				$this->$property = call_user_func([$this, "get".$property]);
				
				return $this->$property;
			}
			
			// Allow easy conversion to the old array format for the new data objects
			if ($property == "Array") {
				$raw_properties = get_object_vars($this);
				$changed_properties = [];
				
				foreach ($raw_properties as $key => $value) {
					$changed_properties[$this->_camelCaseToUnderscore($key)] = $value;
				}
				
				return $changed_properties;
			}
			
			trigger_error("Invalid property on ".get_called_class().": $property", E_USER_ERROR);
			
			return null;
		}
		
		public function __isset($property): bool
		{
			// Many inherited objects will have read-only properties
			if (property_exists($this, $property)) {
				return true;
			}
			
			// Allow for protected get(Property) methods
			if (method_exists($this, "get".$property)) {
				return true;
			}
			
			if ($property == "Array") {
				return true;
			}
			
			return false;
		}
		
		// Courtesy of StackOverflow: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
		public static function _camelCaseToUnderscore(string $string): string
		{
			preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
			$ret = $matches[0];
			
			foreach ($ret as &$match) {
				$match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
			}
			
			return implode("_", $ret);
		}
		
		/*
			Function: inherit
				Copies another object's properties back to this object.

			Parameters:
				object - Another object
		*/
		
		public function inherit($object): void
		{
			$properties = get_object_vars($object);
			
			foreach ($properties as $key => $value) {
				$this->$key = $object->$key;
			}
		}
		
	}