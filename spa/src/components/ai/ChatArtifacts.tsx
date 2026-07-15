import { useNavigate } from "react-router-dom";
import { FileText, LayoutGrid, SlidersHorizontal, Tag, Users, type LucideIcon } from "lucide-react";
import type { SearchModuleEntryGroup, SearchResultGroups } from "@/api/endpoints/search";
import { decodeHtmlEntitiesDom } from "@/lib/html";
import { modulePath } from "@/lib/moduleActions";
import { moduleEntryEditPath, pageEditPath, settingEditPath } from "@/lib/routes";

/**
 * The navigable entities an assistant turn touched, rendered as deep links below
 * its answer. These come from the tools' artifacts (never the model text), so the
 * user can jump straight to a page/module/entry the assistant referenced.
 */

interface ChatArtifactsProps {
	artifacts: SearchResultGroups | undefined;
	/** Close the chat panel after navigating. */
	onNavigate: () => void;
}

interface ArtifactLink {
	key: string;
	icon: LucideIcon;
	title: string;
	subtitle: string;
	path: string;
}

const displayText = (value: unknown): string => {
	if (value == null) {
		return "";
	}

	const raw = String(value);

	if (raw.trim() === "") {
		return "";
	}

	return decodeHtmlEntitiesDom(raw, 5);
};

const asList = (value: unknown): unknown[] => (Array.isArray(value) ? value : []);

/** Best human label from a module-view cache row (column2 is often the title). */
const entryLabel = (item: Record<string, unknown>): string => {
	for (const key of ["column2", "column1", "title", "name", "id"]) {
		const label = displayText(item[key]);

		if (label !== "") {
			return label;
		}
	}

	return "Entry";
};

/** Flatten every artifact group into one ordered list of navigable links. */
const buildLinks = (groups: SearchResultGroups | undefined): ArtifactLink[] => {
	if (!groups) {
		return [];
	}

	const links: ArtifactLink[] = [];

	for (const p of asList(groups.pages) as Record<string, unknown>[]) {
		if (p.id == null) {
			continue;
		}

		links.push({
			key: `p-${p.id}`,
			icon: FileText,
			title: displayText(p.nav_title) || "Untitled",
			subtitle: displayText(p.path) || "/",
			path: pageEditPath(p.id as string | number),
		});
	}

	for (const m of asList(groups.modules) as Record<string, unknown>[]) {
		if (typeof m.route !== "string" || !m.route) {
			continue;
		}

		links.push({
			key: `m-${m.id}`,
			icon: LayoutGrid,
			title: displayText(m.name) || "Module",
			subtitle: displayText(m.route),
			path: modulePath({ route: m.route }),
		});
	}

	for (const group of asList(groups.entries) as SearchModuleEntryGroup[]) {
		const route = group?.module?.route;

		if (!route) {
			continue;
		}

		for (const item of asList(group.items) as Record<string, unknown>[]) {
			const entryId = item.id as string | number | undefined;

			if (entryId == null || entryId === "") {
				continue;
			}

			links.push({
				key: `e-${group.module.id}-${entryId}`,
				icon: LayoutGrid,
				title: entryLabel(item),
				subtitle: displayText(group.module.name || group.module.route),
				path: moduleEntryEditPath(route, "edit", entryId),
			});
		}
	}

	for (const s of asList(groups.settings) as Record<string, unknown>[]) {
		if (s.id == null) {
			continue;
		}

		links.push({
			key: `s-${s.id}`,
			icon: SlidersHorizontal,
			title: displayText(s.name) || String(s.id),
			subtitle: String(s.id),
			path: settingEditPath(s.id as string | number),
		});
	}

	for (const t of asList(groups.tags) as Record<string, unknown>[]) {
		if (t.id == null) {
			continue;
		}

		links.push({
			key: `t-${t.id}`,
			icon: Tag,
			title: displayText(t.tag),
			subtitle: "tag",
			path: "/tags",
		});
	}

	for (const u of asList(groups.users) as Record<string, unknown>[]) {
		if (u.id == null) {
			continue;
		}

		links.push({
			key: `u-${u.id}`,
			icon: Users,
			title: displayText(u.name),
			subtitle: displayText(u.email),
			path: `/users/${encodeURIComponent(String(u.id))}/edit`,
		});
	}

	return links;
};

export const ChatArtifacts = ({ artifacts, onNavigate }: ChatArtifactsProps) => {
	const navigate = useNavigate();
	const links = buildLinks(artifacts);

	if (links.length === 0) {
		return null;
	}

	return (
		<div className="mt-2 flex flex-col gap-px rounded-lg border border-border bg-surface-2/40 p-1">
			{links.map((link) => {
				const Icon = link.icon;

				return (
					<button
						key={link.key}
						type="button"
						onClick={() => {
							navigate(link.path);
							onNavigate();
						}}
						className="flex items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-hover"
					>
						<Icon size={13} className="shrink-0 text-text-3" />
						<span className="min-w-0 flex-1">
							<span className="block truncate text-[12.5px] text-text">
								{link.title}
							</span>
							{link.subtitle ? (
								<span className="block truncate text-[11px] text-text-3">
									{link.subtitle}
								</span>
							) : null}
						</span>
					</button>
				);
			})}
		</div>
	);
};
