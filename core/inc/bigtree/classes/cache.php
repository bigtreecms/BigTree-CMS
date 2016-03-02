<?php
	/*
		Class: BigTree\Cache
			Provides an interface for the bigtree_caches table.
	*/

	namespace BigTree;
	
	use BigTreeCMS;

	class Cache {

		/*
			Function: delete
				Deletes data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data (if no key is passed, deletes all data for a given identifier)
		*/

		static function delete($identifier,$key = false) {
			if ($key === false) {
				BigTreeCMS::$DB->query("DELETE FROM bigtree_caches WHERE `identifier` = ?",$identifier);
			} else {
				BigTreeCMS::$DB->query("DELETE FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?",$identifier,$key);
			}
		}

		/*
			Function: get
				Retrieves data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data.
				max_age - The maximum age (in seconds) for the data, defaults to any age.
				decode - Decode JSON (defaults to true, specify false to return JSON)

			Returns:
				Data from the table (json decoded, objects convert to keyed arrays) if it exists.
		*/

		static function get($identifier,$key,$max_age = false,$decode = true) {
			if ($max_age) {
				// We need to get MySQL's idea of what time it is so that if PHP's differs we don't screw up caches.
				if (!BigTreeCMS::$MySQLTime) {
					BigTreeCMS::$MySQLTime = BigTreeCMS::$DB->fetchSingle("SELECT NOW()");
				}
				$max_age = date("Y-m-d H:i:s",strtotime(BigTreeCMS::$MySQLTime) - $max_age);

				$entry = BigTreeCMS::$DB->fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ? AND timestamp >= ?",$identifier,$key,$max_age);
			} else {
				$entry = BigTreeCMS::$DB->fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?",$identifier,$key);
			}

			return $decode ? json_decode($entry,true) : $entry;
		}

		/*
			Function: put
				Puts data into BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data.
				value - The data to store.
				replace - Whether to replace an existing value (defaults to true).

			Returns:
				True if successful, false if the indentifier/key combination already exists and replace was set to false.
		*/

		static function put($identifier,$key,$value,$replace = true) {
			$exists = BigTreeCMS::$DB->exists("bigtree_caches",array("identifier" => $identifier,"key" => $key));
			if (!$replace && $exists) {
				return false;
			}

			$value = BigTree::json($value);
			
			if ($exists) {
				return BigTreeCMS::$DB->update("bigtree_caches",array("identifier" => $identifier,"key" => $key),array("value" => $value));
			} else {
				return BigTreeCMS::$DB->insert("bigtree_caches",array("identifier" => $identifier,"key" => $key,"value" => $value));
			}
		}

		/*
			Function: putUnique
				Puts data into BigTree's cache table with a random unqiue key and returns the key.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				value - The data to store

			Returns:
				They unique cache key.
		*/

		static function putUnique($identifier,$value) {
			$success = false;
			while (!$success) {
				$key = uniqid("",true);
				$success = static::cachePut($identifier,$key,$value,false);
			}
			return $key;
		}
	}
