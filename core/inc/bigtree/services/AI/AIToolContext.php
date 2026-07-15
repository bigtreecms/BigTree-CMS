<?php
	namespace BigTree\Services\AI;

	/**
	 * Per-turn state handed to every tool during an agent loop. Holds the acting
	 * user (the sole source of truth for permission checks), the per-domain result
	 * cap, and the conversation id so mutating tools can scope their proposals.
	 *
	 * The context deliberately carries no service or backend references — a tool's
	 * collaborators are injected into the tool itself at construction, so the
	 * context stays a plain, reusable value across every driver (search, chat, …).
	 */
	class AIToolContext {
		/** @var object|array The authenticated user (Request->user). */
		public $user;

		/** @var int Per-domain result cap a tool should honor. */
		public $limit;

		/** @var string Conversation id, empty for the stateless search loop. */
		public $conversation_id;

		/**
		 * @param object|array $user
		 */
		public function __construct($user, int $limit = 8, string $conversation_id = "") {
			$this->user = $user;
			$this->limit = max(1, $limit);
			$this->conversation_id = $conversation_id;
		}

		/**
		 * The user's global admin level (0 editor, 1 administrator, 2 developer).
		 */
		public function userLevel(): int {
			if (is_object($this->user)) {
				return (int)($this->user->level ?? 0);
			}

			if (is_array($this->user)) {
				return (int)($this->user["level"] ?? 0);
			}

			return 0;
		}
	}
