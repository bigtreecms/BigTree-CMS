<?php
	/*
		Class: BigTree\Globalize
			A helper class for globalizing arrays.
	*/

	namespace BigTree;

	class Globalize {

		/*
			Function: arrayObject
				Globalizes all the keys of an array or object into global variables without compromising super global ($_) variables.
				Optionally runs a list of functions (passed in after the array) on the data.
			
			Parameters:
				array - An array with key/value pairs.
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data
			
			See Also:
				<globalizeGETVars>
				<globalizePOSTVars>
		*/
		
		static function arrayObject($array) {
			if (is_object($array)) {
				$array = get_object_vars($array);
			}

			if (!is_array($array)) {
				return false;
			}

			// We don't want to lose track of our array while globalizing, so we're going to save things into $bigtree
			// Since we're not in the global scope, it doesn't matter that we're junking up $bigtree
			$bigtree = array("functions" => array_slice(func_get_args(), 1), "array" => $array);

			foreach ($bigtree["array"] as $bigtree["key"] => $bigtree["val"]) {
				// Prevent messing with super globals
				if (substr($bigtree["key"], 0, 1) != "_" && !in_array($bigtree["key"], array("admin", "bigtree", "cms"))) {
					// Fix for PHP 7
					$key = $bigtree["key"];
					global $$key;

					if (is_array($bigtree["val"])) {
						$$key = static::recurse($bigtree["val"], $bigtree["functions"]);
					} else {
						foreach ($bigtree["functions"] as $bigtree["function"]) {
							// Backwards compatibility with old array passed syntax
							if (is_array($bigtree["function"])) {
								foreach ($bigtree["function"] as $bigtree["f"]) {
									$bigtree["val"] = $bigtree["f"]($bigtree["val"]);
								}
							} else {
								$bigtree["val"] = $bigtree["function"]($bigtree["val"]);
							}
						}

						$$key = $bigtree["val"];
					}
				}
			}
			
			return true;
		}

		static function recurse($data, $functions) {
			foreach ($data as $key => $val) {
				if (is_array($val)) {
					$data[$key] = static::recurse($val, $functions);
				} else {
					foreach ($functions as $func) {
						// Backwards compatibility with old array passed syntax
						if (is_array($func)) {
							foreach ($func as $f) {
								$val = $f($val);
							}
						} else {
							$val = $func($val);
						}
					}
					$data[$key] = $val;
				}
			}

			return $data;
		}

		/*
			Function: GET
				Globalizes all the $_GET variables without compromising $_ variables.
				Optionally runs a list of functions passed in as arguments on the data.

			Parameters:
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data

			See Also:
				<globalizeArray>
				<globalizePOSTVars>

		*/

		static function GET() {
			$args = func_get_args();

			return call_user_func_array("BigTree::globalizeArray", array_merge(array($_GET), $args));
		}

		/*
			Function: POST
				Globalizes all the $_POST variables without compromising $_ variables.
				Optionally runs a list of functions passed in as arguments on the data.

			Parameters:
				functions - Pass in additional arguments to run functions (i.e. "htmlspecialchars") on the data

			See Also:
				<globalizeArray>
				<globalizeGETVars>
		*/

		static function POST() {
			$args = func_get_args();

			return call_user_func_array("BigTree::globalizeArray", array_merge(array($_POST), $args));
		}

	}
