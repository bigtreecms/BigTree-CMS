<?php
	namespace BigTree\Services;

	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree;

	/**
	 * Filesystem codegen for module actions: reads/writes/moves/deletes the PHP-
	 * adjacent ".js" drawing source that a locally authored ("module" render)
	 * action stores on disk. Extracted from ModuleService so the riskiest, most
	 * self-contained concern (filesystem IO) lives apart from plain CRUD.
	 *
	 * Behavior is identical to the former private ModuleService helpers; this is a
	 * pure relocation. ModuleService delegates to an instance of this class.
	 */
	class ModuleActionSourceService {
		/**
		 * Filesystem path of a local module action's source ("" if any segment is
		 * unsafe). Mirrors where legacy custom actions live:
		 *   - extension module → extensions/{extension}/modules/{local}/{action}.js
		 *   - core/custom module → custom/admin/modules/{route}/{action}.js
		 */
		public function actionSourcePath(array $module, $action_route) {
			$action = (string)$action_route;

			if (!$this->safeSegment($action)) {
				return "";
			}

			[$extension, $local] = $this->moduleRouteParts($module);

			if (!$this->safeSegment($local)) {
				return "";
			}

			if ($extension !== "") {
				if (!$this->safeSegment($extension)) {
					return "";
				}

				return SERVER_ROOT . "extensions/$extension/modules/$local/$action.js";
			}

			return SERVER_ROOT . "custom/admin/modules/$local/$action.js";
		}

		/** Write a local module action's source to disk, creating the directory. */
		public function writeActionSource(array $module, $action_route, $source) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path === "") {
				throw new BadRequestException("Invalid module or action route.", "invalid_route");
			}

			if (!BigTree::putFile($path, $source)) {
				throw new BadRequestException("Could not write the action file — check that its modules directory is writable.", "module_write_failed");
			}
		}

		/** Read a local module action's source from disk ("" if none). */
		public function readActionSource(array $module, $action_route) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path === "") {
				return "";
			}

			return is_file($path) ? (string)file_get_contents($path) : "";
		}

		/** Remove a local module action's source file. */
		public function deleteActionSource(array $module, $action_route) {
			$path = $this->actionSourcePath($module, $action_route);

			if ($path !== "" && is_file($path)) {
				@unlink($path);
			}
		}

		/** Move a module action's source file when its route changes. */
		public function moveActionSource(array $module, $old_route, $new_route) {
			$old_path = $this->actionSourcePath($module, $old_route);
			$new_path = $this->actionSourcePath($module, $new_route);

			if ($old_path !== "" && $new_path !== "" && is_file($old_path) && !is_file($new_path)) {
				BigTree::makeDirectory(dirname($new_path));
				@rename($old_path, $new_path);
			}
		}

		/**
		 * A single path segment is safe when it's non-empty, contains only
		 * letters / digits / dot / dash / underscore, and has no ".." traversal.
		 * (Routes are already alnum+dash, but this guards the filesystem regardless.)
		 */
		private function safeSegment($segment) {

			return \BigTree\Api\Sanitize::pathSegment((string)$segment);
		}

		/**
		 * Split a module record into [extension, local-route]. An extension module's
		 * route is namespaced "{extension}*{local-route}"; a core/custom module has
		 * no extension and the route is used as-is. Falls back to splitting the route
		 * on `*` when the `extension` field is absent.
		 */
		private function moduleRouteParts(array $module) {
			$route = (string)($module["route"] ?? "");
			$extension = (string)($module["extension"] ?? "");

			if ($extension !== "" && strpos($route, $extension . "*") === 0) {
				$route = substr($route, strlen($extension) + 1);
			} elseif ($extension === "" && strpos($route, "*") !== false) {
				[$extension, $route] = explode("*", $route, 2);
			}

			return [$extension, $route];
		}
	}
