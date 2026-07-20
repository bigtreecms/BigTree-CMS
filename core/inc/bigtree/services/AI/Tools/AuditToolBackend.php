<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the get_audit_trail read tool calls, implemented by AuditService.
	 *
	 * Administrator-gated: the audit trail spans every table on the site, so an
	 * unfiltered read would leak the existence and edit history of content the caller
	 * has no access to. The backend re-checks level.
	 */
	interface AuditToolBackend {
		/**
		 * Recent audit-trail entries, optionally narrowed by source (via), table, user
		 * or start date. Returns denied | error | ["entries" => [...]].
		 *
		 * @param array<string,mixed> $filters
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiAuditTrail(array $filters, int $limit, $user): array;
	}
