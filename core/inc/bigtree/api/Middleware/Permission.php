<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree\Services\PermissionService;

	/**
	 * Route-metadata permission gate. Runs after Authenticate.
	 *
	 * Supported declarations:
	 *  - 'public'
	 *  - ['level' => N]
	 *  - ['self' => 'param_name']   (user must be this user, or level:1+)
	 *  - ['module' => '%route_param%' | string, 'min' => 'v|e|p']
	 *  - ['page'   => '%route_param%' | int,    'min' => 'v|e|p']
	 *  - ['callback' => [Class::class, 'method']]
	 *  - ['any' => [ ...nested permission decls... ]]
	 */
	class Permission {
		public function handle(Request $request, callable $next) {
			$decl = $request->route["permission"] ?? null;

			if ($decl === null || $decl === "public") {
				return $next($request);
			}

			if (!$request->user) {
				// Should never happen — Authenticate runs first and would have thrown 401.
				throw new AuthorizationException("No authenticated user", "permission_denied", 403);
			}

			$this->enforce($decl, $request);

			return $next($request);
		}

		private function enforce($decl, Request $request) {
			if (isset($decl["any"]) && is_array($decl["any"])) {
				foreach ($decl["any"] as $sub) {
					try { $this->enforce($sub, $request); return; }
					catch (AuthorizationException $e) { /* try next */ }
				}
				throw new AuthorizationException("None of the alternative permissions matched", "permission_denied", 403);
			}

			if (isset($decl["level"])) {
				if ($request->user->level < (int)$decl["level"]) {
					throw new AuthorizationException("Requires level " . (int)$decl["level"], "permission_denied", 403);
				}
				return;
			}

			if (isset($decl["self"])) {
				$param = $decl["self"];
				$target = $request->route_params[$param] ?? ($request->body[$param] ?? null);
				if ($target !== null && (int)$target === (int)$request->user->id) return;
				if ($request->user->level >= 1) return;
				throw new AuthorizationException("Must be the same user or an admin", "permission_denied", 403);
			}

			if (isset($decl["module"])) {
				$module = $this->resolveParam($decl["module"], $request);
				$min = $decl["min"] ?? "v";
				if (!PermissionService::userHasModuleAccess($request->user, $module, $min)) {
					throw new AuthorizationException("Module access insufficient", "permission_denied", 403);
				}
				return;
			}

			if (isset($decl["page"])) {
				$page_id = (int)$this->resolveParam($decl["page"], $request);
				$min = $decl["min"] ?? "v";
				if (!PermissionService::userHasPageAccess($request->user, $page_id, $min)) {
					throw new AuthorizationException("Page access insufficient", "permission_denied", 403);
				}
				return;
			}

			if (isset($decl["callback"]) && is_array($decl["callback"])) {
				[$class, $method] = $decl["callback"];
				if (!class_exists($class) || !method_exists($class, $method)) {
					throw new AuthorizationException("Permission callback missing", "permission_denied", 403);
				}
				if (!call_user_func([$class, $method], $request)) {
					throw new AuthorizationException("Callback denied access", "permission_denied", 403);
				}
				return;
			}

			throw new AuthorizationException("Unknown permission declaration", "permission_denied", 403);
		}

		private function resolveParam($value, Request $request) {
			if (is_string($value) && preg_match('/^%(\w+)%$/', $value, $m)) {
				$name = $m[1];
				return $request->route_params[$name] ?? ($request->body[$name] ?? ($request->query[$name] ?? null));
			}
			return $value;
		}
	}
