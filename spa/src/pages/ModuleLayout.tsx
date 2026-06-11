import { createContext, useCallback, useContext } from "react";
import { Navigate, Outlet, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { SubNav } from "@/components/shell/SubNav";
import { useAuthStore } from "@/auth/store";
import { useSiteInfo } from "@/hooks/useSiteInfo";
import { LEVEL } from "@/lib/permissions";
import { moduleActionPath, modulePath, visibleModuleActions } from "@/lib/moduleActions";
import { modulesApi, type ModuleAction, type ModuleSummary } from "@/api/endpoints/modules";

interface ModuleContextValue {
	/** Internal slug id — used for every API call. */
	moduleId: string;
	module: ModuleSummary | undefined;
	actions: ModuleAction[];
	/** Conventional add/edit form actions, for view-row links. */
	addAction: ModuleAction | undefined;
	editAction: ModuleAction | undefined;
	isLoading: boolean;
}

const ModuleContext = createContext<ModuleContextValue | null>(null);

/** Read the module record + actions loaded once by <ModuleLayout />. */
export const useModuleContext = (): ModuleContextValue => {
	const ctx = useContext(ModuleContext);

	if (!ctx) {
		throw new Error("useModuleContext must be used within a ModuleLayout route");
	}

	return ctx;
};

/**
 * Route-based link builders for view renderers (edit a row, run a custom row
 * action). Edit falls back to the conventional "edit" route when the module has
 * no explicit edit action, matching the legacy view-row links.
 */
export const useModuleEntryLinks = () => {
	const { module, editAction } = useModuleContext();

	const editPath = useCallback(
		(entryId: string | number): string => {
			return module
				? moduleActionPath(module, editAction ?? { route: "edit" }, entryId)
				: "#";
		},
		[module, editAction]
	);

	const actionPath = useCallback(
		(actionRoute: string, entryId: string | number): string => {
			return module ? moduleActionPath(module, { route: actionRoute }, entryId) : "#";
		},
		[module]
	);

	return { editPath, actionPath };
};

/**
 * Layout route for /modules/:moduleRoute/*. Resolves the module by its URL route
 * (mirroring the legacy admin) via the cached module list, then loads that
 * module's record + actions a single time. Children read everything through
 * useModuleContext and the <ModuleDispatcher /> resolves the active action from
 * the remaining path. Draws the breadcrumb base and the module sub-nav.
 */
export const ModuleLayout = () => {
	const { moduleRoute } = useParams<{ moduleRoute: string }>();
	const route = moduleRoute ?? "";
	const userLevel = useAuthStore((s) => s.user?.level ?? LEVEL.NORMAL);
	const site = useSiteInfo();

	const listQuery = useQuery({
		queryKey: ["modules", "list"],
		queryFn: () => modulesApi.list(),
	});

	const resolved = listQuery.data?.find((m) => m.route === route);
	const moduleId = resolved?.id ?? "";

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const actionsQuery = useQuery({
		queryKey: ["modules", "actions", moduleId],
		queryFn: () => modulesApi.actions(moduleId),
		enabled: moduleId !== "",
	});

	// The list resolved but no module owns this route → 404 to the module index.
	if (listQuery.isSuccess && !resolved) {
		return <Navigate to="/modules" replace />;
	}

	const module = moduleQuery.data ?? resolved;
	const actions = actionsQuery.data ?? [];
	const addAction = actions.find((a) => a.route === "add");
	const editAction = actions.find((a) => a.route === "edit");
	const navItems = module
		? visibleModuleActions(module, actions, userLevel, site?.admin_root)
		: [];

	const value: ModuleContextValue = {
		moduleId,
		module,
		actions,
		addAction,
		editAction,
		isLoading: listQuery.isLoading || moduleQuery.isLoading || actionsQuery.isLoading,
	};

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Modules", to: "/modules" },
					{
						label: module?.name ?? "…",
						to: module ? modulePath(module) : "/modules",
					},
				]}
			/>

			{navItems.length > 0 && <SubNav items={navItems} />}

			<ModuleContext.Provider value={value}>
				<Outlet />
			</ModuleContext.Provider>
		</div>
	);
};
