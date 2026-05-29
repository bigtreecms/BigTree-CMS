import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import { settingsApi, type SettingCreateBody } from "@/api/endpoints/settings";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

import { SelectField, TextField } from "./TemplateEdit";

/**
 * /developer/settings/add — definition-only form. Once created, the
 * value-side editing happens at /settings/:id/edit (which uses FieldRenderer
 * based on the `type` set here).
 */
export const SettingAdd = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [body, setBody] = useState<SettingCreateBody>({
		id: "",
		name: "",
		description: "",
		type: "text",
		settings: {},
		locked: false,
		system: false,
		encrypted: false,
	});
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);

	const createMutation = useMutation({
		mutationFn: () => settingsApi.create(body),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["settings"] });
			toast.success("Setting created");
			navigate(`/settings/${encodeURIComponent(fresh.id)}/edit`);
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Create failed");
			}
		},
	});

	const set = (patch: Partial<SettingCreateBody>) => setBody((prev) => ({ ...prev, ...patch }));

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Settings", to: "/developer/settings" },
					{ label: "Add" },
				]}
			/>

			<PageHead
				title="Add setting"
				sub="Define a new setting. The value-editor opens after creation."
				actions={
					<Link
						to="/developer/settings"
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
					createMutation.mutate();
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<TextField
						label="ID"
						value={body.id}
						onChange={(v) => set({ id: v })}
						hint="Stable storage key, cannot change later."
						error={fieldErrors.id}
						required
					/>
					<TextField
						label="Name"
						value={body.name ?? ""}
						onChange={(v) => set({ name: v })}
						error={fieldErrors.name}
					/>
				</div>

				<TextField
					label="Description"
					value={body.description ?? ""}
					onChange={(v) => set({ description: v })}
				/>

				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<SelectField
						label="Field type"
						value={body.type ?? "text"}
						onChange={(v) => set({ type: v })}
						options={[
							{ value: "text", label: "Text" },
							{ value: "textarea", label: "Textarea" },
							{ value: "html", label: "Rich text (HTML)" },
							{ value: "checkbox", label: "Checkbox" },
							{ value: "list", label: "List (select)" },
							{ value: "image", label: "Image" },
							{ value: "upload", label: "File upload" },
							{ value: "video", label: "Video" },
							{ value: "matrix", label: "Matrix (repeating)" },
						]}
					/>
					<div />
				</div>

				<div className="grid grid-cols-1 gap-3 rounded-md border border-border bg-surface-2 p-3 md:grid-cols-3">
					<label className="flex items-center gap-2 text-[12.5px] text-text-2">
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={!!body.encrypted}
							onChange={(e) => set({ encrypted: e.target.checked })}
						/>
						Encrypted at rest
					</label>
					<label className="flex items-center gap-2 text-[12.5px] text-text-2">
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={!!body.locked}
							onChange={(e) => set({ locked: e.target.checked })}
						/>
						Locked (cannot delete)
					</label>
					<label className="flex items-center gap-2 text-[12.5px] text-text-2">
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={!!body.system}
							onChange={(e) => set({ system: e.target.checked })}
						/>
						System
					</label>
				</div>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Link
						to="/developer/settings"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2 hover:bg-hover"
					>
						Cancel
					</Link>
					<button
						type="submit"
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
						disabled={createMutation.isPending}
					>
						<Save size={13} />
						{createMutation.isPending ? "Creating…" : "Create setting"}
					</button>
				</div>
			</form>
		</div>
	);
};
