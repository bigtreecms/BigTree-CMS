<?php
	/**
	 * Session-based logout for the front-end BigTree bar.
	 *
	 * The bar has PHP session cookies only — not the SPA JWT refresh token —
	 * so it cannot call POST /auth/logout. Clears $_SESSION["bigtree_admin"] and
	 * bigtree_admin cookies, then redirects to the public site (or SPA login).
	 *
	 * Expects bootstrap + BigTreeAdmin already loaded by core/admin/router.php.
	 *
	 * @global BigTreeAdmin $admin
	 * @global array $bigtree
	 */

	// Prefer POST (state-changing). GET is accepted for simple <a href> parity
	// with the classic bar (logout CSRF risk is the same class as classic).
	$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

	if ($method !== "POST" && $method !== "GET") {
		http_response_code(405);
		header("Allow: GET, POST");
		header("Content-Type: text/plain; charset=utf-8");
		echo "Method not allowed.";
		die();
	}

	\BigTree\Services\AuthService::clearLegacyPhpSession();

	$return = $_GET["return"] ?? $_POST["return"] ?? "www";
	$target = WWW_ROOT;

	if ($return === "admin") {
		$target = rtrim(ADMIN_ROOT, "/") . "/login";
	}

	header("Location: " . $target, true, 302);
	die();
