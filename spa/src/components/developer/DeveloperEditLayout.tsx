import type { FormEvent, ReactNode } from "react";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { EditPageGuard } from "@/components/ui/EditPageGuard";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";

import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

interface DeveloperEditLayoutProps {
	width: "narrow" | "medium" | "wide";
	/** Breadcrumb section label (e.g. "Callouts"). */
	section: string;
	/** Breadcrumb + Back + FormFooter cancelTo path. */
	listPath: string;
	isAdd: boolean;
	title: string;
	sub?: string;
	/** Pre-derived: `!isAdd && detailQ.isLoading`. */
	loading: boolean;
	/** Pre-derived: `isAdd ? undefined : detailQ.error`. */
	queryError: unknown;
	/** Submit hook's general error → Alert. */
	error?: string | null;
	isDirty: boolean;
	/**
	 * When provided, children are wrapped in FormShell + FormFooter.
	 * Omit for tabbed editors (ModuleDesignerEdit) that own their own body.
	 */
	onSubmit?: (e: FormEvent) => void;
	submitLabel?: string;
	saving?: boolean;
	/** CalloutEdit / FeedEdit / TemplateEdit pass `false`. Default `true`. */
	formShellBounded?: boolean;
	/** Extra PageHead actions rendered before the Back button. */
	headActions?: ReactNode;
	children: ReactNode;
}

/**
 * Shared chrome for developer add/edit pages. Owns EditPageGuard, breadcrumb,
 * PageHead (Back), DeveloperSectionNav, optional submit error Alert, optional
 * FormShell + FormFooter, and UnsavedChangesGuard. Callers supply field bodies
 * (and submit wiring when using the form slot).
 */
export const DeveloperEditLayout = ({
	width,
	section,
	listPath,
	isAdd,
	title,
	sub,
	loading,
	queryError,
	error,
	isDirty,
	onSubmit,
	submitLabel,
	saving = false,
	formShellBounded,
	headActions,
	children,
}: DeveloperEditLayoutProps) => {
	const body =
		onSubmit != null ? (
			<FormShell
				bounded={formShellBounded}
				onSubmit={onSubmit}
				footer={
					<FormFooter
						cancelTo={listPath}
						submitLabel={submitLabel ?? (isAdd ? "Create" : "Save")}
						loading={saving}
						loadingLabel="Saving…"
					/>
				}
			>
				{children}
			</FormShell>
		) : (
			children
		);

	return (
		<EditPageGuard width={width} loading={loading} error={queryError}>
			<PageContainer width={width}>
				<Breadcrumb
					items={[
						{ label: "Developer", to: "/developer" },
						{ label: section, to: listPath },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					title={title}
					sub={sub}
					actions={
						<>
							{headActions}
							<Button icon={<ChevronLeft size={13} />} to={listPath}>
								Back
							</Button>
						</>
					}
				/>

				<DeveloperSectionNav />

				{error ? (
					<Alert tone="danger" className="mb-3">
						{error}
					</Alert>
				) : null}

				{body}

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
