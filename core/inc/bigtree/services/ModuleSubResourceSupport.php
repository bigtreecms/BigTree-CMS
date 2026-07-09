<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;

	/**
	 * Shared sub-resource resolution for module collaborators. Modules expose
	 * forms/views/reports/actions as nested ("sub") resources stored inside the
	 * module's JSONDB record; the route handlers for each cluster all need the
	 * same helpers to load a module, find a sub-row, or fetch one (404 on miss),
	 * plus the mechanical CRUD skeleton that wraps them — the module-context
	 * prologue, the find-or-404, the bucket list, and the delete-with-action-
	 * cascade. This trait holds them once so ModuleService and the extracted
	 * collaborators (ModuleFormService/ModuleViewService/ModuleReportService)
	 * can `use` it instead of duplicating or coupling back into ModuleService.
	 *
	 * Keep this to scaffolding only — sub-resource resolution and the CRUD
	 * skeleton around it. Per-bucket write shapes (present()/field maps) and any
	 * bucket-specific cascade logic deliberately stay in each service.
	 */
	trait ModuleSubResourceSupport {
		private function loadModule($id) {
			$m = Entity::findOrFailJson("modules", $id, "Module");

			return $m;
		}

		private function getSubResource($module_id, $bucket, $sub_id) {
			$module = BigTreeJSONDB::get("modules", $module_id);
			$found = $this->findSub($module[$bucket] ?? [], $sub_id);

			if (!$found) {
				throw new NotFoundException("Sub-resource $sub_id not found");
			}
			return $found;
		}

		private function findSub(array $rows, $id) {
			foreach ($rows as $row) {
				if (($row["id"] ?? "") == $id) {
					return $row;
				}
			}

			return null;
		}

		/**
		 * The prologue every sub-resource handler shares: resolve the "id" route
		 * param, load the module (404 on miss), and open the JSONDB subset writer
		 * scoped to that module's subtree. Returns [$module_id, $module, $context]
		 * for list-destructuring at the call site; skip the middle slot with
		 * `[$module_id, , $context]` when the loaded row isn't needed.
		 */
		private function moduleContext(Request $request): array {
			$module_id = $request->routeParam("id");
			$module = $this->loadModule($module_id);
			$context = BigTreeJSONDB::getSubset("modules", $module_id);

			return [$module_id, $module, $context];
		}

		/**
		 * Find a sub-row in $module[$bucket] or throw NotFound with a
		 * bucket-specific label ("Form 3 not found"). The find-or-404 that opens
		 * every update/delete/relation handler.
		 */
		private function requireSub(array $module, string $bucket, $sub_id, string $label): array {
			$existing = $this->findSub($module[$bucket] ?? [], $sub_id);

			if (!$existing) {
				throw new NotFoundException("$label $sub_id not found");
			}

			return $existing;
		}

		/** The identical bucket-list body: load module → 200 with the bucket's rows. */
		private function listBucket(Request $request, string $bucket): Response {
			$module = $this->loadModule($request->routeParam("id"));

			return Response::ok(array_values($module[$bucket] ?? []));
		}

		/**
		 * The identical delete-with-action-cascade body shared by forms/views/
		 * reports: delete the sub-row, then delete any module action bound to it
		 * via $action_key ("form"/"view"/"report"). 404s when the sub-row is
		 * absent using $label.
		 */
		private function deleteSubCascade(Request $request, string $bucket, string $action_key, string $label): Response {
			$sub_id = $request->routeParam("sid");
			[, $module, $context] = $this->moduleContext($request);
			$this->requireSub($module, $bucket, $sub_id, $label);

			$context->delete($bucket, $sub_id);

			foreach ($module["actions"] ?? [] as $action) {
				if (($action[$action_key] ?? "") == $sub_id) {
					$context->delete("actions", $action["id"]);
				}
			}

			return Response::noContent();
		}
	}
