import type { ReactNode } from "react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { LoadingText } from "@/components/ui/LoadingText";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface ConfigureLayoutProps {
	title: string;
	sub?: string;
	actions?: ReactNode;
	/**
	 * When provided, the layout renders the shared loading/error gate above the
	 * children (any TanStack query satisfies the `{ isLoading, error }` shape).
	 * Children still guard their own `data`-dependent JSX, so they only render
	 * once loaded.
	 */
	query?: { isLoading: boolean; error: unknown };
	children: ReactNode;
}

/**
 * Shared chrome for every /developer/configure/* page. Threads the
 * Configure-active state through the Developer sub-nav and pins the
 * "Configure" crumb to the area landing page.
 */
export const ConfigureLayout = ({ title, sub, actions, query, children }: ConfigureLayoutProps) => (
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

		{query?.isLoading && <LoadingText />}

		{query?.error ? <ErrorPanel error={query.error} /> : null}

		{children}
	</PageContainer>
);
