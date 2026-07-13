<?php
	/**
	 * Verifies reserved top-level page routes via PageService::reservedTopLevelRoutes()
	 * (which mirrors the legacy admin constructor's list + admin path segment).
	 */

	use BigTree\Services\PageService;

	function test_reserved_routes_property_exists() {
		$reserved = PageService::reservedTopLevelRoutes();
		T::ok(is_array($reserved), "reservedTopLevelRoutes() returns an array");
		T::ok(in_array("ajax", $reserved, true), "reserved list contains 'ajax'");
		T::ok(in_array("css", $reserved, true), "reserved list contains 'css'");
		T::ok(in_array("_preview", $reserved, true), "reserved list contains '_preview'");
	}
