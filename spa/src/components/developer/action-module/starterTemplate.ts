/**
 * Default source a new custom (module) action starts from — a working screen that
 * draws with the SPA's own primitives + a real field type, then submits a payload
 * to the module class's handler via host.invoke(). Authored in TSX/JSX; compiled
 * in-browser by Sucrase before it runs (see actionModuleLoader.ts).
 */
export const ACTION_STARTER = `/**
 * Custom action. Draw your UI with "@bigtree/ui" primitives and "@bigtree/fields"
 * field types, then submit to your module class's handler with host.invoke(payload).
 * Return a React component as { contractVersion, Component }.
 */
import { useState } from "react";
import { Stack, Row, Button, Heading, Note } from "@bigtree/ui";
import { FieldRenderer } from "@bigtree/fields";

export default {
	contractVersion: 1,

	Component({ host }) {
		const [note, setNote] = useState("");
		const [result, setResult] = useState(null);

		const run = async () => {
			try {
				const res = await host.invoke({ note });
				setResult(res);
				host.toast("Action ran", "success");
			} catch (err) {
				host.toast(err.message || "Action failed", "error");
			}
		};

		return (
			<Stack>
				<Heading>{host.context.action.name}</Heading>
				<Note>Edit this action's code to build your own UI.</Note>

				<FieldRenderer
					field={{ column: "note", title: "Note", type: "text" }}
					value={note}
					onChange={setNote}
				/>

				<Row>
					<Button onClick={run}>Run handler</Button>
				</Row>

				{result != null && <Note>Result: {JSON.stringify(result)}</Note>}
			</Stack>
		);
	},
};
`;

/** Quick reference for the action host API, shown beneath the editor. */
export const ACTION_HOST_API_REFERENCE: Array<{ name: string; desc: string }> = [
	{ name: "host.context", desc: "moduleId, action, params, selection, userLevel." },
	{
		name: "host.invoke(payload)",
		desc: "Run this action's server handler; resolves with its result.",
	},
	{ name: "host.invokeAction(route, payload)", desc: "Run another action's handler by route." },
	{ name: "host.navigate(to)", desc: "Navigate within the admin." },
	{ name: "host.toast(msg, kind)", desc: "Show a toast (success / error / info / warning)." },
	{
		name: 'import … from "@bigtree/ui"',
		desc: "Stack, Row, Button, Heading, Note, TextInput, …",
	},
	{
		name: 'import … from "@bigtree/fields"',
		desc: "FieldRenderer — draw any built-in field type.",
	},
	{ name: 'import … from "react"', desc: "useState and the rest of React." },
];
