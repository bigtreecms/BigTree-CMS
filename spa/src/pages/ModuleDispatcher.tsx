import { useParams } from "react-router-dom";

import { isRunnableAction, resolveActionByRoute } from "@/lib/moduleActions";
import { useModuleContext } from "@/pages/ModuleLayout";
import { ModuleAction } from "@/pages/ModuleAction";
import { ModuleEntryAdd } from "@/pages/ModuleEntryAdd";
import { ModuleEntryEdit } from "@/pages/ModuleEntryEdit";
import { ModuleReport } from "@/pages/ModuleReport";
import { ModuleView } from "@/pages/ModuleView";
import { EmptyState } from "@/components/ui/EmptyState";
import { Card } from "@/components/ui/Card";

const card = (message: string) => <EmptyState>{message}</EmptyState>;

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
		return <ModuleAction actionId={action.id} commands={commands} />;
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
			return <ModuleEntryEdit entryId={eid} formId={action.form} />;
		}

		return <ModuleEntryAdd formId={action.form} />;
	}

	// Runnable check failed → legacy custom-PHP action. Classic admin UI is
	// gone; show a porting message instead of linking out.
	return (
		<Card className="p-9 text-center">
			<p className="mb-1 text-[13px] font-medium">
				This action is not available in the new admin.
			</p>
			<p className="text-[12.5px] text-text-3">
				{module ? (
					<>
						“{action.name}” on <strong>{module.name}</strong> is a custom PHP page.
						Rebuild it as a JavaScript module action, or as a form / view / report, to
						use it here.
					</>
				) : (
					<>
						It's a custom PHP page. Rebuild it as a JavaScript module action, or as a
						form / view / report, to use it here.
					</>
				)}
			</p>
		</Card>
	);
};
