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
 * The new SPA API flow (BigTreeAutoModule::createItem via AutoModuleService)
 * does NOT invoke field processors — so geocoding currently does not fire on
 * save through the new path. This component is intentionally a read-only
 * panel that surfaces the configured source fields and is honest about the
 * "not wired up yet" status, rather than pretending to work.
 *
 * Follow-up to make this functional end-to-end: invoke
 * `BigTreeAdmin::processField` for geocoding fields in AutoModuleService
 * before passing data to createItem, OR run the geocode call client-side.
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

			<p className="mt-1.5 text-text-3/80">
				Note: the SPA's save path does not yet invoke server-side field processors — lat/lng
				won't update via this flow until that's wired up. Existing values are preserved.
			</p>
		</div>
	);
};
