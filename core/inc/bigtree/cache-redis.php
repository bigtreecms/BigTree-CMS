<?php
	/*
		Class: BigTree\RedisCache
			Provides a PSR-16 compliant interface for BigTree's caching system through Predis.
	*/
	
	namespace BigTree;

	use BigTree, InvalidArgumentException;
	use Predis\Client;
	use Psr\SimpleCache\CacheInterface;
	
	class RedisCache implements CacheInterface
	{

		protected $Client;
		protected $DefaultTTL;
		protected $Store;

		function __construct($config, string $store, ?int $default_ttl = null)
		{
			$this->Client = new Client($config);
			$this->DefaultTTL = $default_ttl;
			$this->Store = $store;
		}

		/*
			Function: clear
				Clears all of BigTree's cached entries for the store.
		*/


		public function clear(): void
		{
			$keys = $this->Client->keys($this->Store."-*");

			foreach ($keys as $key) {
				$this->Client->del($key);
			}
		}
		
		/*
			Function: delete
				Deletes data from BigTree's cache.

			Parameters:
				key - The key to delete.
		*/
		
		public function delete($key): bool
		{
			return $this->Client->del($this->Store."-".$key);
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
			$entry = $this->Client->get($this->Store."-".$key);
			
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
			return $this->Client->exists($this->Store."-".$key);
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
			$success = $this->Client->set($this->Store."-".$key, $value);

			if (is_null($ttl) && !empty($this->DefaultTTL)) {
				$ttl = $this->DefaultTTL;
			}
			
			if (gettype($ttl) === "object") {
				if (get_class($ttl) !== "DateInterval") {
					throw new InvalidArgumentException("ttl value is invalid");

					return false;
				}

				$date = new DateTime("now");
				$date->add($ttl);

				$this->Client->expire($this->Store."-".$key, $date->getTimestamp() - time());
			} elseif (is_numeric($ttl)) {
				$this->Client->expire($this->Store."-".$key, $ttl);
			} elseif (!is_null($ttl)) {
				throw new InvalidArgumentException("ttl value is invalid");
				
				return false;				
			}

			return !empty($success);
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
