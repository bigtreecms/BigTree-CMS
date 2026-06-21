import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, useNavigate, useParams } from "react-router-dom";
import { ChevronLeft, History, Key, Save, ShieldCheck } from "lucide-react";

import {
	usersApi,
	type UpdateUserPayload,
	type UserAlerts,
	type UserDetail,
	type UserPermissions,
	type UserLevelLabel,
	levelToLabel,
} from "@/api/endpoints/users";
import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { AccessDenied } from "@/components/ui/AccessDenied";
import { Checkbox } from "@/components/ui/Checkbox";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { SelectField } from "@/components/ui/SelectField";
import { SubNav } from "@/components/ui/SubNav";
import { TextField } from "@/components/ui/TextField";
import { GravatarAvatar } from "@/components/users/GravatarAvatar";
import { ModulePermissionsTree } from "@/components/users/ModulePermissionsTree";
import { PagePermissionsTree } from "@/components/users/PagePermissionsTree";
import { PasswordChangeDialog } from "@/components/users/PasswordChangeDialog";
import { ResourcePermissionsTree } from "@/components/users/ResourcePermissionsTree";
import { TimezoneSelect } from "@/components/users/TimezoneSelect";
import { isAdmin, isDeveloper } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import { ApiError } from "@/types/api";
import { useReturnTo } from "@/hooks/useReturnTo";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { Card } from "@/components/ui/Card";

type PermsTab = "pages" | "modules" | "files";

/**
 * Full user editor — name/email/company/level/timezone/daily_digest plus the
 * Pages / Modules / Files permissions trees. Mirrors the PHP admin's
 * `users/edit.php`, including the level-aware UI (Administrators and
 * Developers are publishers of the entire site, so the radios collapse to
 * a single "everything" message and only the page tree's alerts column
 * remains useful).
 */
export const UserEdit = () => {
	const { id: idParam } = useParams<{ id: string }>();
	const id = Number(idParam);
	const navigate = useNavigate();
	const returnTo = useReturnTo("/users");
	const queryClient = useQueryClient();
	const currentUser = useAuthStore((s) => s.user);

	const isSelf = !!currentUser && currentUser.id === id;
	const canManageUsers = isAdmin(currentUser);

	const userQ = useQuery({
		queryKey: ["users", "detail", id],
		queryFn: () => usersApi.get(id),
		enabled: Number.isFinite(id) && id > 0,
	});

	const [form, setForm] = useState<UpdateUserPayload>({});
	const [permissions, setPermissions] = useState<UserPermissions>({});
	const [alerts, setAlerts] = useState<UserAlerts>({});
	const [permsTab, setPermsTab] = useState<PermsTab>("pages");
	const [passwordOpen, setPasswordOpen] = useState(false);
	const [confirmDelete, setConfirmDelete] = useState(false);
	const [confirmRemove2fa, setConfirmRemove2fa] = useState(false);
	const [seeded, setSeeded] = useState(false);

	// Seed local state when the fetched user changes.
	useEffect(() => {
		if (!userQ.data) {
			return;
		}

		const u = userQ.data;
		setForm({
			email: u.email,
			name: u.name,
			company: u.company,
			level: u.level,
			timezone: u.timezone,
			daily_digest: u.daily_digest,
		});
		setPermissions(u.permissions ?? {});
		setAlerts(u.alerts ?? {});
		setSeeded(true);
	}, [userQ.data]);

	const updateMutation = useMutation({
		mutationFn: (payload: UpdateUserPayload) => usersApi.update(id, payload),
		onSuccess: (fresh: UserDetail) => {
			queryClient.setQueryData(["users", "detail", id], fresh);
			queryClient.invalidateQueries({ queryKey: ["users", "list"] });
			toast.success("User updated");
			navigate(returnTo);
		},
		onError: (err: unknown) => {
			if (err instanceof ApiError) {
				toast.error(err.message);

				return;
			}

			toast.error("Failed to update user");
		},
	});

	const deleteMutation = useMutation({
		mutationFn: () => usersApi.delete(id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["users", "list"] });
			toast.success("User deleted");
			navigate("/users");
		},
		onError: (err: unknown) => {
			if (err instanceof ApiError) {
				toast.error(err.message);

				return;
			}

			toast.error("Failed to delete user");
		},
	});

	const remove2faMutation = useMutation({
		mutationFn: () => usersApi.removeTwoFactor(id),
		onSuccess: (fresh) => {
			queryClient.setQueryData<UserDetail | undefined>(["users", "detail", id], (prev) =>
				prev ? { ...prev, two_factor_enabled: fresh.two_factor_enabled } : prev
			);
			setConfirmRemove2fa(false);
			toast.success("Two-factor authentication removed");
		},
		onError: (err: unknown) => {
			if (err instanceof ApiError) {
				toast.error(err.message);

				return;
			}

			toast.error("Failed to remove two-factor authentication");
		},
	});

	const editedLevel = form.level ?? userQ.data?.level ?? 0;
	const editedIsAdmin = editedLevel >= 1;

	const levelLabel = useMemo<UserLevelLabel>(() => levelToLabel(editedLevel), [editedLevel]);

	const targetUser = userQ.data;
	const canEditThisUser = canManageUsers || isSelf;
	const canEditLevel = canManageUsers && !isSelf;

	const isDirty =
		useDirtyTracker({ form, permissions, alerts }, seeded) &&
		!updateMutation.isPending &&
		!deleteMutation.isPending;

	if (!Number.isFinite(id) || id <= 0) {
		return <AccessDenied title="Invalid user" message="That URL doesn't point to a user." />;
	}

	if (userQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4 text-[13px] text-text-3">
				Loading user…
			</div>
		);
	}

	if (userQ.error) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4">
				<ErrorPanel error={userQ.error} />
			</div>
		);
	}

	if (!targetUser) {
		return <AccessDenied title="User not found" message="This user no longer exists." />;
	}

	if (!canEditThisUser) {
		return <AccessDenied />;
	}

	// Block editing a higher-level user (PHP admin enforces the same).
	if (!isSelf && !!currentUser && targetUser.level > currentUser.level) {
		return (
			<AccessDenied
				title="Higher access level"
				message="You can't edit a user whose access level is higher than your own."
			/>
		);
	}

	const submit = (event: React.FormEvent) => {
		event.preventDefault();

		const payload: UpdateUserPayload = {
			email: form.email,
			name: form.name,
			company: form.company,
			timezone: form.timezone,
			daily_digest: form.daily_digest,
			alerts,
		};

		if (canEditLevel) {
			payload.level = form.level;
			payload.permissions = permissions;
		}

		updateMutation.mutate(payload);
	};

	const displayName = targetUser.name?.trim() || targetUser.email;

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={[{ label: "Users", to: "/users" }, { label: displayName }]} />

			<PageHead
				title={isSelf ? "Edit profile" : displayName}
				sub={
					isSelf ? "Update your account details." : `${levelLabel} · ${targetUser.email}`
				}
				actions={
					<>
						<Button icon={<ChevronLeft size={13} />} onClick={() => navigate("/users")}>
							Back to users
						</Button>

						<Button icon={<Key size={13} />} onClick={() => setPasswordOpen(true)}>
							Change password
						</Button>

						<Button
							variant="primary"
							type="submit"
							form="user-edit-form"
							icon={<Save size={13} />}
							disabled={updateMutation.isPending}
						>
							{updateMutation.isPending ? "Saving…" : "Save changes"}
						</Button>
					</>
				}
			/>

			{isDeveloper(currentUser) && !isSelf && (
				<div className="mb-3 flex items-center gap-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
					<Link
						to={`/developer/debug/audit?user=${id}`}
						className="inline-flex items-center gap-1.5 text-accent hover:underline"
					>
						<History size={13} />
						View audit trail
					</Link>

					{targetUser.two_factor_enabled && (
						<button
							type="button"
							onClick={() => setConfirmRemove2fa(true)}
							disabled={remove2faMutation.isPending}
							className="inline-flex items-center gap-1.5 text-danger hover:underline disabled:opacity-60"
						>
							<ShieldCheck size={13} />
							Remove two-factor authentication
						</button>
					)}
				</div>
			)}

			<form id="user-edit-form" onSubmit={submit} className="space-y-6">
				<div className="grid gap-6 md:grid-cols-2">
					<Card>
						<div className="rounded-t-xl flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
							<GravatarAvatar email={form.email ?? targetUser.email} size={40} />
							<div className="min-w-0">
								<div className="text-[13px] font-semibold tracking-[-0.01em]">
									Account
								</div>
								<div className="truncate text-[11.5px] text-text-3">
									Avatar from Gravatar
								</div>
							</div>
						</div>

						<div className="rounded-b-xl space-y-4 p-4">
							<TextField
								label="Email"
								type="email"
								required
								value={form.email ?? ""}
								onChange={(email) => setForm({ ...form, email })}
							/>

							{canEditLevel && (
								<SelectField
									label="User level"
									value={String(form.level ?? 0)}
									onChange={(level) => setForm({ ...form, level: Number(level) })}
									options={[
										{ value: "0", label: "Normal User" },
										{ value: "1", label: "Administrator" },
										...(isDeveloper(currentUser)
											? [{ value: "2", label: "Developer" }]
											: []),
									]}
									hint={
										editedLevel === 2
											? "Full access including the Developer section."
											: editedLevel === 1
												? "Manage pages, modules, files, settings, and other users."
												: "Access only to pages, modules, and folders granted below."
									}
								/>
							)}

							<Checkbox
								label="Send daily digest email"
								checked={form.daily_digest ?? false}
								onChange={(daily_digest) => setForm({ ...form, daily_digest })}
							/>
						</div>
					</Card>

					<Card>
						<div className="rounded-t-xl border-b border-border bg-surface-2 px-4 py-3 text-[13px] font-semibold tracking-[-0.01em]">
							Personal
						</div>

						<div className="rounded-b-xl space-y-4 p-4">
							<TextField
								label="Name"
								value={form.name ?? ""}
								onChange={(name) => setForm({ ...form, name })}
							/>

							<TextField
								label="Company"
								value={form.company ?? ""}
								onChange={(company) => setForm({ ...form, company })}
							/>

							<label className="block">
								<span className="mb-1 block text-[12px] font-medium text-text-2">
									Timezone
								</span>
								<TimezoneSelect
									value={form.timezone ?? ""}
									onChange={(tz) => setForm({ ...form, timezone: tz })}
								/>
							</label>
						</div>
					</Card>
				</div>

				{canEditLevel && (
					<Card>
						<div className="rounded-t-xl flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-2 px-4 py-3">
							<div className="min-w-0">
								<div className="text-[13px] font-semibold tracking-[-0.01em]">
									Permissions
								</div>
								<div className="text-[11.5px] text-text-3">
									{editedIsAdmin
										? "Administrators and Developers are publishers of the entire site."
										: "Pick what this Normal User can access. Sub-pages and sub-folders inherit unless overridden."}
								</div>
							</div>

							{!editedIsAdmin && (
								<SubNav<PermsTab>
									value={permsTab}
									onChange={setPermsTab}
									items={[
										{ value: "pages", label: "Pages" },
										{ value: "modules", label: "Modules" },
										{ value: "files", label: "Files" },
									]}
								/>
							)}
						</div>

						<div className="p-4">
							{(editedIsAdmin || permsTab === "pages") && (
								<PagePermissionsTree
									value={permissions.page}
									alerts={alerts}
									onChange={(next) =>
										setPermissions({ ...permissions, page: next })
									}
									onAlertsChange={setAlerts}
									isAdminUser={editedIsAdmin}
								/>
							)}

							{!editedIsAdmin && permsTab === "modules" && (
								<ModulePermissionsTree
									value={permissions.module}
									onChange={(next) =>
										setPermissions({ ...permissions, module: next })
									}
									gbpValue={permissions.module_gbp}
									onGbpChange={(next) =>
										setPermissions({ ...permissions, module_gbp: next })
									}
								/>
							)}

							{!editedIsAdmin && permsTab === "files" && (
								<ResourcePermissionsTree
									value={permissions.resources}
									onChange={(next) =>
										setPermissions({ ...permissions, resources: next })
									}
								/>
							)}
						</div>
					</Card>
				)}
			</form>

			{canManageUsers && !isSelf && (
				<div className="mt-6 rounded-xl border border-danger/30 bg-danger/5 p-4">
					<div className="flex items-start justify-between gap-3">
						<div>
							<div className="text-[13px] font-semibold tracking-[-0.01em] text-danger">
								Delete this user
							</div>
							<p className="mt-0.5 text-[12px] text-text-2">
								Revokes admin access immediately. Page revisions authored by this
								user remain attributed.
							</p>
						</div>

						<button
							type="button"
							onClick={() => setConfirmDelete(true)}
							className="rounded-md bg-danger px-3 py-1.5 text-[12.5px] font-medium text-white hover:bg-danger/90"
						>
							Delete user
						</button>
					</div>
				</div>
			)}

			<PasswordChangeDialog
				open={passwordOpen}
				onOpenChange={setPasswordOpen}
				userId={id}
				requireCurrent={isSelf}
			/>

			<ConfirmDialog
				open={confirmDelete}
				onOpenChange={setConfirmDelete}
				title="Delete user?"
				description={`This will permanently delete ${displayName}.`}
				confirmLabel="Delete"
				variant="danger"
				onConfirm={() => deleteMutation.mutate()}
			/>

			<ConfirmDialog
				open={confirmRemove2fa}
				onOpenChange={setConfirmRemove2fa}
				title="Remove two-factor authentication?"
				description={`${displayName} will be able to sign in with just their password until they re-enrol. Use this when they've lost their authenticator.`}
				confirmLabel="Remove 2FA"
				variant="danger"
				onConfirm={() => remove2faMutation.mutate()}
			/>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
