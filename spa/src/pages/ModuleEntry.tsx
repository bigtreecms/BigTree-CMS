import { useEffect } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { modulesApi, type ModuleAction } from "@/api/endpoints/modules";

/**
 * Entry point for /modules/:id. Resolves the module's default action and
 * redirects to the right Phase 7 renderer route. The actions endpoint already
 * returns rows sorted by position DESC, so the first item is canonical.
 *
 * Resolution order: view → report → form. If the first action only has a
 * form id (no view), we send the user to /modules/:id/view/0/add and let the
 * renderer use the form id from history state — but in practice the PHP admin
 * always pairs forms with views, so this fallback is rarely hit.
 *
 * While Phase 7 is still pending the target route renders <Placeholder />;
 * the resolver still does its job so when the renderer ships nothing about
 * this page changes.
 */

interface ResolveResult {
	to: string;
	from?: ModuleAction;
}

const resolveTarget = (moduleId: number, actions: ModuleAction[]): ResolveResult | null => {
	if (actions.length === 0) {
		return null;
	}

	for (const action of actions) {
		if (action.view) {
			return { to: `/modules/${moduleId}/view/${action.view}`, from: action };
		}

		if (action.report) {
			return { to: `/modules/${moduleId}/report/${action.report}`, from: action };
		}

		if (action.form) {
			return {
				to: `/modules/${moduleId}/view/0/add`,
				from: action,
			};
		}
	}

	return null;
};

export const ModuleEntry = () => {
	const { id } = useParams<{ id: string }>();
	const moduleId = id ? Number.parseInt(id, 10) : NaN;
	const navigate = useNavigate();

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: Number.isFinite(moduleId),
	});

	const actionsQuery = useQuery({
		queryKey: ["modules", "actions", moduleId],
		queryFn: () => modulesApi.actions(moduleId),
		enabled: Number.isFinite(moduleId),
	});

	const target =
		actionsQuery.data && Number.isFinite(moduleId)
			? resolveTarget(moduleId, actionsQuery.data)
			: null;

	useEffect(() => {
		if (target) {
			navigate(target.to, { replace: true });
		}
	}, [target, navigate]);

	if (!Number.isFinite(moduleId)) {
		return <Navigate to="/modules" replace />;
	}

	const isLoading = moduleQuery.isLoading || actionsQuery.isLoading;

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Modules", to: "/modules" },
					{ label: moduleQuery.data?.name ?? "…" },
				]}
			/>

			<PageHead title={moduleQuery.data?.name ?? "Module"} />

			{isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading module…
				</div>
			) : actionsQuery.data && actionsQuery.data.length === 0 ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					This module has no actions configured.
				</div>
			) : target ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Opening {target.from?.name ?? "default action"}…
				</div>
			) : (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					This module has no resolvable default action.
				</div>
			)}
		</div>
	);
};
