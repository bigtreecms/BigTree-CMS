<?php
/**
 * CI-only router for `php -S`: emulates the .htaccess rewrite so the API
 * responds at /admin/api/v1/... without Apache/nginx.
 *
 * Usage (from repo root):
 *   REPO_ROOT="$(pwd)/" php -S 127.0.0.1:8080 core/inc/bigtree/api/_smoke/ci-router.php
 *
 * Do NOT deploy this file to production — it bypasses .htaccess entirely.
 */

// Repo root is passed as an env var so the depth from this file to the root
// never needs to be hard-coded.
$server_root = rtrim(getenv("REPO_ROOT") ?: realpath(__DIR__ . "/../../../../../") . "/", "/") . "/";

$uri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
$path = ltrim((string)$uri, "/");

// Serve real static files directly (assets, robots.txt, etc.).
$candidate = $server_root . $path;

if ($path !== "" && is_file($candidate)) {
	return false;
}

// Inject the rewritten URL exactly as the .htaccess RewriteRule does.
$_GET["bigtree_htaccess_url"] = $path;

// Change into the repo root's "core/" directory so that relative includes
// inside launch.php resolve correctly: launch.php uses "../core/admin/router.php"
// which is relative to the directory of the including script. Setting CWD to
// $server_root/core/ means "../core/admin/..." resolves to $server_root/core/admin/...
chdir($server_root . "core/");

// launch.php reads $server_root and $bigtree before anything else.
require $server_root . "core/launch.php";
