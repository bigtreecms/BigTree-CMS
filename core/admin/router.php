<?php
	/**
	 * Admin front controller: REST API, front-end bar, packaged SPA.
	 *
	 * Classic PHP admin UI routing was removed — the SPA in core/admin/dist is
	 * the admin interface. Reference branch `master` for historical UI code.
	 *
	 * @global array $bigtree
	 * @global string $server_root
	 */

	// Set a definition to check for being in the admin
	define("BIGTREE_ADMIN_ROUTED", true);

	// Set static root for those without it
	if (!isset($bigtree["config"]["static_root"])) {
		$bigtree["config"]["static_root"] = $bigtree["config"]["www_root"];
	}

	// Make sure no notice gets thrown for $bigtree["path"] being too small.
	$bigtree["path"] = array_pad($bigtree["path"], 4, "");

	// -------------------------------------------------------------------------
	// 1) REST API at /admin/api/v1/... — stateless Kernel (no PHP session).
	// -------------------------------------------------------------------------
	if ($bigtree["path"][1] === "api" && $bigtree["path"][2] === "v1") {
		if (file_exists("../custom/bootstrap.php")) {
			include "../custom/bootstrap.php";
		} else {
			include "../core/bootstrap.php";
		}

		require BigTree::path("inc/bigtree/api/Kernel.php");
		BigTree\Api\Kernel::handle($bigtree["path"]);
		die();
	}

	// -------------------------------------------------------------------------
	// 2) Extension asset prefix: /admin/*/{extension_id}/...
	// -------------------------------------------------------------------------
	if ($bigtree["path"][1] == "*") {
		define("EXTENSION_ROOT", $server_root."extensions/".$bigtree["path"][2]."/");
		$bigtree["extension_context"] = $bigtree["path"][2];
		$bigtree["path"] = array_merge([$bigtree["path"][0]], array_slice($bigtree["path"], 3));
	}

	require_once __DIR__ . "/_spa-serve.php";

	// Extension static files — never fall through to the SPA dist.
	if (defined("EXTENSION_ROOT")) {
		if (bigtree_serve_extension_static($bigtree["path"])) {
			die();
		}

		http_response_code(404);
		header("Content-Type: text/plain; charset=utf-8");
		echo "Not found.";
		die();
	}

	$admin_path = bigtree_admin_path_from_config($bigtree["config"]);
	$admin_subpath = array_slice($bigtree["path"], 1);
	$admin_rel = implode("/", array_filter($admin_subpath, static function ($s) {
		return $s !== "" && $s !== null;
	}));

	$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

	// -------------------------------------------------------------------------
	// 3) Classic bookmark → SPA redirects (GET/HEAD)
	// -------------------------------------------------------------------------
	if ($method === "GET" || $method === "HEAD") {
		$location = bigtree_classic_to_spa_redirect($admin_subpath, $admin_path);

		if ($location !== null) {
			header("Location: " . $location, true, 302);
			die();
		}
	}

	// -------------------------------------------------------------------------
	// 4) Front-end bar surface (session PHP; not classic admin UI)
	// -------------------------------------------------------------------------
	$is_bar_js = ($bigtree["path"][1] === "ajax" && (
		$bigtree["path"][2] === "bar.js" || $bigtree["path"][2] === "bar.js.php"
	));
	$is_bar_logout = ($bigtree["path"][1] === "ajax" && $bigtree["path"][2] === "bar-logout");
	$is_bar_css = ($bigtree["path"][1] === "css" && $bigtree["path"][2] === "bar.css");
	$is_bar_image = (
		$bigtree["path"][1] === "images"
		&& preg_match('/^icon-sprite\.(svg|png)$/', $bigtree["path"][2] ?? "")
	);

	if ($is_bar_css || $is_bar_image) {
		$rel = $bigtree["path"][1] . "/" . implode("/", array_slice($bigtree["path"], 2));
		$rel = rtrim($rel, "/");

		if (bigtree_serve_bar_static($rel)) {
			die();
		}

		http_response_code(404);
		header("Content-Type: text/plain; charset=utf-8");
		echo "Not found.";
		die();
	}

	if ($is_bar_js || $is_bar_logout) {
		if (file_exists("../custom/bootstrap.php")) {
			include "../custom/bootstrap.php";
		} else {
			include "../core/bootstrap.php";
		}

		// BigTreeAdmin loads $_SESSION["bigtree_admin"] for permission checks.
		$admin = new BigTreeAdmin;

		if ($is_bar_logout) {
			include BigTree::path("admin/ajax/bar-logout.php");
			die();
		}

		include BigTree::path("admin/ajax/bar.js.php");
		die();
	}

	// -------------------------------------------------------------------------
	// 5–7) Packaged SPA from core/admin/dist
	// -------------------------------------------------------------------------
	$dist_root = realpath($server_root . "core/admin/dist");

	if ($dist_root === false || !is_dir($dist_root)) {
		http_response_code(503);
		header("Content-Type: text/plain; charset=utf-8");
		echo "BigTree admin UI is not installed (missing core/admin/dist). "
			. "Run: cd spa && npm ci && npm run build:package";
		die();
	}

	$file = bigtree_resolve_dist_file($dist_root, $admin_rel);

	if ($file !== null) {
		$real = realpath($file);

		if ($real !== false && bigtree_dist_path_is_safe($dist_root, $real)) {
			if (str_ends_with(strtolower($real), ".map")) {
				http_response_code(404);
				header("Content-Type: text/plain; charset=utf-8");
				echo "Not found.";
				die();
			}

			bigtree_serve_static_file($real, $admin_path, $admin_rel);
			die();
		}
	}

	if ($method === "GET" || $method === "HEAD") {
		bigtree_serve_spa_index(
			$dist_root . DIRECTORY_SEPARATOR . "index.html",
			$admin_path,
			$bigtree["config"]
		);
		die();
	}

	http_response_code(404);
	header("Content-Type: text/plain; charset=utf-8");
	echo "Not found.";
	die();
