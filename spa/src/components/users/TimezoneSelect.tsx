import { useMemo } from "react";

import { Select } from "@/components/ui/Select";

interface TimezoneSelectProps {
	value: string;
	onChange: (next: string) => void;
	id?: string;
	className?: string;
}

/**
 * <select> of IANA timezones, grouped by continent so the option list is
 * navigable. Mirrors the layout PHP's `DateTimeZone::listIdentifiers()` +
 * optgroup chunking produces. Browsers without `Intl.supportedValuesOf`
 * fall back to a short curated list. Renders through the shared {@link Select}
 * primitive so it lines up with the rest of the admin's form controls.
 */
export const TimezoneSelect = ({ value, onChange, id, className }: TimezoneSelectProps) => {
	const grouped = useMemo(() => groupZones(getZones()), []);
	const browserDefault = Intl.DateTimeFormat().resolvedOptions().timeZone;

	return (
		<Select
			id={id}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			className={className}
		>
			<option value="">Default ({browserDefault})</option>

			{grouped.map(({ continent, zones }) => (
				<optgroup key={continent} label={continent}>
					{zones.map((tz) => (
						<option key={tz.value} value={tz.value}>
							{tz.label}
						</option>
					))}
				</optgroup>
			))}
		</Select>
	);
};

interface ZoneEntry {
	value: string;
	label: string;
}

const groupZones = (zones: string[]): Array<{ continent: string; zones: ZoneEntry[] }> => {
	const buckets = new Map<string, ZoneEntry[]>();

	for (const tz of zones) {
		const parts = tz.split("/");
		const continent = parts[0] ?? "Other";
		const city = parts[1] ?? "UTC";
		const locality = parts[2];

		const label = locality
			? `${city.replaceAll("_", " ")} – ${locality.replaceAll("_", " ")}`
			: city.replaceAll("_", " ");

		const list = buckets.get(continent) ?? [];
		list.push({ value: tz, label });
		buckets.set(continent, list);
	}

	const ordered: Array<{ continent: string; zones: ZoneEntry[] }> = [];

	for (const [continent, list] of [...buckets.entries()].sort((a, b) =>
		a[0].localeCompare(b[0])
	)) {
		list.sort((a, b) => a.label.localeCompare(b.label));
		ordered.push({ continent, zones: list });
	}

	return ordered;
};

const FALLBACK_ZONES = [
	"UTC",
	"America/New_York",
	"America/Chicago",
	"America/Denver",
	"America/Los_Angeles",
	"Europe/London",
	"Europe/Berlin",
	"Europe/Paris",
	"Asia/Tokyo",
	"Asia/Shanghai",
	"Asia/Singapore",
	"Australia/Sydney",
];

const getZones = (): string[] => {
	const intlAny = Intl as unknown as { supportedValuesOf?: (key: string) => string[] };

	if (typeof intlAny.supportedValuesOf === "function") {
		try {
			return intlAny.supportedValuesOf("timeZone");
		} catch {
			return FALLBACK_ZONES;
		}
	}

	return FALLBACK_ZONES;
};
