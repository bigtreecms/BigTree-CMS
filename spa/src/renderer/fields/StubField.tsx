import type { FieldComponentProps } from "./types";

interface StubFieldProps extends FieldComponentProps {
	/** Override the default "isn't editable yet" banner with a specific reason. */
	note?: string;
}

/**
 * Fallback for field types whose dedicated renderer hasn't shipped yet. We
 * show the raw value in a monospace block plus a small banner naming the
 * type so users (and developers) know the data is being round-tripped but
 * not edited. Submitting the form preserves the value via the renderer's
 * controlled state. Callers can pass `note` to explain a specific failure
 * (e.g. a legacy field the server bridge couldn't render).
 */
export const StubField = ({ field, value, note }: StubFieldProps) => {
	const display =
		value == null
			? ""
			: typeof value === "string"
				? value
				: (() => {
						try {
							return JSON.stringify(value, null, 2);
						} catch {
							return String(value);
						}
					})();

	return (
		<div className="rounded-md border border-dashed border-border bg-surface-2 p-3 text-[12px] text-text-3">
			<div className="mb-1.5 flex items-center gap-2">
				<span className="rounded bg-accent-soft px-1.5 py-0.5 text-[10.5px] font-semibold uppercase tracking-wide text-accent">
					{field.type}
				</span>
				<span>
					{note ?? "This field type isn't editable in the SPA yet — value is preserved."}
				</span>
			</div>
			{display && (
				<pre className="max-h-32 overflow-auto whitespace-pre-wrap break-words font-mono text-[11.5px] text-text-2">
					{display}
				</pre>
			)}
		</div>
	);
};
