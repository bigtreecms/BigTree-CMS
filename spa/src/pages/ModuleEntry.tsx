import { useEffect } from "react";
import { useNavigate } from "react-router-dom";

import { useModuleContext } from "@/pages/ModuleLayout";
import { moduleActionTarget } from "@/lib/moduleActions";

/**
 * Index page for /modules/:id. Resolves the module's default action and
 * redirects to the matching renderer route. The actions endpoint returns rows
 * sorted by position DESC, so the first runnable action is canonical.
 *
 * Resolution order follows the action's own target: view → report → form
 * (see `moduleActionTarget`). The breadcrumb + sub-nav are drawn by
 * <ModuleLayout />, so this page only renders a brief transitional state.
 */
export const ModuleEntry = () => {
	const { moduleId, actions, isLoading } = useModuleContext();
	const navigate = useNavigate();

	const target = (() => {
		for (const action of actions) {
			const to = moduleActionTarget(moduleId, action);

			if (to) {
				return to;
			}
		}

		return null;
	})();

	useEffect(() => {
		if (target) {
			navigate(target, { replace: true });
		}
	}, [target, navigate]);

	const message = isLoading
		? "Loading module…"
		: target
			? "Opening default action…"
			: "This module has no resolvable default action.";

	return (
		<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
			{message}
		</div>
	);
};
