<?php

/**
 * PHPStan-only bootstrap.
 *
 * The real core/bootstrap.php cannot run standalone under PHPStan (it expects
 * web globals, runs a composer-check that die()s, and opendir()s install-only
 * dirs). But the two legacy god-classes the API/services call into —
 * BigTreeAdmin and BigTreeCMS — are created at runtime via eval() in
 * bootstrap.php (so they can optionally extend a site-custom subclass). They
 * therefore have no static definition for PHPStan to discover.
 *
 * Mirror bootstrap.php's default (non-custom) branch here so PHPStan resolves
 * BigTreeAdmin::* / BigTreeCMS::* calls against the real base-class APIs.
 * Requiring the base-class files only declares classes — neither has top-level
 * executable code or include-time side effects.
 */

require_once __DIR__ . "/core/inc/bigtree/admin.php"; // BigTreeAdminBase
require_once __DIR__ . "/core/inc/bigtree/cms.php";   // BigTreeCMSBase

if (!class_exists("BigTreeAdmin")) {
	class BigTreeAdmin extends BigTreeAdminBase
	{
	}
}

if (!class_exists("BigTreeCMS")) {
	class BigTreeCMS extends BigTreeCMSBase
	{
	}
}

// Path/URL constants that core/bootstrap.php define()s at runtime from config.
// The API/services dereference them pervasively (SERVER_ROOT, SITE_ROOT, …), so
// declaring dummies here keeps both existing and NEW code from tripping
// constant.notFound. Values are irrelevant to static analysis.
foreach ([
	"SERVER_ROOT" => "/",
	"SITE_ROOT" => "/",
	"ADMIN_ROOT" => "/admin/",
	"WWW_ROOT" => "/",
	"STATIC_ROOT" => "/",
	"DOMAIN" => "http://localhost",
] as $constant => $value) {
	if (!defined($constant)) {
		define($constant, $value);
	}
}
