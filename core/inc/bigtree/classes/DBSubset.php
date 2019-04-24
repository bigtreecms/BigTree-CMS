<?php
	/*
		Class: BigTree\DBSubset
			A subset of data from the JSON DB.
	*/
	
	namespace BigTree;
	
	class DBSubset
	{
		
		public $Cache;
		
		private $ID;
		private $Type;
		
		public function __construct(string $type, string $id, $data)
		{
			$this->Cache = $data;
			$this->ID = $id;
			$this->Type = $type;
		}
		
		public function check(string $type): void
		{
			if (!is_array($this->Cache[$type])) {
				$this->Cache[$type] = [];
			}
		}
		
		public function delete(string $type, string $id, ?string $alternate_id_column = null): bool
		{
			$this->check($type);
			
			$removed = false;
			
			foreach ($this->Cache[$type] as $index => $item) {
				if (!is_null($alternate_id_column) &&
					isset($item[$alternate_id_column]) &&
					$item[$alternate_id_column] == $id
				) {
					unset($this->Cache[$type][$index]);
					$removed = true;
				} elseif (isset($item["id"]) && $item["id"] == $id) {
					unset($this->Cache[$type][$index]);
					$removed = true;
				}
			}
			
			$this->save();
			
			return $removed;
		}
		
		public function exists(string $type, string $id, ?string $alternate_id_column = null): bool
		{
			$this->check($type);
			
			foreach ($this->Cache[$type] as $item) {
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
		
		public function get(string $type, string $id, ?string $alternate_id_column = null)
		{
			$this->check($type);
			
			foreach ($this->Cache[$type] as $item) {
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
		
		public function getAll(string $type, ?string $sort_column = null, string $sort_direction = "ASC"): array
		{
			$this->check($type);
			$items = $this->Cache[$type];
			
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
		
		public function incrementPosition(string $type): bool
		{
			$this->check($type);
			
			foreach ($this->Cache[$type] as $index => $item) {
				$this->Cache[$type][$index]["position"]++;
			}
			
			$this->save();
		}
		
		public function insert(string $type, array $entry): string
		{
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
			
			$this->Cache[$type][] = Link::tokenize($entry);
			$this->save();
			
			return $entry["id"];
		}
		
		public function save(): void
		{
			DB::saveSubsetData($this->Type, $this->ID, $this->Cache);
		}
		
		public function search(string $type, array $fields, string $query): array
		{
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
		
		public function unique(string $type, string $field, string $value, ?string $ignored = null): string
		{
			$this->check($type);
			
			$unique = true;
			$x = 2;
			$original = $value;
			
			do {
				foreach ($this->Cache[$type] as $entry) {
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
		
		public function update(string $type, string $id, array $data): void
		{
			$this->check($type);
			$data = Link::tokenize($data);
			
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
