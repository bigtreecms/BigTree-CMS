import { TabStrip, type TabStripItem } from "@/components/ui/TabStrip";

import { LinkFinder } from "./LinkFinder";

export type PageTabValue = "properties" | "content" | "seo" | "sharing";

/** Tab order, shared with `PageWizardFooter` so Back/Next walk the same sequence. */
export const PAGE_TABS: PageTabValue[] = ["properties", "content", "seo", "sharing"];

const TAB_ITEMS: TabStripItem[] = [
	{ value: "properties", label: "Properties" },
	{ value: "content", label: "Content" },
	{ value: "seo", label: "SEO" },
	{ value: "sharing", label: "Sharing" },
];

interface PageTabStripProps {
	onChange: (next: PageTabValue) => void;
	value: PageTabValue;
}

/**
 * The Page editor's tab header (Properties / Content / SEO / Sharing) with the
 * LinkFinder typeahead pinned to the right. Shared by PageEdit and PageAdd so
 * the tab UI lives here rather than being imported across sibling pages.
 */
export const PageTabStrip = ({ value, onChange }: PageTabStripProps) => (
	<TabStrip
		tabs={TAB_ITEMS}
		trailing={<LinkFinder />}
		value={value}
		onChange={(next) => onChange(next as PageTabValue)}
	/>
);
