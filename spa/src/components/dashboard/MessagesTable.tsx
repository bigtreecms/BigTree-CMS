import { ChevronRight } from "lucide-react";
import { LinkBtn } from "./LinkBtn";
import type { Message } from "@/api/endpoints/dashboard";

interface MessagesTableProps {
	messages: Message[];
}

export const MessagesTable = ({ messages }: MessagesTableProps) => {
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
							<span
								className="grid h-6 w-6 place-items-center rounded-full text-[10.5px] font-semibold text-white"
								style={{ background: avatarColor(m.sender) }}
							>
								{senderInitials(m.sender)}
							</span>
							<span>User #{m.sender}</span>
						</span>
						<span className="overflow-hidden text-ellipsis whitespace-nowrap">
							{m.subject}
						</span>
						<span className="text-[12px] text-text-3 tabular-nums">{date}</span>
						<span className="text-[12px] text-text-3 tabular-nums">{time}</span>
						<LinkBtn>
							View <ChevronRight size={11} />
						</LinkBtn>
					</div>
				);
			})}
		</div>
	);
};

const splitDateTime = (iso: string): { date: string; time: string } => {
	try {
		const d = new Date(iso.replace(" ", "T"));

		if (Number.isNaN(d.getTime())) {
			return { date: iso, time: "" };
		}

		const date = `${d.getMonth() + 1}/${d.getDate()}/${String(d.getFullYear()).slice(-2)}`;
		const time = d.toLocaleTimeString([], {
			hour: "numeric",
			minute: "2-digit",
		});

		return { date, time };
	} catch {
		return { date: iso, time: "" };
	}
};

/** Deterministic avatar color from a user id — same algo style as the prototype. */
const avatarColor = (id: number): string => {
	const hue = (id * 47) % 360;

	return `oklch(58% 0.11 ${hue})`;
};

/** Placeholder initials while we don't have user-detail lookups wired yet. */
const senderInitials = (id: number): string => {
	return `#${id}`.slice(-2);
};
