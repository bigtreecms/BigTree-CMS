import { useEffect, useRef, useState } from "react";
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

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Pager } from "@/components/ui/Pager";
import { SuccessToast } from "@/components/ui/SuccessToast";
import { usersApi, type UserListItem, levelToLabel, labelToLevel } from "@/api/endpoints/users";

// Types
interface User {
	first: string;
	last: string;
	email: string;
	company: string;
	level: "Administrator" | "Editor" | "Normal";
}

/** Map an API user item into the local UI shape (splitting name for the form). */
function mapApiUserToUi(user: UserListItem): User {
	const parts = user.name.trim().split(/\s+/);
	const first = parts[0] ?? "";
	const last = parts.slice(1).join(" ");

	return {
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
	if (level === "Administrator") {
		return (
			<span className="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-px text-[11px] font-medium text-accent">
				<Key size={9} /> Administrator
			</span>
		);
	}
	if (level === "Editor") {
		return (
			<span className="inline-flex items-center gap-1.5 rounded-full bg-info-bg px-2 py-px text-[11px] font-medium text-info">
				<span className="inline-block h-1.5 w-1.5 rounded-full bg-current" />
				Editor
			</span>
		);
	}
	return (
		<span className="inline-flex items-center rounded-full border border-border bg-surface-2 px-2 py-px text-[11px] font-medium text-text-3">
			Normal
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

	const [view, setView] = useState<View>("list");
	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const [sort, setSort] = useState<{ key: SortKey; dir: SortDir }>({ key: "name", dir: "asc" });
	const [confirmDelete, setConfirmDelete] = useState<User | null>(null);
	const [toasts, setToasts] = useState<Array<{ id: number; title: string }>>([]);

	// Add User form state
	const [first, setFirst] = useState("");
	const [last, setLast] = useState("");
	const [email, setEmail] = useState("");
	const [company, setCompany] = useState("");
	const [level, setLevel] = useState<User["level"]>("Normal");
	const [sendInvite, setSendInvite] = useState(true);

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

	const addToast = (title: string) => {
		const id = Date.now();
		setToasts((t) => [...t, { id, title }]);

		setTimeout(() => {
			setToasts((t) => t.filter((x) => x.id !== id));
		}, 3200);
	};

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
			addToast(`Deleted ${user.first} ${user.last}`);
		},
	});

	const handleDelete = (user: User) => {
		setConfirmDelete(null);
		deleteUserMutation.mutate(user);
	};

	const createUserMutation = useMutation({
		mutationFn: (payload: {
			first: string;
			last: string;
			email: string;
			company: string;
			level: User["level"];
		}) => {
			const name = `${payload.first.trim()} ${payload.last.trim()}`.trim();
			return usersApi.create({
				email: payload.email,
				name,
				company: payload.company || undefined,
				level: labelToLevel(payload.level),
			});
		},
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["users"] });
			addToast("User created" + (sendInvite ? " — invite sent" : ""));

			// Reset form and go back to list
			setFirst("");
			setLast("");
			setEmail("");
			setCompany("");
			setLevel("Normal");
			setSendInvite(true);
			setView("list");
		},
	});

	const submitAddUser = (e: React.FormEvent) => {
		e.preventDefault();

		if (!first.trim() || !last.trim() || !/.+@.+\..+/.test(email)) {
			return;
		}

		createUserMutation.mutate({
			first,
			last,
			email,
			company,
			level,
		});
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
						<button
							type="button"
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-medium text-accent-fg hover:bg-accent-hover"
							onClick={() => setView("add")}
						>
							<Plus size={14} />
							<span>Add user</span>
						</button>
					) : (
						<button
							type="button"
							className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-sm hover:bg-hover"
							onClick={() => setView("list")}
						>
							<ChevronLeft size={14} />
							<span>Back to list</span>
						</button>
					)
				}
			/>

			{/* Sub-nav — matches original BigTree + prototype exactly */}
			<div className="mb-4 inline-flex rounded-md border border-border bg-surface p-0.5 text-[12.5px] font-medium">
				<button
					type="button"
					className={`flex items-center gap-1.5 rounded px-3 py-1.5 transition-colors ${view === "list" ? "bg-accent-soft text-accent font-semibold" : "text-text-2 hover:bg-hover hover:text-text"}`}
					onClick={() => setView("list")}
				>
					<span>View Users</span>
				</button>
				<button
					type="button"
					className={`flex items-center gap-1.5 rounded px-3 py-1.5 transition-colors ${view === "add" ? "bg-accent-soft text-accent font-semibold" : "text-text-2 hover:bg-hover hover:text-text"}`}
					onClick={() => setView("add")}
				>
					<Plus size={13} />
					<span>Add User</span>
				</button>
			</div>

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
								const id = `${u.first}-${u.last}`;
								return (
									<div
										key={id}
										className="grid grid-cols-1 gap-x-4 border-b border-border px-3.5 py-2 text-[13px] last:border-b-0 hover:bg-surface-2 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1.7fr)_minmax(0,1.4fr)_140px_74px] md:items-center md:py-1.5"
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
												onClick={(e) => e.stopPropagation()}
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
						<div>
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								First name *
							</label>
							<input
								ref={firstRef}
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={first}
								onChange={(e) => setFirst(e.target.value)}
								placeholder="e.g. Alex"
							/>
						</div>
						<div>
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								Last name *
							</label>
							<input
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={last}
								onChange={(e) => setLast(e.target.value)}
								placeholder="e.g. Chen"
							/>
						</div>

						<div className="md:col-span-2">
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								Email address *
							</label>
							<input
								type="email"
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={email}
								onChange={(e) => setEmail(e.target.value)}
								placeholder="name@northwind-energy.local"
							/>
							<p className="mt-1 text-[12px] text-text-3">
								Used for login and password-reset emails.
							</p>
						</div>

						<div>
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								Company / Team
							</label>
							<input
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={company}
								onChange={(e) => setCompany(e.target.value)}
								placeholder="e.g. Northwind Digital Team"
							/>
						</div>

						<div>
							<label className="mb-1 block text-[12px] font-medium text-text-2">
								User level
							</label>
							<select
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={level}
								onChange={(e) => setLevel(e.target.value as User["level"])}
							>
								<option value="Normal">Normal</option>
								<option value="Editor">Editor</option>
								<option value="Administrator">Administrator</option>
							</select>
							<p className="mt-1 text-[12px] text-text-3">
								{level === "Administrator" &&
									"Full access — manage users, settings, and developer tools."}
								{level === "Editor" &&
									"Manage pages, modules, and files. Cannot edit settings."}
								{level === "Normal" && "Edit assigned pages and modules only."}
							</p>
						</div>
					</div>

					<div className="mx-4 mb-4 rounded-lg border border-border bg-surface-2 p-3">
						<div className="flex items-center gap-3">
							<Switch
								on={sendInvite}
								onChange={setSendInvite}
								label="Send invite email"
							/>
							<div>
								<div className="text-[12.5px] font-medium">
									Send invitation email
								</div>
								<div className="text-[11.5px] text-text-3">
									{sendInvite
										? "User will receive a magic link to set their password."
										: "Account created in a pending state."}
								</div>
							</div>
						</div>
					</div>

					<div className="flex justify-end gap-2 border-t border-border bg-surface-2 px-4 py-3">
						<button
							type="button"
							className="rounded-md border border-border px-4 py-1.5 text-sm hover:bg-hover"
							onClick={() => setView("list")}
						>
							Cancel
						</button>
						<button
							type="submit"
							disabled={!validAdd || createUserMutation.isPending}
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-4 py-1.5 text-sm font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
						>
							{createUserMutation.isPending ? (
								"Creating..."
							) : (
								<>
									<Plus size={14} />
									Create user
								</>
							)}
						</button>
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

			{/* Toasts */}
			{toasts.length > 0 && (
				<div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
					{toasts.map((t) => (
						<SuccessToast
							key={t.id}
							title={t.title}
							onClose={() => setToasts((ts) => ts.filter((x) => x.id !== t.id))}
						/>
					))}
				</div>
			)}
		</div>
	);
};
