import { MapPin } from "lucide-react";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Geocoding (informational placeholder).
 *
 * The legacy `core/admin/field-types/geocoding/{process,settings}.php` has no
 * `draw.php` — it's a phantom field that runs server-side at submit time. It
 * reads `settings.fields` (source columns from the same form), concatenates
 * their values into an address string, calls `BigTreeGeocoding::geocode()`,
 * and writes the result into `latitude` / `longitude` columns on the entry
 * row. The field itself never stores a value of its own.
 *
 * AutoModuleService runs the geocoding processor on save (see
 * applyGeocoding): it concatenates the configured source columns, geocodes the
 * address, and writes `latitude` / `longitude` onto the entry. This component
 * is a read-only panel that surfaces the configured source fields; the field
 * has no value of its own.
 */
interface GeocodingFieldSettings {
	fields?: string[] | string;
}

const normalizeFields = (settings: GeocodingFieldSettings): string[] => {
	const raw = settings.fields;

	if (Array.isArray(raw)) {
		return raw.map((v) => String(v).trim()).filter((v) => v.length > 0);
	}

	if (typeof raw === "string" && raw.length > 0) {
		return raw
			.split(",")
			.map((v) => v.trim())
			.filter((v) => v.length > 0);
	}

	return [];
};

export const GeocodingField = ({ field }: FieldComponentProps) => {
	const settings = settingsOf(field) as GeocodingFieldSettings;
	const sourceFields = normalizeFields(settings);

	return (
		<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
			<div className="mb-1.5 flex items-center gap-1.5 text-text-2">
				<MapPin size={13} />
				<span className="font-medium">Geocoding</span>
			</div>

			{sourceFields.length === 0 ? (
				<p>No source fields configured. (Set them in the form designer.)</p>
			) : (
				<p>
					Computes <code className="rounded bg-surface px-1 text-text-2">latitude</code>{" "}
					and <code className="rounded bg-surface px-1 text-text-2">longitude</code> from{" "}
					{sourceFields.map((name, index) => (
						<span key={name}>
							<code className="rounded bg-surface px-1 text-text-2">{name}</code>
							{index < sourceFields.length - 1 ? ", " : ""}
						</span>
					))}{" "}
					on save.
				</p>
			)}
		</div>
	);
};
