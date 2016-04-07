<?php
	/*
		Class: BigTree\JSON
			Provides an interface for manipulating JSON.
	*/

	namespace BigTree;

	class JSON {

		static $PrettyPrint = false;
		
		/*
			Function: encode
				Encodes a variable as JSON. Uses pretty print if available. Optionally escapes for SQL.

			Parameters:
				var - Variable to JSON encode.
				sql - Whether to SQL escape the JSON (defaults to false).

			Returns:
				A JSON encoded string.
		*/

		static function encode($var,$sql = false) {
			// Only run version compare once in case we're encoding a lot of JSON
			if (static::$PrettyPrint === false) {
				if (version_compare(PHP_VERSION,"5.4.0") >= 0) {
					static::$PrettyPrint = 1;
				} else {
					static::$PrettyPrint = 0;
				}
			}

			// Use pretty print if we have PHP 5.4 or higher
			$json = (static::$PrettyPrint) ? json_encode($var,JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES) : json_encode($var);
			
			// SQL escape if requested
			if ($sql) {
				return SQL::escape($json);
			}

			return $json;
		}

		/*
			Function: encodeColumns
				Returns a JSON string of only the specified columns from each row in a dataset in compact format.

			Parameters:
				data - An array of rows/arrays
				columns - The columns of each row/sub-array to return in JSON
				preserve_keys - Whether to perserve keys (false turns the output into a JSON array, defaults to false)

			Returns:
				A JSON string.
		*/

		static function encodeColumns($data,$columns = array(),$preserve_keys = false) {
			// Only run version compare once in case we're encoding a lot of JSON
			if (static::$PrettyPrint === false) {
				if (version_compare(PHP_VERSION,"5.4.0") >= 0) {
					static::$PrettyPrint = 1;
				} else {
					static::$PrettyPrint = 0;
				}
			}

			$simple_data = array();
			foreach ($data as $key => $val) {
				$row = array();
				foreach ($columns as $column) {
					$row[$column] = $val[$column];
				}
				if ($preserve_keys) {
					$simple_data[$key] = $row;
				} else {
					$simple_data[] = $row;
				}
			}

			return (static::$PrettyPrint) ? json_encode($simple_data,JSON_UNESCAPED_SLASHES) : json_encode($simple_data);
		}

	}
