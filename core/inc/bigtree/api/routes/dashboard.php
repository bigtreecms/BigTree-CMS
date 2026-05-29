<?php
	use BigTree\Services\DashboardService;
	use BigTree\Services\IntegrityService;

	return [
		"GET /dashboard/summary" => [
			"service" => [DashboardService::class, "summary"],
			"permission" => ["level" => 0],
		],
		"GET /dashboard/content-alerts" => [
			"service" => [DashboardService::class, "contentAlerts"],
			"permission" => ["level" => 0],
		],

		// Lightweight structural-health numbers used by the dashboard summary and
		// the developer Site Status read-out. (The full broken-link scan lives at
		// /dashboard/integrity/* below.)
		"GET /dashboard/integrity" => [
			"service" => [DashboardService::class, "integrity"],
			"permission" => ["level" => 1],
		],

		// — Site Integrity (broken link/image checker) —
		// Incremental scan of every page + module entry. The SPA builds a work
		// list via /start, then checks one page or entry per request, resuming a
		// prior session if one is cached.
		"GET /dashboard/integrity/state" => [
			"service" => [IntegrityService::class, "state"],
			"permission" => ["level" => 1],
		],
		"POST /dashboard/integrity/start" => [
			"service" => [IntegrityService::class, "start"],
			"permission" => ["level" => 1],
		],
		"POST /dashboard/integrity/check-page" => [
			"service" => [IntegrityService::class, "checkPage"],
			"permission" => ["level" => 1],
		],
		"POST /dashboard/integrity/check-module-item" => [
			"service" => [IntegrityService::class, "checkModuleItem"],
			"permission" => ["level" => 1],
		],
		"POST /dashboard/integrity/reset" => [
			"service" => [IntegrityService::class, "reset"],
			"permission" => ["level" => 1],
		],
		"GET /dashboard/integrity/export" => [
			"service" => [IntegrityService::class, "export"],
			"permission" => ["level" => 1],
		],

		// — Analytics (Google Analytics 4) —
		// Read-only endpoints for the SPA dashboard. Rebuild is rate-limited because
		// each call makes a series of expensive GA4 API requests.
		"GET /dashboard/analytics" => [
			"service" => [DashboardService::class, "analytics"],
			"permission" => ["level" => 1],
		],
		"GET /dashboard/analytics/status" => [
			"service" => [DashboardService::class, "analyticsStatusEndpoint"],
			"permission" => ["level" => 1],
		],
		"POST /dashboard/analytics/cache" => [
			"service" => [DashboardService::class, "rebuildAnalyticsCache"],
			"permission" => ["level" => 1],
			"rate_limit" => ["per_minute" => 2],
			"audit" => ["table" => "analytics", "type" => "cache_rebuilt", "entry" => "0"],
		],
	];
