<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Manifest;
	use BigTreeAdmin;
	use BigTreeCMS;
	use BigTree;
	use SQL;

	/**
	 * System utilities exposed to the SPA: version, cache clear, security policy.
	 * No file-level operations (DB backups etc.) in v1 — those need careful streaming
	 * design and are deferred.
	 */
	class SystemService {
		public function version(Request $request) {
			$revision = (int)BigTreeCMS::getSetting("bigtree-internal-revision");
			$version_file = SERVER_ROOT . "core/version.php";
			$version = "";

			if (file_exists($version_file)) {
				include $version_file;
				$version = $bigtree_version ?? "";
			}

			return Response::ok([
				"version" => $version,
				"revision" => $revision,
				"php" => PHP_VERSION,
			]);
		}

		public function clearCache(Request $request) {
			$dir = SERVER_ROOT . "cache/";
			$preserve = [".gitkeep", "bigtree-hooks.json"];

			foreach (scandir($dir) ?: [] as $f) {
				if ($f === "." || $f === "..") {
					continue;
				}

				if (in_array($f, $preserve, true)) {
					continue;
				}
				$path = $dir . $f;

				if (is_dir($path)) {
					BigTree::deleteDirectory($path);
				} else {
					@unlink($path);
				}
			}

			Manifest::clearCache();

			return Response::noContent();
		}

		public function getSecurityPolicy(Request $request) {
			$policy = BigTreeCMS::getSetting("bigtree-internal-security-policy") ?: [];

			return Response::ok($policy);
		}

		public function updateSecurityPolicy(Request $request) {
			$existing = BigTreeCMS::getSetting("bigtree-internal-security-policy") ?: [];
			$merged = array_replace_recursive(is_array($existing) ? $existing : [], $request->body);
			BigTreeAdmin::updateInternalSettingValue("bigtree-internal-security-policy", $merged);

			return Response::ok($merged);
		}

		public function unbanIP(Request $request) {
			$ip = (string)$request->body["ip"];
			SQL::query("DELETE FROM bigtree_login_bans WHERE ip = ?", ip2long($ip));

			return Response::noContent();
		}

		public function unbanUser(Request $request) {
			$user_id = (int)$request->body["user_id"];
			SQL::query("UPDATE bigtree_login_bans SET expires = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE user = ?", $user_id);

			return Response::noContent();
		}
	}
