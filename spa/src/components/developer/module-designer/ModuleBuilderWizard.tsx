import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Save, Trash } from "lucide-react";

import { Alert } from "@/components/ui/Alert";
import { FieldGrid } from "@/components/ui/FieldGrid";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import {
	modulesApi,
	type ModuleScaffoldBody,
	type ModuleScaffoldField,
} from "@/api/endpoints/modules";
import { fieldTypesApi, fieldTypesForUseCase } from "@/api/endpoints/field-types";

import { ApiError } from "@/types/api";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { toast } from "@/lib/toast";
import { validateRequired } from "@/lib/formValidation";

import { IconPicker } from "@/components/developer/IconPicker";

import { CheckboxInput, SelectInput, TextInput } from "./inputs";

/**
 * "Build the table for me" wizard — the auto-build path of the Add Module flow.
 * Collects module metadata, a NEW table name, and a list of fields (title +
 * type), then POSTs /modules/scaffold which creates the table + columns, the
 * module, an add/edit form, and a landing view in one shot. On success it drops
 * the developer into the freshly-built module's designer to refine field
 * settings / views. Mirrors the legacy `modules/designer/*` flow.
 */

interface FieldRow {
	uid: number;
	title: string;
	type: string;
}

let rowCounter = 0;
const newRow = (): FieldRow => ({ uid: ++rowCounter, title: "", type: "text" });

const VIEW_TYPE_OPTIONS = [
	{ value: "searchable", label: "Searchable list" },
	{ value: "draggable", label: "Draggable (orderable) list" },
];

export const ModuleBuilderWizard = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [name, setName] = useState("");
	const [table, setTable] = useState("");
	const [route, setRoute] = useState("");
	const [icon, setIcon] = useState("");
	const [className, setClassName] = useState("");
	const [group, setGroup] = useState("");
	const [itemTitle, setItemTitle] = useState("");
	const [viewTitle, setViewTitle] = useState("");
	const [viewType, setViewType] = useState<"searchable" | "draggable">("searchable");
	const [actions, setActions] = useState({ approve: false, feature: false, archive: false });
	const [rows, setRows] = useState<FieldRow[]>(() => [newRow()]);

	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	useScrollToFirstError(fieldErrors);
	const [generalError, setGeneralError] = useState<string | null>(null);

	const groupsQ = useQuery({
		queryKey: ["module-groups", "list"],
		queryFn: () => modulesApi.listGroups(),
	});

	const typesQ = useQuery({
		queryKey: ["field-types", "list"],
		queryFn: () => fieldTypesApi.list(),
	});

	const typeOptions = fieldTypesForUseCase(typesQ.data, "modules").map((t) => ({
		value: t.id,
		label: t.name,
	}));

	const scaffoldMutation = useMutation({
		mutationFn: (body: ModuleScaffoldBody) => modulesApi.scaffold(body),
		onSuccess: (mod) => {
			queryClient.invalidateQueries({ queryKey: ["modules"] });
			// A new table now exists — refresh the table pickers that cache /db/tables.
			queryClient.invalidateQueries({ queryKey: ["db", "tables"] });
			toast.success("Module built", {
				description: `Created the “${mod.table}” table, form, and view.`,
			});
			navigate(`/developer/modules/${encodeURIComponent(mod.id)}`, { replace: true });
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Build failed");
			}
		},
	});

	const setRow = (uid: number, patch: Partial<FieldRow>) => {
		setRows((prev) => prev.map((r) => (r.uid === uid ? { ...r, ...patch } : r)));
	};

	const addRow = () => setRows((prev) => [...prev, newRow()]);

	const removeRow = (uid: number) => {
		setRows((prev) => (prev.length > 1 ? prev.filter((r) => r.uid !== uid) : prev));
	};

	const handleSubmit = (event: React.FormEvent) => {
		event.preventDefault();

		if (scaffoldMutation.isPending) {
			return;
		}

		const errors = validateRequired([
			{ field: "name", label: "Name", value: name },
			{ field: "table", label: "Table name", value: table },
		]);

		if (!errors.table && table.trim() && !/^[A-Za-z0-9_]+$/.test(table.trim())) {
			errors.table = "Only letters, numbers, and underscores.";
		}

		const cleanFields: ModuleScaffoldField[] = rows
			.map((r) => ({ title: r.title.trim(), type: r.type }))
			.filter((r) => r.title.length > 0);

		if (cleanFields.length === 0) {
			errors.fields = "Add at least one field with a title.";
		}

		if (Object.keys(errors).length > 0) {
			setFieldErrors(errors);
			setGeneralError("Please fix the highlighted fields.");

			return;
		}

		setGeneralError(null);
		setFieldErrors({});
		scaffoldMutation.mutate({
			name: name.trim(),
			table: table.trim(),
			fields: cleanFields,
			group: group || null,
			route: route.trim() || undefined,
			icon: icon || undefined,
			class: className.trim() || undefined,
			item_title: itemTitle.trim() || undefined,
			view_title: viewTitle.trim() || undefined,
			view_type: viewType,
			actions,
		});
	};

	const groupOptions = [
		{ value: "", label: "— No group —" },
		...(groupsQ.data ?? []).map((g) => ({ value: g.id, label: g.name })),
	];

	return (
		<form onSubmit={handleSubmit} className="space-y-4">
			{generalError && <Alert tone="danger">{generalError}</Alert>}

			<Card className="space-y-4 p-4">
				<FieldGrid>
					<TextInput
						label="Name"
						value={name}
						onChange={setName}
						error={fieldErrors.name}
						hint="For example, News."
						required
					/>
					<SelectInput
						label="Group"
						value={group}
						onChange={setGroup}
						options={groupOptions}
					/>
					<TextInput
						label="Table name"
						value={table}
						onChange={setTable}
						error={fieldErrors.table}
						hint="A new MySQL table to create. Letters, numbers, underscores."
						required
						mono
					/>
					<TextInput
						label="Handler class"
						value={className}
						onChange={setClassName}
						error={fieldErrors.class}
						hint="Optional custom module class."
						mono
					/>
					<TextInput
						label="Route"
						value={route}
						onChange={setRoute}
						hint="URL slug. Auto-generated from the name when left blank."
						error={fieldErrors.route}
						mono
					/>
				</FieldGrid>

				<IconPicker
					value={icon}
					onChange={setIcon}
					hint="Shown beside the module in the admin navigation."
				/>
			</Card>

			<Card className="space-y-3 p-4">
				<div className="flex items-center justify-between">
					<span className="text-[13px] font-semibold text-text">Fields</span>
					<button
						type="button"
						onClick={addRow}
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1.5 text-[12px] hover:bg-hover"
					>
						<Plus size={13} />
						Add field
					</button>
				</div>

				<p className="text-[11.5px] text-text-3">
					Each field becomes a column on the new table and a field on the add/edit form.
					You can refine settings after the module is built.
				</p>

				{fieldErrors.fields && (
					<span data-field-error className="block text-[11.5px] text-danger">
						{fieldErrors.fields}
					</span>
				)}

				<ul className="space-y-2">
					{rows.map((r) => (
						<li key={r.uid} className="flex items-end gap-2">
							<div className="flex-1">
								<TextInput
									label="Title"
									value={r.title}
									onChange={(v) => setRow(r.uid, { title: v })}
								/>
							</div>
							<div className="w-48">
								<SelectInput
									label="Type"
									value={r.type}
									onChange={(v) => setRow(r.uid, { type: v })}
									options={
										typeOptions.length > 0
											? typeOptions
											: [{ value: "text", label: "Text" }]
									}
								/>
							</div>
							<button
								type="button"
								onClick={() => removeRow(r.uid)}
								disabled={rows.length === 1}
								className="mb-1 rounded p-2 text-text-3 hover:bg-hover hover:text-danger disabled:opacity-30"
								title="Remove field"
								aria-label="Remove field"
							>
								<Trash size={14} />
							</button>
						</li>
					))}
				</ul>
			</Card>

			<Card className="space-y-4 p-4">
				<FieldGrid>
					<SelectInput
						label="Landing view type"
						value={viewType}
						onChange={(v) =>
							setViewType(v === "draggable" ? "draggable" : "searchable")
						}
						options={VIEW_TYPE_OPTIONS}
					/>
					<TextInput
						label="Item title"
						value={itemTitle}
						onChange={setItemTitle}
						hint="Singular, e.g. Article. Derived from the name when blank."
					/>
					<TextInput
						label="View title"
						value={viewTitle}
						onChange={setViewTitle}
						hint="Plural, e.g. Articles. Derived from the name when blank."
					/>
				</FieldGrid>

				<div className="space-y-2">
					<span className="text-[12px] font-medium text-text-2">Extra actions</span>
					<p className="text-[11px] text-text-3">
						Each adds a status column to the table and an action to the landing view.
					</p>
					<CheckboxInput
						label="Approvable (adds an approved column)"
						checked={actions.approve}
						onChange={(v) => setActions((p) => ({ ...p, approve: v }))}
					/>
					<CheckboxInput
						label="Featurable (adds a featured column)"
						checked={actions.feature}
						onChange={(v) => setActions((p) => ({ ...p, feature: v }))}
					/>
					<CheckboxInput
						label="Archivable (adds an archived column)"
						checked={actions.archive}
						onChange={(v) => setActions((p) => ({ ...p, archive: v }))}
					/>
				</div>
			</Card>

			<div className="flex justify-end">
				<Button
					variant="primary"
					type="submit"
					icon={<Save size={13} />}
					loading={scaffoldMutation.isPending}
					loadingLabel="Building…"
				>
					Build module
				</Button>
			</div>
		</form>
	);
};
