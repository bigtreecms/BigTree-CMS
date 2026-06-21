import { useQuery } from "@tanstack/react-query";
import { ShieldCheck, UserPen } from "lucide-react";

import { SlideOver } from "@/components/ui/SlideOver";
import { GravatarAvatar } from "@/components/users/GravatarAvatar";

import { pagesApi, type PageAccessUser } from "@/api/endpoints/pages";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface AccessLevelsDialogProps {
	open: boolean;
	onOpenChange: (open: boolean) => void;
	/** Page whose access is being inspected (null hides the dialog). */
	pageId: number | null;
}

/**
 * Read-only breakdown of who can edit vs. publish a page — the SPA's take on
 * legacy pages/access-levels.php. Admin-only (the endpoint enforces it);
 * permissions themselves are changed per-user in the user editor.
 */
export const AccessLevelsDialog = ({ open, onOpenChange, pageId }: AccessLevelsDialogProps) => {
	const query = useQuery({
		queryKey: ["pages", "access-levels", pageId],
		queryFn: () => pagesApi.accessLevels(pageId as number),
		enabled: open && pageId !== null,
	});

	return (
		<SlideOver
			open={open}
			onOpenChange={onOpenChange}
			title="Access levels"
			description="Who can edit or publish this page. Change access in each user's editor."
		>
			{query.isLoading ? (
				<div className="grid h-32 place-items-center text-[13px] text-text-3">Loading…</div>
			) : query.error ? (
				<div className="rounded-md border border-danger/30 bg-danger-bg px-3 py-2 text-[12.5px] text-danger">
					Could not load access levels.
				</div>
			) : (
				<div className="space-y-5">
					<UserList
						title="Publishers"
						icon={<ShieldCheck size={13} />}
						hint="Can edit and make changes live."
						users={query.data?.publishers ?? []}
						empty="No one can publish this page."
					/>
					<UserList
						title="Editors"
						icon={<UserPen size={13} />}
						hint="Can edit; changes await publisher approval."
						users={query.data?.editors ?? []}
						empty="No editor-level users for this page."
					/>
				</div>
			)}
		</SlideOver>
	);
};

interface UserListProps {
	title: string;
	icon: React.ReactNode;
	hint: string;
	users: PageAccessUser[];
	empty: string;
}

const UserList = ({ title, icon, hint, users, empty }: UserListProps) => (
	<section>
		<h3 className="mb-0.5 flex items-center gap-1.5 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
			{icon}
			{title}
			<span className="tabular-nums">({users.length})</span>
		</h3>
		<p className="mb-2 text-[11.5px] text-text-3">{hint}</p>

		{users.length === 0 ? (
			<InlineEmpty pad="sm">{empty}</InlineEmpty>
		) : (
			<ul className="divide-y divide-border rounded-md border border-border bg-surface">
				{users.map((user) => (
					<li key={user.id} className="flex items-center gap-2.5 px-3 py-2">
						<GravatarAvatar email={user.email} size={24} />
						<div className="min-w-0">
							<div className="truncate text-[12.5px] font-medium">{user.name}</div>
							<div className="truncate text-[11.5px] text-text-3">{user.email}</div>
						</div>
					</li>
				))}
			</ul>
		)}
	</section>
);
