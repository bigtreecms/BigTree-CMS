<?php
	/*
		Class: BigTreeJSONDB
			An abstraction layer for reading and writing to the JSON database files used for building site configurations.
	*/

	class BigTreeJSONDB {

		public static $Cache = [];

		private static function cache($type) {
			if (!isset(self::$Cache[$type])) {
				if (file_exists(SERVER_ROOT."custom/json-db/$type.json")) {
					self::$Cache[$type] = json_decode(file_get_contents(SERVER_ROOT."custom/json-db/$type.json"), true);
				} else {
					self::$Cache[$type] = [];
				}
			}
		}

		private static function cleanArray(&$array) {
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

		static function delete($type, $id, $alternate_id_column = false) {
			static::cache($type);

			foreach (static::$Cache[$type] as $index => $item) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					unset(static::$Cache[$type][$index]);
				} elseif (isset($item["id"]) && $item["id"] == $id) {
					unset(static::$Cache[$type][$index]);
				}
			}

			return static::save($type);
		}

		static function exists($type, $id, $alternate_id_column = false) {
			static::cache($type);

			foreach (static::$Cache[$type] as $item) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					return true;
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return true;
				}
			}

			return false;
		}

		static function get($type, $id, $alternate_id_column = false) {
			static::cache($type);

			foreach (static::$Cache[$type] as $item) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					return BigTree::untranslateArray($item);
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return BigTree::untranslateArray($item);
				}
			}

			return null;
		}

		static function getSubset($type, $id) {
			static::cache($type);

			foreach (static::$Cache[$type] as $item) {
				if (isset($item["id"]) && $id == $item["id"]) {
					return new BigTreeJSONDBSubset($type, $id, $item);
				}
			}

			return null;
		}

		static function getAll($type, $sort_column = false, $sort_direction = "ASC") {
			static::cache($type);

			$items = static::$Cache[$type];

			if (!$sort_column) {
				return BigTree::untranslateArray($items);
			}

			if ($sort_column == "position") {
				usort($items, function($first, $second) {
					$first["position"] = $first["position"] ?? 0;
					$second["position"] = $second["position"] ?? 0;
					
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

				return BigTree::untranslateArray($items);
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

			return BigTree::untranslateArray($items);
		}

		static function incrementPosition($type) {
			static::cache($type);

			foreach (static::$Cache[$type] as $index => $item) {
				static::$Cache[$type][$index]["position"]++;
			}

			return static::save($type);
		}

		static function insert($type, $entry) {
			static::cache($type);

			if (empty($entry["id"])) {
				$found = true;

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

			static::$Cache[$type][] = BigTree::translateArray($entry);

			// A failed write returns false rather than the id it would have had. The
			// appended entry is rolled back out of the cache with it, so the rest of the
			// request reads the store that is actually on disk.
			if (!static::save($type)) {
				array_pop(static::$Cache[$type]);

				return false;
			}

			return $entry["id"];
		}

		/*
			Function: save
				Writes a store back to disk.

				Every template, callout, module, feed, field type and setting definition
				on the install lives in one of these files, and this was the only write
				surface in the stack whose failure was unobservable: the file_put_contents
				return was discarded, so an unwritable directory, a full disk or a partial
				write was indistinguishable from success and everything downstream — a
				REST 200, an AI proposal's "published" outcome, the audit row it writes —
				believed it.

			Returns:
				true if the store was written, false if it could not be encoded or written.
		*/

		static function save($type) {
			// Make sure we don't blow away the whole result set if someone saves before doing anything
			self::cache($type);

			// Make sure numeric arrays save as arrays
			static::cleanArray(self::$Cache[$type]);

			$path = SERVER_ROOT."custom/json-db/$type.json";
			$json = BigTree::json(self::$Cache[$type]);

			// An encode failure used to reach file_put_contents as `false`, which writes
			// an empty file — so one invalid UTF-8 byte anywhere in the store turned
			// "add a field to a template" into "every template on the install is gone".
			if (!is_string($json)) {
				return false;
			}

			// The whole store is rewritten on every save, so a crash or a full disk
			// mid-write left a truncated file that cache() decodes to nothing. A temp
			// file plus rename() is atomic within the filesystem: a reader sees either
			// the store as it was or the store as it now is, never half of it.
			$temporary_path = $path.".".getmypid().".tmp";
			$written = file_put_contents($temporary_path, $json);

			if ($written === false || $written < strlen($json)) {
				@unlink($temporary_path);

				return false;
			}

			if (!rename($temporary_path, $path)) {
				@unlink($temporary_path);

				return false;
			}

			BigTree::setPermissions($path);

			return true;
		}

		static function saveSubsetData($type, $id, $data) {
			foreach (self::$Cache[$type] as $index => $item) {
				if (isset($item["id"]) && $id == $item["id"]) {
					self::$Cache[$type][$index] = $data;
				}
			}

			return self::save($type);
		}

		static function search($type, $fields, $query) {
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

		static function update($type, $id, $data) {
			static::cache($type);
			$data = BigTree::translateArray($data);
			$updated = false;

			foreach (static::$Cache[$type] as $index => $entry) {
				if (isset($entry["id"]) && $entry["id"] == $id) {
					foreach ($data as $key => $value) {
						static::$Cache[$type][$index][$key] = $value;
					}
					
					$updated = true;
				}
			}
			
			if (!$updated) {
				$data["id"] = $id;

				static::$Cache[$type][] = $data;
			}

			return static::save($type);
		}

	}
