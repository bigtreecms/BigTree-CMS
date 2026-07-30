import type { ReactNode } from "react";

import { TabStrip, type TabStripItem } from "@/components/ui/TabStrip";

export type { TabStripItem as TabItem };

interface TabsProps {
	/** Optional trailing slot on the strip (actions, search, …). */
	actions?: ReactNode;
	className?: string;
	/** Prefix for aria ids linking tabs to panels. */
	idBase?: string;
	onChange: (value: string) => void;
	tabs: TabStripItem[];
	value: string;
}

/**
 * Controlled tab strip for custom actions — same chrome as the page editor and
 * TabbedEditor. Host owns active value + panel content.
 */
export const Tabs = ({ tabs, value, onChange, actions, idBase, className }: TabsProps) => (
	<TabStrip
		className={className}
		idBase={idBase}
		tabs={tabs}
		trailing={actions}
		value={value}
		onChange={onChange}
	/>
);

interface TabPanelProps {
	children: ReactNode;
	className?: string;
	/** Must match the corresponding tab's `value`. */
	id?: string;
	idBase?: string;
	/** When false, the panel is hidden (still mounted so state survives). */
	active?: boolean;
	value: string;
	/** Currently selected tab value from the host. */
	current: string;
}

/**
 * Panel that shows when `current === value`. Prefer keeping inactive panels
 * mounted (`hidden`) so local field state isn't lost on tab switches.
 */
export const TabPanel = ({
	children,
	value,
	current,
	idBase,
	className = "",
	active,
}: TabPanelProps) => {
	const isActive = active ?? current === value;

	return (
		<div
			aria-labelledby={idBase ? `${idBase}-tab-${value}` : undefined}
			className={isActive ? className : `hidden ${className}`}
			hidden={!isActive}
			id={idBase ? `${idBase}-panel-${value}` : undefined}
			role="tabpanel"
		>
			{children}
		</div>
	);
};
