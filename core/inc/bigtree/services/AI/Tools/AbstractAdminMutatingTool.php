<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Base for administrator-only mutating tools (settings, tags, user management).
	 * Hidden from an editor's registry so the model never offers them, and their
	 * backends re-check level ≥ 1 at both validation and approval. Two-phase like
	 * every mutating tool.
	 */
	abstract class AbstractAdminMutatingTool extends AbstractMutatingTool {
		public function isAvailable($user): bool {

			return CapabilitySummary::level($user) >= 1;
		}
	}
