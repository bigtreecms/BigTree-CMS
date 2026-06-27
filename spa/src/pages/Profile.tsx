import { useEffect, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Key, Save, ShieldCheck, User } from "lucide-react";

import { usersApi, type UpdateUserPayload, type UserDetail } from "@/api/endpoints/users";
import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Loading } from "@/components/ui/Loading";
import { TabbedEditor } from "@/components/ui/TabbedEditor";
import { TextField } from "@/components/ui/TextField";
import { GravatarAvatar } from "@/components/users/GravatarAvatar";
import { PasskeysPanel } from "@/components/users/PasskeysPanel";
import { PasswordChangeDialog } from "@/components/users/PasswordChangeDialog";
import { TimezoneSelect } from "@/components/users/TimezoneSelect";
import { TwoFactorPanel } from "@/components/users/TwoFactorPanel";
import { queryKeys } from "@/lib/queryKeys";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { useToastMutation } from "@/hooks/useToastMutation";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Card } from "@/components/ui/Card";
import { Field } from "@/components/ui/Field";
import { SectionLabel } from "@/components/ui/SectionLabel";

/**
 * Self-service profile editor — Account + Security tabs.
 *
 *   Account:  the editable identity fields the user can change about
 *             themselves. PATCH /users/{id} (server strips level/perms).
 *   Security: TOTP enroll / disable, passkey list / add / delete, and the
 *             Change Password trigger.
 */

type TabValue = "account" | "security";

export const Profile = () => {
	const queryClient = useQueryClient();
	const currentUser = useAuthStore((s) => s.user);
	const setSession = useAuthStore((s) => s.setSession);

	const meQ = useQuery({
		queryKey: queryKeys.users.me(),
		queryFn: usersApi.me,
	});

	const [form, setForm] = useState<UpdateUserPayload>({});
	const [passwordOpen, setPasswordOpen] = useState(false);
	const [tab, setTab] = useState<TabValue>("account");
	const [seeded, setSeeded] = useState(false);

	useEffect(() => {
		if (!meQ.data) {
			return;
		}

		setForm({
			email: meQ.data.email,
			name: meQ.data.name,
			company: meQ.data.company,
			timezone: meQ.data.timezone,
			daily_digest: meQ.data.daily_digest,
		});
		setSeeded(true);
	}, [meQ.data]);

	const updateMutation = useToastMutation({
		mutationFn: (payload: UpdateUserPayload) => {
			if (!meQ.data) {
				return Promise.reject(new Error("Profile not loaded"));
			}

			return usersApi.update(meQ.data.id, payload);
		},
		invalidate: [queryKeys.users.detail(meQ.data?.id ?? 0)],
		successMessage: "Profile saved",
		errorMessage: "Failed to save profile",
		onSuccess: (fresh: UserDetail) => {
			queryClient.setQueryData(queryKeys.users.me(), fresh);

			// Keep the auth store in sync so the topbar shows the latest name.
			const auth = useAuthStore.getState();

			if (auth.accessToken && auth.refreshToken && auth.expiresAt) {
				const remainingSeconds = Math.max(
					0,
					Math.floor((auth.expiresAt - Date.now()) / 1000)
				);
				setSession(auth.accessToken, auth.refreshToken, remainingSeconds, {
					id: fresh.id,
					email: fresh.email,
					name: fresh.name,
					level: fresh.level,
					timezone: fresh.timezone,
				});
			}
		},
	});

	const isDirty = useDirtyTracker(form, seeded) && !updateMutation.isPending;

	if (!currentUser) {
		return null;
	}

	if (meQ.isLoading) {
		return (
			<PageContainer width="narrow">
				<Loading label="Loading profile…" />
			</PageContainer>
		);
	}

	if (meQ.error) {
		return (
			<PageContainer width="narrow">
				<ErrorPanel error={meQ.error} />
			</PageContainer>
		);
	}

	const me = meQ.data;

	if (!me) {
		return null;
	}

	const submit = (event: React.FormEvent) => {
		event.preventDefault();
		updateMutation.mutate(form);
	};

	const tabs = [
		{
			value: "account" as const,
			label: "Account",
			icon: <User size={13} />,
			content: (
				<AccountTab
					me={me}
					form={form}
					onChange={setForm}
					onSubmit={submit}
					saving={updateMutation.isPending}
				/>
			),
		},
		{
			value: "security" as const,
			label: "Security",
			icon: <ShieldCheck size={13} />,
			content: <SecurityTab me={me} onChangePassword={() => setPasswordOpen(true)} />,
		},
	];

	return (
		<PageContainer width="narrow">
			<Breadcrumb items={[{ label: "Profile" }]} />

			<PageHead
				title="Profile"
				sub="Manage your account details, passkeys, and password."
				actions={
					tab === "account" ? (
						<Button
							variant="primary"
							type="submit"
							form="profile-form"
							icon={<Save size={13} />}
							loading={updateMutation.isPending}
							loadingLabel="Saving…"
						>
							Save
						</Button>
					) : (
						<Button icon={<Key size={13} />} onClick={() => setPasswordOpen(true)}>
							Change password
						</Button>
					)
				}
			/>

			<TabbedEditor tabs={tabs} value={tab} onChange={(v) => setTab(v as TabValue)} />

			<PasswordChangeDialog
				open={passwordOpen}
				onOpenChange={setPasswordOpen}
				userId={me.id}
				requireCurrent={true}
			/>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};

interface AccountTabProps {
	me: UserDetail;
	form: UpdateUserPayload;
	onChange: (next: UpdateUserPayload) => void;
	onSubmit: (event: React.FormEvent) => void;
	saving: boolean;
}

const AccountTab = ({ me, form, onChange, onSubmit }: AccountTabProps) => (
	<form
		id="profile-form"
		onSubmit={onSubmit}
		className="rounded-xl border border-border bg-surface"
	>
		<div className="flex items-center gap-4 border-b border-border bg-surface-2 px-4 py-3">
			<GravatarAvatar email={form.email ?? me.email} name={form.name ?? me.name} size={48} />
			<div className="min-w-0">
				<div className="text-[13.5px] font-semibold tracking-[-0.01em]">
					{me.name || me.email}
				</div>
				<div className="truncate text-[11.5px] text-text-3">{me.email}</div>
			</div>
		</div>

		<div className="grid gap-4 p-4 md:grid-cols-2">
			<TextField
				label="Name"
				value={form.name ?? ""}
				onChange={(name) => onChange({ ...form, name })}
			/>

			<TextField
				label="Email"
				type="email"
				required
				value={form.email ?? ""}
				onChange={(email) => onChange({ ...form, email })}
			/>

			<TextField
				label="Company"
				value={form.company ?? ""}
				onChange={(company) => onChange({ ...form, company })}
			/>

			<Field label="Timezone">
				<TimezoneSelect
					value={form.timezone ?? ""}
					onChange={(tz) => onChange({ ...form, timezone: tz })}
				/>
			</Field>

			<Checkbox
				label="Send me a daily digest email"
				checked={form.daily_digest ?? false}
				onChange={(daily_digest) => onChange({ ...form, daily_digest })}
				className="md:col-span-2"
			/>
		</div>
	</form>
);

interface SecurityTabProps {
	me: UserDetail;
	onChangePassword: () => void;
}

const SecurityTab = ({ me, onChangePassword }: SecurityTabProps) => (
	<div className="space-y-4">
		<Card>
			<header className="flex items-center gap-2 border-b border-border bg-surface-2 px-4 py-3">
				<Key size={14} className="text-text-3" />
				<SectionLabel as="h3">Password</SectionLabel>
			</header>
			<div className="flex items-center justify-between gap-3 p-4 text-[12.5px]">
				<span className="text-text-3">
					Change the password you use to sign in with email + password.
				</span>
				<button
					type="button"
					onClick={onChangePassword}
					className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 hover:bg-hover"
				>
					<Key size={13} />
					Change password
				</button>
			</div>
		</Card>

		<TwoFactorPanel enabled={me.two_factor_enabled} />

		<PasskeysPanel />
	</div>
);
