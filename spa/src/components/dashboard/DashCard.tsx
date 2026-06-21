import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

interface DashCardProps {
	icon: LucideIcon;
	title: string;
	sub?: ReactNode;
	action?: ReactNode;
	children: ReactNode;
}

/* ─── Section card chrome ─────────────────────────────────────────────── */

export const DashCard = ({ icon: Icon, title, sub, action, children }: DashCardProps) => {
	return (
		<section className="overflow-hidden rounded-lg border border-border bg-surface">
			<header className="flex flex-wrap items-center gap-2.5 gap-y-1.5 border-b border-border bg-surface-2 px-4 py-3">
				<span className="grid size-5.5 place-items-center rounded-md bg-accent-soft text-accent">
					<Icon size={14} />
				</span>
				<h2 className="text-[12px] font-semibold uppercase tracking-[0.08em] text-text">
					{title}
				</h2>
				{sub && <span className="text-[12.5px] text-text-3">{sub}</span>}
				<span className="flex-1" />
				{action}
			</header>
			<div className="p-4">{children}</div>
		</section>
	);
};
