import { useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";

import { modulesApi } from "@/api/endpoints/modules";
import { useAuthStore } from "@/auth/store";
import { LEVEL } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { useModuleContext } from "@/pages/ModuleLayout";
import { ActionRunner } from "@/renderer/actions/ActionRunner";
import type { ActionHost } from "@/renderer/actions/actionModuleContract";

/**
 * Runtime page for a custom (module) action, rendered by <ModuleDispatcher />
 * when the resolved action's `render` is "module".
 *
 * Loads the action's render contract, builds the ActionHost (context + invoke +
 * navigation/toast) and hands it to <ActionRunner />, which imports and renders
 * the author's React module. Local + trusted (core/verified) modules run
 * in-context; untrusted marketplace modules need the iframe sandbox (a later
 * build step) and show a notice until then.
 */
interface ModuleActionProps {
	actionId: string;
}

export const ModuleAction = ({ actionId }: ModuleActionProps) => {
	const navigate = useNavigate();
	const userLevel = useAuthStore((s) => s.user?.level ?? LEVEL.NORMAL);
	const { moduleId, module, actions } = useModuleContext();

	const action = actions.find((a) => a.id === actionId);

	const schemaQuery = useQuery({
		queryKey: ["modules", moduleId, "actions", actionId, "schema"],
		queryFn: () => modulesApi.actionSchema(moduleId, actionId),
		enabled: moduleId !== "" && actionId !== "",
	});

	const host = useMemo<ActionHost | null>(() => {
		if (!action) {
			return null;
		}

		return {
			context: {
				moduleId,
				action,
				params: { id: moduleId, sid: actionId },
				userLevel,
			},
			invoke: (payload?: unknown) => modulesApi.invokeAction(moduleId, actionId, payload),
			invokeAction: (route: string, payload?: unknown) => {
				const target = actions.find((a) => a.route === route);

				if (!target) {
					return Promise.reject(
						new Error(`No action with route "${route}" on this module.`)
					);
				}

				return modulesApi.invokeAction(moduleId, target.id, payload);
			},
			navigate: (to: string) => navigate(to),
			toast: (message: string, kind = "info") => toast[kind](message),
		};
	}, [action, actions, moduleId, actionId, userLevel, navigate]);

	const schema = schemaQuery.data;
	const title = action?.name ?? module?.name ?? "Action";

	const card = (body: React.ReactNode) => (
		<div className="rounded-xl border border-border bg-surface p-6 text-[13px]">{body}</div>
	);

	return (
		<>
			<PageHead title={title} />

			{schemaQuery.isLoading || !host ? (
				card(<span className="text-text-3">Loading action…</span>)
			) : schemaQuery.isError || !schema ? (
				card(<span className="text-text-3">That action doesn't exist on this module.</span>)
			) : schema.render !== "module" ? (
				card(
					<div>
						<div className="mb-1 font-semibold text-text">
							This action can't run here
						</div>
						<p className="text-text-2">
							It's a legacy custom action. Rebuild it as a JavaScript module to run it
							in the new admin.
						</p>
					</div>
				)
			) : schema.trust === "marketplace" ? (
				card(
					<div>
						<div className="mb-1 font-semibold text-text">Sandboxed action</div>
						<p className="text-text-2">
							This action ships from a marketplace extension and runs in a sandbox
							that isn't enabled yet.
						</p>
					</div>
				)
			) : (
				<ActionRunner
					host={host}
					source={schema.module_source || undefined}
					assetUrl={schema.asset_url || undefined}
				/>
			)}
		</>
	);
};
