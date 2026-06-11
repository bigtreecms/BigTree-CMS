import { useParams } from "react-router-dom";
import { ExternalLink } from "lucide-react";

import { isRunnableAction, legacyActionUrl, resolveActionByRoute } from "@/lib/moduleActions";
import { useModuleContext } from "@/pages/ModuleLayout";
import { useSiteInfo } from "@/hooks/useSiteInfo";
import { ModuleAction } from "@/pages/ModuleAction";
import { ModuleEntryAdd } from "@/pages/ModuleEntryAdd";
import { ModuleEntryEdit } from "@/pages/ModuleEntryEdit";
import { ModuleReport } from "@/pages/ModuleReport";
import { ModuleView } from "@/pages/ModuleView";

const card = (message: string) => (
	<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
		{message}
	</div>
);

/**
 * Resolves the active module action from the URL and renders the matching screen,
 * mirroring the legacy admin's router. Mounted at both the module index (default
 * action) and the `*` splat (`/modules/:moduleRoute/<actionRoute>/<commands>`).
 *
 * The path after the module route is split into segments and matched against the
 * module's action routes (greedy longest-match, see resolveActionByRoute); the
 * trailing segments become commands (e.g. the entry id for an edit). The matched
 * action's relation then decides what loads — a custom module action, a view, a
 * report, or a form (add when no entry id, edit when one is present).
 */
export const ModuleDispatcher = () => {
	const { "*": splat } = useParams();
	const { module, actions, isLoading } = useModuleContext();
	const site = useSiteInfo();

	if (isLoading) {
		return card("Loading module…");
	}

	const segments = (splat ?? "").split("/").filter(Boolean);

	let resolved = resolveActionByRoute(actions, segments);

	// No action owns the landing route ("") — fall back to the first runnable
	// action so the module still has a sensible default screen.
	if (!resolved && segments.length === 0) {
		const first = actions.find(isRunnableAction);

		if (first) {
			resolved = { action: first, commands: [] };
		}
	}

	if (!resolved) {
		return card("That action doesn't exist on this module.");
	}

	const { action, commands } = resolved;

	if (action.render === "module") {
		return <ModuleAction actionId={action.id} />;
	}

	if (action.view) {
		return <ModuleView viewId={action.view} />;
	}

	if (action.report) {
		return <ModuleReport reportId={action.report} />;
	}

	if (action.form) {
		const eid = commands[0];

		if (eid !== undefined) {
			// Pass the raw id through — pending entries carry a "p" prefix (e.g.
			// "p5") that must survive to the edit form / API; parseInt would turn
			// it into NaN. ModuleEntryEdit validates and normalizes it.
			return <ModuleEntryEdit formId={action.form} entryId={eid} />;
		}

		return <ModuleEntryAdd formId={action.form} />;
	}

	// Runnable check (custom/view/report/form) failed → legacy custom-PHP
	// action. Send the user to the classic admin to finish the task there
	// (until the action is ported to the action-module system).
	const legacyUrl =
		module && site?.admin_root
			? legacyActionUrl(site.admin_root, module, action, ...commands)
			: null;

	return (
		<div className="rounded-xl border border-border bg-surface p-9 text-center">
			<p className="mb-1 text-[13px] font-medium">This action runs in the classic admin.</p>
			<p className="mb-4 text-[12.5px] text-text-3">
				It's a custom PHP page that hasn't been ported to the new admin yet.
			</p>
			{legacyUrl ? (
				<a
					href={legacyUrl}
					className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
				>
					<ExternalLink size={13} />
					Open in the classic admin
				</a>
			) : (
				<p className="text-[12.5px] text-text-3">
					Open the classic admin and navigate to this module to use it.
				</p>
			)}
		</div>
	);
};
