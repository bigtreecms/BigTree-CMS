import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * URL-slug field. Today this is a text input with monospace styling and a
 * leading `/` hint. When left empty the route is generated server-side from the
 * configured `source` column(s) and made unique (see AutoModuleService::applyRoute),
 * mirroring the legacy admin's route process.php. The placeholder advertises that
 * auto-generation so the editor knows an empty value is intentional.
 */
export const RouteField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const rawSource = settings.source;
	const source = Array.isArray(rawSource)
		? rawSource.filter(Boolean).join('", "')
		: (rawSource as string) || "";

	return (
		<div className="flex items-stretch gap-0 overflow-hidden rounded-md border border-border bg-surface focus-within:ring-1 focus-within:ring-accent-ring">
			<span className="grid place-items-center border-r border-border bg-surface-2 px-2 font-mono text-[12.5px] text-text-3">
				/
			</span>
			<input
				type="text"
				className={`${INPUT_CLASS} flex-1 rounded-none border-0 font-mono text-[12.5px] focus:ring-0`}
				value={typeof value === "string" ? value : value == null ? "" : String(value)}
				placeholder={source ? `generate automatically from "${source}"` : "url-slug"}
				disabled={disabled}
				onChange={(event) => onChange(event.target.value)}
			/>
		</div>
	);
};
