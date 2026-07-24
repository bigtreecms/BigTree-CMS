<?php
	namespace BigTree\Services\AI;

	/**
	 * The structured envelope every AI tool returns. It carries two things:
	 *
	 *   1. A model-facing payload (toModelPayload) that is JSON-encoded and fed
	 *      back into the chat loop as the tool result. The `status` field lets the
	 *      model distinguish a normal result from a permission denial, a request
	 *      for clarification, or a staged mutation it must not treat as done.
	 *
	 *   2. Navigable "artifacts" — CMS entities (pages, module entries, …) the tool
	 *      touched — that the driver collects for the SPA to render as deep links.
	 *      Artifacts never reach the model; they are UI material only.
	 *
	 * Construct via the named factories; the constructor is intentionally private
	 * so a result is always in exactly one valid shape.
	 */
	class AIToolResult {
		const OK = "ok";
		const DENIED = "denied";
		const NEEDS_INPUT = "needs_input";
		const PROPOSAL = "proposal";
		const ERROR = "error";

		// The second half of a two-step intent, arriving before the first half has
		// been approved. Distinct from ERROR (retrying identically will fail again)
		// and from NEEDS_INPUT (the options a needs_input would offer are exactly the
		// ones the user said they didn't want). See AbstractMutatingTool.
		const NEEDS_PRIOR_CHANGE = "needs_prior_change";

		/** @var string One of the status constants above. */
		public $type;

		/** @var array<string,mixed> Model-facing data for an OK result. */
		public $data = [];

		/** @var array<string,list<array<string,mixed>>> Navigable entities for the SPA (never sent to the model). */
		public $artifacts = [];

		/** @var string Human-readable reason (DENIED) or message (ERROR). */
		public $message = "";

		/** @var list<string> Alternative actions the model can offer after a denial. */
		public $alternatives = [];

		/** @var string Clarifying question (NEEDS_INPUT). */
		public $question = "";

		/** @var list<array<string,mixed>> Choice options for a NEEDS_INPUT prompt. */
		public $options = [];

		/** @var string Human summary of a staged mutation (PROPOSAL). */
		public $summary = "";

		/** @var array<string,mixed> Preview / diff of a staged mutation (PROPOSAL). */
		public $preview = [];

		/** @var string Server-stored proposal id the SPA approves (PROPOSAL), or the prerequisite's id (NEEDS_PRIOR_CHANGE). */
		public $proposal_id = "";

		/** @var string The tool whose pending proposal must be approved first (NEEDS_PRIOR_CHANGE). */
		public $prior_tool = "";

		private function __construct(string $type) {
			$this->type = $type;
		}

		/**
		 * A successful tool run.
		 *
		 * @param array<string,mixed> $data Model-facing result data.
		 * @param array<string,list<array<string,mixed>>> $artifacts Navigable entities for the SPA.
		 */
		public static function ok(array $data = [], array $artifacts = []): self {
			$result = new self(self::OK);
			$result->data = $data;
			$result->artifacts = $artifacts;

			return $result;
		}

		/**
		 * A permission denial the model should explain (not retry). Alternatives let
		 * it offer the path that would work ("I can save it as a pending draft").
		 *
		 * @param list<string> $alternatives
		 */
		public static function denied(string $reason, array $alternatives = []): self {
			$result = new self(self::DENIED);
			$result->message = $reason;
			$result->alternatives = $alternatives;

			return $result;
		}

		/**
		 * The tool needs the user to choose or clarify before it can proceed (e.g.
		 * which subtree a new page belongs under). Options are rendered as choice
		 * chips in the SPA.
		 *
		 * @param list<array<string,mixed>> $options
		 */
		public static function needsInput(string $question, array $options = []): self {
			$result = new self(self::NEEDS_INPUT);
			$result->question = $question;
			$result->options = $options;

			return $result;
		}

		/**
		 * A mutation staged for human approval. The model must treat this as pending,
		 * not complete — execution happens only when the user approves $proposal_id.
		 *
		 * @param array<string,mixed> $preview
		 */
		public static function proposal(string $summary, array $preview = [], string $proposal_id = ""): self {
			$result = new self(self::PROPOSAL);
			$result->summary = $summary;
			$result->preview = $preview;
			$result->proposal_id = $proposal_id;

			return $result;
		}

		/**
		 * This change depends on one that is staged but not yet approved.
		 *
		 * Nothing is written during a chat turn, so the container a second call needs
		 * ("the Promos group", "the News module") does not exist while its own proposal
		 * is still on screen. Saying so — and naming the card to approve first — is the
		 * only answer that moves the conversation forward: an error invites an
		 * identical retry, and the needs_input this replaces offered the model a list
		 * of the groups that already exist, which is precisely what the user didn't ask
		 * for.
		 */
		public static function needsPriorChange(string $message, string $proposal_id = "", string $prior_tool = ""): self {
			$result = new self(self::NEEDS_PRIOR_CHANGE);
			$result->message = $message;
			$result->proposal_id = $proposal_id;
			$result->prior_tool = $prior_tool;

			return $result;
		}

		/**
		 * A recoverable tool error (bad arguments, not-found). Distinct from denied:
		 * the model may fix its arguments and try again.
		 */
		public static function error(string $message): self {
			$result = new self(self::ERROR);
			$result->message = $message;

			return $result;
		}

		public function isOk(): bool {

			return $this->type === self::OK;
		}

		/**
		 * The JSON-encodable payload handed back to the model as the tool result.
		 * Always includes a `status` so the model can branch on outcome.
		 *
		 * @return array<string,mixed>
		 */
		public function toModelPayload(): array {
			switch ($this->type) {
				case self::OK:
					return array_merge(["status" => self::OK], $this->data);

				case self::DENIED:
					return [
						"status" => self::DENIED,
						"reason" => $this->message,
						"alternatives" => $this->alternatives,
					];

				case self::NEEDS_INPUT:
					return [
						"status" => self::NEEDS_INPUT,
						"question" => $this->question,
						"options" => $this->options,
					];

				case self::PROPOSAL:
					return [
						"status" => self::PROPOSAL,
						"summary" => $this->summary,
						"proposal_id" => $this->proposal_id,
						"preview" => $this->preview,
					];

				case self::NEEDS_PRIOR_CHANGE:
					return [
						"status" => self::NEEDS_PRIOR_CHANGE,
						"message" => $this->message,
						"pending_proposal_id" => $this->proposal_id,
						"pending_tool" => $this->prior_tool,
					];

				default:
					return [
						"status" => self::ERROR,
						"error" => $this->message,
					];
			}
		}
	}
