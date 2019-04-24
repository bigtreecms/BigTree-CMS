<?php
	
	/*
		Class: BigTree\DB
			An abstraction layer for reading and writing to the JSON database files used for building site configurations.
	*/
	
	namespace BigTree;
	
	class DB
	{
		
		public static $Cache = [];
		
		private static function cache(string $type): void
		{
			if (!isset(self::$Cache[$type])) {
				if (file_exists(SERVER_ROOT."custom/json-db/$type.json")) {
					self::$Cache[$type] = json_decode(file_get_contents(SERVER_ROOT."custom/json-db/$type.json"), true);
				} else {
					self::$Cache[$type] = [];
				}
			}
		}
		
		private static function cleanArray(array &$array): void
		{
			$is_numeric = true;
			
			foreach ($array as $key => &$value) {
				if (!is_int($key)) {
					$is_numeric = false;
				}
				
				// SQL Revisions in extensions are numeric but need to stay keyed properly
				if (is_array($value) && $key != "sql_revisions") {
					static::cleanArray($value);
				}
			}
			
			if ($is_numeric) {
				$array = array_values($array);
			}
		}
		
		public static function delete(string $type, string $id, ?string $alternate_id_column = null): bool
		{
			static::cache($type);
			
			$removed = false;
			
			foreach (static::$Cache[$type] as $index => $item) {
				if (!is_null($alternate_id_column) &&
					isset($item[$alternate_id_column]) &&
					$item[$alternate_id_column] == $id)
				{
					unset(static::$Cache[$type][$index]);
					$removed = true;
				} elseif (isset($item["id"]) && $item["id"] == $id) {
					unset(static::$Cache[$type][$index]);
					$removed = true;
				}
			}
			
			static::save($type);
			
			return $removed;
		}
		
		public static function exists(string $type, string $id, ?string $alternate_id_column = null): bool
		{
			static::cache($type);
			
			foreach (static::$Cache[$type] as $item) {
				if (!is_null($alternate_id_column) &&
					isset($item[$alternate_id_column]) &&
					$item[$alternate_id_column] == $id
				) {
					return true;
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return true;
				}
			}
			
			return false;
		}
		
		public static function get(string $type, string $id, ?string $alternate_id_column = null)
		{
			static::cache($type);
			
			foreach (static::$Cache[$type] as $item) {
				if (!is_null($alternate_id_column) &&
					isset($item[$alternate_id_column]) &&
					$item[$alternate_id_column] == $id
				) {
					return Link::detokenize($item);
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return Link::detokenize($item);
				}
			}
			
			return null;
		}
		
		public static function getSubset(string $type, string $id): ?DBSubset
		{
			static::cache($type);
			
			foreach (static::$Cache[$type] as $item) {
				if (isset($item["id"]) && $id == $item["id"]) {
					return new DBSubset($type, $id, $item);
				}
			}
			
			return null;
		}
		
		public static function getAll(string $type, ?string $sort_column = null, string $sort_direction = "ASC"): array
		{
			static::cache($type);
			
			$items = static::$Cache[$type];
			
			if (is_null($sort_column)) {
				return Link::detokenize($items);
			}
			
			if ($sort_column == "position") {
				usort($items, function($first, $second) {
					if ($first["position"] > $second["position"]) {
						return -1;
					} elseif ($first["position"] < $second["position"]) {
						return 1;
					} else {
						if ($first["id"] > $second["id"]) {
							return -1;
						} else {
							return 1;
						}
					}
				});
				
				return Link::detokenize($items);
			}
			
			$sort_by = [];
			
			foreach ($items as $item) {
				$sort_by[] = $item[$sort_column];
			}
			
			if ($sort_direction == "DESC") {
				array_multisort($sort_by, SORT_DESC, $items);
			} else {
				array_multisort($sort_by, SORT_ASC, $items);
			}
			
			return Link::detokenize($items);
		}
		
		public static function incrementPosition(string $type): void
		{
			static::cache($type);
			
			foreach (static::$Cache[$type] as $index => $item) {
				static::$Cache[$type][$index]["position"]++;
			}
			
			static::save($type);
		}
		
		public static function insert(string $type, array $entry): string
		{
			static::cache($type);
			
			if (empty($entry["id"])) {
				$found = true;
				$unique_id = null;
				
				while ($found) {
					$unique_id = $type."-".uniqid(true);
					$found = false;
					
					foreach (static::$Cache[$type] as $item) {
						if ($item["id"] == $unique_id) {
							$found = true;
						}
					}
				}
				
				$entry["id"] = $unique_id;
			}
			
			static::$Cache[$type][] = Link::tokenize($entry);
			static::save($type);
			
			return $entry["id"];
		}
		
		public static function save(string $type): void
		{
			// Make sure we don't blow away the whole result set if someone saves before doing anything
			self::cache($type);
			
			// Make sure numeric arrays save as arrays
			static::cleanArray(self::$Cache[$type]);
			
			file_put_contents(SERVER_ROOT."custom/json-db/$type.json", JSON::encode(self::$Cache[$type]));
			FileSystem::setPermissions(SERVER_ROOT."custom/json-db/$type.json");
		}
		
		public static function saveSubsetData(string $type, string $id, $data): void
		{
			foreach (self::$Cache[$type] as $index => $item) {
				if (isset($item["id"]) && $id == $item["id"]) {
					self::$Cache[$type][$index] = $data;
				}
			}
			
			self::save($type);
		}
		
		public static function search(string $type, array $fields, string $query): array
		{
			static::cache($type);
			$results = [];
			
			foreach (static::$Cache[$type] as $item) {
				$match = false;
				
				foreach ($fields as $field) {
					if (stripos($item[$field], $query) !== false) {
						$match = true;
					}
				}
				
				if ($match) {
					$results[] = $item;
				}
			}
			
			return $results;
		}
		
		public static function unique(string $type, string $field, string $value, ?string $ignored = null): string
		{
			static::cache($type);
			
			$unique = true;
			$x = 2;
			$original = $value;
			
			do {
				foreach (static::$Cache[$type] as $entry) {
					if ($entry[$field] == $value && (is_null($ignored) || $entry["id"] != $ignored)) {
						$unique = false;
					}
				}
				
				if (!$unique) {
					$value = $original."-".$x;
					$x++;
				}
			} while (!$unique);
			
			return $value;
		}
		
		public static function update(string $type, string $id, array $data): void
		{
			static::cache($type);
			$data = Link::tokenize($data);
			
			foreach (static::$Cache[$type] as $index => $entry) {
				if (isset($entry["id"]) && $entry["id"] == $id) {
					foreach ($data as $key => $value) {
						static::$Cache[$type][$index][$key] = $value;
					}
				}
			}
			
			static::save($type);
		}
		
	}
