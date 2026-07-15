<?php
	namespace BigTree\Services\AI\Tools;

	use BigTree\Services\AI\CapabilitySummary;

	/**
	 * Base for the developer-only mutating tools (templates, modules, callouts). These
	 * are hidden from every non-developer's registry — a level-0/1 model never sees
	 * them, so it cannot hallucinate the capability — and their backends re-check
	 * developer level at both validation and approval. Two-phase like every mutating
	 * tool: they stage a proposal and write nothing during the turn.
	 */
	abstract class AbstractDeveloperTool extends AbstractMutatingTool {
		public function isAvailable($user): bool {

			return CapabilitySummary::level($user) >= 2;
		}
	}
