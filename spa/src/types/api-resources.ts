/**
 * Resource shapes shared across endpoint modules (pages, modules, files, etc.).
 *
 * These mirror what BigTree's API present()-style helpers return. Keep this
 * file thin — anything that lives entirely under a single endpoint module
 * stays in that module's own file.
 */

export interface Tag {
	id: number;
	tag: string;
	route: string;
	usage_count?: number;
}

export interface ResourceFolder {
	id: number;
	parent: number;
	name: string;
	updated_at?: string;
}

export interface ResourceFile {
	id: number;
	folder: number;
	name: string;
	type: string;
	file: string;
	thumbs?: Record<string, string>;
	is_image: boolean;
	width?: number;
	height?: number;
	size?: number;
	date?: string;
	creator?: number;
}

export interface LockOwner {
	id: number;
	name: string;
	email: string;
}

export interface LockInfo {
	lock_id: number;
	expires_at: string;
}

export interface PendingChangeRef {
	id: number;
	table: string;
	item_id: string;
	user: number;
	date: string;
	type: "EDIT" | "NEW";
}
