<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the get_content_alerts read tool calls, implemented by DashboardService.
	 *
	 * "What content is stale?" is a natural chat ask that had no tool and no decline
	 * line, so the model rediscovered the wall by failing. Read-only and level-0,
	 * matching the dashboard panel it mirrors — but unlike the dashboard, every page
	 * is filtered by the caller's own view access rather than assumed visible.
	 */
	interface DashboardToolBackend {
		/**
		 * Pages flagged as stale, in two groups:
		 *
		 *  - `stale`: pages past their own `max_age` (the site-wide staleness rule,
		 *    which create_page/update_page can now set).
		 *  - `watched`: pages past the caller's personal alert threshold for them.
		 *
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiContentAlerts($user, int $limit): array;
	}
