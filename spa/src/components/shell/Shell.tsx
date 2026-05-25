import { Outlet } from "react-router-dom";
import { useEffect, useState } from "react";
import { TopBar } from "./TopBar";
import { TabNav } from "./TabNav";
import { QuickSearch } from "./QuickSearch";
import { applyTheme, resolveInitialTheme } from "@/lib/theme";

/**
 * Outer layout wrapper. Renders the TopBar + TabNav and an <Outlet /> for the
 * active route. The router uses this as the element for the authenticated
 * route subtree.
 */
export const Shell = () => {
	const [dark, setDark] = useState(() => resolveInitialTheme() === "dark");
	const [searchOpen, setSearchOpen] = useState(false);

	useEffect(() => {
		applyTheme(dark ? "dark" : "light");
	}, [dark]);

	// Global ⌘K / Ctrl+K toggles the quick search palette from anywhere
	useEffect(() => {
		const onKey = (e: KeyboardEvent) => {
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
				e.preventDefault();
				setSearchOpen((o) => !o);
			}
		};
		document.addEventListener("keydown", onKey);
		return () => document.removeEventListener("keydown", onKey);
	}, []);

	return (
		<div className="flex min-h-screen flex-col">
			<TopBar
				dark={dark}
				onToggleDark={() => setDark((d) => !d)}
				onOpenSearch={() => setSearchOpen(true)}
			/>
			<TabNav />
			<main className="flex-1">
				<Outlet />
			</main>

			<QuickSearch open={searchOpen} onClose={() => setSearchOpen(false)} />
		</div>
	);
};
