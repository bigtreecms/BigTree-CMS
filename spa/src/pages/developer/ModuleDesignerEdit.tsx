import { useState } from "react";
import { Link, Navigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
	ChevronLeft,
	FileText,
	LayoutList,
	ListChecks,
	Send,
	Settings as SettingsIcon,
	Table,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { TabbedEditor, type TabbedEditorTab } from "@/components/ui/TabbedEditor";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ModuleActionsTab } from "@/components/developer/module-designer/ModuleActionsTab";
import { ModuleEmbedFormsTab } from "@/components/developer/module-designer/ModuleEmbedFormsTab";
import { ModuleFormsTab } from "@/components/developer/module-designer/ModuleFormsTab";
import { ModuleReportsTab } from "@/components/developer/module-designer/ModuleReportsTab";
import { ModuleShellTab } from "@/components/developer/module-designer/ModuleShellTab";
import { ModuleViewsTab } from "@/components/developer/module-designer/ModuleViewsTab";

import { modulesApi } from "@/api/endpoints/modules";

/**
 * Combined add / edit module designer.
 *
 *   Add mode (no :id) shows only the shell form; once the module is created
 *   we navigate to the edit URL where the sub-resource tabs become available
 *   (they need a module id to scope their CRUD calls).
 */
export const ModuleDesignerEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const [tab, setTab] = useState("shell");

	const detailQ = useQuery({
		queryKey: ["modules", "detail", idParam],
		queryFn: () => modulesApi.get(idParam as string),
		enabled: !isAdd,
	});

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-screen-lg px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/modules" replace />;
	}

	const module = detailQ.data ?? null;
	const moduleTable = module?.table ?? "";
	const title = isAdd ? "New module" : module?.name || idParam || "Edit module";

	const tabs: TabbedEditorTab[] = [
		{
			value: "shell",
			label: "Shell",
			icon: <SettingsIcon size={13} />,
			content: <ModuleShellTab moduleId={idParam ?? null} module={module} />,
		},
	];

	if (!isAdd && idParam) {
		tabs.push(
			{
				value: "actions",
				label: "Actions",
				icon: <ListChecks size={13} />,
				content: <ModuleActionsTab moduleId={idParam} />,
			},
			{
				value: "forms",
				label: "Forms",
				icon: <FileText size={13} />,
				content: <ModuleFormsTab moduleId={idParam} moduleTable={moduleTable} />,
			},
			{
				value: "views",
				label: "Views",
				icon: <LayoutList size={13} />,
				content: <ModuleViewsTab moduleId={idParam} moduleTable={moduleTable} />,
			},
			{
				value: "reports",
				label: "Reports",
				icon: <Table size={13} />,
				content: <ModuleReportsTab moduleId={idParam} moduleTable={moduleTable} />,
			},
			{
				value: "embed-forms",
				label: "Embed forms",
				icon: <Send size={13} />,
				content: <ModuleEmbedFormsTab moduleId={idParam} moduleTable={moduleTable} />,
			}
		);
	}

	return (
		<div className="mx-auto max-w-screen-lg px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Modules", to: "/developer/modules" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={title}
				sub={
					isAdd
						? "Define a new module shell. Forms, views and reports become available after it's created."
						: "Editing module definition."
				}
				actions={
					<Link
						to="/developer/modules"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
					>
						<ChevronLeft size={13} />
						Back
					</Link>
				}
			/>

			<DeveloperSectionNav />

			{isAdd ? (
				<div className="rounded-xl border border-border bg-surface-2 p-4">
					<ModuleShellTab moduleId={null} module={null} />
				</div>
			) : (
				<TabbedEditor tabs={tabs} value={tab} onChange={setTab} />
			)}
		</div>
	);
};
