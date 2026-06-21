import { Outlet, useLocation } from "react-router-dom";
import { useEffect, useState } from "react";
import { Wrench } from "lucide-react";
import { TopBar } from "./TopBar";
import { TabNav } from "./TabNav";
import { EmulationBanner } from "./EmulationBanner";
import { QuickSearch } from "./QuickSearch";
import { Toaster } from "@/components/ui/Toaster";
import { Button } from "@/components/ui/Button";
import { ErrorBoundary } from "@/components/ui/ErrorBoundary";
import { applyTheme, resolveInitialTheme } from "@/lib/theme";
import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";

/**
 * Outer layout wrapper. Renders the TopBar + TabNav and an <Outlet /> for the
 * active route. The router uses this as the element for the authenticated
 * route subtree.
 */
export const Shell = () => {
	const [dark, setDark] = useState(() => resolveInitialTheme() === "dark");
	const [searchOpen, setSearchOpen] = useState(false);
	const { pathname } = useLocation();
	const developerLockout = useAuthStore((s) => s.developerLockout);

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

	if (developerLockout) {
		return (
			<div className="grid min-h-screen place-items-center bg-bg px-4">
				<div className="w-full max-w-[420px] rounded-lg border border-border bg-surface p-6 text-center shadow-md">
					<div className="mx-auto mb-3 grid size-10 place-items-center rounded-full bg-info-bg text-info">
						<Wrench size={18} />
					</div>
					<h1 className="mb-1 text-[15px] font-semibold tracking-[-0.01em]">
						Maintenance underway
					</h1>
					<p className="mb-4 text-[12.5px] text-text-3">
						We are currently undergoing site maintenance. If your need is urgent, please
						contact your webmaster.
					</p>
					<Button variant="secondary" onClick={() => void authApi.logout()}>
						Sign out
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="flex min-h-screen flex-col">
			<TopBar
				dark={dark}
				onToggleDark={() => setDark((d) => !d)}
				onOpenSearch={() => setSearchOpen(true)}
			/>
			<TabNav />
			<EmulationBanner />
			<main className="flex-1">
				<ErrorBoundary resetKey={pathname}>
					<Outlet />
				</ErrorBoundary>
			</main>

			<QuickSearch open={searchOpen} onClose={() => setSearchOpen(false)} />

			<Toaster />
		</div>
	);
};
