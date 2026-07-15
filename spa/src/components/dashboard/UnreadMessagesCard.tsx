import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Mail, ChevronRight } from "lucide-react";
import { DashCard } from "./DashCard";
import { QueryRenderer } from "@/components/ui/QueryRenderer";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Button } from "@/components/ui/Button";
import { MessagesTable } from "./MessagesTable";
import type { Message } from "@/api/endpoints/dashboard";

interface UnreadMessagesCardProps {
	currentUserId: number;
	error: unknown;
	loading: boolean;
	messages: Message[];
}

export const UnreadMessagesCard = ({
	messages,
	currentUserId,
	loading,
	error,
}: UnreadMessagesCardProps) => {
	const navigate = useNavigate();

	// Filter to genuinely-unread inbox items: addressed to me AND not yet in read_by.
	const unread = useMemo(
		() =>
			messages.filter(
				(m) => m.recipients.includes(currentUserId) && !m.read_by.includes(currentUserId)
			),
		[messages, currentUserId]
	);

	return (
		<DashCard
			action={
				<Button size="sm" variant="secondary" onClick={() => navigate("/messages")}>
					View all messages
					<ChevronRight size={11} />
				</Button>
			}
			icon={Mail}
			sub={
				loading
					? "Loading…"
					: unread.length === 0
						? "All caught up"
						: `${unread.length} unread`
			}
			title="Unread messages"
		>
			<QueryRenderer
				empty={<InlineEmpty icon={Mail}>No unread messages</InlineEmpty>}
				error={error}
				isEmpty={unread.length === 0}
			>
				<MessagesTable messages={unread} />
			</QueryRenderer>
		</DashCard>
	);
};
