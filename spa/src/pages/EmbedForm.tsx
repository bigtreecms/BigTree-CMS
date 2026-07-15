import { Navigate, useParams } from "react-router-dom";

import { EmbedFormRenderer } from "@/renderer/embed/EmbedFormRenderer";

/**
 * /embed/:hash — public-facing embed form page.
 *
 * Mounted outside Shell so it has no TopBar/TabNav chrome — third-party pages
 * iframe this URL to expose a single module form to anonymous visitors. The
 * route sits next to /login as a sibling of the ProtectedRoute subtree.
 */
export const EmbedForm = () => {
	const { hash } = useParams<{ hash: string }>();

	if (!hash) {
		return <Navigate replace to="/" />;
	}

	return <EmbedFormRenderer hash={hash} />;
};
