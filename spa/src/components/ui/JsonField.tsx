import type { ReactNode } from "react";

import { useJsonDraft } from "../../hooks/useJsonDraft";
import { Field, type FieldSize } from "./Field";
import { TextArea } from "./TextArea";

interface JsonFieldProps {
	hint?: string;
	/** Override the validation error shown when the value is not a JSON object. */
	invalidMessage?: string;
	/** Visible label. Omit for an unlabeled editor (supply `aria-label` on the textarea via a wrapper). */
	label?: ReactNode;
	onChange: (next: Record<string, unknown>) => void;
	rows?: number;
	size?: FieldSize;
	value: unknown;
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
		<Field error={error ?? undefined} hint={hint} label={label} size={size}>
			<TextArea
				mono
				className="leading-relaxed"
				rows={rows}
				spellCheck={false}
				value={draft}
				onBlur={commit}
				onChange={(e) => setDraft(e.target.value)}
			/>
		</Field>
	);
};
