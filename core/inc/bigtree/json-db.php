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

			static::save($type);
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

			static::save($type);
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
			static::save($type);

			return $entry["id"];
		}

		static function save($type) {
			// Make sure we don't blow away the whole result set if someone saves before doing anything
			self::cache($type);

			// Make sure numeric arrays save as arrays
			static::cleanArray(self::$Cache[$type]);

			file_put_contents(SERVER_ROOT."custom/json-db/$type.json", BigTree::json(self::$Cache[$type]));
			BigTree::setPermissions(SERVER_ROOT."custom/json-db/$type.json");
		}

		static function saveSubsetData($type, $id, $data) {
			foreach (self::$Cache[$type] as $index => $item) {
				if (isset($item["id"]) && $id == $item["id"]) {
					self::$Cache[$type][$index] = $data;
				}
			}

			self::save($type);
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
