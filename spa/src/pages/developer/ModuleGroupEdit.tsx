import { useEffect, useState } from "react";
import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { ModuleGroupModulesList } from "@/components/developer/ModuleGroupModulesList";

import { modulesApi, type ModuleGroup } from "@/api/endpoints/modules";

import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "@/components/ui/TextField";
import { Loading } from "@/components/ui/Loading";

interface Body {
	name: string;
	route: string;
	position: number;
}

export const ModuleGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/module-groups");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: queryKeys.moduleGroups.list(),
		queryFn: () => modulesApi.listGroups(),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<Body>({ name: "", route: "", position: 0 });
	const { error, fieldErrors, handleSubmit, onMutationError } = useFormSubmit();
	const [seeded, setSeeded] = useState(isAdd);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			const found = detailQ.data.find((g: ModuleGroup) => g.id === idParam);

			if (found) {
				setBody({
					name: found.name,
					route: found.route ?? "",
					position: found.position ?? 0,
				});
				setSeeded(true);
			}
		}
	}, [isAdd, detailQ.data, idParam]);

	const saveMutation = useMutation({
		mutationFn: () =>
			isAdd
				? modulesApi.createGroup({ name: body.name, route: body.route || undefined })
				: modulesApi.updateGroup(idParam as string, body),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.moduleGroups.root() });
			toast.success(isAdd ? "Group created" : "Group saved");

			if (isAdd) {
				navigate(`/developer/module-groups/${encodeURIComponent(fresh.id)}/edit`, {
					replace: true,
				});
			} else {
				navigate(returnTo);
			}
		},
		onError: (err) => onMutationError(err, "Save failed"),
	});

	const isDirty = useDirtyTracker(body, seeded) && !saveMutation.isPending;

	if (!isAdd && !idParam) {
		return <Navigate to="/developer/module-groups" replace />;
	}

	if (!isAdd && detailQ.isLoading) {
		return (
			<PageContainer width="narrow">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (!isAdd && detailQ.error) {
		return (
			<PageContainer width="narrow">
				<ErrorPanel error={detailQ.error} />
			</PageContainer>
		);
	}

	const set = (patch: Partial<Body>) => setBody((prev) => ({ ...prev, ...patch }));

	return (
		<PageContainer width="narrow">
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
					<Button icon={<ChevronLeft size={13} />} to="/developer/module-groups">
						Back
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{error && (
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			<FormShell
				onSubmit={(e) =>
					handleSubmit(
						e,
						() =>
							validateRequired([{ field: "name", label: "Name", value: body.name }]),
						() => saveMutation.mutate()
					)
				}
				footer={
					<FormFooter
						cancelTo="/developer/module-groups"
						submitLabel={isAdd ? "Create group" : "Save group"}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
					/>
				}
			>
				<div className="space-y-4">
					<TextField
						label="Name"
						value={body.name}
						onChange={(v) => set({ name: v })}
						error={fieldErrors.name}
						required
					/>
					<FieldGrid>
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
					</FieldGrid>
				</div>
			</FormShell>

			{!isAdd && idParam && (
				<div className="mt-4">
					<ModuleGroupModulesList groupId={idParam} />
				</div>
			)}

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};
