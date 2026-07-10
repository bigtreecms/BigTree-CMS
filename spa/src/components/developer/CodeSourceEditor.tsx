import { useState, type KeyboardEvent } from "react";

import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { SectionLabel } from "@/components/ui/SectionLabel";

export interface HostApiReferenceRow {
	name: string;
	desc: string;
}

interface CodeSourceEditorProps {
	/** Section heading above the editor; doubles as the textarea's accessible name. */
	label: string;
	value: string;
	onChange: (next: string) => void;
	/** Rows rendered in the collapsible "Host API reference" table. */
	reference: HostApiReferenceRow[];
	/** Overrides `label` as the textarea's accessible name. */
	ariaLabel?: string;
}

/**
 * The shared authoring surface for in-context module code: a labeled monospace
 * textarea whose Tab key inserts a tab character (instead of moving focus), plus
 * the collapsible host-API reference beneath it. Composed by the field-type
 * {@link ModuleSourceEditor} and the action {@link ActionModuleEditor}, which
 * each add their own extras (settings-schema builder, live preview).
 *
 * The textarea keeps its own class string rather than {@link inputClassFor}: it
 * sits on `bg-surface-2` at a code-block density (`p-3`, `leading-relaxed`) that
 * no input tier covers.
 */
export const CodeSourceEditor = ({
	label,
	value,
	onChange,
	reference,
	ariaLabel,
}: CodeSourceEditorProps) => {
	const [showApi, setShowApi] = useState(false);

	// Tab inserts a tab character instead of moving focus, so code stays editable.
	const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
		if (event.key !== "Tab") {
			return;
		}

		event.preventDefault();

		const target = event.currentTarget;
		const { selectionStart, selectionEnd } = target;
		const next = value.slice(0, selectionStart) + "\t" + value.slice(selectionEnd);

		onChange(next);
		requestAnimationFrame(() => {
			target.selectionStart = target.selectionEnd = selectionStart + 1;
		});
	};

	return (
		<>
			<div>
				<SectionLabel className="mb-2">{label}</SectionLabel>
				<textarea
					value={value}
					aria-label={ariaLabel ?? label}
					onChange={(e) => onChange(e.target.value)}
					onKeyDown={onKeyDown}
					spellCheck={false}
					autoCapitalize="off"
					autoCorrect="off"
					rows={18}
					className="w-full rounded-md border border-border bg-surface-2 p-3 font-mono text-[12.5px] leading-relaxed text-text focus:outline-none focus:ring-1 focus:ring-accent-ring"
				/>
			</div>

			<div className="rounded-md border border-border bg-surface">
				<DisclosureToggle
					open={showApi}
					onToggle={() => setShowApi((v) => !v)}
					size={14}
					className="w-full gap-1.5 px-3 py-2 text-[12px] font-medium text-text-2"
					label="Host API reference"
				/>
				{showApi && (
					<dl className="grid grid-cols-1 gap-x-4 gap-y-1.5 border-t border-border p-3 sm:grid-cols-[auto_1fr]">
						{reference.map((row) => (
							<div key={row.name} className="contents">
								<dt className="font-mono text-[11.5px] text-accent">{row.name}</dt>
								<dd className="text-[12px] text-text-2">{row.desc}</dd>
							</div>
						))}
					</dl>
				)}
			</div>
		</>
	);
};
