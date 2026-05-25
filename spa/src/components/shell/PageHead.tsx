import type { ReactNode } from "react";

interface PageHeadProps {
	title: string;
	sub?: ReactNode;
	actions?: ReactNode;
	badge?: ReactNode;
}

/**
 * Title block at the top of a screen: H1 + subtitle on the left, action
 * buttons on the right. Matches the prototype's `.page-head` styling.
 */
export const PageHead = ({ title, sub, actions, badge }: PageHeadProps) => {
	return (
		<div className="flex flex-wrap items-start justify-between gap-3 py-3">
			<div className="min-w-0">
				<h1 className="flex items-center gap-2 text-[22px] font-semibold tracking-[-0.015em] text-text">
					{title}
					{badge}
				</h1>
				{sub && <div className="mt-0.5 text-[13px] text-text-3">{sub}</div>}
			</div>
			{actions && <div className="flex items-center gap-2">{actions}</div>}
		</div>
	);
};
