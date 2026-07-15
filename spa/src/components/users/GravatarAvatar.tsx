import { Avatar } from "@/components/ui/Avatar";

interface GravatarAvatarProps {
	className?: string;
	email: string;
	/** Display name — drives the initials/color fallback when no Gravatar exists. */
	name?: string | null;
	size?: number;
}

/**
 * Thin wrapper over `ui/Avatar` in Gravatar mode — kept as a named, intent-revealing
 * entry point for the user-facing avatars (Profile, UserEdit, access dialogs).
 * Renders the account's Gravatar, falling back to an initials disc.
 */
export const GravatarAvatar = ({ email, name, size = 56, className }: GravatarAvatarProps) => (
	<Avatar gravatar className={className} email={email} name={name} size={size} />
);
