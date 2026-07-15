import type { ReactNode } from "react";

import { SubNavItemContent } from "./SubNavItemContent";

export interface SubNavItem<T extends string = string> {
	icon?: ReactNode;
	label: string;
	to?: string;
	value: T;
}

interface SubNavProps<T extends string> {
	className?: string;
	items: SubNavItem<T>[];
	onChange: (value: T) => void;
	value: T;
}

/**
 * Segmented pill control. Extracted from the original Users.tsx implementation
 * so every section can share the same visual + interaction language.
 *
 * Routing-aware sub-navs should pass `to` on each item and use the controlling
 * page's router-driven `value` (e.g. derived from `useParams` or `useMatch`).
 * The `onChange` handler is still the source of truth — wire it to either
 * setState or `navigate(to)`.
 */
export const SubNav = <T extends string>({ items, value, onChange, className }: SubNavProps<T>) => {
	return (
		<div
			className={`inline-flex rounded-md border border-border bg-surface p-0.5 text-[12.5px] font-medium ${className ?? ""}`}
		>
			{items.map((item) => {
				const active = item.value === value;

				return (
					<button
						className={`flex items-center gap-1.5 rounded px-3 py-1.5 transition-colors ${
							active
								? "bg-accent-soft text-accent font-semibold"
								: "text-text-2 hover:bg-hover hover:text-text"
						}`}
						key={item.value}
						type="button"
						onClick={() => onChange(item.value)}
					>
						<SubNavItemContent icon={item.icon} label={item.label} />
					</button>
				);
			})}
		</div>
	);
};
