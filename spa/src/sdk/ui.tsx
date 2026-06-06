import type { ButtonHTMLAttributes, CSSProperties, ReactNode } from "react";

/**
 * `@bigtree/ui` — the stable primitives surface custom action modules import.
 * Deliberately a small, curated kit (not the SPA's whole internal component set)
 * so author modules are insulated from internal refactors. Built on the same
 * design tokens as the rest of the admin, so custom actions look native.
 *
 * Exposed to author modules via the import map (public/sdk/ui.js → the global the
 * app registers at startup). See registerSdk.ts and spa/.custom-module-actions-design.md.
 */

interface StackProps {
	children: ReactNode;
	/** Vertical gap in px (default 16). */
	gap?: number;
	className?: string;
}

/** Vertical flex column. */
export const Stack = ({ children, gap = 16, className = "" }: StackProps) => {
	const style: CSSProperties = { display: "flex", flexDirection: "column", gap };

	return (
		<div style={style} className={className}>
			{children}
		</div>
	);
};

interface RowProps {
	children: ReactNode;
	/** Horizontal gap in px (default 8). */
	gap?: number;
	className?: string;
}

/** Horizontal flex row, vertically centered. */
export const Row = ({ children, gap = 8, className = "" }: RowProps) => {
	const style: CSSProperties = { display: "flex", alignItems: "center", gap };

	return (
		<div style={style} className={className}>
			{children}
		</div>
	);
};

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
	variant?: "primary" | "secondary";
}

const BUTTON_PRIMARY =
	"rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover";
const BUTTON_SECONDARY =
	"rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] font-medium text-text hover:bg-surface-2 disabled:opacity-60";

/** Primary / secondary action button. */
export const Button = ({ variant = "primary", className = "", type, ...props }: ButtonProps) => (
	<button
		type={type ?? "button"}
		className={`${variant === "primary" ? BUTTON_PRIMARY : BUTTON_SECONDARY} ${className}`}
		{...props}
	/>
);

/** Muted helper / caption text. */
export const Note = ({ children }: { children: ReactNode }) => (
	<p className="text-[12px] text-text-3">{children}</p>
);

/** Section heading. */
export const Heading = ({ children }: { children: ReactNode }) => (
	<h2 className="text-[14px] font-semibold text-text">{children}</h2>
);

// Re-export the shared form inputs + the canonical input class so authors get
// real, styled controls without rebuilding them.
export {
	TextInput,
	SelectInput,
	CheckboxInput,
	TextareaInput,
} from "@/components/developer/module-designer/inputs";
export { INPUT_CLASS } from "@/renderer/fields/types";
