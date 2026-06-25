import { DataColumnSelect } from "./DataColumnSelect";

import { TextField } from "@/components/ui/TextField";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { SectionLabel } from "@/components/ui/SectionLabel";

/**
 * Per-feed-type settings form — the SPA equivalent of the legacy
 * `ajax/developer/feed-settings/{type}.php` dialogs. RSS / RSS 2.0 feeds map
 * the source table's columns onto feed fields (title, description, link, …);
 * "custom" feeds only carry sort/limit/parser and define their own output
 * fields elsewhere.
 *
 * Settings are merged (never replaced wholesale) so keys not surfaced for the
 * current type — e.g. when switching rss ↔ custom — are preserved.
 */
interface FeedSettingsControlProps {
	/** Feed type: "custom" | "rss" | "rss2". */
	type: string;
	/** Source table; column pickers stay disabled until one is chosen. */
	table: string;
	settings: Record<string, unknown>;
	onChange: (next: Record<string, unknown>) => void;
}

const str = (value: unknown): string => (value == null ? "" : String(value));

export const FeedSettingsControl = ({
	type,
	table,
	settings,
	onChange,
}: FeedSettingsControlProps) => {
	const set = (patch: Record<string, unknown>) => onChange({ ...settings, ...patch });

	const text = (key: string, label: string, hint?: string) => (
		<TextField
			label={label}
			value={str(settings[key])}
			onChange={(v) => set({ [key]: v })}
			hint={hint}
		/>
	);

	const column = (key: string, label: string, hint?: string) => (
		<DataColumnSelect
			label={label}
			table={table}
			value={str(settings[key])}
			onChange={(v) => set({ [key]: v })}
			hint={hint}
		/>
	);

	if (type === "custom") {
		return (
			<FieldGrid>
				{column("sort", "Order by")}
				{text("limit", "Limit", "Defaults to 15.")}
				{text(
					"parser",
					"Parser function",
					"Receives the table rows, returns a filtered array."
				)}
			</FieldGrid>
		);
	}

	const isRss2 = type === "rss2";

	return (
		<div className="space-y-4">
			<FieldGrid>
				{text("feed_title", "Feed title")}
				{text("feed_link", "Original content link", "e.g. a link back to the news page.")}
				{text("limit", "Limit", "Defaults to 15.")}
				{text(
					"parser",
					"Parser function",
					"Receives the table rows, returns a filtered array."
				)}
			</FieldGrid>

			<SectionLabel>Field mapping</SectionLabel>
			<FieldGrid>
				{column("title", "Title field")}
				{column("description", "Description field")}
				{text("content_limit", "Description content limit", "Default is 500 characters.")}
				{column("link", "Link field")}
				{text("link_gen", "Link generator", "Wrap field names in {} for dynamic links.")}
				{isRss2 && column("date", "Date field")}
				{isRss2 && column("creator", "Creator field")}
				{column("sort", "Order by")}
			</FieldGrid>
		</div>
	);
};
