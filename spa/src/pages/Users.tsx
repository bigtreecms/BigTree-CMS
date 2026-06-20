import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
	ChevronDown,
	ChevronLeft,
	ChevronUp,
	Edit,
	Key,
	Plus,
	Search,
	Trash,
	X,
} from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Field } from "@/components/ui/Field";
import { Pager } from "@/components/ui/Pager";
import { SelectField } from "@/components/ui/SelectField";
import { SubNav } from "@/components/ui/SubNav";
import { TextField } from "@/components/ui/TextField";
import { TextInput } from "@/components/ui/TextInput";
import { TimezoneSelect } from "@/components/users/TimezoneSelect";
import { isDeveloper } from "@/lib/permissions";
import { toast } from "@/lib/toast";
import {
	usersApi,
	type UserListItem,
	type UserLevelLabel,
	levelToLabel,
	labelToLevel,
} from "@/api/endpoints/users";

// Types
interface User {
	id: number;
	first: string;
	last: string;
	email: string;
	company: string;
	level: UserLevelLabel;
}

/** Map an API user item into the local UI shape (splitting name for the form). */
function mapApiUserToUi(user: UserListItem): User {
	const parts = user.name.trim().split(/\s+/);
	const first = parts[0] ?? "";
	const last = parts.slice(1).join(" ");

	return {
		id: user.id,
		first,
		last,
		email: user.email,
		company: user.company ?? "",
		level: levelToLabel(user.level),
	};
}

type View = "list" | "add";
type SortKey = "name" | "email" | "company" | "level";
type SortDir = "asc" | "desc";

// No longer using large static mock data — we fetch from the real API below.

const USERS_PER_PAGE = 15;

// Deterministic hue from name (stable across renders, matches prototype)
const hueFor = (first: string, last: string): number => {
	let h = 0;
	const s = first + last;

	for (let i = 0; i < s.length; i++) {
		h = (h * 31 + s.charCodeAt(i)) >>> 0;
	}

	return h % 360;
};

interface AvatarProps {
	first: string;
	last: string;
	size?: number;
}

const Avatar = ({ first, last, size = 28 }: AvatarProps) => {
	const h = hueFor(first, last);
	const initials = (first[0] || "?") + (last[0] || "");

	return (
		<span
			className="inline-grid place-items-center rounded-full font-bold select-none tabular-nums"
			style={{
				width: size,
				height: size,
				background: `oklch(72% 0.08 ${h})`,
				color: `oklch(22% 0.04 ${h})`,
				fontSize: size <= 24 ? 10 : 11,
			}}
			aria-hidden="true"
		>
			{initials}
		</span>
	);
};

interface LevelBadgeProps {
	level: User["level"];
}

const LevelBadge = ({ level }: LevelBadgeProps) => {
	if (level === "Developer") {
		return (
			<span className="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-px text-[11px] font-medium text-accent">
				<Key size={9} /> Developer
			</span>
		);
	}

	if (level === "Administrator") {
		return (
			<span className="inline-flex items-center gap-1.5 rounded-full bg-info-bg px-2 py-px text-[11px] font-medium text-info">
				<span className="inline-block h-1.5 w-1.5 rounded-full bg-current" />
				Administrator
			</span>
		);
	}

	return (
		<span className="inline-flex items-center rounded-full border border-border bg-surface-2 px-2 py-px text-[11px] font-medium text-text-3">
			Normal User
		</span>
	);
};

interface SwitchProps {
	on: boolean;
	onChange: (next: boolean) => void;
	label?: string;
}

const Switch = ({ on, onChange, label }: SwitchProps) => (
	<button
		type="button"
		className="switch inline-flex h-5 w-9 items-center rounded-full border border-border bg-surface p-0.5 transition-colors data-[on=true]:bg-accent"
		data-on={on}
		onClick={() => onChange(!on)}
		aria-label={label}
		aria-pressed={on}
	>
		<span
			className="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform data-[on=true]:translate-x-4"
			data-on={on}
		/>
	</button>
);

// Main component
export const Users = () => {
	const queryClient = useQueryClient();
	const currentUser = useAuthStore((s) => s.user);
	const navigate = useNavigate();

	const [view, setView] = useState<View>("list");
	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const [sort, setSort] = useState<{ key: SortKey; dir: SortDir }>({ key: "name", dir: "asc" });
	const [confirmDelete, setConfirmDelete] = useState<User | null>(null);

	// Add User form state
	const [first, setFirst] = useState("");
	const [last, setLast] = useState("");
	const [email, setEmail] = useState("");
	const [company, setCompany] = useState("");
	const [level, setLevel] = useState<User["level"]>("Normal User");
	const [timezone, setTimezone] = useState("");
	const [dailyDigest, setDailyDigest] = useState(true);
	const [sendInvite, setSendInvite] = useState(true);
	const [password, setPassword] = useState("");

	const firstRef = useRef<HTMLInputElement>(null);

	// Server-side paginated list (real API)
	// Using keepPreviousData so the previous page's data (including total count for the Pager)
	// remains stable during refetches caused by mutations or page changes.
	const {
		data: listResponse,
		isLoading,
		isPlaceholderData,
	} = useQuery({
		queryKey: ["users", "list", { page, q: query }],
		queryFn: () =>
			usersApi.list({
				q: query || undefined,
				page,
				per_page: USERS_PER_PAGE,
			}),
		placeholderData: keepPreviousData,
	});

	const apiUsers = listResponse?.items ?? [];
	const meta = listResponse?.meta ?? {};

	const uiUsers: User[] = apiUsers.map(mapApiUserToUi);

	// Client-side sort only (search + pagination are server-driven)
	const rows = [...uiUsers].sort((a, b) => {
		const dir = sort.dir === "asc" ? 1 : -1;

		if (sort.key === "name") {
			return dir * (a.first + a.last).localeCompare(b.first + b.last);
		}
		if (sort.key === "email") {
			return dir * a.email.localeCompare(b.email);
		}
		if (sort.key === "company") {
			return dir * a.company.localeCompare(b.company);
		}
		if (sort.key === "level") {
			return dir * a.level.localeCompare(b.level);
		}
		return 0;
	});

	// Use server meta for pagination
	const totalFromServer = meta.total ?? rows.length;
	const totalPages = Math.max(1, Math.ceil(totalFromServer / USERS_PER_PAGE));
	const safePage = Math.min(page, totalPages);
	const pageRows = rows; // server already returned the correct page of results

	useEffect(() => {
		setPage(1);
	}, [query, sort.key, sort.dir]);

	useEffect(() => {
		if (view === "add") {
			setTimeout(() => {
				firstRef.current?.focus();
			}, 60);
		}
	}, [view]);

	const toggleSort = (key: SortKey) => {
		setSort((s) => {
			if (s.key === key) {
				return { key, dir: s.dir === "asc" ? "desc" : "asc" };
			}

			return { key, dir: "asc" };
		});
	};

	const sortCaret = (key: SortKey) => {
		if (sort.key !== key) {
			return <ChevronDown size={10} className="opacity-40" />;
		}

		if (sort.dir === "asc") {
			return <ChevronUp size={10} />;
		}

		return <ChevronDown size={10} />;
	};

	const deleteUserMutation = useMutation({
		mutationFn: (user: User) => {
			// We need the real ID. Since our local User doesn't carry id,
			// we look it up from the last API response by email.
			const apiUser = apiUsers.find((u) => u.email === user.email);
			if (!apiUser) throw new Error("User not found");

			return usersApi.delete(apiUser.id);
		},
		onSuccess: (_, user) => {
			queryClient.invalidateQueries({ queryKey: ["users"] });
			toast.success(`Deleted ${user.first} ${user.last}`);
		},
		onError: () => {
			toast.error("Failed to delete user");
		},
	});

	const handleDelete = (user: User) => {
		deleteUserMutation.mutate(user);
	};

	const createUserMutation = useMutation({
		mutationFn: () => {
			const name = `${first.trim()} ${last.trim()}`.trim();

			return usersApi.create({
				email,
				name,
				company: company || undefined,
				level: labelToLevel(level),
				timezone: timezone || undefined,
				daily_digest: dailyDigest,
				password: !sendInvite && password ? password : undefined,
			});
		},
		onSuccess: (created) => {
			queryClient.invalidateQueries({ queryKey: ["users"] });
			toast.success("User created" + (sendInvite ? " — invitation sent" : ""));

			// Reset form
			setFirst("");
			setLast("");
			setEmail("");
			setCompany("");
			setLevel("Normal User");
			setTimezone("");
			setDailyDigest(true);
			setSendInvite(true);
			setPassword("");

			navigate(`/users/${created.id}/edit`);
		},
		onError: () => {
			toast.error("Failed to create user");
		},
	});

	const submitAddUser = (e: React.FormEvent) => {
		e.preventDefault();

		if (!first.trim() || !last.trim() || !/.+@.+\..+/.test(email)) {
			return;
		}

		createUserMutation.mutate();
	};

	const validAdd = first.trim() && last.trim() && /.+@.+\..+/.test(email);

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb
				items={[{ label: "Users" }, { label: view === "add" ? "Add User" : "View Users" }]}
			/>

			<PageHead
				title="Users"
				sub={
					view === "add"
						? "Create a new admin account."
						: `${totalFromServer} accounts have access to this site.`
				}
				actions={
					view === "list" ? (
						<Button
							variant="primary"
							icon={<Plus size={13} />}
							onClick={() => setView("add")}
						>
							Add user
						</Button>
					) : (
						<Button icon={<ChevronLeft size={13} />} onClick={() => setView("list")}>
							Back to list
						</Button>
					)
				}
			/>

			<SubNav<View>
				className="mb-4"
				value={view}
				onChange={setView}
				items={[
					{ value: "list", label: "View Users" },
					{ value: "add", label: "Add User", icon: <Plus size={13} /> },
				]}
			/>

			{view === "list" && (
				<>
					{/* Toolbar */}
					<div className="mb-3 flex flex-wrap items-center gap-3">
						<div className="relative flex-1 max-w-md">
							<Search
								size={14}
								className="absolute left-3 top-1/2 -translate-y-1/2 text-text-3"
							/>
							<input
								className="w-full rounded-md border border-border bg-surface py-1.5 pl-9 pr-9 text-[13.5px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring"
								placeholder="Search by name, email, company…"
								value={query}
								onChange={(e) => setQuery(e.target.value)}
							/>
							{query && (
								<button
									type="button"
									className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-text-3 hover:bg-hover hover:text-text"
									onClick={() => setQuery("")}
									aria-label="Clear search"
								>
									<X size={14} />
								</button>
							)}
						</div>

						<div className="flex-1" />

						<span className="text-[12px] text-text-3 tabular-nums">
							{query
								? `${rows.length} of ${totalFromServer}`
								: `${totalFromServer} users`}
						</span>

						<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
					</div>

					{/* Users Table (CSS grid to match prototype responsive behavior) */}
					<div className="overflow-hidden rounded-xl border border-border bg-surface">
						{/* Head */}
						<div className="hidden md:grid grid-cols-[minmax(0,1.3fr)_minmax(0,1.7fr)_minmax(0,1.4fr)_140px_74px] items-center gap-4 border-b border-border bg-surface-2 px-3.5 py-2 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
							<button
								type="button"
								className="flex items-center gap-1 text-left hover:text-text"
								onClick={() => toggleSort("name")}
							>
								Name {sortCaret("name")}
							</button>
							<button
								type="button"
								className="flex items-center gap-1 text-left hover:text-text"
								onClick={() => toggleSort("email")}
							>
								Email {sortCaret("email")}
							</button>
							<button
								type="button"
								className="flex items-center gap-1 text-left hover:text-text"
								onClick={() => toggleSort("company")}
							>
								Company {sortCaret("company")}
							</button>
							<button
								type="button"
								className="flex items-center gap-1 text-left hover:text-text"
								onClick={() => toggleSort("level")}
							>
								User level {sortCaret("level")}
							</button>
							<div className="text-right">Actions</div>
						</div>

						{isLoading && !isPlaceholderData ? (
							<div className="p-9 text-center text-[13px] text-text-3">
								Loading users…
							</div>
						) : pageRows.length === 0 ? (
							<div className="p-9 text-center text-[13px] text-text-3">
								No users match “{query}”.
							</div>
						) : (
							pageRows.map((u) => {
								return (
									<div
										key={u.id}
										role="button"
										tabIndex={0}
										onClick={() => navigate(`/users/${u.id}/edit`)}
										onKeyDown={(e) => {
											if (e.key === "Enter" || e.key === " ") {
												e.preventDefault();
												navigate(`/users/${u.id}/edit`);
											}
										}}
										className="grid grid-cols-1 gap-x-4 border-b border-border px-3.5 py-2 text-[13px] last:border-b-0 hover:bg-surface-2 cursor-pointer md:grid-cols-[minmax(0,1.3fr)_minmax(0,1.7fr)_minmax(0,1.4fr)_140px_74px] md:items-center md:py-1.5"
									>
										{/* Name + Avatar */}
										<div className="flex items-center gap-3 md:gap-2.5">
											<Avatar first={u.first} last={u.last} />
											<span className="font-medium text-text">
												{u.first} {u.last}
											</span>
										</div>

										{/* Email */}
										<div
											className="pl-11 text-[11.5px] text-text-2 md:pl-0 font-mono tabular-nums truncate"
											title={u.email}
										>
											{u.email}
										</div>

										{/* Company */}
										<div
											className="pl-11 text-[12.5px] text-text-2 md:pl-0 truncate"
											title={u.company}
										>
											{u.company}
										</div>

										{/* Level */}
										<div className="pl-11 md:pl-0">
											<LevelBadge level={u.level} />
										</div>

										{/* Actions */}
										<div className="flex items-center justify-end gap-1 pl-11 md:pl-0">
											<button
												type="button"
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
												title="Edit"
												onClick={(e) => {
													e.stopPropagation();
													navigate(`/users/${u.id}/edit`);
												}}
											>
												<Edit size={15} />
											</button>
											<button
												type="button"
												className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
												title="Delete"
												onClick={(e) => {
													e.stopPropagation();
													setConfirmDelete(u);
												}}
											>
												<Trash size={15} />
											</button>
										</div>
									</div>
								);
							})
						)}
					</div>

					{totalPages > 1 && (
						<div className="mt-3 flex items-center justify-between text-[12px] text-text-3">
							<span className="tabular-nums">
								Showing {(safePage - 1) * USERS_PER_PAGE + 1}–
								{Math.min(safePage * USERS_PER_PAGE, rows.length)} of{" "}
								{totalFromServer}
							</span>
							<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
						</div>
					)}
				</>
			)}

			{view === "add" && (
				<form
					onSubmit={submitAddUser}
					className="max-w-2xl rounded-xl border border-border bg-surface"
				>
					<div className="flex items-baseline justify-between border-b border-border bg-surface-2 px-4 py-3 text-[12.5px]">
						<span className="font-semibold">Account details</span>
						<span className="text-text-3">Required fields marked with *</span>
					</div>

					<div className="grid grid-cols-1 gap-x-6 gap-y-4 p-4 md:grid-cols-2">
						<Field label="First name" required>
							<TextInput
								ref={firstRef}
								value={first}
								onChange={(e) => setFirst(e.target.value)}
								placeholder="e.g. Alex"
							/>
						</Field>
						<TextField
							label="Last name"
							required
							value={last}
							onChange={setLast}
							placeholder="e.g. Chen"
						/>

						<TextField
							className="md:col-span-2"
							label="Email address"
							required
							type="email"
							value={email}
							onChange={setEmail}
							placeholder="name@northwind-energy.local"
							hint="Used for login and password-reset emails."
						/>

						<TextField
							label="Company / Team"
							value={company}
							onChange={setCompany}
							placeholder="e.g. Northwind Digital Team"
						/>

						<SelectField
							label="User level"
							value={level}
							onChange={(next) => setLevel(next as User["level"])}
							options={[
								{ value: "Normal User", label: "Normal User" },
								{ value: "Administrator", label: "Administrator" },
								...(isDeveloper(currentUser)
									? [{ value: "Developer", label: "Developer" }]
									: []),
							]}
							hint={
								level === "Developer"
									? "Full access including the Developer section (templates, module designer, configure, debug)."
									: level === "Administrator"
										? "Manage pages, modules, files, settings, and other users."
										: "Per-resource access only — set up grants after creation."
							}
						/>

						<div>
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								Timezone
							</label>
							<TimezoneSelect value={timezone} onChange={setTimezone} />
						</div>

						<div className="md:col-span-2">
							<label className="flex items-center gap-2">
								<input
									type="checkbox"
									checked={dailyDigest}
									onChange={(e) => setDailyDigest(e.target.checked)}
									className="h-4 w-4 cursor-pointer accent-accent"
								/>
								<span className="text-[12.5px] text-text-2">
									Send daily digest email
								</span>
							</label>
						</div>
					</div>

					<div className="mx-4 mb-4 rounded-lg border border-border bg-surface-2 p-3">
						<div className="flex items-center gap-3">
							<Switch
								on={sendInvite}
								onChange={setSendInvite}
								label="Send invite email"
							/>
							<div className="flex-1">
								<div className="text-[12.5px] font-medium">
									Send invitation email
								</div>
								<div className="text-[11.5px] text-text-3">
									{sendInvite
										? "User receives an email to set their own password."
										: "Set an initial password for the user below."}
								</div>
							</div>
						</div>

						{!sendInvite && (
							<Field label="Initial password" className="mt-3">
								<TextInput
									type="password"
									autoComplete="new-password"
									value={password}
									onChange={(e) => setPassword(e.target.value)}
									placeholder="At least 8 characters"
								/>
							</Field>
						)}
					</div>

					<div className="flex justify-end gap-2 border-t border-border bg-surface-2 px-4 py-3">
						<Button variant="secondary" onClick={() => setView("list")}>
							Cancel
						</Button>
						<Button
							variant="primary"
							type="submit"
							disabled={!validAdd || createUserMutation.isPending}
						>
							{createUserMutation.isPending ? (
								"Creating..."
							) : (
								<>
									<Plus size={14} />
									Create user
								</>
							)}
						</Button>
					</div>
				</form>
			)}

			{/* Delete confirmation */}
			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDelete(null);
						}
					}}
					title="Delete user?"
					description="This will revoke admin access immediately. Page revisions authored by this user remain attributed to them."
					confirmLabel="Delete user"
					variant="danger"
					onConfirm={() => handleDelete(confirmDelete)}
				/>
			)}
		</div>
	);
};
