<?php
	use BigTree\Services\DashboardService;

	return [
		"GET /dashboard/summary" => [
			"service" => [DashboardService::class, "summary"],
			"permission" => ["level" => 0],
		],
		"GET /dashboard/content-alerts" => [
			"service" => [DashboardService::class, "contentAlerts"],
			"permission" => ["level" => 0],
		],
		"GET /dashboard/integrity" => [
			"service" => [DashboardService::class, "integrity"],
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
