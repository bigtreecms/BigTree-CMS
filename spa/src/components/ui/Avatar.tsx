import { useState } from "react";

import { gravatarUrl } from "@/lib/gravatar";

interface AvatarProps {
	/** Layout-only classes appended to the wrapper. */
	className?: string;
	/** Email — feeds Gravatar lookup, and the initials/color fallback if no `name`. */
	email?: string | null;
	/**
	 * When set with an `email`, render that account's Gravatar on top of the
	 * initials disc, falling back to the disc if no Gravatar exists.
	 */
	gravatar?: boolean;
	/** Display name — used for both initials and (unless `seed` is set) color. */
	name?: string | null;
	/**
	 * Override the color seed (e.g. a stable user id) when the display name isn't
	 * a reliable identity. Defaults to `name || email`.
	 */
	seed?: string;
	/** Diameter in px. Default 28. */
	size?: number;
}

/**
 * Circular avatar — initials on a deterministic, theme-independent pastel disc,
 * with an optional Gravatar overlay. The single source of truth for the
 * "circle with initials, color derived from a name" pattern previously
 * reimplemented in Users, TopBar, MessagesTable and GravatarAvatar.
 *
 * The disc is `aria-hidden` — it is decorative; the accessible identity comes
 * from the adjacent name text at the call site.
 */
export const Avatar = ({ name, email, size = 28, gravatar, seed, className }: AvatarProps) => {
	const [gravatarFailed, setGravatarFailed] = useState(false);

	const initials = initialsFor(name, email);
	const hue = hueFor(seed ?? name ?? email ?? "");
	const showGravatar = gravatar && !!email && !gravatarFailed;

	return (
		<span
			aria-hidden="true"
			className={`relative inline-grid shrink-0 place-items-center overflow-hidden rounded-full font-semibold tabular-nums select-none${className ? ` ${className}` : ""}`}
			style={{
				width: size,
				height: size,
				background: `oklch(72% 0.08 ${hue})`,
				color: `oklch(22% 0.04 ${hue})`,
				fontSize: Math.max(9, Math.round(size * 0.4)),
			}}
		>
			{initials}
			{showGravatar && (
				<img
					alt=""
					className="absolute inset-0 size-full object-cover"
					height={size}
					loading="lazy"
					src={gravatarUrl(email, size)}
					width={size}
					onError={() => setGravatarFailed(true)}
				/>
			)}
		</span>
	);
};

/** Initials from a name (first + last initial), falling back to the email, then "?". */
const initialsFor = (name?: string | null, email?: string | null): string => {
	const trimmed = name?.trim();

	if (trimmed) {
		const parts = trimmed.split(/\s+/);
		const first = parts[0]?.[0] ?? "";
		const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? "") : "";

		return (first + last).toUpperCase() || "?";
	}

	const emailChar = email?.trim()[0];

	return emailChar ? emailChar.toUpperCase() : "?";
};

/** Deterministic hue (0–359) from a seed string — stable across renders. */
const hueFor = (seed: string): number => {
	let h = 0;

	for (let i = 0; i < seed.length; i++) {
		h = (h * 31 + seed.charCodeAt(i)) >>> 0;
	}

	return h % 360;
};
