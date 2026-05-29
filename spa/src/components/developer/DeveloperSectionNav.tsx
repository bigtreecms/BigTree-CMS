import { Link, useLocation } from "react-router-dom";
import { Cog, Hammer, Stethoscope } from "lucide-react";

/**
 * Three-pill sub-nav reused at the top of every Developer-section page. Picks
 * its active state by matching the leading URL segment under /developer.
 */
type Section = "create" | "configure" | "debug";

const SECTIONS: Array<{ id: Section; label: string; icon: React.ReactNode; to: string }> = [
	{ id: "create", label: "Create", icon: <Hammer size={13} />, to: "/developer" },
	{ id: "configure", label: "Configure", icon: <Cog size={13} />, to: "/developer/configure" },
	{ id: "debug", label: "Debug", icon: <Stethoscope size={13} />, to: "/developer/debug" },
];

const sectionFromPath = (pathname: string): Section => {
	if (pathname.startsWith("/developer/configure")) {
		return "configure";
	}

	if (pathname.startsWith("/developer/debug")) {
		return "debug";
	}

	return "create";
};

export const DeveloperSectionNav = () => {
	const location = useLocation();
	const active = sectionFromPath(location.pathname);

	return (
		<nav className="mb-4 inline-flex rounded-md border border-border bg-surface p-0.5">
			{SECTIONS.map((s) => {
				const isActive = s.id === active;

				return (
					<Link
						key={s.id}
						to={s.to}
						className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[12.5px] transition-colors ${
							isActive
								? "bg-accent-soft font-medium text-accent"
								: "text-text-2 hover:bg-hover hover:text-text"
						}`}
						aria-current={isActive ? "page" : undefined}
					>
						{s.icon}
						<span>{s.label}</span>
					</Link>
				);
			})}
		</nav>
	);
};
