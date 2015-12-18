<?php
	/*
		Class: BigTreeSQL
			A MySQL helper class that wraps the pre-4.3 functions.
			When BigTree is bootstrapped, $db, $cms->DB, and $admin->DB are instances of this class.
	*/

	class BigTreeSQL {

		var $ActiveQuery;
		var $Connection = "disconnected";
		var $ErrorLog = array();
		var $QueryLog = array();
		var $WriteConnection = "disconnected";

		function __construct($chain_query = false) {
			// Chained instances should use the primary connection
			if ($chain_query) {
				$this->ActiveQuery = $chain_query;
				$this->Connection = BigTreeCMS::$DB->Connection;
				$this->WriteConnection = BigTreeCMS::$DB->WriteConnection;
			}
		}

		/*
			Function: connect
				Sets up the internal connections to the MySQL server(s).
		*/

		function connect($property,$type) {
			global $bigtree;

			// Initializing optional params, if they don't exist yet due to older install
			!empty($bigtree["config"][$type]["host"]) || $bigtree["config"][$type]["host"] = null;
			!empty($bigtree["config"][$type]["port"]) || $bigtree["config"][$type]["port"] = 3306;
			!empty($bigtree["config"][$type]["socket"]) || $bigtree["config"][$type]["socket"] = null;

			$this->$property = new mysqli(
				$bigtree["config"][$type]["host"],
				$bigtree["config"][$type]["user"],
				$bigtree["config"][$type]["password"],
				$bigtree["config"][$type]["name"],
				$bigtree["config"][$type]["port"],
				$bigtree["config"][$type]["socket"]
			);

			// Make sure everything is run in UTF8, turn off strict mode if set
			$this->$property->query("SET NAMES 'utf8'");
			$this->$property->query("SET SESSION sql_mode = ''");

			// Remove BigTree connection parameters once it is setup.
			unset($bigtree["config"][$type]["user"]);
			unset($bigtree["config"][$type]["password"]);

			return $this->$property;
		}

		/*
			Function: escape
				Equivalent to mysql_real_escape_string.
				Escapes non-string values by first encoding them as JSON.

			Parameters:
				string - Value to escape

			Returns:
				Escaped string
		*/

		function escape($string) {
			if (!is_string($string) && !is_numeric($string) && !is_bool($string) && $string) {
				$string = BigTree::json($string);
			}
			
			$connection = ($this->Connection && $this->Connection !== "disconnected") ? $this->Connection : $this->connect("Connection","db");
			return $connection->real_escape_string($string);
		}

		/*
			Function: exists
				Checks to see if an entry exists for given key/value pairs.

			Parameters:
				table - The table to search
				values - An array of key/value pairs to match against (i.e. "id" => "10")

			Returns:
				true if a row already exists that matches the passed in key/value pairs.
		*/

		function exists($table,$values) {
			if (!is_array($values) || !array_filter($values)) {
				trigger_error("BigTreeSQL::exists expects a non-empty array as its second parameter");
				return false;
			}

			$where = array();
			foreach ($values as $key => $value) {
				$where[] = "`$key` = ?";
			}

			// Push the query onto the array stack so it's the first query parameter
			array_unshift($values,"SELECT COUNT(*) FROM `$table` WHERE ".implode(" AND ",$where));

			// Execute query, return a single result
			$query = call_user_func_array(array($this,"query"),$values);
			return $query->fetch(false,true) ? true : false;
		}

		/*
			Function: fetch
				Equivalent to calling mysql_fetch_assoc on a query.
				If a query string is passed rather than a chained call it will return a single row after executing the query.

			Parameters:
				query - Optional, a query to execute before fetching
				single - Optional, if set to true only returns the first column of the row instead of an array

			Returns:
				A row from the active query (or false if no more rows exist)
		*/

		function fetch($query = false,$single = false) {
			if ($query) {
				return $this->query($query)->fetch(false,$single);
			}

			if (is_bool($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetch called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				$result = $this->ActiveQuery->fetch_assoc();
				if ($single) {
					return current($result);
				}
				return $result;
			}
		}

		/*
			Function: fetchAll
				Returns all remaining rows for the active query.
				If a query string is passed rather than a chained call it will return the results after executing the query.

			Parameters:
				query - Optional, a query to execute before fetching
				single - Optional, if set to true only returns the first column of each row instead of an array

			
			Returns:
				An array of rows from the active query.
		*/

		function fetchAll($query = false,$single = false) {
			if ($query) {
				return $this->query($query)->fetchAll(false,$single);
			}

			if (is_bool($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetchAll called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				$results = array();
				while ($result = $this->ActiveQuery->fetch_assoc()) {
					if ($single) {
						$results[] = current($result);
					} else {
						$results[] = $result;
					}
				}
				return $results;
			}
		}

		/*
			Function: insert
				Inserts a row into the database and returns the primary key

			Parameters:
				table - The table to insert a row into
				row - An associative array of columns and values (i.e. "column" => "value")

			Returns:
				Primary key of the inserted row
		*/

		function insert($table,$row) {
			$query = "INSERT INTO `$table` ";
			$columns = array();
			$values = array();
			foreach ($row as $column => $value) {
				$columns[] = "`$column`";
				$values[] = "'".$this->escape($value)."'";
			}
			return $this->query("INSERT INTO `$table` (".implode(",",$columns).") VALUES (".implode(",",$values).")")
						->insertID();
		}

		/*
			Function: insertID
				Equivalent to calling mysql_insert_id.

			Returns:
				The primary key for the most recently inserted row.
		*/

		function insertID() {
			if ($this->WriteConnection) {
				return $this->WriteConnection->insert_id;
			} else {
				return $this->Connection->insert_id;
			}
		}

		/*
			Function: query
				Queries the MySQL server(s).
				If you pass additional parameters "?" characters in your query statement
				  will be replaced with escaped values in the order they are found.

			Parameters:
				query - The MYSQL query to execute
				... - Optional parameters that will invoke MySQL prepared statement fills

			Returns:
				Another instance of BigTreeSQL for chaining fetch, fetchAll, insertID, or rows methods.
		*/

		function query($query) {
			global $bigtree;

			// Debug should log queries
			if ($bigtree["config"]["debug"]) {
				$this->QueryLog[] = $query;
			}

			// Setup our read connection if it disconnected for some reason
			$connection = ($this->Connection && $this->Connection !== "disconnected") ? $this->Connection : $this->connect("Connection","db");

			// If we have a separate write host, let's find out if we're writing and use it if so
			if (isset($bigtree["config"]["db_write"]) && $bigtree["config"]["db_write"]["host"]) {
				$commands = explode(" ",$query);
				$fc = strtolower($commands[0]);
				if ($fc == "create" || $fc == "drop" || $fc == "insert" || $fc == "update" || $fc == "set" || $fc == "grant" || $fc == "flush" || $fc == "delete" || $fc == "alter" || $fc == "load" || $fc == "optimize" || $fc == "repair" || $fc == "replace" || $fc == "lock" || $fc == "restore" || $fc == "rollback" || $fc == "revoke" || $fc == "truncate" || $fc == "unlock") {
					$connection = ($this->WriteConnection && $this->WriteConnection !== "disconnected") ? $this->WriteConnection : $this->connect("WriteConnection","db_write");
				}
			}

			// If we only have a single argument we're not doing a prepared statement thing
			$args = func_get_args();
			if (count($args) == 1) {
				$query_response = $connection->query($query);
			} else {
				// Check argument and ? count to trigger warnings
				$wildcard_count = substr_count($query,"?");
				if ($wildcard_count != (count($args) - 1)) {
					trigger_error("BigTreeSQL::query error - wildcard and argument count do not match ($wildcard_count '?' found, ".(count($args) - 1)." arguments provided)",E_USER_WARNING);
				}

				// Do the replacements and escapes
				$x = 1;
				while (($position = strpos($query,"?")) !== false) {
					$query = substr($query,0,$position)."'".$this->Connection->real_escape_string($args[$x])."'".substr($query,$position + 1);
					$x++;
				}

				// Return the query object
				$query_response = $connection->query($query);
			}

			// Log errors
			if (is_bool($query_response)) {
				$this->ErrorLog[] = $connection->error;
			}

			return new BigTreeSQL($query_response);
		}

		/*
			Function: rows
				Equivalent to calling mysql_num_rows.

			Returns:
				Number of rows for the active query.
		*/

		function rows($query) {
			return $query->num_rows;
		}
	}

	// Backwards compatibility

	function sqlquery($query) {
		return BigTreeCMS::$DB->query($query);
	}

	function sqlfetch($query) {
		return $query->fetch();
	}

	function sqlrows($result) {
		return $result->num_rows;
	}

	function sqlid() {
		return BigTreeCMS::$DB->insertID();
	}

	function sqlescape($string) {
		return BigTreeCMS::$DB->escape($string);
	}