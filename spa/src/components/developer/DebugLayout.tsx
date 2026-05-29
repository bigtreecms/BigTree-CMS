import type { ReactNode } from "react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface DebugLayoutProps {
	title: string;
	sub?: string;
	actions?: ReactNode;
	children: ReactNode;
}

/**
 * Shared chrome for every /developer/debug/* page. Threads the Debug-active
 * state through the Developer sub-nav and pins the "Debug" crumb to the area
 * landing page.
 */
export const DebugLayout = ({ title, sub, actions, children }: DebugLayoutProps) => (
	<div className="mx-auto max-w-screen-2xl px-6 py-4">
		<Breadcrumb
			items={[
				{ label: "Developer", to: "/developer" },
				{ label: "Debug", to: "/developer/debug" },
				{ label: title },
			]}
		/>

		<PageHead title={title} sub={sub} actions={actions} />

		<DeveloperSectionNav />

		{children}
	</div>
);
