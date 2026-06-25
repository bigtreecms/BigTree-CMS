import { useId } from "react";
import type { ReactNode } from "react";

import { TabStrip, type TabStripItem } from "./TabStrip";

export interface TabbedEditorTab extends TabStripItem {
	content: ReactNode;
}

interface TabbedEditorProps {
	tabs: TabbedEditorTab[];
	value: string;
	onChange: (value: string) => void;
	className?: string;
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
			<TabStrip tabs={tabs} value={value} onChange={onChange} idBase={baseId} />

			<div
				role="tabpanel"
				id={`${baseId}-panel-${value}`}
				aria-labelledby={`${baseId}-tab-${value}`}
				className="pt-4 focus:outline-none"
			>
				{active?.content}
			</div>
		</div>
	);
};
