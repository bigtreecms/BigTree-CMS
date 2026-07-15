<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the pending-change AI tools call, implemented by PendingChangeService.
	 *
	 * get_pending_changes is offered to everyone but scoped in the backend to the
	 * changes the user submitted or may publish. publish_pending_change is a two-phase
	 * publisher action whose approval reuses the exact same apply logic as the REST
	 * approve route, so the two paths can never diverge.
	 */
	interface PendingChangeToolBackend {
		/**
		 * Pending changes relevant to the acting user (mine / awaiting my approval),
		 * each tagged with mine + can_publish.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiPendingChanges(int $limit, $user): array;

		/**
		 * Validate publishing a pending change without applying it: existence and
		 * publisher rights. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidatePublishChange(array $args, $user): array;

		/**
		 * Apply an approved publish from a stored payload. Re-checks publisher rights.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiPublishChange(array $payload, $user): array;
	}
