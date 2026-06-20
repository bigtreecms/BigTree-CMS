import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Save, ShieldOff } from "lucide-react";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { Button } from "@/components/ui/Button";
import { inputClass } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { systemApi, type SecurityPolicy } from "@/api/endpoints/system";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const narrowInputClass =
	"w-16 rounded-md border border-border bg-surface px-2 py-1 text-center text-[13px] tabular-nums outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

/** Empty-but-shaped policy so the form binds before the GET resolves. */
const emptyPolicy = (): SecurityPolicy => ({
	user_fails: { count: "", time: "", ban: "" },
	ip_fails: { count: "", time: "", ban: "" },
	password: { invitations: "", length: "", mixedcase: "", numbers: "", nonalphanumeric: "" },
	two_factor: "",
	remember_disabled: "",
	logout_all: "",
	suspect_geo_check: "",
	include_daily_bans: "",
	allowed_ips: "",
	banned_ips: "",
});

const normalize = (raw: Partial<SecurityPolicy>): SecurityPolicy => {
	const base = emptyPolicy();

	return {
		...base,
		...raw,
		user_fails: { ...base.user_fails, ...(raw.user_fails ?? {}) },
		ip_fails: { ...base.ip_fails, ...(raw.ip_fails ?? {}) },
		password: { ...base.password, ...(raw.password ?? {}) },
	};
};

export const DebugSecurity = () => {
	const queryClient = useQueryClient();

	const policyQ = useQuery({
		queryKey: ["system", "security-policy"],
		queryFn: () => systemApi.securityPolicy.get(),
	});

	const [draft, setDraft] = useState<SecurityPolicy | null>(null);

	useEffect(() => {
		if (policyQ.data) {
			setDraft(normalize(policyQ.data));
		}
	}, [policyQ.data]);

	const saveMutation = useMutation({
		mutationFn: (next: SecurityPolicy) => systemApi.securityPolicy.update(next),
		onSuccess: (fresh) => {
			queryClient.setQueryData(["system", "security-policy"], fresh);
			toast.success("Security policy updated");
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not save policy";
			toast.error(msg);
		},
	});

	const onSubmit = (e: React.FormEvent) => {
		e.preventDefault();

		if (draft) {
			saveMutation.mutate(draft);
		}
	};

	const setFail = (
		group: "user_fails" | "ip_fails",
		key: "count" | "time" | "ban",
		value: string
	) => {
		setDraft((d) => (d ? { ...d, [group]: { ...d[group], [key]: value } } : d));
	};

	const setPassword = (key: keyof SecurityPolicy["password"], value: string) => {
		setDraft((d) => (d ? { ...d, password: { ...d.password, [key]: value } } : d));
	};

	const toggle = (key: "two_factor" | "remember_disabled", on: boolean, onValue = "on") => {
		setDraft((d) => (d ? { ...d, [key]: on ? onValue : "" } : d));
	};

	const togglePassword = (key: keyof SecurityPolicy["password"], on: boolean) => {
		setPassword(key, on ? "on" : "");
	};

	return (
		<DebugLayout
			title="Security policy"
			sub="Brute-force protection, password requirements, and login IP restrictions."
		>
			{policyQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{policyQ.error && <ErrorPanel error={policyQ.error} />}

			{draft && (
				<FormShell
					bounded={false}
					onSubmit={onSubmit}
					footer={
						<Button
							variant="primary"
							type="submit"
							icon={<Save size={13} />}
							disabled={saveMutation.isPending}
						>
							{saveMutation.isPending ? "Saving…" : "Save policy"}
						</Button>
					}
				>
					<div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
						<div className="space-y-5">
							<section>
								<h3 className="mb-1 text-[13px] font-semibold text-text">
									Failed logins
								</h3>
								<p className="mb-3 text-[11.5px] text-text-3">
									Rules to throttle password brute-forcing.
								</p>

								<div className="space-y-2 text-[12.5px] leading-7 text-text-2">
									<div>
										<input
											className={narrowInputClass}
											value={String(draft.user_fails.count)}
											onChange={(e) =>
												setFail("user_fails", "count", e.target.value)
											}
										/>{" "}
										failed logins for a given <strong>user</strong> over{" "}
										<input
											className={narrowInputClass}
											value={String(draft.user_fails.time)}
											onChange={(e) =>
												setFail("user_fails", "time", e.target.value)
											}
										/>{" "}
										minutes bans the <strong>user</strong> for{" "}
										<input
											className={narrowInputClass}
											value={String(draft.user_fails.ban)}
											onChange={(e) =>
												setFail("user_fails", "ban", e.target.value)
											}
										/>{" "}
										minutes (or until reset).
									</div>
									<div>
										<input
											className={narrowInputClass}
											value={String(draft.ip_fails.count)}
											onChange={(e) =>
												setFail("ip_fails", "count", e.target.value)
											}
										/>{" "}
										failed logins for a given <strong>IP</strong> over{" "}
										<input
											className={narrowInputClass}
											value={String(draft.ip_fails.time)}
											onChange={(e) =>
												setFail("ip_fails", "time", e.target.value)
											}
										/>{" "}
										minutes bans the <strong>IP</strong> for{" "}
										<input
											className={narrowInputClass}
											value={String(draft.ip_fails.ban)}
											onChange={(e) =>
												setFail("ip_fails", "ban", e.target.value)
											}
										/>{" "}
										hours.
									</div>
								</div>
							</section>

							<section>
								<h3 className="mb-2 text-[13px] font-semibold text-text">
									Passwords
								</h3>

								<div className="space-y-2">
									<Checkbox
										label="Require mixed-case characters"
										checked={!!draft.password.mixedcase}
										onChange={(on) => togglePassword("mixedcase", on)}
									/>
									<Checkbox
										label="Require numbers"
										checked={!!draft.password.numbers}
										onChange={(on) => togglePassword("numbers", on)}
									/>
									<Checkbox
										label="Require non-alphanumeric characters (e.g. $ # ^ *)"
										checked={!!draft.password.nonalphanumeric}
										onChange={(on) => togglePassword("nonalphanumeric", on)}
									/>
									<Checkbox
										label="Email invitations for users to set their initial password"
										checked={!!draft.password.invitations}
										onChange={(on) => togglePassword("invitations", on)}
									/>
								</div>

								<div className="mt-3 max-w-[220px]">
									<Field label="Minimum password length (0 = no minimum)">
										<input
											className={inputClass}
											value={String(draft.password.length)}
											onChange={(e) => setPassword("length", e.target.value)}
										/>
									</Field>
								</div>
							</section>

							<section>
								<h3 className="mb-2 text-[13px] font-semibold text-text">
									Login options
								</h3>

								<div className="space-y-2">
									<Checkbox
										label="Enable two-factor authentication (Google Authenticator)"
										checked={draft.two_factor === "google"}
										onChange={(on) => toggle("two_factor", on, "google")}
									/>
									<Checkbox
										label='Disable "Remember Me"'
										checked={!!draft.remember_disabled}
										onChange={(on) => toggle("remember_disabled", on)}
									/>
								</div>
							</section>
						</div>

						<div className="space-y-5">
							<Field label="Allowed IP ranges">
								<textarea
									className={`${inputClass} h-24 resize-y font-mono text-[12px]`}
									placeholder="e.g. 192.168.1.1, 192.168.1.128"
									value={draft.allowed_ips}
									onChange={(e) =>
										setDraft((d) =>
											d ? { ...d, allowed_ips: e.target.value } : d
										)
									}
								/>
								<p className="mt-1 text-[11.5px] text-text-3">
									One range per line — two IPs separated by a comma. Leave blank
									to allow all.
								</p>
							</Field>

							<Field label="Permanently banned IPs">
								<textarea
									className={`${inputClass} h-24 resize-y font-mono text-[12px]`}
									value={draft.banned_ips}
									onChange={(e) =>
										setDraft((d) =>
											d ? { ...d, banned_ips: e.target.value } : d
										)
									}
								/>
								<p className="mt-1 text-[11.5px] text-text-3">One IP per line.</p>
							</Field>
						</div>
					</div>
				</FormShell>
			)}

			<UnbanPanel />
		</DebugLayout>
	);
};

interface CheckboxProps {
	label: string;
	checked: boolean;
	onChange: (checked: boolean) => void;
}

const Checkbox = ({ label, checked, onChange }: CheckboxProps) => (
	<label className="flex cursor-pointer items-start gap-2 text-[12.5px] text-text-2">
		<input
			type="checkbox"
			checked={checked}
			onChange={(e) => onChange(e.target.checked)}
			className="mt-0.5 accent-[var(--accent)]"
		/>
		<span>{label}</span>
	</label>
);

/**
 * Lifts active bans. Separate from the policy form since these are immediate
 * actions, not part of the saved draft.
 */
const UnbanPanel = () => {
	const [ip, setIp] = useState("");
	const [userId, setUserId] = useState("");

	const unbanIP = useMutation({
		mutationFn: (value: string) => systemApi.bans.unbanIP(value),
		onSuccess: () => {
			toast.success("IP unbanned");
			setIp("");
		},
		onError: (err) => {
			const msg = err instanceof ApiError && err.message ? err.message : "Could not unban IP";
			toast.error(msg);
		},
	});

	const unbanUser = useMutation({
		mutationFn: (value: number) => systemApi.bans.unbanUser(value),
		onSuccess: () => {
			toast.success("User unbanned");
			setUserId("");
		},
		onError: (err) => {
			const msg =
				err instanceof ApiError && err.message ? err.message : "Could not unban user";
			toast.error(msg);
		},
	});

	return (
		<section className="mt-6 overflow-hidden rounded-xl border border-border bg-surface">
			<header className="flex items-center gap-2 border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				<ShieldOff size={13} />
				Lift a login ban
			</header>

			<div className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
				<form
					className="flex items-end gap-2"
					onSubmit={(e) => {
						e.preventDefault();

						if (ip.trim()) {
							unbanIP.mutate(ip.trim());
						}
					}}
				>
					<div className="flex-1">
						<Field label="Unban IP address">
							<input
								className={inputClass}
								placeholder="e.g. 203.0.113.5"
								value={ip}
								onChange={(e) => setIp(e.target.value)}
							/>
						</Field>
					</div>
					<button
						type="submit"
						disabled={!ip.trim() || unbanIP.isPending}
						className="mb-[1px] rounded-md border border-border bg-surface px-3 py-2 text-[12.5px] font-medium text-text-2 hover:border-border-strong hover:bg-hover disabled:opacity-60"
					>
						Unban
					</button>
				</form>

				<form
					className="flex items-end gap-2"
					onSubmit={(e) => {
						e.preventDefault();
						const parsed = Number(userId);

						if (Number.isInteger(parsed) && parsed > 0) {
							unbanUser.mutate(parsed);
						}
					}}
				>
					<div className="flex-1">
						<Field label="Unban user (ID)">
							<input
								className={inputClass}
								placeholder="e.g. 42"
								value={userId}
								onChange={(e) => setUserId(e.target.value)}
							/>
						</Field>
					</div>
					<button
						type="submit"
						disabled={!userId.trim() || unbanUser.isPending}
						className="mb-[1px] rounded-md border border-border bg-surface px-3 py-2 text-[12.5px] font-medium text-text-2 hover:border-border-strong hover:bg-hover disabled:opacity-60"
					>
						Unban
					</button>
				</form>
			</div>
		</section>
	);
};
