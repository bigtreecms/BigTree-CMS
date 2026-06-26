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
	messages: Message[];
	currentUserId: number;
	loading: boolean;
	error: unknown;
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
			icon={Mail}
			title="Unread messages"
			sub={
				loading
					? "Loading…"
					: unread.length === 0
						? "All caught up"
						: `${unread.length} unread`
			}
			action={
				<Button variant="secondary" size="sm" onClick={() => navigate("/messages")}>
					View all messages
					<ChevronRight size={11} />
				</Button>
			}
		>
			<QueryRenderer
				error={error}
				isEmpty={unread.length === 0}
				empty={<InlineEmpty icon={Mail}>No unread messages</InlineEmpty>}
			>
				<MessagesTable messages={unread} />
			</QueryRenderer>
		</DashCard>
	);
};
