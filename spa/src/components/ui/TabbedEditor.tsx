import * as Tabs from "@radix-ui/react-tabs";
import type { ReactNode } from "react";

export interface TabbedEditorTab {
	value: string;
	label: ReactNode;
	icon?: ReactNode;
	content: ReactNode;
	disabled?: boolean;
}

interface TabbedEditorProps {
	tabs: TabbedEditorTab[];
	value: string;
	onChange: (value: string) => void;
	className?: string;
}

/**
 * Radix Tabs scaffold for multi-tab edit screens (Page edit, Module designer,
 * Settings edit). The host page controls the active tab so it can be reflected
 * in the URL / breadcrumb when desired.
 */
export const TabbedEditor = ({ tabs, value, onChange, className }: TabbedEditorProps) => {
	return (
		<Tabs.Root
			value={value}
			onValueChange={onChange}
			className={`flex flex-col ${className ?? ""}`}
		>
			<Tabs.List className="flex min-w-0 items-center gap-1 overflow-x-auto border-b border-border bg-surface px-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
				{tabs.map((tab) => (
					<Tabs.Trigger
						key={tab.value}
						value={tab.value}
						disabled={tab.disabled}
						className="-mb-px flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-[13px] font-medium text-text-3 transition-colors hover:text-text disabled:cursor-not-allowed disabled:opacity-40 data-[state=active]:border-accent data-[state=active]:text-accent"
					>
						{tab.icon}
						<span>{tab.label}</span>
					</Tabs.Trigger>
				))}
			</Tabs.List>

			{tabs.map((tab) => (
				<Tabs.Content key={tab.value} value={tab.value} className="pt-4 focus:outline-none">
					{tab.content}
				</Tabs.Content>
			))}
		</Tabs.Root>
	);
};
