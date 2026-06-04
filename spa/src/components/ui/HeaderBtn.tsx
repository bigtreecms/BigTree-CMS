import type { MouseEventHandler, ReactNode } from "react";

interface HeaderBtnProps {
	icon: ReactNode;
	primary?: boolean;
	children: ReactNode;
	onClick?: MouseEventHandler<HTMLButtonElement | HTMLAnchorElement>;
	/** When set, the button renders as an `<a>` so it can open a URL. */
	href?: string;
	/** Anchor target (e.g. `_blank`); only used together with `href`. */
	target?: string;
}

const baseClassName =
	"inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[12.5px] font-medium transition-colors";

const variantClassName = (primary?: boolean) =>
	primary
		? `${baseClassName} bg-accent text-accent-fg hover:bg-accent-hover`
		: `${baseClassName} border border-border bg-surface text-text-2 hover:border-border-strong hover:bg-hover`;

export const HeaderBtn = ({ icon, primary, children, onClick, href, target }: HeaderBtnProps) => {
	const className = variantClassName(primary);

	if (href) {
		return (
			<a
				href={href}
				target={target}
				rel={target === "_blank" ? "noopener noreferrer" : undefined}
				onClick={onClick}
				className={className}
			>
				{icon}
				{children}
			</a>
		);
	}

	return (
		<button type="button" onClick={onClick} className={className}>
			{icon}
			{children}
		</button>
	);
};
