<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The read seam behind list_404s, implemented by FourOhFourService.
	 *
	 * Separate from RedirectToolBackend, which is the create_redirect *write* seam on
	 * the same service: the two are different capabilities behind the same admin
	 * screen, and only one of them stages a proposal. Both gate at level 1, matching
	 * the routes they mirror.
	 *
	 * create_redirect shipped without this, so the assistant could write the answer to
	 * a question it was not allowed to ask: "what's 404ing since the relaunch?" and
	 * "fix our broken links" both start at the log, and the model either had to be
	 * handed the URLs by the user or decline a read it was permitted to perform. The
	 * decline line it fell back on was worded about *editing* 404 monitoring, so it
	 * refused a read on the strength of wording about writes.
	 */
	interface FourOhFourToolBackend {
		/**
		 * The 404 log, filtered exactly as `GET /404s` filters it (type bucket, site
		 * key, search term) and capped by $limit. Returns denied | error | the rows.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiList404s(array $args, int $limit, $user): array;
	}
