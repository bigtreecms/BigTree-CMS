import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Eye, EyeOff, Lock, Save, ShieldAlert } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { FormShell } from "@/components/ui/FormShell";
import { LockBanner } from "@/components/ui/LockBanner";

import { settingsApi, type SettingDetail } from "@/api/endpoints/settings";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { isFieldRequired, isFieldValueEmpty } from "@/renderer/forms/validation";
import type { ModuleFormField } from "@/api/endpoints/modules";

import { useAuthStore } from "@/auth/store";
import { useLock } from "@/hooks/useLock";
import { useReturnTo } from "@/hooks/useReturnTo";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Loading } from "@/components/ui/Loading";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { sanitizeHtml } from "@/lib/html";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

/**
 * /settings/:id/edit — value editor for a single setting.
 *
 * The setting's `type` + `settings` blob drives the FieldRenderer dispatch, so
 * any field type we've built (text / textarea / html / image / matrix / etc.)
 * just works. The page acquires `useLock("config:settings", id)` so two
 * admins can't clobber each other.
 *
 * Encrypted settings: the API only returns the value when the caller is
 * level≥2 AND passes `?include_encrypted=true`. We attempt the decrypt fetch
 * on mount for publishers; for editors we render a Reveal action the user can
 * trigger (still server-gated).
 */
export const SettingEdit = () => {
	const { id } = useParams<{ id: string }>();
	const settingId = id ?? "";
	const navigate = useNavigate();
	const returnTo = useReturnTo("/settings");
	const queryClient = useQueryClient();
	const userLevel = useAuthStore((s) => s.user?.level ?? 0);
	const isPublisher = userLevel >= 2;

	const [revealEncrypted, setRevealEncrypted] = useState(false);
	const [value, setValue] = useState<unknown>(undefined);
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [fieldError, setFieldError] = useState<string | null>(null);

	const settingQuery = useQuery({
		queryKey: ["settings", "detail", settingId, { includeEncrypted: revealEncrypted }],
		queryFn: () => settingsApi.get(settingId, { includeEncrypted: revealEncrypted }),
		enabled: settingId !== "",
	});

	const lock = useLock({
		table: "config:settings",
		itemId: settingId,
		title: settingQuery.data?.name,
		enabled: settingId !== "" && Boolean(settingQuery.data),
	});

	// Seed local editable value from the server payload. We use the inequality
	// check against `undefined` so a legitimately-null setting value still
	// initialises (vs leaving the renderer stuck on "no value yet").
	useEffect(() => {
		if (!settingQuery.data) {
			return;
		}

		// Don't re-seed if we already loaded the value once — preserves user
		// edits across refetches.
		setValue((prev: unknown) => (prev === undefined ? (settingQuery.data?.value ?? "") : prev));
		setGeneralError(null);
	}, [settingQuery.data]);

	const saveMutation = useMutation({
		mutationFn: () => settingsApi.updateValue(settingId, value),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: ["settings", "list"] });
			queryClient.setQueryData(
				["settings", "detail", settingId, { includeEncrypted: revealEncrypted }],
				fresh
			);
			toast.success("Setting saved");
			navigate(returnTo);
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Save failed");
			}
		},
	});

	// `value` is undefined until the setting loads (and stays undefined for an
	// unrevealed encrypted secret), so it doubles as the "ready" signal.
	const isDirty =
		useDirtyTracker(value, value !== undefined) &&
		!saveMutation.isPending &&
		!lock.ownedByOther;

	if (!settingId) {
		return <ErrorPanel error={new Error("Missing setting id")} />;
	}

	if (settingQuery.isLoading || !settingQuery.data) {
		return (
			<PageContainer width="narrow">
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (settingQuery.error) {
		return (
			<PageContainer width="narrow">
				<ErrorPanel error={settingQuery.error} />
			</PageContainer>
		);
	}

	const setting = settingQuery.data;
	const readOnly = lock.ownedByOther;
	// Encrypted values need explicit reveal — until reveal, lock down the
	// editor so the user doesn't accidentally overwrite an unloaded secret.
	const valueWithheld = Boolean(setting.value_omitted);

	const breadcrumbs = [
		{ label: "Settings", to: "/settings" },
		{ label: setting.name || setting.id },
	];

	// The description is already shown in the PageHead `sub`, so we deliberately
	// omit it as the field subtitle — otherwise it renders again in FieldRow and
	// a third time inside label-driven fields like CheckboxField.
	const formField: ModuleFormField = {
		column: setting.id,
		title: setting.name || setting.id,
		type: setting.type || "text",
		settings: setting.settings,
	};

	const handleSave = () => {
		if (readOnly || saveMutation.isPending || valueWithheld) {
			return;
		}

		if (isFieldRequired(formField) && isFieldValueEmpty(formField, value)) {
			setFieldError(`${formField.title} is required.`);
			setGeneralError("Please fill in the required fields.");

			return;
		}

		setFieldError(null);
		setGeneralError(null);
		saveMutation.mutate();
	};

	return (
		<PageContainer width="narrow">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				title={setting.name || setting.id}
				sub={
					setting.description ? (
						// Descriptions are WYSIWYG HTML authored in the developer
						// section; render them as markup (margins flattened to keep
						// the subtitle on a tight baseline).
						<div
							className="[&_p]:m-0 [&_p+p]:mt-1"
							dangerouslySetInnerHTML={{ __html: sanitizeHtml(setting.description) }}
						/>
					) : undefined
				}
			/>

			<FlagBar
				setting={setting}
				revealEncrypted={revealEncrypted}
				onReveal={() => setRevealEncrypted(true)}
				canReveal={isPublisher}
			/>

			{readOnly && (
				<LockBanner
					owner={lock.lockOwner}
					lockedAt={lock.lockedAt}
					onUnlock={lock.forceUnlock}
				/>
			)}

			{generalError && (
				<Alert tone="danger" className="mb-3">
					{generalError}
				</Alert>
			)}

			<FormShell
				onSubmit={(e) => {
					e.preventDefault();
					handleSave();
				}}
				footer={
					<>
						<Button onClick={() => navigate("/settings")}>Cancel</Button>
						<Button
							variant="primary"
							type="submit"
							icon={<Save size={13} />}
							disabled={readOnly || saveMutation.isPending || valueWithheld}
						>
							{saveMutation.isPending ? "Saving…" : "Save"}
						</Button>
					</>
				}
			>
				{valueWithheld ? (
					<InlineEmpty pad="lg">
						This setting is encrypted.{" "}
						{isPublisher
							? "Click the Reveal button above to decrypt and edit it."
							: "Only publishers can decrypt and edit it."}
					</InlineEmpty>
				) : (
					// Single-field page — the PageHead title already names the
					// setting, so we render the control without FieldRow's
					// duplicate label and keep only its error markup.
					<>
						<FieldRenderer
							field={formField}
							value={value}
							onChange={setValue}
							disabled={readOnly}
						/>
						{fieldError && (
							<div data-field-error className="mt-1 text-[11.5px] text-danger">
								{fieldError}
							</div>
						)}
					</>
				)}
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};

interface FlagBarProps {
	setting: SettingDetail;
	revealEncrypted: boolean;
	canReveal: boolean;
	onReveal: () => void;
}

const FlagBar = ({ setting, revealEncrypted, canReveal, onReveal }: FlagBarProps) => {
	const flags: React.ReactNode[] = [];

	if (setting.encrypted) {
		flags.push(
			<span
				key="enc"
				className="inline-flex items-center gap-1 rounded bg-info-bg px-1.5 py-0.5 text-[11px] font-medium text-info"
			>
				<ShieldAlert size={11} />
				Encrypted
			</span>
		);
	}

	if (setting.locked) {
		flags.push(
			<span
				key="locked"
				className="inline-flex items-center gap-1 rounded bg-warn-bg px-1.5 py-0.5 text-[11px] font-medium text-warn"
			>
				<Lock size={11} />
				Locked
			</span>
		);
	}

	if (setting.system) {
		flags.push(
			<span
				key="sys"
				className="rounded bg-surface-2 px-1.5 py-0.5 text-[11px] font-medium text-text-3"
			>
				System
			</span>
		);
	}

	const showReveal = setting.encrypted && setting.value_omitted && canReveal;
	const showHidden = setting.encrypted && !setting.value_omitted;

	if (flags.length === 0 && !showReveal && !showHidden) {
		return null;
	}

	return (
		<div className="mb-3 flex flex-wrap items-center gap-2">
			{flags}
			{showReveal && (
				<button
					type="button"
					onClick={onReveal}
					className="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2 py-0.5 text-[11.5px] hover:bg-hover"
				>
					<Eye size={11} />
					Reveal value
				</button>
			)}
			{showHidden && revealEncrypted && (
				<span className="inline-flex items-center gap-1 text-[11.5px] text-text-3">
					<EyeOff size={11} />
					Value decrypted in this session
				</span>
			)}
		</div>
	);
};
