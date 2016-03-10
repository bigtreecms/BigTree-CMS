<?php
	namespace BigTree;

	class BaseObject {

		function __get($property) {
			// Many inherited objects will have read-only ID
			if ($property == "ID") {
				return $this->ID;
			}

			// Allow easy conversion to the old array format for the new data objects
			if ($property == "Array") {
				$raw_properties = get_object_vars($this);
				$changed_properties = array();

				// We're going to change from CamelCase to underscore_case for array conversion.
				// Courtesy of StackOverflow: http://stackoverflow.com/questions/1993721/how-to-convert-camelcase-to-camel-case
				foreach ($raw_properties as $key => $value) {
					preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $key, $matches);
  					$ret = $matches[0];
  					foreach ($ret as &$match) {
  					  $match = ($match == strtoupper($match)) ? strtolower($match) : lcfirst($match);
  					}
  					$changed_properties[implode('_', $ret)] = $value;
				}

				return $changed_properties;
			}
		}

	}