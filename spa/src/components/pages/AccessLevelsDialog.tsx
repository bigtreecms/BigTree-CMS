import { useQuery } from "@tanstack/react-query";
import { ShieldCheck, UserPen } from "lucide-react";

import { Alert } from "@/components/ui/Alert";
import { SlideOver } from "@/components/ui/SlideOver";
import { Loading } from "@/components/ui/Loading";
import { GravatarAvatar } from "@/components/users/GravatarAvatar";

import { pagesApi, type PageAccessUser } from "@/api/endpoints/pages";
import { queryKeys } from "@/lib/queryKeys";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { SectionLabel } from "@/components/ui/SectionLabel";

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
		queryKey: queryKeys.pages.accessLevels(pageId as number),
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
				<Loading variant="block" className="h-32" />
			) : query.error ? (
				<Alert tone="danger">Could not load access levels.</Alert>
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
		<SectionLabel as="h3" icon={icon} className="mb-0.5">
			{title}
			<span className="tabular-nums">({users.length})</span>
		</SectionLabel>
		<p className="mb-2 text-[11.5px] text-text-3">{hint}</p>

		{users.length === 0 ? (
			<InlineEmpty pad="sm">{empty}</InlineEmpty>
		) : (
			<ul className="divide-y divide-border rounded-md border border-border bg-surface">
				{users.map((user) => (
					<li key={user.id} className="flex items-center gap-2.5 px-3 py-2">
						<GravatarAvatar email={user.email} name={user.name} size={24} />
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
