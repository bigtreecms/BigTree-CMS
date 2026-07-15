import type { PermissionCode } from "@/api/endpoints/users";

export interface PermissionOption {
	label: string;
	value: PermissionCode;
}

export const PAGE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Publisher" },
	{ value: "e", label: "Editor" },
	{ value: "n", label: "No Access" },
	{ value: "i", label: "Inherit" },
];

export const MODULE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Publisher" },
	{ value: "e", label: "Editor" },
	{ value: "n", label: "No Access" },
];

export const RESOURCE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Creator" },
	{ value: "e", label: "Consumer" },
	{ value: "n", label: "No Access" },
	{ value: "i", label: "Inherit" },
];
