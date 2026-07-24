import { api } from "@/api/client";
import type { SearchResultGroups } from "@/api/endpoints/search";

/**
 * Client for the AI assistant chat endpoints (AIChatService, Phase 2).
 * Only available when features.ai_chat is true on the auth user.
 */

/** One tool the model ran during a turn — rendered as a "Searching pages…" row. */
export interface ChatToolChoice {
	description?: string;
	id?: string;
	label: string;
}

export interface ChatToolActivity {
	arguments: Record<string, unknown>;
	name: string;
	/** Choices offered alongside `question`, when status is "needs_input". */
	options?: ChatToolChoice[];
	/** The question to put to the user, when status is "needs_input". */
	question?: string;
	/**
	 * AIToolResult status: ok | denied | needs_input | needs_prior_change |
	 * proposal | error. "needs_prior_change" means nothing was staged — the change
	 * depends on another proposal the user hasn't approved yet.
	 */
	status: string;
}

/**
 * "failed" is an approval that ran (or was refused before running) and did not
 * apply — a stale target, or an approval-time re-validation that came back
 * `mode: error`. It stays approvable so the user can retry once the cause is fixed.
 */
export type ProposalStatus = "pending" | "approved" | "rejected" | "expired" | "failed";

/**
 * A staged mutation (Phase 3) the assistant produced during a turn. Nothing has
 * changed in the CMS — the user reviews the summary/preview and approves or rejects
 * it. Status is mutable and refreshed on conversation reload.
 */
export interface ChatProposal {
	/** Field/diff preview for the card (shape varies by tool). */
	preview: Record<string, unknown>;
	proposal_id: string;
	/** Outcome once approved (e.g. { mode, page_id } for create_page); null until then. */
	result: Record<string, unknown> | null;
	status: ProposalStatus;
	summary: string;
	/** Tool that produced it, e.g. "create_page". */
	tool: string;
}

/** A persisted assistant/user message. */
export interface ChatMessage {
	content: string;
	created_at: string | null;
	id: number;
	proposals: ChatProposal[];
	role: "user" | "assistant";
	tool_activity: ChatToolActivity[];
}

/** Response from POST /ai/chat: the assistant turn plus navigable artifacts. */
export interface ChatTurn {
	/** Same group shapes as federated search, for deep-linking (never seen by the model). */
	artifacts: SearchResultGroups;
	conversation_id: number;
	message: {
		id: number;
		role: "assistant";
		content: string;
		tool_activity: ChatToolActivity[];
		proposals: ChatProposal[];
		created_at: string;
	};
	title: string;
}

/** Response from approving/rejecting a proposal: the proposal in its resolved state. */
export interface ProposalResolution {
	proposal: ChatProposal;
}

export interface ConversationSummary {
	created_at: string | null;
	id: number;
	title: string;
	updated_at: string | null;
}

export interface ConversationDetail {
	conversation: ConversationSummary;
	messages: ChatMessage[];
}

export const aiApi = {
	/**
	 * Send one message. Omit conversation_id to start a new conversation; the
	 * response carries the (possibly newly created) conversation_id to reuse.
	 */
	chat: (message: string, conversationId?: number) =>
		api.post<ChatTurn>("/ai/chat", {
			message,
			conversation_id: conversationId,
		}),

	/** The signed-in user's conversations, most-recent first (first page). */
	listConversations: () => api.get<ConversationSummary[]>("/ai/conversations"),

	getConversation: (id: number) => api.get<ConversationDetail>(`/ai/conversations/${id}`),

	deleteConversation: (id: number) => api.delete<void>(`/ai/conversations/${id}`),

	/** Approve a staged proposal — runs the change server-side (permission re-checked). */
	approveProposal: (id: string) =>
		api.post<ProposalResolution>(`/ai/proposals/${encodeURIComponent(id)}/approve`),

	/** Reject a staged proposal — discards it without running anything. */
	rejectProposal: (id: string) =>
		api.post<ProposalResolution>(`/ai/proposals/${encodeURIComponent(id)}/reject`),
};
