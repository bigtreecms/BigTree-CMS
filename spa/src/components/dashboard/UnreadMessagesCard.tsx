import { useMemo } from "react";
import { Mail, ChevronRight } from "lucide-react";
import { DashCard } from "./DashCard";
import { CardError } from "./CardError";
import { CardEmpty } from "./CardEmpty";
import { SmallBtn } from "./SmallBtn";
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
				<SmallBtn>
					View all messages
					<ChevronRight size={11} />
				</SmallBtn>
			}
		>
			{error ? (
				<CardError error={error} />
			) : unread.length === 0 ? (
				<CardEmpty icon={Mail} label="No unread messages" />
			) : (
				<MessagesTable messages={unread} />
			)}
		</DashCard>
	);
};
