import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";
import { InputSchemaBuilder } from "@/components/developer/InputSchemaBuilder";

import {
	fieldTypesApi,
	type FieldTypeCreateBody,
	type FieldUseCase,
	type InputDescriptor,
} from "@/api/endpoints/field-types";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useReturnTo } from "@/hooks/useReturnTo";
import { validateRequired } from "@/lib/formValidation";

import { TextField } from "./TemplateEdit";

/**
 * The detail endpoint returns a field type's raw record, where `use_cases` may be
 * either the stored associative map ({ templates: "on" }) or — for older flat
 * payloads — a list of slugs. Normalize both to the list the checkboxes use.
 */
const toUseCaseList = (value: unknown): string[] => {
	if (Array.isArray(value)) {
		return value.filter((v): v is string => typeof v === "string");
	}

	if (value && typeof value === "object") {
		return Object.entries(value as Record<string, unknown>)
			.filter(([, v]) => !!v)
			.map(([k]) => k);
	}

	return [];
};

const USE_CASES: Array<{ value: FieldUseCase; label: string }> = [
	{ value: "templates", label: "Templates" },
	{ value: "modules", label: "Modules" },
	{ value: "settings", label: "Settings" },
	{ value: "callouts", label: "Callouts" },
	{ value: "feeds", label: "Feeds" },
];

export const FieldTypeEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const isAdd = !idParam;
	const navigate = useNavigate();
	const returnTo = useReturnTo("/developer/field-types");
	const queryClient = useQueryClient();

	const detailQ = useQuery({
		queryKey: ["field-types", "detail", idParam],
		queryFn: () => fieldTypesApi.get(idParam as string),
		enabled: !isAdd,
	});

	const [body, setBody] = useState<FieldTypeCreateBody>(() =>
		isAdd ? { id: "", name: "", use_cases: [], self_draw: false, input_schema: [] } : { id: "" }
	);
	const [mode, setMode] = useState<"declarative" | "server">("declarative");
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);
	const [generalError, setGeneralError] = useState<string | null>(null);

	useEffect(() => {
		if (!isAdd && detailQ.data) {
			const data = detailQ.data;
			const inputSchema = Array.isArray(data.input_schema)
				? (data.input_schema as InputDescriptor[])
				: [];

			setBody({
				id: data.id,
				name: data.name,
				use_cases: toUseCaseList(data.use_cases),
				self_draw: !!data.self_draw,
				input_schema: inputSchema,
			});
			setMode(
				data.render === "declarative" || inputSchema.length > 0 ? "declarative" : "server"
			);
		}
	}, [isAdd, detailQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: FieldTypeCreateBody) =>
			isAdd ? fieldTypesApi.create(next) : fieldTypesApi.update(idParam as string, next),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["field-types"] });
			toast.success(isAdd ? "Field type created" : "Field type saved");

			if (isAdd) {
				navigate(`/developer/field-types/${encodeURIComponent(fresh.id)}/edit`, {
					replace: true,
				});
			} else {
				navigate(returnTo);
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
		return <Navigate to="/developer/field-types" replace />;
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

	const set = (patch: Partial<FieldTypeCreateBody>) => setBody((prev) => ({ ...prev, ...patch }));
	const selectedUseCases = new Set(body.use_cases ?? []);

	const toggleUseCase = (v: string) => {
		const next = new Set(selectedUseCases);

		if (next.has(v)) {
			next.delete(v);
		} else {
			next.add(v);
		}

		set({ use_cases: Array.from(next) });
	};

	const title = isAdd ? "Add custom field type" : body.name || idParam || "Edit field type";

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Field types", to: "/developer/field-types" },
					{ label: isAdd ? "Add" : "Edit" },
				]}
			/>

			<PageHead
				title={title}
				sub="Compose a custom field type from built-in primitives (declarative), or register one that ships its own draw.php / process.php under custom/admin/field-types/{id}/."
				actions={
					<Link
						to="/developer/field-types"
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
						{ field: "id", label: "ID", value: body.id },
						{ field: "name", label: "Name", value: body.name },
					]);

					if (Object.keys(errors).length > 0) {
						setFieldErrors(errors);
						setGeneralError("Please fill in the required fields.");

						return;
					}

					setFieldErrors({});
					setGeneralError(null);
					const payload: FieldTypeCreateBody =
						mode === "declarative"
							? {
									...body,
									render: "declarative",
									value_type: "object",
									self_draw: false,
									input_schema: body.input_schema ?? [],
								}
							: { ...body, render: "server", input_schema: [] };

					saveMutation.mutate(payload);
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<TextField
						label="ID"
						value={body.id ?? ""}
						onChange={(v) => set({ id: v })}
						hint="Becomes the folder name under custom/admin/field-types/."
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
						Use cases
					</div>
					<div className="flex flex-wrap gap-3 rounded-md border border-border bg-surface-2 p-3">
						{USE_CASES.map((u) => (
							<label
								key={u.value}
								className="inline-flex cursor-pointer items-center gap-2 text-[12.5px] text-text-2"
							>
								<input
									type="checkbox"
									className="h-4 w-4 accent-accent"
									checked={selectedUseCases.has(u.value)}
									onChange={() => toggleUseCase(u.value)}
								/>
								{u.label}
							</label>
						))}
					</div>
				</div>

				<div>
					<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Rendering
					</div>
					<div className="flex flex-col gap-2 rounded-md border border-border bg-surface-2 p-3">
						<label className="flex items-start gap-2 text-[12.5px] text-text-2">
							<input
								type="radio"
								name="render-mode"
								className="mt-0.5 h-4 w-4 accent-accent"
								checked={mode === "declarative"}
								onChange={() => setMode("declarative")}
							/>
							<span>
								<span className="font-medium">Declarative</span> — compose this
								field from built-in primitives. Renders natively in the SPA.
							</span>
						</label>
						<label className="flex items-start gap-2 text-[12.5px] text-text-2">
							<input
								type="radio"
								name="render-mode"
								className="mt-0.5 h-4 w-4 accent-accent"
								checked={mode === "server"}
								onChange={() => setMode("server")}
							/>
							<span>
								<span className="font-medium">Server-rendered</span> — ships its own
								draw.php / process.php under custom/admin/field-types/
								{idParam || "{id}"}/.
							</span>
						</label>
					</div>
				</div>

				{mode === "declarative" ? (
					<div>
						<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Fields
						</div>
						<InputSchemaBuilder
							value={body.input_schema ?? []}
							onChange={(next) => set({ input_schema: next })}
						/>
					</div>
				) : (
					<label className="flex items-center gap-2 text-[12.5px] text-text-2">
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={!!body.self_draw}
							onChange={(e) => set({ self_draw: e.target.checked })}
						/>
						Self-drawing (the type renders its own outer wrapper, not just the input)
					</label>
				)}

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Link
						to="/developer/field-types"
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
						{saveMutation.isPending ? "Saving…" : isAdd ? "Create field type" : "Save"}
					</button>
				</div>
			</form>
		</div>
	);
};
