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
	];
