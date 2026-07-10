import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

import { CardHeader } from "@/components/ui/Card";

interface DashCardProps {
	icon: LucideIcon;
	title: string;
	sub?: ReactNode;
	action?: ReactNode;
	children: ReactNode;
}

/* ─── Section card chrome ─────────────────────────────────────────────── */

/**
 * The dashboard's section card. Composes the shared {@link CardHeader} bar, but
 * keeps its own `rounded-lg` `<section>` wrapper rather than {@link Card} — the
 * dashboard grid sits at a tighter radius than the `rounded-xl` page cards.
 */
export const DashCard = ({ icon: Icon, title, sub, action, children }: DashCardProps) => {
	return (
		<section className="overflow-hidden rounded-lg border border-border bg-surface">
			<CardHeader className="flex flex-wrap items-center gap-2.5 gap-y-1.5">
				<span className="grid size-5.5 place-items-center rounded-md bg-accent-soft text-accent">
					<Icon size={14} />
				</span>
				<h2 className="text-[12px] font-semibold uppercase tracking-[0.08em] text-text">
					{title}
				</h2>
				{sub && <span className="text-[12.5px] text-text-3">{sub}</span>}
				<span className="flex-1" />
				{action}
			</CardHeader>
			<div className="p-4">{children}</div>
		</section>
	);
};
