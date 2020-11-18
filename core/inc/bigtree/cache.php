<?php
	/*
		Class: BigTree\Cache
			Provides a PSR-16 compliant interface for the bigtree_caches table.
	*/
	
	namespace BigTree;

	use BigTree, InvalidArgumentException, SQL;
	use Psr\SimpleCache\CacheInterface;
	
	class Cache implements CacheInterface
	{

		protected $DefaultTTL;
		protected $Store;

		function __construct(string $store, ?int $default_ttl = null)
		{
			$this->DefaultTTL = $default_ttl;
			$this->Store = $store;
		}

		/*
			Function: clear
				Clears all of BigTree's cached entries for the store.
		*/


		public function clear(): void
		{
			SQL::delete("bigtree_caches", ["identifier" => $this->Store]);
		}
		
		/*
			Function: delete
				Deletes data from BigTree's cache.

			Parameters:
				key - The key to delete.
		*/
		
		public function delete($key): bool
		{
			SQL::delete("bigtree_caches", ["identifier" => $this->Store, "key" => $key]);

			return true;
		}

		/*
			Function: deleteMultople
				Deletes multiple entries from BigTree's cache.

			Parameters:
				keys - An array of keys to delete.
		*/
		
		public function deleteMultiple($keys): bool
		{
			if (!is_iterable($keys)) {
				throw new InvalidArgumentException("keys must be an iterable variable");
			}

			foreach ($keys as $key) {
				$this->delete($key);
			}

			return true;
		}
		
		/*
			Function: get
				Retrieves data from BigTree's cache.

			Parameters:
				key - The key for your data.
				default - A default value to return if there is a cache miss.

			Returns:
				Data from the cache or default if a cache miss.
		*/
		
		public function get($key, $default = null)
		{
			$entry = SQL::fetchSingle("SELECT `value` FROM bigtree_caches
									   WHERE `identifier` = ?
										 AND `key` = ?
										 AND (`expires` IS NULL OR `expires` > NOW())", $this->Store, $key);
			
			return $entry ? json_decode($entry, true) : $default;
		}

		/*
			Function: getMultiple
				Retrieves multiple pieces of data from BigTree's cache.

			Parameters:
				keys - An iterable of keys for your data.
				default - A default value to return if there is a cache miss.

			Returns:
				An array of data entries from the cache or default if a cache miss.
		*/
		
		public function getMultiple($keys, $default = null): array
		{
			if (!is_iterable($keys)) {
				throw new InvalidArgumentException("keys must be an iterable variable");
			}

			$results = [];

			foreach ($keys as $key) {
				$results[$key] = $this->get($key, $default);
			}

			return $results;
		}

		/*
			Function: has
				Determines if BigTree's cache has a value for provided key.

			Parameters:
				key - The key for your data.

			Returns:
				true if the data exists in the cache.
		*/

		public function has($key): bool
		{
			return SQL::exists("bigtree_caches", ["identifier" => $this->Store, "key" => $key]);
		}
		
		/*
			Function: set
				Puts data into BigTree's cache table.

			Parameters:
				key - The key for your data.
				value - The data to store.
				ttl - The number of seconds to keep the data (if left null the data will not expire)

			Returns:
				True if successful.
		*/
		
		public function set($key, $value, $ttl = null): bool
		{
			$value = BigTree::json($value);
			$exists = $this->has($key);

			if (is_null($ttl) && !empty($this->DefaultTTL)) {
				$ttl = $this->DefaultTTL;
			}

			if (is_null($ttl)) {
				$data = [
					"identifier" => $this->Store, 
					"key" => $key,
					"value" => $value,
					"expires" => null
				];

				if ($exists) {
					SQL::update("bigtree_caches", ["identifier" => $this->Store, "key" => $key], $data);
				} else {
					SQL::insert("bigtree_caches", $data);
				}

				return true;
			}

			if (gettype($ttl) === "object") {
				if (get_class($ttl) !== "DateInterval") {
					throw new InvalidArgumentException("ttl value is invalid");

					return false;
				}

				$date_add = "DATE_ADD(DATE_ADD(DATE_ADD(DATE_ADD(DATE_ADD(DATE_ADD(NOW(),
				             INTERVAL ".$ttl->s." SECOND),
				             INTERVAL ".$ttl->i." MINUTE),
				             INTERVAL ".$ttl->h." HOUR),
				             INTERVAL ".$ttl->d." DAY),
				             INTERVAL ".$ttl->m." MONTH),
				             INTERVAL ".$ttl->y." YEAR)";
			} elseif (is_numeric($ttl)) {
				$ttl = intval($ttl);

				$date_add = "DATE_ADD(NOW(), INTERVAL $ttl SECOND)";
			} else {
				throw new InvalidArgumentException("ttl value is invalid");
				
				return false;				
			}

			if ($exists) {
				SQL::query("UPDATE bigtree_caches SET `value` = ?, expires = $date_add
				            WHERE `identifier` = ? AND `key` = ?", $value, $this->Store, $key);
			} else {
				SQL::query("INSERT INTO `bigtree_caches` (`identifier`, `key`, `value`, `expires`)
				            VALUES (?, ?, ?, $date_add)", $this->Store, $key, $value);
			}

			return true;
		}

		/*
			Function: setMultiple
				Puts multiple data entries into BigTree's cache table.

			Parameters:
				values - A key -> value array of data entries.
				ttl - The number of seconds to keep the data (if left null the data will not expire)

			Returns:
				True if successful.
		*/
		
		public function setMultiple($values, $ttl = null): bool
		{
			if (!is_iterable($values)) {
				throw new InvalidArgumentException("values must be an iterable variable");
			}

			foreach ($values as $key => $value) {
				$this->set($key, $value, $ttl);
			}

			return true;
		}
	}
