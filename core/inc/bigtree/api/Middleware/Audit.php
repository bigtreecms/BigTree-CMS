<?php
	namespace BigTree\Api\Middleware;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Services\AuditService;

	/**
	 * Post-handler audit logger. Runs only on 2xx responses for routes that
	 * declare an 'audit' metadata block.
	 *
	 * Declaration:
	 *   'audit' => [
	 *      'table' => 'bigtree_users' | '%table%',
	 *      'type'  => 'created' | 'updated' | 'deleted' | ...,
	 *      'entry' => '%id%' (optional; defaults to route param 'id' or response data.id)
	 *   ]
	 *
	 * Both 'table' and 'entry' accept a '%token%'. A token resolves against, in
	 * order: the values the service put on $response->audit, the route params, the
	 * request body, and the response's data envelope. 'table' takes tokens because
	 * a module entry's table is only knowable at runtime — the route can name the
	 * module, not the table the row lives in, and auditing every module's entries
	 * under one literal string partitioned the trail (audit #16 A2).
	 */
	class Audit {
		public function after(Request $request, Response $response) {
			$decl = $request->route["audit"] ?? null;

			if (!is_array($decl)) {
				return;
			}

			if ($response->status < 200 || $response->status >= 300) {
				return;
			}

			if (!$request->user) {
				return;
			}

			$table = $this->resolve($decl["table"] ?? "", $request, $response);
			$entry = $this->resolve($decl["entry"] ?? "%id%", $request, $response);

			$audit_id = AuditService::write(
				$table !== null ? (string)$table : "",
				$entry !== null ? (string)$entry : "",
				$decl["type"] ?? "updated",
				$request->user->id,
				[
					"ip" => $request->ip,
					"user_agent" => substr($request->user_agent, 0, 255),
					"request_id" => $request->request_id,
					"method" => $request->method,
					"path" => substr($request->path, 0, 255),
				]
			);
		}

		/**
		 * Resolve one declared audit value. A plain string is used as-is; a
		 * '%token%' is looked up on the response's audit bag first (the service's
		 * own runtime answer, which is the only place a module entry's table is
		 * known), then the route params, the body, and the response envelope.
		 *
		 * @param mixed $declared
		 * @return mixed
		 */
		private function resolve($declared, Request $request, Response $response) {
			if (!is_string($declared) || !preg_match('/^%(\w+)%$/', $declared, $m)) {

				return $declared;
			}

			$name = $m[1];

			if (is_array($response->audit) && array_key_exists($name, $response->audit)) {

				return $response->audit[$name];
			}

			$value = $request->route_params[$name] ?? ($request->body[$name] ?? null);

			if ($value === null && is_array($response->body) && isset($response->body["data"][$name])) {
				$value = $response->body["data"][$name];
			}

			return $value;
		}
	}
