<?
	// - MySQL Call Wrapper Functions -
	// Support for splitting reads/writes and handling error throwing automatically.

	$sqlerrors = array();
	$sqlqueries = array();
	
	function bigtree_setup_sql_connection($read_write = "read") {
		global $bigtree;
		
		if ($read_write == "read") {
			$connection = mysql_connect($bigtree["config"]["db"]["host"],$bigtree["config"]["db"]["user"],$bigtree["config"]["db"]["password"]);
			mysql_select_db($bigtree["config"]["db"]["name"],$connection);
			mysql_query("SET NAMES 'utf8'",$connection);
		} else {
			$connection = mysql_connect($bigtree["config"]["db_write"]["host"],$bigtree["config"]["db_write"]["user"],$bigtree["config"]["db_write"]["password"]);
			mysql_select_db($bigtree["config"]["db_write"]["name"],$connection);
			mysql_query("SET NAMES 'utf8'",$connection);
		}
		return $connection;
	}

	// If we're splitting writes off, make a different sqlquery function.  We're doing two functions so that the normal one doesn't need to figure out which connection to use and just uses the default.
	if (isset($bigtree["config"]["db_write"]) && $bigtree["config"]["db_write"]["host"]) {
		function sqlquery($query,$connection = false,$type = "read") {
			global $sqlerrors,$bigtree;
			
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
				array_push($sqlerrors,$sqlerror);
				return false;
			}
			
			return $q;
		}
	} else {
		function sqlquery($query,$connection = false) {
			global $sqlerrors,$bigtree;
			
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
				array_push($sqlerrors,$sqlerror);
				return false;
			}
			
			return $q;
		}
	}

	function sqlfetch($query,$ignore_errors = false) {
		// If the query is boolean, it's probably a "false" from a failed sql query.
		if (is_bool($query) && !$ignore_errors) {
			global $sqlerrors;
			throw new Exception("sqlfetch() called on invalid query resource. The most likely cause is an invalid sqlquery() call. Last error returned was: ".$sqlerrors[count($sqlerrors)-1]);
		} else {
			return mysql_fetch_assoc($query);
		}
	}

	function sqlrows($result) {
		return mysql_num_rows($result);
	}

	function sqlid() {
		return mysql_insert_id();
	}

	function sqlcolumns($table,$db = false) {
		$cols = array();
		if ($db) {
			$q = mysql_db_query($db,"DESCRIBE $table");
		} else {
			$q = sqlquery("DESCRIBE $table");
		}
		while ($f = sqlfetch($q,true)) {
			$tparts = explode(" ",$f["Type"]);
			$type = explode("(",$tparts[0]);
			if (sizeof($type) == 2) {
				$size = substr($type[1],0,-1);
			} else {
				$size = "";
			}
			$type = $type[0];
			unset($tparts[0]);
			$type_extras = implode(" ",$tparts);
			$key = $f["Field"];
			
			if ($type == "enum") {
				$options = explode(",",$size);
				foreach ($options as &$option) {
					$option = trim($option,"'");
				}
				$cols[$key] = array("name" => $key,"type" => $type,"type_extras" => $type_extras, "options" => $options,"key" => $f["Key"],"default" => $f["Default"],"null" => $f["Null"],"extra" => $f["Extra"]);
			} else {
				$cols[$key] = array("name" => $key,"type" => $type,"type_extras" => $type_extras, "size" => $size,"key" => $f["Key"],"default" => $f["Default"],"null" => $f["Null"],"extra" => $f["Extra"]);
			}
		}
		return $cols;
	}
?>