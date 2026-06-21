import { useState } from "react";
import { Navigate, useParams, useSearchParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
	ChevronLeft,
	Database,
	FileText,
	LayoutList,
	ListChecks,
	Send,
	Settings as SettingsIcon,
	Table,
	Wand2,
} from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { Button } from "@/components/ui/Button";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { TabbedEditor, type TabbedEditorTab } from "@/components/ui/TabbedEditor";
import { EmptyState } from "@/components/ui/EmptyState";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ModuleActionsTab } from "@/components/developer/module-designer/ModuleActionsTab";
import { ModuleEmbedFormsTab } from "@/components/developer/module-designer/ModuleEmbedFormsTab";
import { ModuleFormsTab } from "@/components/developer/module-designer/ModuleFormsTab";
import { ModuleReportsTab } from "@/components/developer/module-designer/ModuleReportsTab";
import { ModuleShellTab } from "@/components/developer/module-designer/ModuleShellTab";
import { ModuleViewsTab } from "@/components/developer/module-designer/ModuleViewsTab";
import { ModuleBuilderWizard } from "@/components/developer/module-designer/ModuleBuilderWizard";

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
	const [searchParams, setSearchParams] = useSearchParams();
	const tab = searchParams.get("tab") ?? "shell";

	// Add mode forks (legacy modules/start.php): pick an existing table, or have
	// the designer build the table + form + view for you.
	const [addMode, setAddMode] = useState<"choose" | "existing" | "build">("choose");

	const setTab = (value: string) => {
		setSearchParams(
			(prev) => {
				const next = new URLSearchParams(prev);

				if (value === "shell") {
					next.delete("tab");
				} else {
					next.set("tab", value);
				}

				return next;
			},
			{ replace: true }
		);
	};

	const detailQ = useQuery({
		queryKey: ["modules", "detail", idParam],
		queryFn: () => modulesApi.get(idParam as string),
		enabled: !isAdd,
	});

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-5xl px-6 py-4">
				<EmptyState>Loading…</EmptyState>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-5xl px-6 py-4">
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

	const activeTab = tabs.some((t) => t.value === tab) ? tab : "shell";

	return (
		<div className="mx-auto max-w-5xl px-6 py-4">
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
						? "Create a module from an existing table, or have the designer build the table for you."
						: "Editing module definition."
				}
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/developer/modules">
						Back
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{isAdd ? (
				addMode === "choose" ? (
					<div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
						<button
							type="button"
							onClick={() => setAddMode("existing")}
							className="flex flex-col items-start gap-2 rounded-xl border border-border bg-surface p-5 text-left transition-colors hover:border-border-strong hover:bg-hover"
						>
							<span className="grid size-9 place-items-center rounded-lg bg-accent-soft text-accent">
								<Database size={18} />
							</span>
							<span className="text-[13.5px] font-semibold text-text">
								Use an existing table
							</span>
							<span className="text-[12px] text-text-3">
								Point the module at a MySQL table you've already created, then set
								up its forms and views.
							</span>
						</button>
						<button
							type="button"
							onClick={() => setAddMode("build")}
							className="flex flex-col items-start gap-2 rounded-xl border border-border bg-surface p-5 text-left transition-colors hover:border-border-strong hover:bg-hover"
						>
							<span className="grid size-9 place-items-center rounded-lg bg-accent-soft text-accent">
								<Wand2 size={18} />
							</span>
							<span className="text-[13.5px] font-semibold text-text">
								Build the table for me
							</span>
							<span className="text-[12px] text-text-3">
								Define a few fields and the designer creates the table, an add/edit
								form, and a landing view automatically.
							</span>
						</button>
					</div>
				) : (
					<div className="space-y-3">
						<button
							type="button"
							onClick={() => setAddMode("choose")}
							className="inline-flex items-center gap-1.5 text-[12px] text-text-3 hover:text-text"
						>
							<ChevronLeft size={13} />
							Back to options
						</button>

						{addMode === "existing" ? (
							<div className="rounded-xl border border-border bg-surface-2 p-4">
								<ModuleShellTab moduleId={null} module={null} />
							</div>
						) : (
							<ModuleBuilderWizard />
						)}
					</div>
				)
			) : (
				<TabbedEditor tabs={tabs} value={activeTab} onChange={setTab} />
			)}
		</div>
	);
};
