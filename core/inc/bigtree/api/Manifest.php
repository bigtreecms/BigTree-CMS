<?php
	namespace BigTree\Api;

	class Manifest {
		const CACHE_FILE = "cache/bigtree-api-manifest.json";

		private static $routes = null;

		public static function load() {
			if (self::$routes !== null) {
				return self::$routes;
			}

			$sources = self::sourceFiles();
			$cache_path = SERVER_ROOT . self::CACHE_FILE;
			$cache_meta_path = $cache_path . ".meta";
			$current_mtime_signature = self::mtimeSignature($sources);

			if (file_exists($cache_path) && file_exists($cache_meta_path) && trim(file_get_contents($cache_meta_path)) === $current_mtime_signature) {
				self::$routes = json_decode(file_get_contents($cache_path), true);

				if (is_array(self::$routes)) {
					// Restore callable arrays — JSON loses class string identity but ours are already strings.

					return self::$routes;
				}
			}

			$merged = [];

			foreach ($sources as $file) {
				$set = include $file;

				if (!is_array($set)) {
					continue;
				}

				foreach ($set as $key => $route) {
					if ($route === false) {
						unset($merged[$key]);
					} else {
						$merged[$key] = $route;
					}
				}
			}

			self::$routes = $merged;

			// Best-effort cache write; failures are non-fatal.
			@file_put_contents($cache_path, json_encode($merged));
			@file_put_contents($cache_meta_path, $current_mtime_signature);

			return self::$routes;
		}

		public static function clearCache() {
			@unlink(SERVER_ROOT . self::CACHE_FILE);
			@unlink(SERVER_ROOT . self::CACHE_FILE . ".meta");
			self::$routes = null;
		}

		private static function sourceFiles() {
			$files = [];

			$core_dir = SERVER_ROOT . "core/inc/bigtree/api/routes/";

			foreach (glob($core_dir . "*.php") ?: [] as $f) $files[] = $f;

			$ext_dir = SERVER_ROOT . "extensions/";

			foreach (glob($ext_dir . "*/api/routes/*.php") ?: [] as $f) $files[] = $f;

			$custom_dir = SERVER_ROOT . "custom/inc/bigtree/api/routes/";

			foreach (glob($custom_dir . "*.php") ?: [] as $f) $files[] = $f;

			return $files;
		}

		private static function mtimeSignature(array $files) {
			$sig = [];

			foreach ($files as $f) $sig[] = $f . ":" . @filemtime($f);
			return md5(implode("|", $sig));
		}
	}
