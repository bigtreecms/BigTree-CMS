import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ModuleGroupModulesList } from "@/components/developer/ModuleGroupModulesList";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "./TemplateEdit";

interface Body {
	name: string;
	route: string;
	position: number;
}

export const ModuleGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["module-groups", "list"],
		queryFn: () => modulesApi.listGroups(),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<Body>({ name: "", route: "", position: 0 });
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			const found = detailQ.data.find((g: ModuleGroup) => g.id === idParam);

			if (found) {
				setBody({
					name: found.name,
					route: found.route ?? "",
					position: found.position ?? 0,
				});
			}
		}
	}, [isAdd, detailQ.data, idParam]);

	const saveMutation = useMutation({
		mutationFn: () =>
			isAdd
				? modulesApi.createGroup({ name: body.name, route: body.route || undefined })
				: modulesApi.updateGroup(idParam as string, body),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["module-groups"] });
			toast.success(isAdd ? "Group created" : "Group saved");

			if (isAdd) {
				navigate(`/developer/module-groups/${encodeURIComponent(fresh.id)}/edit`, {
					replace: true,
				});
			}
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Save failed");
			}
		},
	});

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/module-groups" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			</div>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<ErrorPanel error={detailQ.error} />
			</div>
		);
	}

	const set = (patch: Partial<Body>) => setBody((prev) => ({ ...prev, ...patch }));

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Module groups", to: "/developer/module-groups" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={isAdd ? "Add module group" : body.name || idParam || "Edit module group"}
				actions={
					<Link
						to="/developer/module-groups"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
					>
						<ChevronLeft size={13} />
						Back
					</Link>
				}
			/>

			<DeveloperSectionNav />

			{generalError && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{generalError}
				</div>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();

					const errors = validateRequired([
						{ field: "name", label: "Name", value: body.name },
					]);

					if (Object.keys(errors).length > 0) {
						setFieldErrors(errors);
						setGeneralError("Please fill in the required fields.");

						return;
					}

					setFieldErrors({});
					setGeneralError(null);
					saveMutation.mutate();
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<TextField
					label="Name"
					value={body.name}
					onChange={(v) => set({ name: v })}
					error={fieldErrors.name}
					required
				/>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<TextField
						label="Route"
						value={body.route}
						onChange={(v) => set({ route: v })}
						hint="Used on the Modules tab URL."
						error={fieldErrors.route}
					/>
					<TextField
						label="Position"
						type="number"
						value={String(body.position)}
						onChange={(v) => set({ position: Number(v) || 0 })}
					/>
				</div>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Link
						to="/developer/module-groups"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
					>
						Cancel
					</Link>
					<button
						type="submit"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
						disabled={saveMutation.isPending}
					>
						<Save size={13} />
						{saveMutation.isPending ? "Saving…" : isAdd ? "Create group" : "Save group"}
					</button>
				</div>
			</form>

			{!isAdd && idParam && (
				<div className="mt-4">
					<ModuleGroupModulesList groupId={idParam} />
				</div>
			)}
		</div>
	);
};
