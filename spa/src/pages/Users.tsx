import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { ChevronDown, ChevronLeft, ChevronUp, Edit, Key, Plus, Trash } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Avatar } from "@/components/ui/Avatar";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Loading } from "@/components/ui/Loading";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { Field } from "@/components/ui/Field";
import { Pager } from "@/components/ui/Pager";
import { SearchInput } from "@/components/ui/SearchInput";
import { Toolbar } from "@/components/ui/Toolbar";
import { SelectField } from "@/components/ui/SelectField";
import { SubNav } from "@/components/ui/SubNav";
import { Switch } from "@/components/ui/Switch";
import { TextField } from "@/components/ui/TextField";
import { TextInput } from "@/components/ui/TextInput";
import { Card, CardFooter, CardHeader } from "@/components/ui/Card";
import { IconButton } from "@/components/ui/IconButton";
import { TimezoneSelect } from "@/components/users/TimezoneSelect";
import { toast } from "@/lib/toast";
import { derivePagination } from "@/lib/pagination";
import { queryKeys } from "@/lib/queryKeys";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useToastMutation } from "@/hooks/useToastMutation";
import {
	usersApi,
	type UserListItem,
	type UserLevelLabel,
	levelToLabel,
} from "@/api/endpoints/users";
import { userLevelHint, userLevelOptions } from "@/components/users/userLevels";

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

interface LevelBadgeProps {
	level: User["level"];
}

const LevelBadge = ({ level }: LevelBadgeProps) => {
	if (level === "Developer") {
		return (
			<Badge tone="accent" icon={<Key size={9} />}>
				Developer
			</Badge>
		);
	}

	if (level === "Administrator") {
		return (
			<Badge tone="info" dot>
				Administrator
			</Badge>
		);
	}

	return <Badge bordered>Normal User</Badge>;
};

// Main component
export const Users = () => {
	const currentUser = useAuthStore((s) => s.user);
	const navigate = useNavigate();

	const [view, setView] = useState<View>("list");
	const [query, setQuery] = useState("");
	const [page, setPage] = useState(1);
	const [sort, setSort] = useState<{ key: SortKey; dir: SortDir }>({ key: "name", dir: "asc" });
	const deleteDialog = useConfirmDialog<User>();

	// Add User form state
	const [first, setFirst] = useState("");
	const [last, setLast] = useState("");
	const [email, setEmail] = useState("");
	const [company, setCompany] = useState("");
	const [level, setLevel] = useState(0);
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
		queryKey: queryKeys.users.list({ page, q: query }),
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
	const {
		total: totalFromServer,
		totalPages,
		safePage,
	} = derivePagination({
		rows,
		meta,
		page,
		perPage: USERS_PER_PAGE,
	});
	const pageRows = rows; // server already returned the correct page of results

	useEffect(() => {
		setPage(1);
	}, [query, sort.key, sort.dir]);

	useEffect(() => {
		if (view === "add") {
			const timer = setTimeout(() => {
				firstRef.current?.focus();
			}, 60);

			return () => clearTimeout(timer);
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

	const deleteUserMutation = useToastMutation({
		mutationFn: (user: User) => {
			// We need the real ID. Since our local User doesn't carry id,
			// we look it up from the last API response by email.
			const apiUser = apiUsers.find((u) => u.email === user.email);

			if (!apiUser) {
				throw new Error("User not found");
			}

			return usersApi.delete(apiUser.id);
		},
		invalidate: [["users"]],
		errorMessage: "Failed to delete user",
		onSuccess: (_, user) => {
			toast.success(`Deleted ${user.first} ${user.last}`);
		},
	});

	const handleDelete = (user: User) => {
		deleteUserMutation.mutate(user);
	};

	const createUserMutation = useToastMutation({
		mutationFn: () => {
			const name = `${first.trim()} ${last.trim()}`.trim();

			return usersApi.create({
				email,
				name,
				company: company || undefined,
				level,
				timezone: timezone || undefined,
				daily_digest: dailyDigest,
				password: !sendInvite && password ? password : undefined,
			});
		},
		invalidate: [["users"]],
		errorMessage: "Failed to create user",
		onSuccess: (created) => {
			toast.success("User created" + (sendInvite ? " — invitation sent" : ""));

			// Reset form
			setFirst("");
			setLast("");
			setEmail("");
			setCompany("");
			setLevel(0);
			setTimezone("");
			setDailyDigest(true);
			setSendInvite(true);
			setPassword("");

			navigate(`/users/${created.id}/edit`);
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
		<PageContainer width="wide">
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
					<Toolbar
						search={
							<SearchInput
								value={query}
								onChange={setQuery}
								placeholder="Search by name, email, company…"
							/>
						}
					>
						<span className="text-[12px] text-text-3 tabular-nums">
							{query
								? `${rows.length} of ${totalFromServer}`
								: `${totalFromServer} users`}
						</span>

						<Pager page={safePage} totalPages={totalPages} onChange={setPage} />
					</Toolbar>

					{/* Users Table (CSS grid to match prototype responsive behavior) */}
					<Card className="overflow-hidden">
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
							<Loading variant="block" label="Loading users…" />
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
											<Avatar name={`${u.first} ${u.last}`} />
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
											<IconButton
												title="Edit"
												label="Edit"
												onClick={(e) => {
													e.stopPropagation();
													navigate(`/users/${u.id}/edit`);
												}}
											>
												<Edit size={15} />
											</IconButton>
											<IconButton
												tone="danger"
												title="Delete"
												label="Delete"
												onClick={(e) => {
													e.stopPropagation();
													deleteDialog.open(u);
												}}
											>
												<Trash size={15} />
											</IconButton>
										</div>
									</div>
								);
							})
						)}
					</Card>

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
				<Card as="form" onSubmit={submitAddUser} className="max-w-2xl">
					<CardHeader className="flex items-baseline justify-between rounded-t-xl text-[12.5px]">
						<span className="font-semibold">Account details</span>
						<span className="text-text-3">Required fields marked with *</span>
					</CardHeader>

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
							value={String(level)}
							onChange={(next) => setLevel(Number(next))}
							options={userLevelOptions(currentUser)}
							hint={userLevelHint(level)}
						/>

						<Field label="Timezone">
							<TimezoneSelect value={timezone} onChange={setTimezone} />
						</Field>

						<div className="md:col-span-2">
							<Checkbox
								label="Send daily digest email"
								checked={dailyDigest}
								onChange={setDailyDigest}
							/>
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

					<CardFooter className="rounded-b-xl">
						<Button variant="secondary" onClick={() => setView("list")}>
							Cancel
						</Button>
						<Button
							variant="primary"
							type="submit"
							disabled={!validAdd}
							loading={createUserMutation.isPending}
							loadingLabel="Creating…"
							icon={<Plus size={14} />}
						>
							Create user
						</Button>
					</CardFooter>
				</Card>
			)}

			{/* Delete confirmation */}
			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(open) => {
						if (!open) {
							deleteDialog.close();
						}
					}}
					title="Delete user?"
					description="This will revoke admin access immediately. Page revisions authored by this user remain attributed to them."
					confirmLabel="Delete user"
					variant="danger"
					onConfirm={() => handleDelete(deleteDialog.item!)}
				/>
			)}
		</PageContainer>
	);
};
