import { Outlet } from "react-router-dom";
import { useEffect, useState } from "react";
import { TopBar } from "./TopBar";
import { TabNav } from "./TabNav";
import { applyTheme, resolveInitialTheme } from "@/lib/theme";

/**
 * Outer layout wrapper. Renders the TopBar + TabNav and an <Outlet /> for the
 * active route. The router uses this as the element for the authenticated
 * route subtree.
 */
export const Shell = () => {
	const [dark, setDark] = useState(() => resolveInitialTheme() === "dark");

	useEffect(() => {
		applyTheme(dark ? "dark" : "light");
	}, [dark]);

	return (
		<div className="flex min-h-screen flex-col">
			<TopBar
				siteName="BigTree"
				dark={dark}
				onToggleDark={() => setDark((d) => !d)}
				onOpenSearch={() => {
					/* TODO: wire global ⌘K palette */
				}}
			/>
			<TabNav />
			<main className="flex-1">
				<Outlet />
			</main>
		</div>
	);
};
