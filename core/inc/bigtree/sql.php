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
			Function: delete
				Deletes a row in the given table

			Parameters:
				table - The table to insert a row into
				id - The ID of the row to delete (or an associate array of key/value pairs to match)

			Returns:
				true if successful (even if no rows match)
		*/

		function delete($table,$id) {
			$values = $where = array();

			// If the ID is an associative array we match based on the given columns
			if (is_array($id)) {
				foreach ($id as $column => $value) {
					$where[] = "`$column` = ?";
					array_push($values,$value);
				}
			// Otherwise default to id
			} else {
				$where[] = "`id` = ?";
				array_push($values,$id);
			}

			// Add the query and the id parameter into the function parameters
			array_unshift($values,"DELETE FROM `$table` WHERE ".implode(" AND ",$where));

			// Call BigTreeSQL::query
			$response = call_user_func_array(array($this,"query"),$values);
			return $response->ActiveQuery ? true : false;
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
				values - An array of key/value pairs to match against (i.e. "id" => "10") or just an ID

			Returns:
				true if a row already exists that matches the passed in key/value pairs.
		*/

		function exists($table,$values) {
			if (!is_array($values) || !array_filter($values)) {
				trigger_error("BigTreeSQL::exists expects a non-empty array as its second parameter");
				return false;
			}

			// Passing an array of key/value pairs
			if (is_array($values)) {
				$where = array();
				foreach ($values as $key => $value) {
					$where[] = "`$key` = ?";
				}
			// Allow for just passing an ID
			} else {
				$where = array("`id` = ?");
				$values = array($values);
			}

			// Push the query onto the array stack so it's the first query parameter
			array_unshift($values,"SELECT COUNT(*) FROM `$table` WHERE ".implode(" AND ",$where));

			// Execute query, return a single result
			return call_user_func_array(array($this,"fetchSingle"),$values) ? true : false;
		}

		/*
			Function: fetch
				Equivalent to calling mysql_fetch_assoc on a query.
				If a query string is passed rather than a chained call it will return a single row after executing the query.

			Parameters:
				query - Optional, a query to execute before fetching
				parameters - Additional parameters to send to the query method

			Returns:
				A row from the active query (or false if no more rows exist)
		*/

		function fetch() {
			// Allow this to be called without calling query first
			$args = func_get_args();
			if (count($args)) {
				$query = call_user_func_array(array($this,"query"),$args);
				return $query->fetch();
			}

			// Chained call
			if (is_bool($this->ActiveQuery) || is_null($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetch called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				return $this->ActiveQuery->fetch_assoc();
			}
		}

		/*
			Function: fetchAll
				Returns all remaining rows for the active query.
				If a query string is passed rather than a chained call it will return the results after executing the query.

			Parameters:
				query - Optional, a query to execute before fetching
				parameters - Additional parameters to send to the query method
			
			Returns:
				An array of rows from the active query.
		*/

		function fetchAll() {
			// Allow this to be called without calling query first
			$args = func_get_args();
			if (count($args)) {
				$query = call_user_func_array(array($this,"query"),$args);
				return $query->fetchAll();
			}

			// Chained call
			if (is_bool($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetchAll called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				$results = array();
				while ($result = $this->ActiveQuery->fetch_assoc()) {
					$results[] = $result;
				}
				return $results;
			}
		}

		/*
			Function: fetchAllSingle
				Equivalent to the fetchAll method but only the first column of each row is returned.

			Parameters:
				query - Optional, a query to execute before fetching
				parameters - Additional parameters to send to the query method
			
			Returns:
				An array of the first column of each row from the active query.

			See Also:
				<fetchAll>
		*/

		function fetchAllSingle() {
			// Allow this to be called without calling query first
			$args = func_get_args();
			if (count($args)) {
				$query = call_user_func_array(array($this,"query"),$args);
				return $query->fetchAllSingle();
			}

			// Chained call
			if (is_bool($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetchAllSingle called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				$results = array();
				while ($result = $this->ActiveQuery->fetch_assoc()) {
					$results[] = current($result);
				}
				return $results;
			}
		}

		/*
			Function: fetchSingle
				Equivalent to the fetch method but only the first column of the row is returned.
			
			Parameters:
				query - Optional, a query to execute before fetching
				parameters - Additional parameters to send to the query method

			Returns:
				The first column from the returned row.

			See Also:
				<fetch>
		*/

		function fetchSingle() {
			// Allow this to be called without calling query first
			$args = func_get_args();
			if (count($args)) {
				$query = call_user_func_array(array($this,"query"),$args);
				return $query->fetchSingle();
			}

			// Chained call
			if (is_bool($this->ActiveQuery)) {
				trigger_error("BigTreeSQL::fetchSingle called on invalid query resource. The most likely cause is an invalid query call. Last error returned was: ".$this->ErrorLog[count($this->ErrorLog) - 1],E_USER_WARNING);
				return false;
			} else {
				$result = $this->ActiveQuery->fetch_assoc();
				return is_array($result) ? current($result) : false;
			}
		}

		/*
			Function: insert
				Inserts a row into the database and returns the primary key

			Parameters:
				table - The table to insert a row into
				values - An associative array of columns and values (i.e. "column" => "value")

			Returns:
				Primary key of the inserted row
		*/

		function insert($table,$values) {
			if (!is_array($values) || !array_filter($values)) {
				trigger_error("BigTreeSQL::inserts expects a non-empty array as its second parameter");
				return false;
			}

			$columns = array();
			$vals = array();
			foreach ($values as $column => $value) {
				$columns[] = "`$column`";

				if ($value === "NULL" || $value === "NOW()") {
					$vals[] = $value;
				} else {
					$vals[] = "'".$this->escape($value)."'";
				}
			}
			
			$query_response = $this->query("INSERT INTO `$table` (".implode(",",$columns).") VALUES (".implode(",",$vals).")");
			$id = $query_response->insertID();
			return $id ? $id : $query_response->ActiveQuery;
		}

		/*
			Function: insertID
				Equivalent to calling mysql_insert_id.

			Returns:
				The primary key for the most recently inserted row.
		*/

		function insertID() {
			if ($this->WriteConnection && $this->WriteConnection !== "disconnected") {
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
					throw new Exception("BigTreeSQL::query error - wildcard and argument count do not match ($wildcard_count '?' found, ".(count($args) - 1)." arguments provided)");
				}

				// Do the replacements and escapes
				$x = 1;
				while (($position = strpos($query,"?")) !== false) {
					// Allow for these reserved keywords to be let through unescaped
					if ($args[$x] === "NULL" || $args[$x] === "NOW()") {
						$replacement = $args[$x];
					} else {
						$replacement = "'".$this->Connection->real_escape_string($args[$x])."'";
					}

					$query = substr($query,0,$position).$replacement.substr($query,$position + 1);
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

			Parameters:
				query - Optional returned query object (defaults to using chained method)

			Returns:
				Number of rows for the active query.
		*/

		function rows($query = false) {
			if ($query) {
				return $query->ActiveQuery->num_rows;
			}
			return $this->ActiveQuery->num_rows;
		}

		/*
			Function: unique
				Retrieves a unique version of a given field for a table.
				Appends trailing numbers to the string until a unique version is found (i.e. value-2)
				Useful for creating unique routes.

			Parameters:
				table - Table to search
				field - Field that must be unique
				value - Value to check
				id - An optional ID for a record to disregard (either a single value for checking "id" column or key/value pair)
				inverse - Set to true to force the id column to true rather than false

			Returns:
				Unique version of value.
		*/

		function unique($table,$field,$value,$id = false,$inverse = false) {
			$original_value = $value;
			$count = 1;

			// If we're checking against an ID
			if ($id) {

				// Allow for passing array("column" => "value")
				if (is_array($id)) {
					list($id_column) = array_keys($id);
					$id_value = current($id);
				// Allow for passing "value"
				} else {
					$id_column = "id";
					$id_value = $id;
				}

				// If inverse, switch ID requirement to be = rather than !=
				if ($inverse) {
					$query = "SELECT COUNT(*) FROM `$table` WHERE `$field` = ? AND `$id_column` = ?";
				} else {
					$query = "SELECT COUNT(*) FROM `$table` WHERE `$field` = ? AND `$id_column` != ?";
				}

				while ($this->fetchSingle($query,$value,$id_value)) {
					$count++;
					$value = $original_value."-$count";
				}

			// Checking the whole table
			} else {
				while ($this->fetchSingle("SELECT COUNT(*) FROM `$table` WHERE `$field` = ?",$value)) {
					$count++;
					$value = $original_value."-$count";
				}
			}

			return $value;
		}

		/*
			Function: update
				Updates a row in the database

			Parameters:
				table - The table to insert a row into
				id - The ID of the row to update (or an associate array of key/value pairs to match)
				values - An associative array of columns and values (i.e. "column" => "value")

			Returns:
				true if successful (even if no rows match)
		*/

		function update($table,$id,$values) {
			if (!is_array($values) || !array_filter($values)) {
				trigger_error("BigTreeSQL::update expects a non-empty array as its third parameter");
				return false;
			}

			// Setup our array to implode into a query
			$set = array();
			foreach ($values as $column => $value) {
				$set[] = "`$column` = ?";
			}

			$where = array();
			// If the ID is an associative array we match based on the given columns
			if (is_array($id)) {
				foreach ($id as $column => $value) {
					$where[] = "`$column` = ?";
					array_push($values,$value);
				}
			// Otherwise default to id
			} else {
				$where[] = "`id` = ?";
				array_push($values,$id);
			}

			// Add the query and the id parameter into the function parameters
			array_unshift($values,"UPDATE `$table` SET ".implode(", ",$set)." WHERE ".implode(" AND ",$where));

			// Call BigTreeSQL::query
			$response = call_user_func_array(array($this,"query"),$values);
			return $response->ActiveQuery ? true : false;
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
		return $result->rows();
	}

	function sqlid() {
		return BigTreeCMS::$DB->insertID();
	}

	function sqlescape($string) {
		return BigTreeCMS::$DB->escape($string);
	}