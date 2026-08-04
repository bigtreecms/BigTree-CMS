<?php
	use BigTree\Services\AutoModuleService;

	// Every write here audits under `%table%` — the module's real SQL table, filled
	// in by the service (see AutoModuleService::auditEntry). These used to declare
	// the literal string "module_entry": not a real table, so the audit screen's
	// table picker (SHOW TABLES) could never offer it, and shared across every
	// module, so entry 5 in two modules were indistinguishable rows. The assistant
	// audited the same writes under the real table, which split "what happened to
	// this entry" in half — REST's edits under one name, the assistant's under
	// another, and AuditService::list filtering on literal equality (audit #16 A2).
	//
	// `%entry%` on create/update is the id of the row that was actually written
	// ("p12" for a pending draft). Create declared `%id%`, which on this route is
	// the *module* id, so no human entry-create was traceable to its entry (B3).
	return [
		"GET /modules/{id}/entries" => [
			"service" => [AutoModuleService::class, "list"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"query" => ["page" => "int|min:1", "q" => "string|max:200", "sort" => "string|max:64", "view" => "string|max:128"],
		],
		"POST /modules/{id}/entries" => [
			"service" => [AutoModuleService::class, "create"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "created", "entry" => "%entry%"],
		],
		// `form` joins `view` here because edit screens reached via a form action
		// (no view) send the form id as the authoritative table reference.
		// `eid` is a string (not `:int`) so a pending entry — whose view-cache id
		// carries a "p" prefix, e.g. "p5" — can be loaded into the edit form.
		"GET /modules/{id}/entries/{eid}" => [
			"service" => [AutoModuleService::class, "get"],
			"permission" => ["module" => "%id%", "min" => "v"],
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
		],
		// `eid` is a string so a pending entry ("p5") can be re-saved (updates the
		// pending change) or, on publish, promoted to a live row.
		"PATCH /modules/{id}/entries/{eid}" => [
			"service" => [AutoModuleService::class, "update"],
			"permission" => ["module" => "%id%", "min" => "e"],
			"allow_unknown" => true,
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "updated", "entry" => "%entry%"],
		],
		// `eid` is a string (not `:int`) so pending entries — whose view-cache id
		// carries a "p" prefix, e.g. "p5" — can be deleted/rejected too.
		"DELETE /modules/{id}/entries/{eid}" => [
			"service" => [AutoModuleService::class, "delete"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128", "form" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "deleted", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/reorder" => [
			"service" => [AutoModuleService::class, "reorder"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"body" => ["ids" => "required|array", "view" => "string|max:128"],
			// A reorder rewrites many rows at once, so there is no single entry to
			// name. It used to record the module id, which the real table now says
			// on its own.
			"audit" => ["table" => "%table%", "type" => "reordered", "entry" => ""],
		],
		"POST /modules/{id}/entries/{eid:int}/archive" => [
			"service" => [AutoModuleService::class, "toggleArchive"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "archived", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/{eid:int}/approve" => [
			"service" => [AutoModuleService::class, "toggleApprove"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "approved", "entry" => "%eid%"],
		],
		"POST /modules/{id}/entries/{eid:int}/feature" => [
			"service" => [AutoModuleService::class, "toggleFeature"],
			"permission" => ["module" => "%id%", "min" => "p"],
			"query" => ["view" => "string|max:128"],
			"audit" => ["table" => "%table%", "type" => "featured", "entry" => "%eid%"],
		],
	];
