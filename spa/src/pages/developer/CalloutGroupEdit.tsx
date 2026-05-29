import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQueries, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { calloutsApi, type CalloutGroupEditBody } from "@/api/endpoints/callouts";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

import { TextField } from "./TemplateEdit";

export const CalloutGroupEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [groupQ, calloutsQ] = useQueries({
		queries: [
			{
				queryKey: ["callout-groups", "detail", idParam],
				queryFn: () => calloutsApi.getGroup(idParam as string),
				enabled: !isAdd,
			},
			{
				queryKey: ["callouts", "list"],
				queryFn: () => calloutsApi.list(),
			},
		],
	});

	const [body, setBody] = useState<CalloutGroupEditBody>(() =>
		isAdd ? { id: "", name: "", callouts: [] } : {}
	);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (!isAdd && groupQ.data) {
			setBody({
				id: groupQ.data.id,
				name: groupQ.data.name,
				callouts: groupQ.data.callouts ?? [],
			});
		}
	}, [isAdd, groupQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: CalloutGroupEditBody) =>
			isAdd
				? calloutsApi.createGroup(next)
				: calloutsApi.updateGroup(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["callout-groups"] });
			toast.success(isAdd ? "Group created" : "Group saved");

			if (isAdd) {
				navigate(`/developer/callout-groups/${encodeURIComponent(fresh.id)}/edit`, {
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
		return <Navigate to="/developer/callout-groups" replace />;
	}

	if (!isAdd && groupQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading…
				</div>
			</div>
		);
	}

	if (!isAdd && groupQ.error) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<ErrorPanel error={groupQ.error} />
			</div>
		);
	}

	const set = (patch: Partial<CalloutGroupEditBody>) =>
		setBody((prev) => ({ ...prev, ...patch }));
	const selected = new Set(body.callouts ?? []);

	const toggleCallout = (id: string) => {
		const next = new Set(selected);

		if (next.has(id)) {
			next.delete(id);
		} else {
			next.add(id);
		}

		set({ callouts: Array.from(next) });
	};

	const title = isAdd ? "Add callout group" : body.name || idParam || "Edit callout group";
	const callouts = calloutsQ.data ?? [];

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Callout groups", to: "/developer/callout-groups" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={title}
				actions={
					<Link
						to="/developer/callout-groups"
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
					saveMutation.mutate(body);
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<TextField
						label="ID"
						value={body.id ?? ""}
						onChange={(v) => set({ id: v })}
						hint="Lowercase, hyphens or underscores."
						error={fieldErrors.id}
						disabled={!isAdd}
						required
					/>
					<TextField
						label="Name"
						value={body.name ?? ""}
						onChange={(v) => set({ name: v })}
						error={fieldErrors.name}
						required
					/>
				</div>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Callouts in this group
					</div>
					{calloutsQ.isLoading ? (
						<div className="text-[12.5px] text-text-3">Loading callouts…</div>
					) : callouts.length === 0 ? (
						<div className="rounded-md border border-dashed border-border bg-surface-2 px-3 py-3 text-[12.5px] text-text-3">
							No callouts to pick from yet. Create one first.
						</div>
					) : (
						<ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
							{callouts.map((c) => (
								<li key={c.id}>
									<label className="flex cursor-pointer items-center gap-3 px-3 py-2 text-[12.5px] hover:bg-hover">
										<input
											type="checkbox"
											className="h-4 w-4 accent-accent"
											checked={selected.has(c.id)}
											onChange={() => toggleCallout(c.id)}
										/>
										<span className="min-w-0 flex-1">
											<div className="truncate text-text-2">{c.name}</div>
											<div className="truncate font-mono text-[11px] text-text-3">
												{c.id}
											</div>
										</span>
									</label>
								</li>
							))}
						</ul>
					)}
				</div>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Link
						to="/developer/callout-groups"
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
		</div>
	);
};
