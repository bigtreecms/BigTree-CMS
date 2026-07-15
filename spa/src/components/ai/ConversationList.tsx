import { useQuery } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";
import { aiApi } from "@/api/endpoints/ai";
import { queryKeys } from "@/lib/queryKeys";
import { IconButton } from "@/components/ui/IconButton";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * The signed-in user's past conversations, for resuming or deleting. Shown in the
 * chat panel's history view.
 */

interface ConversationListProps {
	activeId: number | null;
	onDelete: (id: number) => void;
	onSelect: (id: number) => void;
}

export const ConversationList = ({ activeId, onSelect, onDelete }: ConversationListProps) => {
	const listQ = useQuery({
		queryKey: queryKeys.ai.conversations(),
		queryFn: () => aiApi.listConversations(),
	});

	if (listQ.isLoading) {
		return (
			<InlineEmpty align="center" className="px-4 py-8" variant="plain">
				Loading…
			</InlineEmpty>
		);
	}

	const conversations = listQ.data ?? [];

	if (conversations.length === 0) {
		return (
			<InlineEmpty align="center" className="px-4 py-10" pad="xl" variant="plain">
				No conversations yet.
			</InlineEmpty>
		);
	}

	return (
		<div className="flex flex-col gap-px p-1">
			{conversations.map((c) => (
				<div
					className={`group flex items-center gap-1 rounded-md px-1 transition-colors hover:bg-hover ${
						c.id === activeId ? "bg-hover" : ""
					}`}
					key={c.id}
				>
					<button
						className="min-w-0 flex-1 truncate p-2  text-left text-[12.5px] text-text"
						title={c.title}
						type="button"
						onClick={() => onSelect(c.id)}
					>
						{c.title || "Untitled conversation"}
					</button>
					<IconButton
						label="Delete conversation"
						title="Delete"
						tone="danger"
						onClick={() => onDelete(c.id)}
					>
						<Trash2 size={13} />
					</IconButton>
				</div>
			))}
		</div>
	);
};
