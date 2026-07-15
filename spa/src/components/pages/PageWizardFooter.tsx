import type { ReactNode } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { CardFooter } from "@/components/ui/Card";

import { PAGE_TABS, type PageTabValue } from "./PageTabStrip";

interface PageWizardFooterProps {
	activeTab: PageTabValue;
	/** The commit actions (Save, Create, Create & Publish …), grouped and right-aligned. */
	children: ReactNode;
	onSelect: (tab: PageTabValue) => void;
	/** Rendered right after Back, before the spacer — e.g. a Delete action. */
	secondary?: ReactNode;
	/** Render the "Next Step" advance button (the page create wizard; PageEdit has none). */
	showNext?: boolean;
}

/**
 * The action bar under the page Properties/Content/SEO/Sharing tabs, shared by
 * PageAdd and PageEdit. It owns the Back/Next tab walk and the responsive
 * layout; each page supplies its own commit buttons as `children`.
 */
export const PageWizardFooter = ({
	activeTab,
	onSelect,
	secondary,
	showNext = false,
	children,
}: PageWizardFooterProps) => {
	const index = PAGE_TABS.indexOf(activeTab);
	const isFirst = index === 0;
	const isLast = index === PAGE_TABS.length - 1;

	return (
		<CardFooter className="flex-wrap items-center" justify="start">
			{!isFirst && (
				<Button
					icon={<ChevronLeft size={13} />}
					variant="secondary"
					onClick={() => onSelect(PAGE_TABS[index - 1] ?? PAGE_TABS[0]!)}
				>
					Back
				</Button>
			)}

			{secondary}

			{/* Desktop-only spacer; on mobile the action group below claims its own row. */}
			<div className="hidden flex-1 sm:block" />

			{showNext && !isLast && (
				<Button
					variant="secondary"
					onClick={() =>
						onSelect(PAGE_TABS[index + 1] ?? PAGE_TABS[PAGE_TABS.length - 1]!)
					}
				>
					Next Step
					<ChevronRight size={13} />
				</Button>
			)}

			{/* On mobile this is a full-width row (so Back sits alone above and the
			    commit actions share a line); on desktop `contents` dissolves the
			    wrapper so the buttons lay out exactly as before. */}
			<div className="flex w-full flex-wrap items-center justify-end gap-2 sm:contents">
				{children}
			</div>
		</CardFooter>
	);
};
