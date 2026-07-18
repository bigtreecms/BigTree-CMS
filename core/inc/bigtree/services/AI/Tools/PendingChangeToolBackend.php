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
		 * One pending change with the field-level diff it would apply. Visible to the
		 * change's author and to anyone who can publish it. Returns denied | error |
		 * ["pending_change" => [...]].
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiGetPendingChange(int $id, $user): array;

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

		/**
		 * Validate rejecting a pending change. Allowed for a publisher or the
		 * change's author. Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRejectChange(array $args, $user): array;

		/**
		 * Apply an approved rejection, discarding the change and its draft resource
		 * allocations. Re-checks rights.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiRejectChange(array $payload, $user): array;
	}
