<?php
	/**
	 * Verifies that the legacy reserved top-level route list is still
	 * available — PageService::uniqueRoute reads from it via BigTreeAdmin::$ReservedTLRoutes
	 * to know which top-level routes to avoid (ajax, css, feeds, js, sitemap.xml, _preview, etc.).
	 *
	 * If BigTree ever moves these to JSONDB or a different property, this test
	 * will fail loudly so we know to update the PageService reference.
	 */

	function test_reserved_routes_property_exists() {
		// Skip if BigTreeAdmin isn't loaded in the standalone test harness.
		if (!class_exists("BigTreeAdmin", false)) {
			echo "  (skipped — BigTreeAdmin not loaded in standalone harness)\n";
			return;
		}
		$reserved = BigTreeAdmin::$ReservedTLRoutes ?? null;
		T::ok(is_array($reserved), "BigTreeAdmin::\$ReservedTLRoutes is an array");
		T::ok(in_array("ajax", $reserved, true), "reserved list contains 'ajax'");
		T::ok(in_array("css", $reserved, true), "reserved list contains 'css'");
		T::ok(in_array("_preview", $reserved, true), "reserved list contains '_preview'");
	}
