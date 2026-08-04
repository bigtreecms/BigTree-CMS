<?php
	/**
	 * Middleware\Audit's token resolution, end to end against the real table.
	 *
	 * A route's `audit` block is a static array, so it can only name literals and
	 * route params. That is why every module-entry write audited under the literal
	 * string "module_entry": the table a row lives in is resolved per request from
	 * the module's view or form, and the declaration had no way to say so. The
	 * table picker on the audit screen is `SHOW TABLES`, so "module_entry" could
	 * never be selected, and the assistant — which did name the real table —
	 * wrote the other half of the same history somewhere else entirely
	 * (audit #16 A2).
	 *
	 * `table` now takes a `%token%` the way `entry` always has, resolved from the
	 * values the service puts on the response. These drive the middleware directly
	 * rather than through a route, because the behaviour under test is the
	 * resolution, not any one endpoint.
	 */

	use BigTree\Api\Middleware\Audit;
	use BigTree\Api\Response;

	/** Run the audit middleware for one declaration + response, returning the row it wrote. */
	function audit_mw_run(array $declaration, Response $response, array $route_params = [], array $body = []): ?array {
		$request = parity_request(1, 2, $route_params, $body);
		$request->route = ["audit" => $declaration];
		$request->ip = "127.0.0.1";
		$request->user_agent = "parity";
		$request->request_id = "audit-mw-" . bin2hex(random_bytes(4));
		$request->method = "POST";
		$request->path = "/audit-mw-test";

		(new Audit())->after($request, $response);

		return SQL::fetch(
			"SELECT a.`table`, a.entry, a.type FROM bigtree_audit_trail a
				LEFT JOIN bigtree_audit_trail_context c ON c.audit_id = a.id
				WHERE c.request_id = ?",
			$request->request_id
		) ?: null;
	}

	function test_audit_middleware_resolves_a_runtime_table_and_entry() {
		if (!parity_db_available()) {
			return;
		}

		try {
			// The shape POST /modules/{id}/entries produces: the route names the
			// module, the service names the table and the row it actually wrote.
			$response = Response::created(["id" => 12], null);
			$response->audit = ["table" => "timber_news", "entry" => "12"];

			$row = audit_mw_run(
				["table" => "%table%", "type" => "created", "entry" => "%entry%"],
				$response,
				["id" => "modules-news"]
			);

			T::ok($row !== null, "a runtime-valued declaration still writes a row");
			T::equals($row["table"], "timber_news", "the table is the module's real table, not “module_entry”");
			T::equals($row["entry"], "12", "the entry is the row written, not the module id");
			T::equals($row["type"], "created", "the declared type is written verbatim");

			// An editor's draft has no live row yet, so it audits under the same "p"
			// prefix every entry seam addresses a pending entry by.
			$pending = Response::created(["pending_id" => 5, "pending" => true], null);
			$pending->audit = ["table" => "timber_news", "entry" => "p5"];

			$row = audit_mw_run(
				["table" => "%table%", "type" => "created", "entry" => "%entry%"],
				$pending,
				["id" => "modules-news"]
			);

			T::equals($row["entry"], "p5", "a pending create audits the draft, not an empty entry");

			// 204s carry no body at all — the audit bag is the only place the table
			// can come from on delete and reorder.
			$deleted = Response::noContent();
			$deleted->audit = ["table" => "timber_news"];

			$row = audit_mw_run(
				["table" => "%table%", "type" => "deleted", "entry" => "%eid%"],
				$deleted,
				["eid" => "12"]
			);

			T::equals($row["table"], "timber_news", "a 204 response still resolves its table");
			T::equals($row["entry"], "12", "and falls back to the route param for the entry");
		} finally {
			SQL::query("DELETE FROM bigtree_audit_trail WHERE `table` = ?", "timber_news");
		}
	}

	function test_audit_middleware_leaves_a_literal_declaration_alone() {
		if (!parity_db_available()) {
			return;
		}

		try {
			$row = audit_mw_run(
				["table" => "bigtree_pages", "type" => "updated", "entry" => "%id%"],
				Response::ok(["id" => 99]),
				["id" => "99"]
			);

			T::ok($row !== null, "a literal declaration writes a row");
			T::equals($row["table"], "bigtree_pages", "a literal table is used verbatim");
			T::equals($row["entry"], "99", "the entry token still resolves from the route params");
		} finally {
			SQL::query("DELETE FROM bigtree_audit_trail WHERE `table` = ? AND entry = ?", "bigtree_pages", "99");
		}
	}

	/**
	 * The other half of the contract: a route that declares a `%token%` for its
	 * table is relying on its service to fill it in, and a service that stops
	 * doing so loses the audit row silently (AuditService::write refuses an empty
	 * table). Every entry-write path is named here rather than inferred, because
	 * the three flag routes delegate to one private method and would otherwise
	 * look uncovered.
	 */
	function test_every_runtime_table_route_has_a_service_that_fills_it() {
		$routes = \BigTree\Api\Manifest::load();
		$tokened = [];

		foreach ($routes as $key => $route) {
			if (($route["audit"]["table"] ?? "") === "%table%") {
				$tokened[] = $key;
			}
		}

		T::equals(count($tokened), 7, "the seven module-entry writes are the routes with a runtime table");

		$missing = [];

		foreach (["create", "update", "respondUpdated", "delete", "reorder", "toggleFlag"] as $method) {
			$body = ai_surface_method_body(\BigTree\Services\AutoModuleService::class, $method);

			T::ok($body !== "", "AutoModuleService::{$method}'s source was read");

			if (strpos($body, "auditEntry(") === false) {
				$missing[] = $method;
			}
		}

		T::equals(implode(", ", $missing), "", "every entry-write path names the table its audit row belongs in");
	}

	/**
	 * An unresolvable token must not be stored as itself. AuditService::write
	 * refuses an empty table, so the row is skipped — a missing row is loud in a
	 * way that a trail full of literal "%table%" entries would not be.
	 */
	function test_audit_middleware_writes_nothing_for_an_unresolved_table() {
		if (!parity_db_available()) {
			return;
		}

		try {
			$row = audit_mw_run(
				["table" => "%table%", "type" => "created", "entry" => "%id%"],
				Response::ok(["id" => 1]),
				["id" => "1"]
			);

			T::equals($row, null, "an unresolved %table% writes no row rather than a literal one");
			T::equals(
				(int)SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_audit_trail WHERE `table` = ?", "%table%"),
				0,
				"and nothing is ever stored under the token itself"
			);
		} finally {
			SQL::query("DELETE FROM bigtree_audit_trail WHERE `table` = ?", "%table%");
		}
	}
