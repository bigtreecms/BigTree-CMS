import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Key, Save } from "lucide-react";

import { usersApi, type UpdateUserPayload, type UserDetail } from "@/api/endpoints/users";
import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { GravatarAvatar } from "@/components/users/GravatarAvatar";
import { PasswordChangeDialog } from "@/components/users/PasswordChangeDialog";
import { TimezoneSelect } from "@/components/users/TimezoneSelect";
import { toast } from "@/lib/toast";
import { ApiError } from "@/types/api";

/**
 * Self-service profile editor. Hits the same `PATCH /users/{id}` route the
 * UserEdit screen uses, but limits the visible fields to those a user is
 * allowed to change about themselves (name/email/company/timezone/daily_digest
 * — never level or permissions; the server strips those for self-updates).
 *
 * 2FA enrollment and passkey management are deferred to a later phase.
 */
export const Profile = () => {
	const queryClient = useQueryClient();
	const currentUser = useAuthStore((s) => s.user);
	const setSession = useAuthStore((s) => s.setSession);

	const meQ = useQuery({
		queryKey: ["users", "me"],
		queryFn: usersApi.me,
	});

	const [form, setForm] = useState<UpdateUserPayload>({});
	const [passwordOpen, setPasswordOpen] = useState(false);

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
	}, [meQ.data]);

	const updateMutation = useMutation({
		mutationFn: (payload: UpdateUserPayload) => {
			if (!meQ.data) {
				return Promise.reject(new Error("Profile not loaded"));
			}

			return usersApi.update(meQ.data.id, payload);
		},
		onSuccess: (fresh: UserDetail) => {
			queryClient.setQueryData(["users", "me"], fresh);
			queryClient.invalidateQueries({ queryKey: ["users", "detail", fresh.id] });

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

			toast.success("Profile saved");
		},
		onError: (err: unknown) => {
			if (err instanceof ApiError) {
				toast.error(err.message);

				return;
			}

			toast.error("Failed to save profile");
		},
	});

	if (!currentUser) {
		return null;
	}

	if (meQ.isLoading) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4 text-[13px] text-text-3">
				Loading profile…
			</div>
		);
	}

	if (meQ.error) {
		return (
			<div className="mx-auto max-w-screen-md px-6 py-4">
				<ErrorPanel error={meQ.error} />
			</div>
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

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb items={[{ label: "Profile" }]} />

			<PageHead
				title="Profile"
				sub="Manage your account details."
				actions={
					<>
						<button
							type="button"
							onClick={() => setPasswordOpen(true)}
							className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							<Key size={14} />
							Change password
						</button>

						<button
							type="submit"
							form="profile-form"
							disabled={updateMutation.isPending}
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
						>
							<Save size={14} />
							{updateMutation.isPending ? "Saving…" : "Save"}
						</button>
					</>
				}
			/>

			<form
				id="profile-form"
				onSubmit={submit}
				className="rounded-xl border border-border bg-surface"
			>
				<div className="flex items-center gap-4 border-b border-border bg-surface-2 px-4 py-3">
					<GravatarAvatar email={form.email ?? me.email} size={48} />
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold tracking-[-0.01em]">
							{me.name || me.email}
						</div>
						<div className="truncate text-[11.5px] text-text-3">{me.email}</div>
					</div>
				</div>

				<div className="grid gap-4 p-4 md:grid-cols-2">
					<label className="block">
						<span className="mb-1 block text-[12px] font-medium text-text-2">Name</span>
						<input
							type="text"
							value={form.name ?? ""}
							onChange={(e) => setForm({ ...form, name: e.target.value })}
							className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						/>
					</label>

					<label className="block">
						<span className="mb-1 block text-[12px] font-medium text-text-2">
							Email
						</span>
						<input
							type="email"
							required
							value={form.email ?? ""}
							onChange={(e) => setForm({ ...form, email: e.target.value })}
							className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						/>
					</label>

					<label className="block">
						<span className="mb-1 block text-[12px] font-medium text-text-2">
							Company
						</span>
						<input
							type="text"
							value={form.company ?? ""}
							onChange={(e) => setForm({ ...form, company: e.target.value })}
							className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
						/>
					</label>

					<label className="block">
						<span className="mb-1 block text-[12px] font-medium text-text-2">
							Timezone
						</span>
						<TimezoneSelect
							value={form.timezone ?? ""}
							onChange={(tz) => setForm({ ...form, timezone: tz })}
						/>
					</label>

					<label className="md:col-span-2 flex items-center gap-2">
						<input
							type="checkbox"
							checked={form.daily_digest ?? false}
							onChange={(e) => setForm({ ...form, daily_digest: e.target.checked })}
							className="h-4 w-4 cursor-pointer accent-accent"
						/>
						<span className="text-[12.5px] text-text-2">
							Send me a daily digest email
						</span>
					</label>
				</div>
			</form>

			<PasswordChangeDialog
				open={passwordOpen}
				onOpenChange={setPasswordOpen}
				userId={me.id}
				requireCurrent={true}
			/>
		</div>
	);
};
