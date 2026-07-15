import { useLayoutEffect, useRef, useState } from "react";
import { NavLink } from "react-router-dom";
import { ChevronDown, ExternalLink, type LucideIcon } from "lucide-react";

import { SubNavItemContent } from "../ui/SubNavItemContent";

/**
 * One entry in a section sub-navigation bar. Section-agnostic: modules build
 * these from their actions (see `lib/moduleActions`), but any section can hand
 * a static list to <SubNav />.
 */
export interface SubNavItem {
	/** Exact-match active state. Defaults to NavLink prefix matching so a view
	 *  stays active on its `/add` and `/edit` sub-routes (legacy substring
	 *  behavior). */
	end?: boolean;
	/**
	 * `to` is a full URL outside the SPA — rendered as a plain anchor with an
	 * external glyph (rarely used after the classic admin cutover).
	 */
	external?: boolean;
	icon?: LucideIcon;
	label: string;
	to: string;
}

interface SubNavProps {
	items: SubNavItem[];
}

/** Reserve enough room for the "More" trigger when items overflow. */
const MORE_RESERVE = 110;

const itemClass = (isActive: boolean): string =>
	[
		"flex items-center gap-1.5 whitespace-nowrap px-3 py-2.5 text-[13px] font-medium transition-colors",
		"border-b-2 -mb-px",
		isActive ? "border-accent text-text" : "border-transparent text-text-3 hover:text-text",
	].join(" ");

const ItemLink = ({ item }: { item: SubNavItem }) => {
	const Icon = item.icon;

	if (item.external) {
		return (
			<a className={itemClass(false)} href={item.to} title="Opens in a new context">
				<SubNavItemContent
					icon={Icon && <Icon size={14} />}
					label={item.label}
					trailing={<ExternalLink className="text-text-3" size={11} />}
				/>
			</a>
		);
	}

	return (
		<NavLink className={({ isActive }) => itemClass(isActive)} end={item.end} to={item.to}>
			<SubNavItemContent icon={Icon && <Icon size={14} />} label={item.label} />
		</NavLink>
	);
};

/**
 * Hover/focus-within dropdown holding items that didn't fit the bar. Mirrors
 * the pattern used by the Dashboard tab's sub-menu.
 */
const MoreMenu = ({ items }: { items: SubNavItem[] }) => {
	return (
		<div className="group relative ml-auto">
			<button
				className="flex items-center gap-1 px-3 py-2.5 text-[13px] font-medium text-text-3 transition-colors hover:text-text"
				type="button"
			>
				<span>More</span>
				<ChevronDown size={12} />
			</button>

			<div
				className="invisible absolute right-0 top-full z-50 min-w-52 -translate-y-1 rounded-md border border-border bg-surface p-1 opacity-0 shadow-md transition-[opacity,transform] group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100"
				role="menu"
			>
				{items.map((item) => {
					const Icon = item.icon;

					if (item.external) {
						return (
							<a
								className="flex items-center gap-2 rounded px-2.5 py-1.5 text-[13px] text-text-2 transition-colors hover:bg-hover hover:text-text"
								href={item.to}
								key={item.to}
								role="menuitem"
								title="Opens in a new context"
							>
								<SubNavItemContent
									icon={Icon && <Icon className="shrink-0" size={14} />}
									label={item.label}
									trailing={
										<ExternalLink className="shrink-0 text-text-3" size={11} />
									}
								/>
							</a>
						);
					}

					return (
						<NavLink
							className={({ isActive }) =>
								[
									"flex items-center gap-2 rounded px-2.5 py-1.5 text-[13px] transition-colors",
									isActive
										? "bg-accent-soft text-accent"
										: "text-text-2 hover:bg-hover hover:text-text",
								].join(" ")
							}
							end={item.end}
							key={item.to}
							role="menuitem"
							to={item.to}
						>
							<SubNavItemContent
								icon={Icon && <Icon className="shrink-0" size={14} />}
								label={item.label}
							/>
						</NavLink>
					);
				})}
			</div>
		</div>
	);
};

/**
 * Section sub-navigation — the SPA equivalent of the legacy `<nav id="sub_nav">`.
 * A horizontal row of links with active highlighting; items that don't fit the
 * available width collapse into a trailing "More" dropdown, measured against an
 * off-screen mirror of the full list so the visible row never clips.
 */
export const SubNav = ({ items }: SubNavProps) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const measureRef = useRef<HTMLDivElement>(null);
	const [visibleCount, setVisibleCount] = useState(items.length);

	useLayoutEffect(() => {
		const container = containerRef.current;
		const measure = measureRef.current;

		if (!container || !measure) {
			return;
		}

		const compute = () => {
			const available = container.clientWidth;
			const children = Array.from(measure.children) as HTMLElement[];
			let used = 0;
			let count = 0;

			children.forEach((child, i) => {
				if (count < i) {
					return;
				}

				used += child.offsetWidth;
				const moreRemain = i < children.length - 1;

				if (used + (moreRemain ? MORE_RESERVE : 0) <= available) {
					count++;
				}
			});

			// Always show at least one item even on very narrow viewports.
			setVisibleCount(count === 0 ? 1 : count);
		};

		compute();

		const observer = new ResizeObserver(compute);
		observer.observe(container);

		return () => observer.disconnect();
	}, [items]);

	const visible = items.slice(0, visibleCount);
	const overflow = items.slice(visibleCount);

	return (
		<nav className="relative mb-4 flex items-stretch border-b border-border">
			{/* Off-screen mirror of the full list, used only to measure natural widths. */}
			<div
				aria-hidden
				className="pointer-events-none invisible absolute left-0 top-0 flex"
				ref={measureRef}
			>
				{items.map((item) => (
					<ItemLink item={item} key={item.to} />
				))}
			</div>

			<div className="flex min-w-0 flex-1 items-stretch overflow-hidden" ref={containerRef}>
				{visible.map((item) => (
					<ItemLink item={item} key={item.to} />
				))}
			</div>

			{overflow.length > 0 && <MoreMenu items={overflow} />}
		</nav>
	);
};
