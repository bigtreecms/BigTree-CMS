import { useMemo, useState, type FormEvent } from "react";

import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Select";
import { TextInput } from "@/components/ui/TextInput";
import type { ModuleReportFilter, ModuleReportFilterOption } from "@/api/endpoints/modules";

/**
 * Filter form for a saved report. Builds inputs from the report's `filters`
 * config:
 *
 *   - `search`     → `<input type="text">`
 *   - `dropdown`   → `<select>` populated from filter_options (resolved server-side)
 *   - `boolean`    → `<select>` with Both / Yes / No
 *   - `date-range` → start + end date inputs (`{ start, end }`)
 *
 * Sort controls are always present so the renderer can pass the chosen column
 * + direction to `getReportResults` on the server.
 *
 * The form holds local state and emits the full payload via `onSubmit`. The
 * parent (ReportRenderer) owns the mutation and result rendering.
 */

interface FilterEntry {
	column: string;
	filter: ModuleReportFilter;
}

export interface ReportFilterFormValues {
	filters: Record<string, unknown>;
	sortField: string;
	sortOrder: "ASC" | "DESC";
}

interface SortField {
	key: string;
	label: string;
}

interface ReportFilterFormProps {
	filters: FilterEntry[];
	filterOptions: Record<string, ModuleReportFilterOption[]>;
	sortFields: SortField[];
	reportType: "view" | "csv";
	submitting?: boolean;
	onSubmit: (values: ReportFilterFormValues) => void;
}

export const ReportFilterForm = ({
	filters,
	filterOptions,
	sortFields,
	reportType,
	submitting,
	onSubmit,
}: ReportFilterFormProps) => {
	const initial = useMemo<Record<string, unknown>>(() => {
		const seed: Record<string, unknown> = {};

		for (const { column, filter } of filters) {
			if (filter.type === "date-range") {
				seed[column] = { start: "", end: "" };
			} else if (filter.type === "boolean") {
				seed[column] = "Both";
			} else {
				seed[column] = "";
			}
		}

		return seed;
	}, [filters]);

	const [values, setValues] = useState<Record<string, unknown>>(initial);
	const [sortField, setSortField] = useState<string>(() => sortFields[0]?.key ?? "id");
	const [sortOrder, setSortOrder] = useState<"ASC" | "DESC">("ASC");

	const setValue = (column: string, next: unknown) => {
		setValues((prev) => ({ ...prev, [column]: next }));
	};

	const setRange = (column: string, key: "start" | "end", next: string) => {
		setValues((prev) => {
			const current =
				typeof prev[column] === "object" && prev[column] !== null
					? (prev[column] as { start?: string; end?: string })
					: { start: "", end: "" };

			return {
				...prev,
				[column]: { ...current, [key]: next },
			};
		});
	};

	const handleSubmit = (e: FormEvent) => {
		e.preventDefault();
		onSubmit({ filters: values, sortField, sortOrder });
	};

	return (
		<Card as="form" padding="sm" onSubmit={handleSubmit}>
			<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
				{filters.map(({ column, filter }) => (
					<div key={column} className="flex flex-col gap-1.5">
						<label className="text-[12px] font-medium text-text-2">
							{filter.title}
						</label>
						<FilterInput
							column={column}
							filter={filter}
							value={values[column]}
							options={filterOptions[column] ?? []}
							onChange={(next) => setValue(column, next)}
							onRangeChange={(key, next) => setRange(column, key, next)}
						/>
					</div>
				))}

				<div className="flex flex-col gap-1.5">
					<label
						htmlFor="report-sort-field"
						className="text-[12px] font-medium text-text-2"
					>
						Sort By
					</label>
					<Select
						id="report-sort-field"
						compact
						value={sortField}
						onChange={(e) => setSortField(e.target.value)}
					>
						{sortFields.map((f) => (
							<option key={f.key} value={f.key}>
								{f.label}
							</option>
						))}
					</Select>
				</div>

				<div className="flex flex-col gap-1.5">
					<label
						htmlFor="report-sort-order"
						className="text-[12px] font-medium text-text-2"
					>
						Sort Order
					</label>
					<Select
						id="report-sort-order"
						compact
						value={sortOrder}
						onChange={(e) => setSortOrder(e.target.value as "ASC" | "DESC")}
					>
						<option value="ASC">Ascending</option>
						<option value="DESC">Descending</option>
					</Select>
				</div>
			</div>

			<div className="mt-4 flex items-center justify-end">
				<Button
					type="submit"
					variant="primary"
					loading={submitting}
					loadingLabel="Running…"
				>
					{reportType === "csv" ? "Export CSV" : "Run Report"}
				</Button>
			</div>
		</Card>
	);
};

interface FilterInputProps {
	column: string;
	filter: ModuleReportFilter;
	value: unknown;
	options: ModuleReportFilterOption[];
	onChange: (next: unknown) => void;
	onRangeChange: (key: "start" | "end", next: string) => void;
}

const FilterInput = ({
	column,
	filter,
	value,
	options,
	onChange,
	onRangeChange,
}: FilterInputProps) => {
	if (filter.type === "search") {
		return (
			<TextInput
				compact
				aria-label={`${column} search query`}
				placeholder="Search query"
				value={typeof value === "string" ? value : ""}
				onChange={(e) => onChange(e.target.value)}
			/>
		);
	}

	if (filter.type === "dropdown") {
		return (
			<Select
				compact
				value={typeof value === "string" ? value : ""}
				onChange={(e) => onChange(e.target.value)}
			>
				<option value="">— Any —</option>
				{options.map((opt) => (
					<option key={`${column}:${String(opt.value)}`} value={String(opt.value ?? "")}>
						{opt.label}
					</option>
				))}
			</Select>
		);
	}

	if (filter.type === "boolean") {
		return (
			<Select
				compact
				value={typeof value === "string" ? value : "Both"}
				onChange={(e) => onChange(e.target.value)}
			>
				<option value="Both">Both</option>
				<option value="Yes">Yes</option>
				<option value="No">No</option>
			</Select>
		);
	}

	if (filter.type === "date-range") {
		const range =
			typeof value === "object" && value !== null
				? (value as { start?: string; end?: string })
				: { start: "", end: "" };

		return (
			<div className="grid grid-cols-2 gap-2">
				<div className="flex flex-col gap-1">
					<TextInput
						compact
						type="date"
						aria-label={`${column} start date`}
						value={range.start ?? ""}
						onChange={(e) => onRangeChange("start", e.target.value)}
					/>
					<span className="text-[11px] text-text-3">Start</span>
				</div>
				<div className="flex flex-col gap-1">
					<TextInput
						compact
						type="date"
						aria-label={`${column} end date`}
						value={range.end ?? ""}
						onChange={(e) => onRangeChange("end", e.target.value)}
					/>
					<span className="text-[11px] text-text-3">End</span>
				</div>
			</div>
		);
	}

	return (
		<EmptyState dashed size="sm">
			Unsupported filter type: {filter.type}
		</EmptyState>
	);
};
