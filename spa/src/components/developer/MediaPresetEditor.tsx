import { Plus, Trash } from "lucide-react";

import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { IconButton } from "@/components/ui/IconButton";
import type { MediaPreset } from "@/api/endpoints/configure";

/**
 * Native editor for a single media preset, replacing the legacy admin's crop
 * modal. A preset defines the image-derivative recipe BigTree runs at upload
 * time:
 *
 *   - `crops[]`         — fixed-size crops, each with their own nested
 *                         `thumbs[]` and `center_crops[]`.
 *   - `thumbs[]`        — proportional thumbnails of the original.
 *   - `center_crops[]`  — crops taken from the center of the original.
 *
 * Every size row is `{ prefix, width, height, grayscale }` with string values
 * to round-trip the legacy storage exactly (`grayscale` is "on" / ""). Widths
 * and heights are output sizes, not pixel selections, so no canvas is needed.
 */

export interface SizeRow {
	prefix?: string;
	width?: string;
	height?: string;
	/** Legacy stores "on" for grayscale, "" otherwise. */
	grayscale?: string;
}

export interface CropRow extends SizeRow {
	thumbs?: SizeRow[];
	center_crops?: SizeRow[];
}

const inputClass =
	"w-full rounded-md border border-border bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-accent focus:ring-1 focus:ring-accent-ring";

/** Legacy stored these as objects keyed by count; normalize to a dense array. */
const asRows = <T,>(value: unknown): T[] => {
	if (Array.isArray(value)) {
		return value.filter((v) => v && typeof v === "object") as T[];
	}

	if (value && typeof value === "object") {
		return Object.values(value as Record<string, T>).filter((v) => v && typeof v === "object");
	}

	return [];
};

interface SizeRowsEditorProps {
	label: string;
	rows: SizeRow[];
	onChange: (rows: SizeRow[]) => void;
}

const SizeRowsEditor = ({ label, rows, onChange }: SizeRowsEditorProps) => {
	const update = (index: number, patch: Partial<SizeRow>) =>
		onChange(rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

	const remove = (index: number) => onChange(rows.filter((_, i) => i !== index));

	const add = () => onChange([...rows, { prefix: "", width: "", height: "", grayscale: "" }]);

	return (
		<div>
			<div className="mb-1.5 flex items-center justify-between">
				<span className="text-[11.5px] font-semibold uppercase tracking-wider text-text-3">
					{label}
				</span>
				<button
					type="button"
					onClick={add}
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-0.5 text-[11.5px] text-text hover:bg-hover"
				>
					<Plus size={11} />
					Add
				</button>
			</div>

			{rows.length === 0 ? (
				<p className="rounded-md border border-dashed border-border bg-surface-2 px-2 py-1.5 text-[11.5px] text-text-3">
					None.
				</p>
			) : (
				<div className="space-y-1.5">
					{rows.map((row, i) => (
						<div key={i} className="flex items-center gap-1.5">
							<input
								className={inputClass}
								placeholder="prefix"
								aria-label="prefix"
								value={row.prefix ?? ""}
								onChange={(e) => update(i, { prefix: e.target.value })}
							/>
							<input
								className={inputClass}
								placeholder="width"
								aria-label="width"
								value={row.width ?? ""}
								onChange={(e) => update(i, { width: e.target.value })}
							/>
							<input
								className={inputClass}
								placeholder="height"
								aria-label="height"
								value={row.height ?? ""}
								onChange={(e) => update(i, { height: e.target.value })}
							/>
							<Checkbox
								size="sm"
								className="whitespace-nowrap"
								label="Gray"
								checked={row.grayscale === "on"}
								onChange={(checked) =>
									update(i, { grayscale: checked ? "on" : "" })
								}
							/>
							<IconButton
								tone="danger"
								onClick={() => remove(i)}
								className="rounded-md border border-border bg-surface"
								label="Remove"
							>
								<Trash size={12} />
							</IconButton>
						</div>
					))}
				</div>
			)}
		</div>
	);
};

interface CropsEditorProps {
	crops: CropRow[];
	onChange: (crops: CropRow[]) => void;
}

const CropsEditor = ({ crops, onChange }: CropsEditorProps) => {
	const update = (index: number, patch: Partial<CropRow>) =>
		onChange(crops.map((c, i) => (i === index ? { ...c, ...patch } : c)));

	const remove = (index: number) => onChange(crops.filter((_, i) => i !== index));

	const add = () =>
		onChange([
			...crops,
			{ prefix: "", width: "", height: "", grayscale: "", thumbs: [], center_crops: [] },
		]);

	return (
		<div>
			<div className="mb-1.5 flex items-center justify-between">
				<span className="text-[11.5px] font-semibold uppercase tracking-wider text-text-3">
					Crops
				</span>
				<button
					type="button"
					onClick={add}
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-0.5 text-[11.5px] text-text hover:bg-hover"
				>
					<Plus size={11} />
					Add crop
				</button>
			</div>

			{crops.length === 0 ? (
				<p className="rounded-md border border-dashed border-border bg-surface-2 px-2 py-1.5 text-[11.5px] text-text-3">
					No crops.
				</p>
			) : (
				<div className="space-y-2">
					{crops.map((crop, i) => (
						<div key={i} className="rounded-md border border-border bg-surface-2 p-2.5">
							<div className="flex items-center gap-1.5">
								<input
									className={inputClass}
									placeholder="prefix"
									aria-label="prefix"
									value={crop.prefix ?? ""}
									onChange={(e) => update(i, { prefix: e.target.value })}
								/>
								<input
									className={inputClass}
									placeholder="width"
									aria-label="width"
									value={crop.width ?? ""}
									onChange={(e) => update(i, { width: e.target.value })}
								/>
								<input
									className={inputClass}
									placeholder="height"
									aria-label="height"
									value={crop.height ?? ""}
									onChange={(e) => update(i, { height: e.target.value })}
								/>
								<Checkbox
									size="sm"
									className="whitespace-nowrap"
									label="Gray"
									checked={crop.grayscale === "on"}
									onChange={(checked) =>
										update(i, { grayscale: checked ? "on" : "" })
									}
								/>
								<IconButton
									tone="danger"
									onClick={() => remove(i)}
									className="rounded-md border border-border bg-surface"
									label="Remove crop"
								>
									<Trash size={12} />
								</IconButton>
							</div>

							<div className="mt-2.5 grid grid-cols-1 gap-2.5 border-t border-border pt-2.5 md:grid-cols-2">
								<SizeRowsEditor
									label="Thumbnails of crop"
									rows={asRows<SizeRow>(crop.thumbs)}
									onChange={(rows) => update(i, { thumbs: rows })}
								/>
								<SizeRowsEditor
									label="Center sub-crops"
									rows={asRows<SizeRow>(crop.center_crops)}
									onChange={(rows) => update(i, { center_crops: rows })}
								/>
							</div>
						</div>
					))}
				</div>
			)}
		</div>
	);
};

interface MediaPresetEditorProps {
	preset: MediaPreset;
	onChange: (patch: Partial<MediaPreset>) => void;
}

export const MediaPresetEditor = ({ preset, onChange }: MediaPresetEditorProps) => {
	const str = (key: string) => (preset[key] as string) ?? "";

	return (
		<div className="space-y-4">
			<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
				<Field label="Min width (px)">
					<input
						className={inputClass}
						type="number"
						min={0}
						value={str("min_width")}
						onChange={(e) => onChange({ min_width: e.target.value })}
					/>
				</Field>
				<Field label="Min height (px)">
					<input
						className={inputClass}
						type="number"
						min={0}
						value={str("min_height")}
						onChange={(e) => onChange({ min_height: e.target.value })}
					/>
				</Field>
				<Field label="Preview prefix">
					<input
						className={inputClass}
						value={str("preview_prefix")}
						onChange={(e) => onChange({ preview_prefix: e.target.value })}
					/>
				</Field>
			</div>

			<Checkbox
				label="Create hi-resolution (retina) images when available"
				checked={preset.retina === "on"}
				onChange={(checked) => onChange({ retina: checked ? "on" : "" })}
			/>

			<CropsEditor
				crops={asRows<CropRow>(preset.crops)}
				onChange={(crops) => onChange({ crops })}
			/>

			<SizeRowsEditor
				label="Thumbnails"
				rows={asRows<SizeRow>(preset.thumbs)}
				onChange={(thumbs) => onChange({ thumbs })}
			/>

			<SizeRowsEditor
				label="Center crops"
				rows={asRows<SizeRow>(preset.center_crops)}
				onChange={(center_crops) => onChange({ center_crops })}
			/>
		</div>
	);
};
