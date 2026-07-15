<?php
	namespace BigTree\Services\AI\Tools;

	/**
	 * Optional contract for an extension-provided mutating tool so the two-phase
	 * approval flow can execute it without a hardcoded switch.
	 *
	 * Core mutating tools are dispatched by name in AIChatService::executeProposal
	 * (each case calls its domain service's ai* method). An extension tool has no
	 * entry in that switch, so it implements this interface instead: at approval
	 * time executeProposal falls back to the tool instance and calls executeApproved.
	 *
	 * executeApproved() runs from the stored, validated payload captured when the
	 * proposal was staged — never anything the model round-tripped — and MUST
	 * re-check permission server-side. The model is never the enforcement layer.
	 */
	interface ApprovableTool {
		/**
		 * Execute a previously staged, user-approved mutation.
		 *
		 * @param array<string,mixed> $payload The validated payload from staging.
		 * @param object|array $user The acting user; permission is re-checked here.
		 * @return array<string,mixed> Outcome recorded on the proposal and shown to the user.
		 */
		public function executeApproved(array $payload, $user): array;
	}
