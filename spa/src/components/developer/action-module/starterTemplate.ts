/**
 * Default source a new custom (module) action starts from — a working screen that
 * draws with the SPA's own primitives + a real field type, then submits a payload
 * to the module class's handler via host.invoke(). Authored in TSX/JSX; compiled
 * in-browser by Sucrase before it runs (see actionModuleLoader.ts).
 */
export const ACTION_STARTER = `/**
 * Custom action. Draw with "@bigtree/ui" (token-based, dark-mode safe) and
 * "@bigtree/fields" for real field types. Submit with host.invoke(payload).
 */
import { useState } from "react";
import {
	Stack,
	Row,
	Button,
	Heading,
	Text,
	Note,
	Panel,
	Tabs,
	TabPanel,
	TextField,
	Alert,
} from "@bigtree/ui";
import { FieldRenderer } from "@bigtree/fields";

export default {
	contractVersion: 1,

	Component({ host }) {
		const [tab, setTab] = useState("form");
		const [note, setNote] = useState("");
		const [result, setResult] = useState(null);
		const [error, setError] = useState(null);

		const run = async () => {
			setError(null);
			try {
				const res = await host.invoke({ note });
				setResult(res);
				host.toast("Action ran", "success");
			} catch (err) {
				setError(err.message || "Action failed");
				host.toast(err.message || "Action failed", "error");
			}
		};

		return (
			<Stack gap={16}>
				<div>
					<Heading level={1}>{host.context.action.name}</Heading>
					<Text muted size="sm">Edit this action to build your own UI.</Text>
				</div>

				{error && <Alert tone="danger" title="Error">{error}</Alert>}

				<Panel flush>
					<Tabs
						idBase="starter"
						value={tab}
						onChange={setTab}
						tabs={[
							{ value: "form", label: "Form" },
							{ value: "result", label: "Result" },
						]}
					/>
					<div className="p-4">
						<TabPanel idBase="starter" value="form" current={tab}>
							<Stack gap={12}>
								<TextField label="Note" value={note} onChange={setNote} />
								<FieldRenderer
									field={{ column: "note2", title: "Also a field type", type: "textarea" }}
									value={note}
									onChange={setNote}
								/>
								<Row>
									<Button variant="primary" onClick={run}>Run handler</Button>
								</Row>
							</Stack>
						</TabPanel>
						<TabPanel idBase="starter" value="result" current={tab}>
							{result != null
								? <Note>Result: {JSON.stringify(result)}</Note>
								: <Note>Run the handler to see a result.</Note>}
						</TabPanel>
					</div>
				</Panel>
			</Stack>
		);
	},
};
`;

/** Quick reference for the action host API, shown beneath the editor. */
export const ACTION_HOST_API_REFERENCE: Array<{ name: string; desc: string }> = [
	{ name: "host.context", desc: "moduleId, action, params (command0…), selection, userLevel." },
	{
		name: "host.invoke(payload)",
		desc: "Run this action's server handler; resolves with its result.",
	},
	{ name: "host.invokeAction(route, payload)", desc: "Run another action's handler by route." },
	{ name: "host.navigate(to)", desc: "Navigate within the admin." },
	{ name: "host.toast(msg, kind)", desc: "Show a toast (success / error / info / warning)." },
	{
		name: 'import … from "@bigtree/ui"',
		desc: "Layout (Stack, Row, Grid, GridItem, Panel, Tabs, SlideOver), type (Heading, Text, Note), forms (TextField, Checkbox…), DragSource + SortableList (palette → list drops), Alert, Button — all dark-mode tokenized.",
	},
	{
		name: 'import … from "@bigtree/fields"',
		desc: "FieldRenderer — draw any built-in field type (text, html, image, …).",
	},
	{ name: 'import … from "react"', desc: "useState and the rest of React." },
];
