import { useRef } from "react";
import type { KeyboardEvent, ReactNode } from "react";

export interface TabStripItem {
	disabled?: boolean;
	icon?: ReactNode;
	label: ReactNode;
	value: string;
}

interface TabStripProps {
	className?: string;
	/**
	 * Prefix for the tab/panel ids so hosts can wire their panels to the strip via
	 * `aria-controls` / `aria-labelledby`. Each tab is `${idBase}-tab-${value}` and
	 * is wired to a panel at `${idBase}-panel-${value}`.
	 */
	idBase?: string;
	onChange: (value: string) => void;
	tabs: TabStripItem[];
	/** Optional content pinned to the far right of the strip (e.g. a LinkFinder). */
	trailing?: ReactNode;
	value: string;
}

/**
 * Horizontal tab strip shared by every multi-tab screen — the Page editor's
 * Properties/Content/SEO/Sharing tabs and the `TabbedEditor` scaffold. The host
 * owns the active value and renders the panels, so this stays a pure, controlled
 * presentation primitive with arrow-key roving focus and an optional trailing
 * slot (used by the Page tabs for the LinkFinder typeahead).
 */
export const TabStrip = ({ tabs, value, onChange, trailing, idBase, className }: TabStripProps) => {
	const refs = useRef<(HTMLButtonElement | null)[]>([]);

	const focusTab = (index: number) => {
		const tab = tabs[index];

		if (!tab || tab.disabled) {
			return;
		}

		refs.current[index]?.focus();
		onChange(tab.value);
	};

	// Roving focus: skip disabled tabs and wrap around the ends.
	const move = (from: number, dir: 1 | -1) => {
		const total = tabs.length;
		let next = from;

		for (let i = 0; i < total; i++) {
			next = (next + dir + total) % total;

			if (!tabs[next]?.disabled) {
				focusTab(next);

				return;
			}
		}
	};

	const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
		switch (event.key) {
			case "ArrowRight":
			case "ArrowDown":
				event.preventDefault();
				move(index, 1);
				break;

			case "ArrowLeft":
			case "ArrowUp":
				event.preventDefault();
				move(index, -1);
				break;

			case "Home":
				event.preventDefault();
				focusTab(tabs.findIndex((tab) => !tab.disabled));
				break;

			case "End": {
				event.preventDefault();
				const fromEnd = [...tabs].reverse().findIndex((tab) => !tab.disabled);
				focusTab(fromEnd === -1 ? -1 : tabs.length - 1 - fromEnd);
				break;
			}
		}
	};

	return (
		<div
			className={`flex min-w-0 items-center border-b border-border bg-surface px-1 ${className ?? ""}`}
		>
			<div
				className="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto [-ms-overflow-style:none] scrollbar-none [&::-webkit-scrollbar]:hidden"
				role="tablist"
			>
				{tabs.map((tab, index) => {
					const active = tab.value === value;

					return (
						<button
							aria-controls={idBase ? `${idBase}-panel-${tab.value}` : undefined}
							aria-selected={active}
							className={`-mb-px flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-[13px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
								active
									? "border-accent text-accent"
									: "border-transparent text-text-3 hover:text-text"
							}`}
							disabled={tab.disabled}
							id={idBase ? `${idBase}-tab-${tab.value}` : undefined}
							key={tab.value}
							ref={(el) => {
								refs.current[index] = el;
							}}
							role="tab"
							tabIndex={active ? 0 : -1}
							type="button"
							onClick={() => onChange(tab.value)}
							onKeyDown={(event) => handleKeyDown(event, index)}
						>
							{tab.icon}
							<span>{tab.label}</span>
						</button>
					);
				})}
			</div>

			{trailing && <div className="flex shrink-0 items-center pl-2">{trailing}</div>}
		</div>
	);
};
