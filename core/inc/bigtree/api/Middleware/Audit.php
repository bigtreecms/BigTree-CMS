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
	 *      'table' => 'bigtree_users',
	 *      'type'  => 'created' | 'updated' | 'deleted' | ...,
	 *      'entry' => '%id%' (optional; defaults to route param 'id' or response data.id)
	 *   ]
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

			$entry = $decl["entry"] ?? "%id%";

			if (is_string($entry) && preg_match('/^%(\w+)%$/', $entry, $m)) {
				$name = $m[1];
				$entry = $request->route_params[$name]
					?? ($request->body[$name] ?? null);

				if ($entry === null && is_array($response->body) && isset($response->body["data"][$name])) {
					$entry = $response->body["data"][$name];
				}
			}

			$audit_id = AuditService::write(
				$decl["table"] ?? "",
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
	}
