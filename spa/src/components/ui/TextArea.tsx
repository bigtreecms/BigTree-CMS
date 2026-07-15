import { forwardRef, type TextareaHTMLAttributes } from "react";

import { inputClassFor, type InputClassOptions } from "./TextInput";

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & InputClassOptions;

/**
 * Bare multi-line text input sharing the canonical {@link inputClass}. Defaults
 * to a resizable vertical handle; pass `rows` to size it. Use the `mono` prop
 * for code/markup entry and `dense` for compact sections.
 */
export const TextArea = forwardRef<HTMLTextAreaElement, TextAreaProps>(
	({ dense, mono, className, rows = 4, ...rest }, ref) => {
		const base = `${inputClassFor({ dense, mono })} resize-y`;

		return (
			<textarea
				className={className ? `${base} ${className}` : base}
				ref={ref}
				rows={rows}
				{...rest}
			/>
		);
	}
);

TextArea.displayName = "TextArea";
