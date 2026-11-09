import type { ReactNode } from "react";

import { Card, CardHeader } from "@/components/ui/Card";
import { pluralize } from "@/lib/number";

interface SearchResultSectionProps {
	children: ReactNode;
	count: number;
	/** What one result is called, for the count ("page", "entry"…). */
	noun: string;
	title: string;
}

/** One group of results on the search results page: a titled card of rows. */
export const SearchResultSection = ({ children, count, noun, title }: SearchResultSectionProps) => (
	<Card className="overflow-hidden">
		<CardHeader className="flex items-center justify-between">
			<h2 className="text-[13px] font-semibold text-text">{title}</h2>
			<span className="text-[11.5px] tabular-nums text-text-3">{pluralize(count, noun)}</span>
		</CardHeader>
		<ul className="m-0 flex list-none flex-col p-0">{children}</ul>
	</Card>
);
