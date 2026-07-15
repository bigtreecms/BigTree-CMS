/**
 * Resource shapes shared across endpoint modules (pages, modules, files, etc.).
 *
 * These mirror what BigTree's API present()-style helpers return. Keep this
 * file thin — anything that lives entirely under a single endpoint module
 * stays in that module's own file.
 */

export interface ResourceFolder {
	id: number;
	name: string;
	parent: number;
	updated_at?: string;
}

export interface ResourceFile {
	creator?: number;
	date?: string;
	file: string;
	folder: number;
	height?: number;
	id: number;
	is_image: boolean;
	name: string;
	size?: number;
	thumbs?: Record<string, string>;
	type: string;
	width?: number;
}

export interface LockOwner {
	email: string;
	id: number;
	name: string;
}

export interface LockInfo {
	expires_at: string;
	lock_id: number;
}

export interface PendingChangeRef {
	date: string;
	id: number;
	item_id: string;
	table: string;
	type: "EDIT" | "NEW";
	user: number;
}
