<?
	/*
		Class: SQL Wrappers
			Support for splitting reads/writes and handling error throwing automatically.
	*/

	$bigtree["sql"]["errors"] = array();
	$bigtree["sql"]["queries"] = array();
	
	if (isset($bigtree["config"]["sql_interface"]) && $bigtree["config"]["sql_interface"] == "mysqli") {
	
		function bigtree_setup_sql_connection($read_write = "read") {
			global $bigtree;
			
			if ($read_write == "read") {
				$connection = new mysqli($bigtree["config"]["db"]["host"],$bigtree["config"]["db"]["user"],$bigtree["config"]["db"]["password"],$bigtree["config"]["db"]["name"]);
				$connection->query("SET NAMES 'utf8'");
				$connection->query("SET SESSION sql_mode = ''");
				// Remove BigTree connection parameters once it is setup.
				unset($bigtree["config"]["db"]["user"]);
				unset($bigtree["config"]["db"]["password"]);
			} else {
				$connection = new mysqli($bigtree["config"]["db_write"]["host"],$bigtree["config"]["db_write"]["user"],$bigtree["config"]["db_write"]["password"],$bigtree["config"]["db_write"]["name"]);
				$connection->query("SET NAMES 'utf8'");
				$connection->query("SET SESSION sql_mode = ''");
				// Remove BigTree connection parameters once it is setup.
				unset($bigtree["config"]["db_write"]["user"]);
				unset($bigtree["config"]["db_write"]["password"]);
			}
			return $connection;
		}
		
		/*
			Function: sqlquery
				Equivalent to mysqli_query / mysql_query in most cases.
				If BigTree has enabled splitting off to a separate write server this function will send all write related queries to the write server and all read queries to the read server.
				If BigTree has not enabled a separate write server the type parameter does not exist.
			
			Parameters:
				query - A query string.
				connection - An optional MySQL connection (normally this is chosen automatically)
				type - Chosen automatically if a connection isn't passed. "read" or "write" to specify which server to use.
				
			Returns:
				A MySQL query resource.
		*/
		
		if (isset($bigtree["config"]["db_write"]) && $bigtree["config"]["db_write"]["host"]) {
			function sqlquery($query,$connection = false,$type = "read") {
				global $bigtree;
				
				if ($bigtree["config"]["debug"]) {
					$bigtree["sql"]["queries"][] = $query;
				}
				
				if (!$connection) {
					$commands = explode(" ",$query);
					$fc = strtolower($bigtree["commands"][0]);
					if ($fc == "create" || $fc == "drop" || $fc == "insert" || $fc == "update" || $fc == "set" || $fc == "grant" || $fc == "flush" || $fc == "delete" || $fc == "alter" || $fc == "load" || $fc == "optimize" || $fc == "repair" || $fc == "replace" || $fc == "lock" || $fc == "restore" || $fc == "rollback" || $fc == "revoke" || $fc == "truncate" || $fc == "unlock") {
						$connection = &$bigtree["mysql_write_connection"];
						$type = "write";
					} else {
						$connection = &$bigtree["mysql_read_connection"];
						$type = "read";
					}
				}
				
				if ($connection === "disconnected") {
					$connection = bigtree_setup_sql_connection($type);
				}	
				
				$q = $connection->query($query);
				$e = $connection->error;
				if ($e) {
					$sqlerror = "<b>".$e."</b> in query &mdash; ".$query;
					array_push($bigtree["sql"]["errors"],$sqlerror);
					return false;
				}
				
				return $q;
			}
		} else {
			function sqlquery($query,$connection = false) {
				global $bigtree;
				
				if ($bigtree["config"]["debug"]) {
					$bigtree["sql"]["queries"][] = $query;
				}
				
				if (!$connection) {
					$connection = &$bigtree["mysql_read_connection"];
				}
				
				if ($connection === "disconnected") {
					$connection = bigtree_setup_sql_connection();
				}
				
				$q = $connection->query($query);
				$e = $connection->error;
				if ($e) {
					$sqlerror = "<b>".$e."</b> in query &mdash; ".$query;
					array_push($bigtree["sql"]["errors"],$sqlerror);
					return false;
				}
				
				return $q;
			}
		}
		
		/*
			Function: sqlfetch
				Equivalent to mysqli_fetch_assoc / mysql_fetch_assoc.
				Throws an exception if it is called on an invalid query resource which includes the most recent MySQL errors.
			
			Parameters:
				query - The mysql query resource (returned via sqlquery or mysql_query or mysql_db_query)
				ignore_errors - If set to true an exception will not be thrown on a bad query resource.
			
			Returns:
				A row from the query in array format with key/value pairs.
		*/
		
		function sqlfetch($query,$ignore_errors = false) {
			global $bigtree;
			// If the query is boolean, it's probably a "false" from a failed sql query.
			if (is_bool($query) && !$ignore_errors) {
				global $bigtree;
				if ($bigtree["config"]["debug"]) {
					throw new Exception("sqlfetch() called on invalid query resource. The most likely cause is an invalid sqlquery() call. Last error returned was: ".$bigtree["sql"]["errors"][count($bigtree["sql"]["errors"])-1]);
				}
				return false;
			} else {
				return $query->fetch_assoc();
			}
		}
		
		/*
			Function: sqlrows
				Equivalent to mysqli_num_rows / mysql_num_rows.
		*/
		
		function sqlrows($result) {
			return $result->num_rows;
		}
		
		/*
			Function: sqlid
				Equivalent to mysqli_insert_id / mysql_insert_id.
		*/
		
		function sqlid() {
			global $bigtree;
			if ($bigtree["mysql_write_connection"] !== "disconnected") {
				return $bigtree["mysql_write_connection"]->insert_id;
			} else {
				return $bigtree["mysql_read_connection"]->insert_id;
			}
		}
		
		/*
			Function: sqlescape
				Equivalent to mysqli_real_escape_string / mysql_real_escape_string
		*/
		
		function sqlescape($string) {
			global $bigtree;
			if ($bigtree["mysql_read_connection"] === "disconnected") {
				$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
			}
			if (!is_string($string) && !is_numeric($string) && !is_bool($string) && $string) {
				throw new Exception("sqlescape expects a string");
			}
			return mysqli_real_escape_string($bigtree["mysql_read_connection"],$string);
		}

	// These are the older MySQL extension versions
	} else {
		function bigtree_setup_sql_connection($read_write = "read") {
			global $bigtree;
			
			if ($read_write == "read") {
				$connection = mysql_connect($bigtree["config"]["db"]["host"],$bigtree["config"]["db"]["user"],$bigtree["config"]["db"]["password"]);
				mysql_select_db($bigtree["config"]["db"]["name"],$connection);
				mysql_query("SET NAMES 'utf8'",$connection);
				mysql_query("SET SESSION sql_mode = ''",$connection);
				// Remove BigTree connection parameters once it is setup.
				unset($bigtree["config"]["db"]["user"]);
				unset($bigtree["config"]["db"]["password"]);
			} else {
				$connection = mysql_connect($bigtree["config"]["db_write"]["host"],$bigtree["config"]["db_write"]["user"],$bigtree["config"]["db_write"]["password"]);
				mysql_select_db($bigtree["config"]["db_write"]["name"],$connection);
				mysql_query("SET NAMES 'utf8'",$connection);
				mysql_query("SET SESSION sql_mode = ''",$connection);
				// Remove BigTree connection parameters once it is setup.
				unset($bigtree["config"]["db_write"]["user"]);
				unset($bigtree["config"]["db_write"]["password"]);
			}
			return $connection;
		}
		
		if (isset($bigtree["config"]["db_write"]) && $bigtree["config"]["db_write"]["host"]) {
			function sqlquery($query,$connection = false,$type = "read") {
				global $bigtree;
				
				if ($bigtree["config"]["debug"]) {
					$bigtree["sql"]["queries"][] = $query;
				}
				
				if (!$connection) {
					$commands = explode(" ",$query);
					$fc = strtolower($bigtree["commands"][0]);
					if ($fc == "create" || $fc == "drop" || $fc == "insert" || $fc == "update" || $fc == "set" || $fc == "grant" || $fc == "flush" || $fc == "delete" || $fc == "alter" || $fc == "load" || $fc == "optimize" || $fc == "repair" || $fc == "replace" || $fc == "lock" || $fc == "restore" || $fc == "rollback" || $fc == "revoke" || $fc == "truncate" || $fc == "unlock") {
						$connection = &$bigtree["mysql_write_connection"];
						$type = "write";
					} else {
						$connection = &$bigtree["mysql_read_connection"];
						$type = "read";
					}
				}
				
				if ($connection === "disconnected") {
					$connection = bigtree_setup_sql_connection($type);
				}	
				
				$q = mysql_query($query,$connection);
				$e = mysql_error();
				if ($e) {
					$sqlerror = "<b>".$e."</b> in query &mdash; ".$query;
					array_push($bigtree["sql"]["errors"],$sqlerror);
					return false;
				}
				
				return $q;
			}
		} else {
			function sqlquery($query,$connection = false) {
				global $bigtree;
				
				if ($bigtree["config"]["debug"]) {
					$bigtree["sql"]["queries"][] = $query;
				}
				
				if (!$connection) {
					$connection = &$bigtree["mysql_read_connection"];
				}
				
				if ($connection === "disconnected") {
					$connection = bigtree_setup_sql_connection();
				}
				
				$q = mysql_query($query,$connection);
				$e = mysql_error();
				if ($e) {
					$sqlerror = "<b>".$e."</b> in query &mdash; ".$query;
					array_push($bigtree["sql"]["errors"],$sqlerror);
					return false;
				}
				
				return $q;
			}
		}
		
		function sqlfetch($query,$ignore_errors = false) {
			global $bigtree;
			
			// If the query is boolean, it's probably a "false" from a failed sql query.
			if (is_bool($query) && !$ignore_errors) {
				global $bigtree;
				if ($bigtree["config"]["debug"]) {
					throw new Exception("sqlfetch() called on invalid query resource. The most likely cause is an invalid sqlquery() call. Last error returned was: ".$bigtree["sql"]["errors"][count($bigtree["sql"]["errors"])-1]);
				}
				return false;
			} else {
				return mysql_fetch_assoc($query);
			}
		}
		
		function sqlrows($result) {
			return mysql_num_rows($result);
		}
		
		function sqlid() {
			global $bigtree;
			
			if ($bigtree["mysql_write_connection"] !== "disconnected") {
				return mysql_insert_id($bigtree["mysql_write_connection"]);
			} else {
				return mysql_insert_id($bigtree["mysql_read_connection"]);			
			}
		}
		
		function sqlescape($string) {
			global $bigtree;
			if ($bigtree["mysql_read_connection"] === "disconnected") {
				$bigtree["mysql_read_connection"] = bigtree_setup_sql_connection();
			}
			if (!is_string($string) && !is_numeric($string) && !is_bool($string) && $string) {
				throw new Exception("sqlescape expects a string");
			}
			return mysql_real_escape_string($string);
		}
	}
?>