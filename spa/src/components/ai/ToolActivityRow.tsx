import { FileText, LayoutGrid, Search, Sparkles, Tag, Users, type LucideIcon } from "lucide-react";
import type { ChatToolActivity } from "@/api/endpoints/ai";

/**
 * One "Searching pages…" progress row summarizing a tool the assistant ran
 * during a turn. Purely informational — the deep-linkable results render
 * separately as artifacts.
 */

interface ToolActivityRowProps {
	activity: ChatToolActivity;
	/** Send a chosen option back as the next message. Omitted on replayed history. */
	onChoose?: (choice: string) => void;
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

	if (status === "needs_prior_change") {
		return "— waiting on an earlier change";
	}

	return "";
};

export const ToolActivityRow = ({ activity, onChoose }: ToolActivityRowProps) => {
	const meta = metaFor(activity.name);
	const Icon = meta.icon;
	const query =
		typeof activity.arguments?.query === "string" ? String(activity.arguments.query) : "";
	const note = statusNote(activity.status);
	// A tool that couldn't proceed without a choice hands back the question and its
	// options. They reached the model and stopped there, so the user saw only
	// "needs more detail" and whatever the model chose to paraphrase.
	const question = activity.status === "needs_input" ? (activity.question ?? "") : "";
	const options = activity.status === "needs_input" ? (activity.options ?? []) : [];

	return (
		<div className="flex flex-col gap-1">
			<div className="flex items-center gap-1.5 text-[11px] text-text-3">
				<Icon className="shrink-0 text-text-3" size={11} />
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

			{question !== "" && (
				<p className="pl-[18px] text-[11.5px] text-text-2">{question}</p>
			)}

			{options.length > 0 && onChoose && (
				<div className="flex flex-wrap gap-1 pl-[18px]">
					{options.map((option, i) => (
						<button
							className="rounded-full border border-border bg-surface-2 px-2 py-0.5 text-[11px] text-text-2 transition-colors hover:border-accent/50 hover:text-text"
							key={option.id ?? `${option.label}-${i}`}
							onClick={() => onChoose(option.label)}
							title={option.description}
							type="button"
						>
							{option.label}
						</button>
					))}
				</div>
			)}
		</div>
	);
};
