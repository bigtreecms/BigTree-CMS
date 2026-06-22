import { forwardRef, type SelectHTMLAttributes } from "react";

import { inputClassFor } from "./TextInput";

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
	/** Compact vertical padding to match dense text inputs (e.g. the module designer). */
	dense?: boolean;
};

/**
 * Bare `<select>` sharing the canonical {@link inputClass} so dropdowns line up
 * with text inputs. Render `<option>`s as children. Use {@link SelectField} when
 * you also want the label/error chrome.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(
	({ dense, className, children, ...rest }, ref) => {
		const base = inputClassFor({ dense });

		return (
			<select ref={ref} className={className ? `${base} ${className}` : base} {...rest}>
				{children}
			</select>
		);
	}
);

Select.displayName = "Select";
