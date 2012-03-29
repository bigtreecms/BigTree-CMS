<?
	// MySQL Call Functions, with brand new wrappers for Transaction logging.
	// ----------------------------------------------------------------------
	// Last revision 5/25/2010 by Tim B.

	$sqlerrors = array();
	$sqlqueries = array();
	$last_query = "";
	$total_queries = 0;
	
	function bigtree_setup_sql_connection($read_write = "read") {
		global $config;
		
		if ($read_write == "read") {
			$connection = mysql_connect($config["db"]["host"],$config["db"]["user"],$config["db"]["password"]);
			mysql_select_db($config["db"]["name"],$connection);
			mysql_query("SET NAMES 'utf8'",$connection);
		} else {
			$connection = mysql_connect($config["db_write"]["host"],$config["db_write"]["user"],$config["db_write"]["password"]);
			mysql_select_db($config["db_write"]["name"],$connection);
			mysql_query("SET NAMES 'utf8'",$connection);
		}
		return $connection;
	}

	// If we're splitting writes off, make a different sqlquery function.  We're doing two functions so that the normal one doesn't need to figure out which connection to use and just uses the default.
	if (isset($config["db_write"]) && $config["db_write"]["host"]) {
		function sqlquery($query,$connection = false,$type = "read") {
			global $sqlerrors;
			
			if (!$connection) {
				$commands = explode(" ",$query);
				$fc = strtolower($commands[0]);
				if ($fc == "create" || $fc == "drop" || $fc == "insert" || $fc == "update" || $fc == "set" || $fc == "grant" || $fc == "flush" || $fc == "delete" || $fc == "alter" || $fc == "load" || $fc == "optimize" || $fc == "repair" || $fc == "replace" || $fc == "lock" || $fc == "restore" || $fc == "rollback" || $fc == "revoke" || $fc == "truncate" || $fc == "unlock") {
					$connection = &$GLOBALS["mysql_write_connection"];
					$type = "write";
				} else {
					$connection = &$GLOBALS["mysql_read_connection"];
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
			global $sqlerrors;
			
			if (!$connection) {
				$connection = &$GLOBALS["mysql_read_connection"];
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

	function sqldbquery($db,$query) {
		global $sqlerrors;
		$q = mysql_db_query($db,$query);
		$e = mysql_error();
		if ($e) {
			$sqlerror = "<b>".$e."</b> in query ".$query;
			array_push($sqlerrors,$sqlerror);
			return false;
		} else {
			return $q;
		}
	}

	function sqlfetchobj($query) {
		return mysql_fetch_object($query);
	}

	function sqlfetch($query,$single_column = false) {
		if (!$single_column) {
			return mysql_fetch_assoc($query);
		} else {
			$f = mysql_fetch_array($query);
			return $f[0];
		}
	}

	function sqlrows($result) {
		return mysql_num_rows($result);
	}

	function sqlid() {
		return mysql_insert_id();
	}

	function sqlforeignkeys($table) {
		$keys = array();
		$q = sqlquery("show table status");
		while ($f = mysql_fetch_array($q)) {
			if ($f["Name"] == $table) {
				$comments = explode(";",$f["Comment"]);
				foreach ($comments as $comment) {
					if (strpos($comment,"REFER") !== false) {
						$com = explode(" ",$comment);
						$tkey = substr($com[1],2,-2);
						$ekey = explode("`",$com[3]);
						$dbtable = explode("/",$ekey[1]);
						$edb = $dbtable[0];
						$etable = $dbtable[1];
						$ekey = $ekey[3];
						$key = array("key" => $tkey,"foreign_db" => $edb,"foreign_table" => $etable,"foreign_key" => $ekey);
						$keys[] = $key;
					}
				}
			}
		}
		return $keys;
	}

	function sqlcolumns($table,$db = false) {
		$cols = array();
		if ($db) {
			$q = sqldbquery($db,"describe $table");
		} else {
			$q = sqlquery("describe $table");
		}
		while ($f = sqlfetch($q)) {
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
			$cols[$key] = array("name" => $key,"type" => $type,"type_extras" => $type_extras, "size" => $size,"key" => $f["Key"],"default" => $f["Default"],"null" => $f["Null"],"extra" => $f["Extra"]);
		}
		return $cols;
	}

	function sqlprimarykey($table) {
		$cols = sqlcolumns($table);
		foreach ($cols as $col) {
			if ($col["key"] == "PRI")
				return $col["name"];
		}
		return false;
	}
?>