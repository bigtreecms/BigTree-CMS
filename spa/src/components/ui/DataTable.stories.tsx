import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { DataTable, type DataTableColumn, type DataTableSort } from "./DataTable";

/**
 * Generic CSS-grid table used by every list view. Rows collapse to a single
 * stacked column on mobile (each cell re-surfaces its header as a label).
 * Supports sortable headers, row clicks, custom row tints, and drag-reorder.
 */
interface User {
	id: number;
	name: string;
	email: string;
	role: string;
}

const users: User[] = [
	{ id: 1, name: "Ada Lovelace", email: "ada@example.com", role: "Admin" },
	{ id: 2, name: "Alan Turing", email: "alan@example.com", role: "Editor" },
	{ id: 3, name: "Grace Hopper", email: "grace@example.com", role: "Editor" },
];

const columns: DataTableColumn<User>[] = [
	{ key: "name", header: "Name", width: "minmax(0,1.4fr)", cell: (u) => u.name, sortable: true },
	{ key: "email", header: "Email", width: "minmax(0,1.6fr)", cell: (u) => u.email },
	{
		key: "role",
		header: "Role",
		width: "120px",
		cell: (u) => u.role,
		headerAlign: "right",
		align: "right",
	},
];

// Pin the generic to a concrete row type so Storybook can type `args`.
const UserDataTable = DataTable<User>;

const meta = {
	title: "UI/DataTable",
	component: UserDataTable,
	tags: ["autodocs"],
	args: { columns, rows: users, getRowKey: (u: User) => u.id },
} satisfies Meta<typeof UserDataTable>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Click the "Name" header to toggle sort direction. */
export const Sortable: Story = {
	render: (args) => {
		const [sort, setSort] = useState<DataTableSort>({ key: "name", dir: "asc" });
		const sorted = [...users].sort((a, b) => {
			const cmp = a.name.localeCompare(b.name);

			return sort.dir === "asc" ? cmp : -cmp;
		});

		return <UserDataTable {...args} rows={sorted} sort={sort} onSortChange={setSort} />;
	},
};

export const Loading: Story = {
	args: { rows: [], isLoading: true },
};

export const Empty: Story = {
	args: { rows: [], emptyLabel: "No users yet." },
};
