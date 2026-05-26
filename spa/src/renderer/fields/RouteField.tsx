import { INPUT_CLASS, settingsOf, type FieldComponentProps } from "./types";

/**
 * URL-slug field. Today this is a text input with monospace styling and a
 * leading `/` hint. The legacy admin auto-fills from another column when
 * `source` is configured; we leave that wiring to the form runtime so it can
 * read sibling form values — for now the input is a plain text input.
 */
export const RouteField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field);
	const source = (settings.source as string) || "";

	return (
		<div className="flex items-stretch gap-0 overflow-hidden rounded-md border border-border bg-surface focus-within:ring-1 focus-within:ring-accent-ring">
			<span className="grid place-items-center border-r border-border bg-surface-2 px-2 font-mono text-[12.5px] text-text-3">
				/
			</span>
			<input
				type="text"
				className={`${INPUT_CLASS} flex-1 rounded-none border-0 font-mono text-[12.5px] focus:ring-0`}
				value={typeof value === "string" ? value : value == null ? "" : String(value)}
				placeholder={source ? `auto-from "${source}"` : "url-slug"}
				disabled={disabled}
				onChange={(event) => onChange(event.target.value)}
			/>
		</div>
	);
};
