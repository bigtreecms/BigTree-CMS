<?php
	namespace BigTree\Services;

	use BigTree\Api\Exceptions\NotFoundException;
	use BigTreeJSONDB;

	/**
	 * Shared sub-resource resolution for module collaborators. Modules expose
	 * forms/views/reports/actions as nested ("sub") resources stored inside the
	 * module's JSONDB record; the route handlers for each cluster all need the
	 * same three helpers to load a module, find a sub-row, or fetch one (404 on
	 * miss). This trait holds them once so ModuleService and the extracted
	 * collaborators (e.g. ModuleReportService) can `use` it instead of
	 * duplicating or coupling back into ModuleService.
	 *
	 * Keep this minimal — sub-resource resolution only. List/meta assembly and
	 * presentation (present()) deliberately stay out.
	 */
	trait ModuleSubResourceSupport {
		private function loadModule($id) {
			$m = BigTreeJSONDB::get("modules", $id);

			if (!$m) {
				throw new NotFoundException("Module $id not found", "resource_not_found", 404);
			}
			return $m;
		}

		private function getSubResource($module_id, $bucket, $sub_id) {
			$module = BigTreeJSONDB::get("modules", $module_id);
			$found = $this->findSub($module[$bucket] ?? [], $sub_id);

			if (!$found) {
				throw new NotFoundException("Sub-resource $sub_id not found", "resource_not_found", 404);
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
	}
