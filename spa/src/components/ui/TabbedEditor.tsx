import { useId } from "react";
import type { ReactNode } from "react";

import { TabStrip, type TabStripItem } from "./TabStrip";

export interface TabbedEditorTab extends TabStripItem {
	content: ReactNode;
}

interface TabbedEditorProps {
	className?: string;
	onChange: (value: string) => void;
	tabs: TabbedEditorTab[];
	value: string;
}

/**
 * Tab scaffold for multi-tab edit screens (Page edit, Module designer, Settings
 * edit). Builds on the shared `TabStrip` for the header and renders the active
 * tab's panel itself. The host controls the active tab so it can be reflected in
 * the URL / breadcrumb when desired.
 */
export const TabbedEditor = ({ tabs, value, onChange, className }: TabbedEditorProps) => {
	const baseId = useId();
	const active = tabs.find((tab) => tab.value === value);

	return (
		<div className={`flex flex-col ${className ?? ""}`}>
			<TabStrip idBase={baseId} tabs={tabs} value={value} onChange={onChange} />

			<div
				aria-labelledby={`${baseId}-tab-${value}`}
				className="pt-4 focus:outline-none"
				id={`${baseId}-panel-${value}`}
				role="tabpanel"
			>
				{active?.content}
			</div>
		</div>
	);
};
