<?php
	/*
		Class: BigTreeJSONDBSubset
			A subset of data from the JSON DB.
	*/

	class BigTreeJSONDBSubset {

		public $Cache;

		private $ID;
		private $Type;

		function __construct($type, $id, $data) {
			$this->Cache = $data;
			$this->ID = $id;
			$this->Type = $type;
		}

		function check($type) {			
			if (!is_array($this->Cache[$type])) {
				$this->Cache[$type] = [];
			}
		}

		function delete($type, $id, $alternate_id_column = false) {
			$this->check($type);

			foreach ($this->Cache[$type] as $index => $entry) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					unset($this->Cache[$type][$index]);
				} elseif (isset($entry["id"]) && $entry["id"] == $id) {
					unset($this->Cache[$type][$index]);
				}
			}

			$this->save();
		}

		function exists($type, $id, $alternate_id_column = false) {
			$this->check($type);

			foreach ($this->Cache[$type] as $item) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					return true;
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return true;
				}
			}

			return false;
		}

		function get($type, $id, $alternate_id_column = false) {
			$this->check($type);

			foreach ($this->Cache[$type] as $item) {
				if ($alternate_id_column !== false && isset($item[$alternate_id_column]) && $item[$alternate_id_column] == $id) {
					return BigTree::untranslateArray($item);
				} elseif (isset($item["id"]) && $id == $item["id"]) {
					return BigTree::untranslateArray($item);
				}
			}

			return null;
		}

		function getAll($type, $sort_column = false, $sort_direction = "ASC") {
			$this->check($type);
			$items = $this->Cache[$type];

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

		function incrementPosition($type) {
			$this->check($type);

			foreach ($this->Cache[$type] as $index => $item) {
				$this->Cache[$type][$index]["position"]++;
			}

			$this->save();
		}

		function insert($type, $entry) {
			$this->check($type);

			if (empty($entry["id"])) {
				$found = true;

				while ($found) {
					$unique_id = $type."-".uniqid(true);
					$found = false;

					foreach ($this->Cache[$type] as $item) {
						if ($item["id"] == $unique_id) {
							$found = true;
						}
					}
				}

				$entry["id"] = $unique_id;
			}

			$this->Cache[$type][] = BigTree::translateArray($entry);
			$this->save();

			return $entry["id"];
		}

		function save() {
			BigTreeJSONDB::saveSubsetData($this->Type, $this->ID, $this->Cache);
		}

		function search($type, $fields, $query) {
			$this->check($type);
			$results = [];

			foreach ($this->Cache[$type] as $item) {
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

		function update($type, $id, $data) {
			$this->check($type);
			$data = BigTree::translateArray($data);

			foreach ($this->Cache[$type] as $index => $entry) {
				if (isset($entry["id"]) && $entry["id"] == $id) {
					foreach ($data as $key => $value) {
						$this->Cache[$type][$index][$key] = $value;
					}
				}
			}

			$this->save();
		}

	}
