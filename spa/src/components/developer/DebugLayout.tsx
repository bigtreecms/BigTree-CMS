import type { ReactNode } from "react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { LoadingText } from "@/components/ui/LoadingText";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface DebugLayoutProps {
	actions?: ReactNode;
	children: ReactNode;
	/**
	 * When provided, the layout renders the shared loading/error gate above the
	 * children (any TanStack query satisfies the `{ isLoading, error }` shape).
	 * Children still guard their own `data`-dependent JSX, so they only render
	 * once loaded.
	 */
	query?: { isLoading: boolean; error: unknown };
	sub?: string;
	title: string;
}

/**
 * Shared chrome for every /developer/debug/* page. Threads the Debug-active
 * state through the Developer sub-nav and pins the "Debug" crumb to the area
 * landing page.
 */
export const DebugLayout = ({ title, sub, actions, query, children }: DebugLayoutProps) => (
	<PageContainer width="wide">
		<Breadcrumb
			items={[
				{ label: "Developer", to: "/developer" },
				{ label: "Debug", to: "/developer/debug" },
				{ label: title },
			]}
		/>

		<PageHead actions={actions} sub={sub} title={title} />

		<DeveloperSectionNav />

		{query?.isLoading && <LoadingText />}

		{query?.error ? <ErrorPanel error={query.error} /> : null}

		{children}
	</PageContainer>
);
