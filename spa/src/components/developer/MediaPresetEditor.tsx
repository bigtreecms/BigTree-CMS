import { Plus, Trash } from "lucide-react";

import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { TextInput } from "@/components/ui/TextInput";
import { useListEditor } from "@/hooks/useListEditor";
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
	/** Legacy stores "on" for grayscale, "" otherwise. */
	grayscale?: string;
	height?: string;
	prefix?: string;
	width?: string;
}

export interface CropRow extends SizeRow {
	center_crops?: SizeRow[];
	thumbs?: SizeRow[];
}

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
	onChange: (rows: SizeRow[]) => void;
	rows: SizeRow[];
}

const SizeRowsEditor = ({ label, rows, onChange }: SizeRowsEditorProps) => {
	const { update, remove, add } = useListEditor<SizeRow>(rows, onChange);

	return (
		<div>
			<div className="mb-1.5 flex items-center justify-between">
				<SectionLabel size="sm">{label}</SectionLabel>
				<button
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-0.5 text-[11.5px] text-text hover:bg-hover"
					type="button"
					onClick={() => add({ prefix: "", width: "", height: "", grayscale: "" })}
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
						<div className="flex items-center gap-1.5" key={i}>
							<TextInput
								compact
								aria-label="prefix"
								className="w-full"
								placeholder="prefix"
								value={row.prefix ?? ""}
								onChange={(e) => update(i, { prefix: e.target.value })}
							/>
							<TextInput
								compact
								aria-label="width"
								className="w-full"
								placeholder="width"
								value={row.width ?? ""}
								onChange={(e) => update(i, { width: e.target.value })}
							/>
							<TextInput
								compact
								aria-label="height"
								className="w-full"
								placeholder="height"
								value={row.height ?? ""}
								onChange={(e) => update(i, { height: e.target.value })}
							/>
							<Checkbox
								checked={row.grayscale === "on"}
								className="whitespace-nowrap"
								label="Gray"
								size="sm"
								onChange={(checked) =>
									update(i, { grayscale: checked ? "on" : "" })
								}
							/>
							<IconButton
								className="rounded-md border border-border bg-surface"
								label="Remove"
								tone="danger"
								onClick={() => remove(i)}
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
	const { update, remove, add } = useListEditor<CropRow>(crops, onChange);

	return (
		<div>
			<div className="mb-1.5 flex items-center justify-between">
				<SectionLabel size="sm">Crops</SectionLabel>
				<button
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-0.5 text-[11.5px] text-text hover:bg-hover"
					type="button"
					onClick={() =>
						add({
							prefix: "",
							width: "",
							height: "",
							grayscale: "",
							thumbs: [],
							center_crops: [],
						})
					}
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
						<div className="rounded-md border border-border bg-surface-2 p-2.5" key={i}>
							<div className="flex items-center gap-1.5">
								<TextInput
									compact
									aria-label="prefix"
									className="w-full"
									placeholder="prefix"
									value={crop.prefix ?? ""}
									onChange={(e) => update(i, { prefix: e.target.value })}
								/>
								<TextInput
									compact
									aria-label="width"
									className="w-full"
									placeholder="width"
									value={crop.width ?? ""}
									onChange={(e) => update(i, { width: e.target.value })}
								/>
								<TextInput
									compact
									aria-label="height"
									className="w-full"
									placeholder="height"
									value={crop.height ?? ""}
									onChange={(e) => update(i, { height: e.target.value })}
								/>
								<Checkbox
									checked={crop.grayscale === "on"}
									className="whitespace-nowrap"
									label="Gray"
									size="sm"
									onChange={(checked) =>
										update(i, { grayscale: checked ? "on" : "" })
									}
								/>
								<IconButton
									className="rounded-md border border-border bg-surface"
									label="Remove crop"
									tone="danger"
									onClick={() => remove(i)}
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
	onChange: (patch: Partial<MediaPreset>) => void;
	preset: MediaPreset;
}

export const MediaPresetEditor = ({ preset, onChange }: MediaPresetEditorProps) => {
	const str = (key: string) => (preset[key] as string) ?? "";

	return (
		<div className="space-y-4">
			<div className="grid grid-cols-1 gap-3 md:grid-cols-3">
				<Field label="Min width (px)">
					<TextInput
						compact
						className="w-full"
						min={0}
						type="number"
						value={str("min_width")}
						onChange={(e) => onChange({ min_width: e.target.value })}
					/>
				</Field>
				<Field label="Min height (px)">
					<TextInput
						compact
						className="w-full"
						min={0}
						type="number"
						value={str("min_height")}
						onChange={(e) => onChange({ min_height: e.target.value })}
					/>
				</Field>
				<Field label="Preview prefix">
					<TextInput
						compact
						className="w-full"
						value={str("preview_prefix")}
						onChange={(e) => onChange({ preview_prefix: e.target.value })}
					/>
				</Field>
			</div>

			<Checkbox
				checked={preset.retina === "on"}
				label="Create hi-resolution (retina) images when available"
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
