import type { ReactNode } from "react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface ConfigureLayoutProps {
	title: string;
	sub?: string;
	actions?: ReactNode;
	children: ReactNode;
}

/**
 * Shared chrome for every /developer/configure/* page. Threads the
 * Configure-active state through the Developer sub-nav and pins the
 * "Configure" crumb to the area landing page.
 */
export const ConfigureLayout = ({ title, sub, actions, children }: ConfigureLayoutProps) => (
	<PageContainer width="wide">
		<Breadcrumb
			items={[
				{ label: "Developer", to: "/developer" },
				{ label: "Configure", to: "/developer/configure" },
				{ label: title },
			]}
		/>

		<PageHead title={title} sub={sub} actions={actions} />

		<DeveloperSectionNav />

		{children}
	</PageContainer>
);
