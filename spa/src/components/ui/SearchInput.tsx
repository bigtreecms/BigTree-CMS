import { Search, X } from "lucide-react";

interface SearchInputProps {
	/** Current query string. */
	value: string;
	/** Called with the new query, and with `""` when the clear button is pressed. */
	onChange: (value: string) => void;
	placeholder?: string;
	/** Layout-only classes for the wrapper (width, margins, flex). `relative` is always applied. */
	className?: string;
	autoFocus?: boolean;
	/** Accessible label for the field when there is no visible label. */
	"aria-label"?: string;
}

/**
 * Toolbar search box — a leading {@link Search} icon, a text input, and a
 * trailing clear button that appears once there's a query. The single source of
 * truth for the list-filter pattern repeated across the admin (Tags, Files,
 * Users, Modules, Settings, …). Pass wrapper layout via `className`; the
 * search-specific input styling is fixed so every toolbar lines up.
 */
export const SearchInput = ({
	value,
	onChange,
	placeholder,
	className,
	autoFocus,
	"aria-label": ariaLabel,
}: SearchInputProps) => (
	<div className={className ? `relative ${className}` : "relative"}>
		<Search
			size={14}
			className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
		/>
		<input
			className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
			placeholder={placeholder}
			value={value}
			onChange={(e) => onChange(e.target.value)}
			autoFocus={autoFocus}
			aria-label={ariaLabel}
		/>
		{value && (
			<button
				type="button"
				className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
				onClick={() => onChange("")}
				aria-label="Clear search"
			>
				<X size={14} />
			</button>
		)}
	</div>
);
