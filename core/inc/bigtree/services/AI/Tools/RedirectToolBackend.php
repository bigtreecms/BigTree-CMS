<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * The seam the create_redirect AI tool calls, implemented by FourOhFourService.
	 *
	 * "Redirect /old-pricing to /pricing" is a routine editor ask that had neither a
	 * tool nor a decline line, so the model rediscovered the wall by failing. The
	 * write is complete by construction — a source and a destination is the whole
	 * record — and goes through the same create301 the REST route uses, so the
	 * source is parsed and the destination converted to an internal-page link
	 * identically. Administrator-only at the tool and re-checked in the backend.
	 */
	interface RedirectToolBackend {
		/**
		 * Validate a proposed 301 redirect without writing: administrator level and a
		 * source path that parses to something redirectable.
		 * Returns denied | error | ok+summary+preview+payload.
		 *
		 * @param array<string,mixed> $args
		 * @param object|array $user
		 * @return array<string,mixed>
		 */
		public function aiValidateRedirectCreate(array $args, $user): array;

		/**
		 * Apply an approved redirect from a stored payload. Re-checks administrator.
		 *
		 * @param array<string,mixed> $payload
		 * @param object|array $user
		 * @return array<string,mixed>
		 * @throws \BigTree\Api\Exceptions\AuthorizationException
		 */
		public function aiCreateRedirect(array $payload, $user): array;
	}
