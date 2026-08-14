<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Read the 404 log — the broken URLs the site is being asked for.
	 *
	 * The other half of create_redirect, which shipped without it. Both are level 1,
	 * both mirror an endpoint at that level (`GET /404s`, `POST /404s/{id}/redirect`),
	 * and the workflow only makes sense in that order: you cannot fix broken links you
	 * cannot see. Until this existed the model either had to be handed the URLs by the
	 * user or decline a read it was permitted to perform (audit #24 B1).
	 *
	 * Read-only. Resolving, ignoring, deleting, importing and exporting log entries
	 * stay out of scope and are named in CapabilitySummary::outOfScope().
	 */
	class List404sTool extends AbstractReadTool {
		/** @var FourOhFourToolBackend */
		private $backend;

		public function __construct(FourOhFourToolBackend $backend) {
			$this->backend = $backend;
		}

		public function name(): string {

			return "list_404s";
		}

		public function isAvailable($user): bool {

			// Matches `GET /404s`' declared level, which is also create_redirect's.
			return CapabilitySummary::level($user) >= 1;
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"List entries from the site's 404 log: URLs visitors asked for that don't exist, ordered by "
					. "how many times each was requested. Use this for \"what's 404ing?\", \"what broken links "
					. "do we have?\" or before proposing redirects with create_redirect. Administrator only.",
				[
					"type" => "object",
					"properties" => [
						"type" => [
							"type" => "string",
							"enum" => ["404", "301", "ignored"],
							"description" => "Which bucket to read. \"404\" (the default) is broken URLs nobody "
								. "has dealt with, \"301\" is URLs that already redirect somewhere, \"ignored\" "
								. "is ones somebody dismissed.",
						],
						"q" => [
							"type" => "string",
							"description" => "Only entries whose broken URL or redirect destination contains "
								. "this text.",
						],
						"site_key" => [
							"type" => "string",
							"description" => "On a multi-site install, only entries for this site's key. Omit "
								. "to read every site's.",
						],
						"limit" => [
							"type" => "integer",
							"description" => "Maximum entries to return (default 25, max 100).",
						],
					],
					"required" => [],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			$limit = (int)($args["limit"] ?? 25);
			$result = $this->backend->aiList404s($args, $limit > 0 ? $limit : 25, $context->user);

			if (isset($result["denied"])) {

				return AIToolResult::denied((string)$result["denied"]);
			}

			if (isset($result["error"])) {

				return AIToolResult::error((string)$result["error"]);
			}

			return AIToolResult::ok($result);
		}
	}
