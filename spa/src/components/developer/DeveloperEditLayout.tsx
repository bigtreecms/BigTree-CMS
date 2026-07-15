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

interface DetailQueryLike {
	error: unknown;
	isLoading: boolean;
}

interface DeveloperEditLayoutProps {
	children: ReactNode;
	/**
	 * Prefer this over `loading`/`queryError`. On the Add route, loading and
	 * query error are suppressed; on Edit they come from the query.
	 * Mutually exclusive with explicit `loading`/`queryError` (use one style).
	 */
	detailQuery?: DetailQueryLike;
	/** Submit hook's general error → Alert. */
	error?: string | null;
	/** CalloutEdit / FeedEdit / TemplateEdit pass `false`. Default `true`. */
	formShellBounded?: boolean;
	/** Extra PageHead actions rendered before the Back button. */
	headActions?: ReactNode;
	isAdd: boolean;
	isDirty: boolean;
	/** Breadcrumb + Back + FormFooter cancelTo path. */
	listPath: string;
	/** Override: pre-derived loading. Prefer `detailQuery` when possible. */
	loading?: boolean;
	/**
	 * When provided, children are wrapped in FormShell + FormFooter.
	 * Omit for tabbed editors (ModuleDesignerEdit) that own their own body.
	 */
	onSubmit?: (e: FormEvent) => void;
	/** Override: pre-derived query error. Prefer `detailQuery` when possible. */
	queryError?: unknown;
	saving?: boolean;
	/** Breadcrumb section label (e.g. "Callouts"). */
	section: string;
	sub?: string;
	submitLabel?: string;
	title: string;
	width: "narrow" | "medium" | "wide";
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
	detailQuery,
	loading: loadingOverride,
	queryError: queryErrorOverride,
	error,
	isDirty,
	onSubmit,
	submitLabel,
	saving = false,
	formShellBounded,
	headActions,
	children,
}: DeveloperEditLayoutProps) => {
	const loading =
		loadingOverride ?? (detailQuery != null ? !isAdd && detailQuery.isLoading : false);
	const queryError =
		queryErrorOverride ?? (detailQuery != null && !isAdd ? detailQuery.error : undefined);

	const body =
		onSubmit != null ? (
			<FormShell
				bounded={formShellBounded}
				footer={
					<FormFooter
						cancelTo={listPath}
						loading={saving}
						loadingLabel="Saving…"
						submitLabel={submitLabel ?? (isAdd ? "Create" : "Save")}
					/>
				}
				onSubmit={onSubmit}
			>
				{children}
			</FormShell>
		) : (
			children
		);

	return (
		<EditPageGuard error={queryError} loading={loading} width={width}>
			<PageContainer width={width}>
				<Breadcrumb
					items={[
						{ label: "Developer", to: "/developer" },
						{ label: section, to: listPath },
						{ label: isAdd ? "Add" : "Edit" },
					]}
				/>

				<PageHead
					actions={
						<>
							{headActions}
							<Button icon={<ChevronLeft size={13} />} to={listPath}>
								Back
							</Button>
						</>
					}
					sub={sub}
					title={title}
				/>

				<DeveloperSectionNav />

				{error ? (
					<Alert className="mb-3" tone="danger">
						{error}
					</Alert>
				) : null}

				{body}

				<UnsavedChangesGuard isDirty={isDirty} />
			</PageContainer>
		</EditPageGuard>
	);
};
