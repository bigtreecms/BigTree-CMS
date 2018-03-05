<?php
	/*
		Class: BigTree\Cache
			Provides an interface for the bigtree_caches table.
	*/
	
	namespace BigTree;
	
	class Cache {
		
		/*
			Function: delete
				Deletes data from BigTree's cache table.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				key - The key for your data (if no key is passed, deletes all data for a given identifier)
		*/
		
		static function delete(string $identifier, ?string $key = null): void {
			if (is_null($key)) {
				SQL::query("DELETE FROM bigtree_caches WHERE `identifier` = ?", $identifier);
			} else {
				SQL::query("DELETE FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?", $identifier, $key);
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
		
		static function get(string $identifier, string $key, ?string $max_age = null, bool $decode = true): array {
			if (!is_null($max_age)) {
				// We need to get MySQL's idea of what time it is so that if PHP's differs we don't screw up caches.
				if (SQL::$MySQLTime === "") {
					SQL::$MySQLTime = SQL::fetchSingle("SELECT NOW()");
				}
				
				$max_age = date("Y-m-d H:i:s", strtotime(SQL::$MySQLTime) - $max_age);
				
				$entry = SQL::fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ? AND timestamp >= ?", $identifier, $key, $max_age);
			} else {
				$entry = SQL::fetchSingle("SELECT value FROM bigtree_caches WHERE `identifier` = ? AND `key` = ?", $identifier, $key);
			}
			
			return $decode ? json_decode($entry, true) : $entry;
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
		
		static function put(string $identifier, string $key, $value, bool $replace = true) {
			$exists = SQL::exists("bigtree_caches", ["identifier" => $identifier, "key" => $key]);
			
			if (!$replace && $exists) {
				return false;
			}
			
			$value = JSON::encode($value);
			
			if ($exists) {
				return SQL::update("bigtree_caches", ["identifier" => $identifier, "key" => $key], ["value" => $value]);
			} else {
				return SQL::insert("bigtree_caches", ["identifier" => $identifier, "key" => $key, "value" => $value]);
			}
		}
		
		/*
			Function: putUnique
				Puts data into BigTree's cache table with a random unqiue key and returns the key.

			Parameters:
				identifier - Uniquid identifier for your data type (i.e. org.bigtreecms.geocoding)
				value - The data to store

			Returns:
				They unique cache key.`
		*/
		
		static function putUnique(string $identifier, $value): string {
			$success = false;
			$key = "";
			
			while (!$success) {
				$key = uniqid("", true);
				$success = static::put($identifier, $key, $value, false);
			}
			
			return $key;
		}
	}
