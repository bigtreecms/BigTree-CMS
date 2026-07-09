import { useNavigate } from "react-router-dom";
import { ChevronRight } from "lucide-react";
import { Avatar } from "@/components/ui/Avatar";
import { Button } from "@/components/ui/Button";
import type { Message } from "@/api/endpoints/dashboard";
import { splitDateTime } from "@/lib/time";

interface MessagesTableProps {
	messages: Message[];
}

export const MessagesTable = ({ messages }: MessagesTableProps) => {
	const navigate = useNavigate();

	return (
		<div className="overflow-hidden rounded-md border border-border">
			<div className="grid h-[34px] grid-cols-[1.4fr_2fr_110px_80px_80px] items-center gap-x-3 border-b border-border bg-surface-2 px-3.5 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				<span>From</span>
				<span>Subject</span>
				<span>Date</span>
				<span>Time</span>
				<span />
			</div>
			{messages.map((m) => {
				const { date, time } = splitDateTime(m.date);

				return (
					<div
						key={m.id}
						className="grid min-h-[44px] grid-cols-[1.4fr_2fr_110px_80px_80px] items-center gap-x-3 border-b border-border px-3.5 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2"
					>
						<span className="inline-flex items-center gap-2 font-medium">
							<Avatar name={m.sender_name} seed={String(m.sender)} size={24} />
							<span>{m.sender_name ?? `User #${m.sender}`}</span>
						</span>
						<span className="truncate">{m.subject}</span>
						<span className="text-[12px] text-text-3 tabular-nums">{date}</span>
						<span className="text-[12px] text-text-3 tabular-nums">{time}</span>
						<Button
							variant="link"
							size="sm"
							className="-mx-2.5 -my-1.5"
							onClick={() => navigate(`/messages/${m.id}`)}
						>
							View <ChevronRight size={11} />
						</Button>
					</div>
				);
			})}
		</div>
	);
};
