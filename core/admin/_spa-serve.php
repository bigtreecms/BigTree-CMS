<?php
	/**
	 * Helpers for serving the packaged admin SPA from core/admin/dist.
	 * Included by core/admin/router.php after the API / bar branches.
	 */

	/** Vite production base placeholder rewritten at serve time to the install admin path. */
	const BIGTREE_ADMIN_BASE_TOKEN = "/__BIGTREE_ADMIN_BASE__";

	/**
	 * URL path of admin_root without trailing slash (e.g. "/admin" or "/remaster/admin").
	 */
	function bigtree_admin_path_from_config(array $config): string {
		$admin_root = $config["admin_root"] ?? "/admin/";
		$path = parse_url($admin_root, PHP_URL_PATH);

		if (!is_string($path) || $path === "" || $path === "/") {
			return "/admin";
		}

		return rtrim($path, "/");
	}

	/**
	 * Minimal classic → SPA redirect map (302 Location path, or null).
	 * Only shapes that differ from SPA routes (or logout) are redirected.
	 *
	 * @param array<int,string> $subpath path segments after the admin segment
	 */
	function bigtree_classic_to_spa_redirect(array $subpath, string $admin_path): ?string {
		$subpath = array_values(array_filter($subpath, static function ($s) {
			return $s !== "" && $s !== null;
		}));

		if (($subpath[0] ?? "") === "logout" && count($subpath) === 1) {
			return $admin_path . "/ajax/bar-logout";
		}

		if (($subpath[0] ?? "") === "login" && ($subpath[1] ?? "") === "logout") {
			return $admin_path . "/ajax/bar-logout";
		}

		// Classic: pages/edit/{id} → SPA: pages/{id}/edit
		if (
			($subpath[0] ?? "") === "pages"
			&& ($subpath[1] ?? "") === "edit"
			&& isset($subpath[2])
			&& $subpath[2] !== ""
		) {
			return $admin_path . "/pages/" . rawurlencode($subpath[2]) . "/edit";
		}

		// Classic: pages/add/{parent} → SPA: pages/add/{parent}
		if (
			($subpath[0] ?? "") === "pages"
			&& ($subpath[1] ?? "") === "add"
			&& isset($subpath[2])
			&& $subpath[2] !== ""
		) {
			return $admin_path . "/pages/add/" . rawurlencode($subpath[2]);
		}

		return null;
	}

	/**
	 * Resolve a file under dist for the relative admin path, or null.
	 * Rejects traversal; directories return null (caller falls through to SPA index).
	 */
	function bigtree_resolve_dist_file(string $dist_root, string $admin_rel): ?string {
		$admin_rel = str_replace("\0", "", $admin_rel);
		$admin_rel = trim($admin_rel, "/");

		if ($admin_rel === "" || str_ends_with($admin_rel, "/")) {
			return null;
		}

		$segments = explode("/", $admin_rel);

		foreach ($segments as $segment) {
			if ($segment === "" || $segment === "." || $segment === "..") {
				return null;
			}
		}

		$candidate = $dist_root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);

		if (!is_file($candidate)) {
			return null;
		}

		return $candidate;
	}

	/**
	 * Ensure $real is inside $dist_root (separator-hardened prefix check).
	 */
	function bigtree_dist_path_is_safe(string $dist_root, string $real): bool {
		$dist_root = rtrim(str_replace("\\", "/", $dist_root), "/");
		$real = str_replace("\\", "/", $real);
		$prefix = $dist_root . "/";

		return $real === $dist_root || str_starts_with($real, $prefix);
	}

	function bigtree_rewrite_admin_base(string $contents, string $admin_path): string {
		if (!str_contains($contents, BIGTREE_ADMIN_BASE_TOKEN)) {
			return $contents;
		}

		return str_replace(BIGTREE_ADMIN_BASE_TOKEN, $admin_path, $contents);
	}

	function bigtree_spa_mime_for(string $file): ?string {
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		$map = [
			"html" => "text/html; charset=utf-8",
			"js" => "text/javascript; charset=utf-8",
			"mjs" => "text/javascript; charset=utf-8",
			"css" => "text/css; charset=utf-8",
			"svg" => "image/svg+xml",
			"png" => "image/png",
			"jpg" => "image/jpeg",
			"jpeg" => "image/jpeg",
			"gif" => "image/gif",
			"webp" => "image/webp",
			"ico" => "image/x-icon",
			"woff2" => "font/woff2",
			"woff" => "font/woff",
			"ttf" => "font/ttf",
			"json" => "application/json",
			"map" => null, // refused by caller
			"txt" => "text/plain; charset=utf-8",
		];

		if (!array_key_exists($ext, $map)) {
			return "application/octet-stream";
		}

		return $map[$ext];
	}

	function bigtree_spa_is_text_mime(string $mime): bool {
		return str_starts_with($mime, "text/")
			|| str_contains($mime, "javascript")
			|| str_contains($mime, "json")
			|| str_contains($mime, "svg");
	}

	function bigtree_spa_cache_headers(string $file, string $admin_rel): void {
		$base = basename($file);

		// Hashed Vite assets: long cache. index.html: no-store.
		if ($base === "index.html") {
			header("Cache-Control: no-store");

			return;
		}

		if (str_starts_with(str_replace("\\", "/", $admin_rel), "assets/")
			|| preg_match('/-[A-Za-z0-9_-]{6,}\.(js|css)$/', $base)
		) {
			header("Cache-Control: public, max-age=31536000, immutable");

			return;
		}

		header("Cache-Control: public, max-age=86400");
	}

	/**
	 * Serve a file from dist (placeholder rewrite for text types). HEAD = headers only.
	 */
	function bigtree_serve_static_file(string $file, string $admin_path, string $admin_rel = ""): void {
		if (str_ends_with(strtolower($file), ".map")) {
			http_response_code(404);
			header("Content-Type: text/plain; charset=utf-8");
			echo "Not found.";

			return;
		}

		$mime = bigtree_spa_mime_for($file);

		if ($mime === null) {
			http_response_code(404);
			header("Content-Type: text/plain; charset=utf-8");
			echo "Not found.";

			return;
		}

		header("Content-Type: " . $mime);
		bigtree_spa_cache_headers($file, $admin_rel);

		$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

		if ($method === "HEAD") {
			return;
		}

		$contents = file_get_contents($file);

		if ($contents === false) {
			http_response_code(500);
			echo "Unable to read asset.";

			return;
		}

		if (bigtree_spa_is_text_mime($mime)) {
			$contents = bigtree_rewrite_admin_base($contents, $admin_path);
		}

		echo $contents;
	}

	/**
	 * Serve SPA index.html with placeholder rewrite + boot config injection.
	 *
	 * @param array $config $bigtree["config"]
	 */
	function bigtree_serve_spa_index(string $index_file, string $admin_path, array $config): void {
		if (!is_file($index_file)) {
			http_response_code(503);
			header("Content-Type: text/plain; charset=utf-8");
			echo "BigTree admin UI is not installed (missing core/admin/dist/index.html).";

			return;
		}

		$html = file_get_contents($index_file);

		if ($html === false) {
			http_response_code(500);
			header("Content-Type: text/plain; charset=utf-8");
			echo "Unable to read admin UI.";

			return;
		}

		$html = bigtree_rewrite_admin_base($html, $admin_path);

		$boot = [
			"basename" => $admin_path,
			"apiBase" => $admin_path . "/api/v1",
			"wwwRoot" => $config["www_root"] ?? "",
			"assetBase" => $admin_path . "/",
		];

		$boot_json = json_encode(
			$boot,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES
		);

		if ($boot_json === false) {
			$boot_json = "{}";
		}

		$boot_script = "<script>window.__BIGTREE_ADMIN__=" . $boot_json . ";</script>";

		if (stripos($html, "</head>") !== false) {
			$html = preg_replace("/<\/head>/i", $boot_script . "</head>", $html, 1);
		} else {
			$html = $boot_script . $html;
		}

		header("Content-Type: text/html; charset=utf-8");
		header("Cache-Control: no-store");

		$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

		if ($method === "HEAD") {
			return;
		}

		echo $html;
	}

	/**
	 * Serve a bar-related static file (css/images) from core/custom admin trees.
	 */
	function bigtree_serve_bar_static(string $relative): bool {
		$relative = ltrim(str_replace("\0", "", $relative), "/");

		if ($relative === "" || str_contains($relative, "..")) {
			return false;
		}

		// Only bar CSS and bar sprite assets (not the classic admin UI tree).
		$allowed = [
			"css/bar.css" => true,
			"images/icon-sprite.svg" => true,
		];

		if (empty($allowed[$relative]) && !preg_match('#^images/icon-sprite\.(svg|png)$#', $relative)) {
			return false;
		}

		$custom = "../custom/admin/" . $relative;
		$core = "../core/admin/" . $relative;
		$file = file_exists($custom) ? $custom : $core;

		if (!is_file($file)) {
			return false;
		}

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if ($ext === "css") {
			header("Content-Type: text/css; charset=utf-8");
		} elseif ($ext === "svg") {
			header("Content-Type: image/svg+xml");
		} elseif ($ext === "png") {
			header("Content-Type: image/png");
		} else {
			header("Content-Type: application/octet-stream");
		}

		header("Cache-Control: public, max-age=86400");

		if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "HEAD") {
			return true;
		}

		readfile($file);

		return true;
	}

	/**
	 * Extension static assets (images/css/js) under EXTENSION_ROOT.
	 */
	function bigtree_serve_extension_static(array $path): bool {
		if (!defined("EXTENSION_ROOT") || empty($path[1])) {
			return false;
		}

		$kind = $path[1];

		if (!in_array($kind, ["images", "css", "js"], true)) {
			return false;
		}

		$rest = implode("/", array_slice($path, 2));

		if ($rest === "" || str_contains($rest, "..") || str_contains($rest, "\0")) {
			return false;
		}

		$file = EXTENSION_ROOT . $kind . "/" . $rest;

		if (!is_file($file)) {
			return false;
		}

		$mime = bigtree_spa_mime_for($file) ?? "application/octet-stream";
		header("Content-Type: " . $mime);
		header("Cache-Control: public, max-age=86400");

		if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "HEAD") {
			return true;
		}

		readfile($file);

		return true;
	}
