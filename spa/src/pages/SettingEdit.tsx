import { useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Eye, EyeOff, Lock, ShieldAlert } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Field } from "@/components/ui/Field";
import { FormFooter } from "@/components/ui/FormFooter";
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
import { useSeededState } from "@/hooks/useSeededState";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Loading } from "@/components/ui/Loading";
import { describeApiError } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";
import { sanitizeHtml } from "@/lib/html";
import { queryKeys } from "@/lib/queryKeys";
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
		queryKey: queryKeys.settings.detail(settingId, { includeEncrypted: revealEncrypted }),
		queryFn: () => settingsApi.get(settingId, { includeEncrypted: revealEncrypted }),
		enabled: settingId !== "",
	});

	const lock = useLock({
		table: "config:settings",
		itemId: settingId,
		title: settingQuery.data?.name,
		enabled: settingId !== "" && Boolean(settingQuery.data),
	});

	// Seed local editable value once from the server payload. Null values
	// still initialise (as "") so the renderer isn't stuck on "no value yet".
	useSeededState(settingQuery.data, (data) => {
		setValue(data.value ?? "");
		setGeneralError(null);
	});

	const saveMutation = useMutation({
		mutationFn: () => settingsApi.updateValue(settingId, value),
		onSuccess: (fresh) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.settings.lists() });
			queryClient.setQueryData(
				queryKeys.settings.detail(settingId, { includeEncrypted: revealEncrypted }),
				fresh
			);
			toast.success("Setting saved");
			navigate(returnTo);
		},
		onError: (err) => {
			setGeneralError(describeApiError(err, "Save failed"));
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
				title={setting.name || setting.id}
			/>

			<FlagBar
				canReveal={isPublisher}
				revealEncrypted={revealEncrypted}
				setting={setting}
				onReveal={() => setRevealEncrypted(true)}
			/>

			{readOnly && (
				<LockBanner
					lockedAt={lock.lockedAt}
					owner={lock.lockOwner}
					onUnlock={lock.forceUnlock}
				/>
			)}

			{generalError && (
				<Alert className="mb-3" tone="danger">
					{generalError}
				</Alert>
			)}

			<FormShell
				footer={
					<FormFooter
						disabled={readOnly || valueWithheld}
						loading={saveMutation.isPending}
						loadingLabel="Saving…"
						submitLabel="Save"
						onCancel={() => navigate("/settings")}
					/>
				}
				onSubmit={(e) => {
					e.preventDefault();
					handleSave();
				}}
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
					<Field as="div" error={fieldError ?? undefined}>
						<FieldRenderer
							disabled={readOnly}
							field={formField}
							value={value}
							onChange={setValue}
						/>
					</Field>
				)}
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};

interface FlagBarProps {
	canReveal: boolean;
	onReveal: () => void;
	revealEncrypted: boolean;
	setting: SettingDetail;
}

const FlagBar = ({ setting, revealEncrypted, canReveal, onReveal }: FlagBarProps) => {
	const flags: React.ReactNode[] = [];

	if (setting.encrypted) {
		flags.push(
			<Badge icon={<ShieldAlert size={11} />} key="enc" size="sm" tone="info">
				Encrypted
			</Badge>
		);
	}

	if (setting.locked) {
		flags.push(
			<Badge icon={<Lock size={11} />} key="locked" size="sm" tone="warn">
				Locked
			</Badge>
		);
	}

	if (setting.system) {
		flags.push(
			<Badge key="sys" size="sm">
				System
			</Badge>
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
				<Button icon={<Eye size={11} />} size="sm" variant="secondary" onClick={onReveal}>
					Reveal value
				</Button>
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
