import { FileText, LayoutGrid, Search, Sparkles, Tag, Users, type LucideIcon } from "lucide-react";
import type { ChatToolActivity } from "@/api/endpoints/ai";

/**
 * One "Searching pages…" progress row summarizing a tool the assistant ran
 * during a turn. Purely informational — the deep-linkable results render
 * separately as artifacts.
 */

interface ToolActivityRowProps {
	activity: ChatToolActivity;
}

interface ToolMeta {
	icon: LucideIcon;
	label: string;
}

const TOOL_META: Record<string, ToolMeta> = {
	search_pages: { icon: FileText, label: "Searched pages" },
	search_modules: { icon: LayoutGrid, label: "Searched modules" },
	search_module_entries: { icon: LayoutGrid, label: "Searched module entries" },
	search_tags: { icon: Tag, label: "Searched tags" },
	search_users: { icon: Users, label: "Searched users" },
	semantic_search: { icon: Sparkles, label: "Semantic search" },
	get_page: { icon: FileText, label: "Read a page" },
	get_module_entry: { icon: LayoutGrid, label: "Read an entry" },
};

const metaFor = (name: string): ToolMeta => TOOL_META[name] ?? { icon: Search, label: name };

/** Trailing note derived from the tool's outcome (empty for a normal result). */
const statusNote = (status: string): string => {
	if (status === "denied") {
		return "— not permitted";
	}

	if (status === "error") {
		return "— failed";
	}

	if (status === "needs_input") {
		return "— needs more detail";
	}

	return "";
};

export const ToolActivityRow = ({ activity }: ToolActivityRowProps) => {
	const meta = metaFor(activity.name);
	const Icon = meta.icon;
	const query =
		typeof activity.arguments?.query === "string" ? String(activity.arguments.query) : "";
	const note = statusNote(activity.status);

	return (
		<div className="flex items-center gap-1.5 text-[11px] text-text-3">
			<Icon size={11} className="shrink-0 text-text-3" />
			<span>
				{meta.label}
				{query ? (
					<>
						{" "}
						<span className="text-text-2">“{query}”</span>
					</>
				) : null}
				{note ? <span className="ml-1 text-warn">{note}</span> : null}
			</span>
		</div>
	);
};
