import { createContext, useContext } from "react";
import { Navigate, Outlet, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { SubNav } from "@/components/shell/SubNav";
import { useAuthStore } from "@/auth/store";
import { LEVEL } from "@/lib/permissions";
import { visibleModuleActions } from "@/lib/moduleActions";
import { modulesApi, type ModuleAction, type ModuleSummary } from "@/api/endpoints/modules";

interface ModuleContextValue {
	moduleId: string;
	module: ModuleSummary | undefined;
	actions: ModuleAction[];
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
 * Layout route for /modules/:id/*. Loads the module record and its actions a
 * single time (children read them via useModuleContext), draws the breadcrumb
 * base and the module sub-nav (the SPA equivalent of legacy `<nav id="sub_nav">`),
 * then renders the active sub-page through the <Outlet />.
 */
export const ModuleLayout = () => {
	const { id } = useParams<{ id: string }>();
	const moduleId = id ?? "";
	const userLevel = useAuthStore((s) => s.user?.level ?? LEVEL.NORMAL);

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

	if (moduleId === "") {
		return <Navigate to="/modules" replace />;
	}

	const actions = actionsQuery.data ?? [];
	const navItems = visibleModuleActions(moduleId, actions, userLevel);

	const value: ModuleContextValue = {
		moduleId,
		module: moduleQuery.data,
		actions,
		isLoading: moduleQuery.isLoading || actionsQuery.isLoading,
	};

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Modules", to: "/modules" },
					{
						label: moduleQuery.data?.name ?? "…",
						to: `/modules/${encodeURIComponent(moduleId)}`,
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
