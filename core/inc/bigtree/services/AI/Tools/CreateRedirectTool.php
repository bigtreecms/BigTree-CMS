<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Two-phase creation of a 301 redirect.
	 *
	 * "Redirect the old pricing URL to the new one" follows naturally from moving or
	 * renaming a page — which the assistant can already do — and had no path at all,
	 * so the model could break a URL and not offer the obvious repair. Administrator
	 * -only, matching the admin UI's placement of 404s under Developer.
	 */
	class CreateRedirectTool extends AbstractAdminMutatingTool {
		/** @var RedirectToolBackend */
		private $backend;

		public function __construct(RedirectToolBackend $backend, ProposalStore $proposals) {
			parent::__construct($proposals);
			$this->backend = $backend;
		}

		public function name(): string {

			return "create_redirect";
		}

		public function definition($user): array {

			return $this->functionDefinition(
				$this->name(),
				"Propose a 301 redirect from an old URL to a new one (administrator only). Requires approval. "
					. "Use this after moving or renaming a page so the old address keeps working. If the old URL "
					. "already redirects somewhere, this replaces that redirect.",
				[
					"type" => "object",
					"properties" => [
						"from" => [
							"type" => "string",
							"description" => "The old path that should redirect, as it appeared on the site "
								. "(e.g. \"/old-pricing\"). A full URL is accepted too.",
						],
						"to" => [
							"type" => "string",
							"description" => "Where it should go — a path on this site (e.g. \"/pricing\") or a "
								. "full external URL.",
						],
						"site_key" => [
							"type" => "string",
							"description" => "Optional site key, for multi-site installs only.",
						],
					],
					"required" => ["from", "to"],
				]
			);
		}

		public function execute(array $args, AIToolContext $context): AIToolResult {
			if (trim((string)($args["from"] ?? "")) === "" || trim((string)($args["to"] ?? "")) === "") {

				return AIToolResult::error("Both `from` (the old path) and `to` (where it should go) are required.");
			}

			$validation = $this->backend->aiValidateRedirectCreate($args, $context->user);

			return $this->stageFromValidation($validation, $context, $this->name());
		}
	}
