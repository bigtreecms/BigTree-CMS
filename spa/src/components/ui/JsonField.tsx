import type { ReactNode } from "react";

import { useJsonDraft } from "../../hooks/useJsonDraft";
import { Field, type FieldSize } from "./Field";
import { TextArea } from "./TextArea";

interface JsonFieldProps {
	/** Visible label. Omit for an unlabeled editor (supply `aria-label` on the textarea via a wrapper). */
	label?: ReactNode;
	value: unknown;
	onChange: (next: Record<string, unknown>) => void;
	hint?: string;
	rows?: number;
	/** Override the validation error shown when the value is not a JSON object. */
	invalidMessage?: string;
	size?: FieldSize;
}

/**
 * Labeled JSON object editor. Wraps {@link Field} + a mono {@link TextArea} around
 * {@link useJsonDraft} — commits on blur, surfaces parse / type errors inline.
 */
export const JsonField = ({
	label,
	value,
	onChange,
	hint,
	rows = 5,
	invalidMessage,
	size,
}: JsonFieldProps) => {
	const { draft, error, setDraft, commit } = useJsonDraft(value, onChange, invalidMessage);

	return (
		<Field label={label} hint={hint} error={error ?? undefined} size={size}>
			<TextArea
				mono
				rows={rows}
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				onBlur={commit}
				spellCheck={false}
				className="leading-relaxed"
			/>
		</Field>
	);
};
