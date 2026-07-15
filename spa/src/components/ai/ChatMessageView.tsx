import { Sparkles } from "lucide-react";
import type { SearchResultGroups } from "@/api/endpoints/search";
import type { ChatProposal, ChatToolActivity } from "@/api/endpoints/ai";
import { ToolActivityRow } from "./ToolActivityRow";
import { ChatArtifacts } from "./ChatArtifacts";
import { ProposalCard } from "./ProposalCard";

/** A single rendered turn — a persisted message or an in-flight placeholder. */
export interface ChatEntry {
	id: string | number;
	role: "user" | "assistant";
	content: string;
	tool_activity?: ChatToolActivity[];
	artifacts?: SearchResultGroups;
	proposals?: ChatProposal[];
	/** Assistant placeholder shown while the turn is still computing. */
	pending?: boolean;
}

interface ChatMessageViewProps {
	entry: ChatEntry;
	/** Close the panel after following an artifact link. */
	onNavigate: () => void;
	/** Approve a staged proposal by id. */
	onApproveProposal: (id: string) => void;
	/** Reject a staged proposal by id. */
	onRejectProposal: (id: string) => void;
	/** proposal_id currently being approved/rejected, if any. */
	busyProposalId?: string | null;
}

const ThinkingDots = () => (
	<span className="inline-flex items-center gap-1" aria-label="Thinking">
		<span className="size-1.5 animate-bounce rounded-full bg-text-3 [animation-delay:-0.3s]" />
		<span className="size-1.5 animate-bounce rounded-full bg-text-3 [animation-delay:-0.15s]" />
		<span className="size-1.5 animate-bounce rounded-full bg-text-3" />
	</span>
);

export const ChatMessageView = ({
	entry,
	onNavigate,
	onApproveProposal,
	onRejectProposal,
	busyProposalId,
}: ChatMessageViewProps) => {
	if (entry.role === "user") {
		return (
			<div className="flex justify-end">
				<div className="max-w-[85%] whitespace-pre-wrap wrap-break-word rounded-2xl rounded-br-sm bg-accent px-3 py-2 text-[13px] text-white">
					{entry.content}
				</div>
			</div>
		);
	}

	const activity = entry.tool_activity ?? [];

	return (
		<div className="flex gap-2">
			<span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border border-accent/30 bg-accent/10 text-accent">
				<Sparkles size={12} />
			</span>

			<div className="min-w-0 flex-1">
				{activity.length > 0 && (
					<div className="mb-1.5 flex flex-col gap-0.5">
						{activity.map((a, i) => (
							<ToolActivityRow key={`${a.name}-${i}`} activity={a} />
						))}
					</div>
				)}

				{entry.pending && entry.content === "" ? (
					<ThinkingDots />
				) : (
					<div className="whitespace-pre-wrap wrap-break-word text-[13px] leading-relaxed text-text-2">
						{entry.content}
						{entry.pending && (
							<span className="ml-0.5 inline-block h-3.5 w-1.5 -translate-y-px animate-pulse rounded-[1px] bg-text-3 align-middle" />
						)}
					</div>
				)}

				<ChatArtifacts artifacts={entry.artifacts} onNavigate={onNavigate} />

				{(entry.proposals ?? []).map((proposal) => (
					<ProposalCard
						key={proposal.proposal_id}
						proposal={proposal}
						onApprove={onApproveProposal}
						onReject={onRejectProposal}
						busy={busyProposalId === proposal.proposal_id}
						onNavigate={onNavigate}
					/>
				))}
			</div>
		</div>
	);
};
