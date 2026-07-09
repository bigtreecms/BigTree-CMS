<?php
	/**
	 * Test fixture hook. Registered by HooksTest under "api.test.capture" so the
	 * test can observe what context the dispatcher hoists into scope. The invoker
	 * shares scope with the include, so mutating $data here surfaces to the caller.
	 */
	$data["seen_user_id"] = $user_id ?? "__unset__";
	$data["seen_previous"] = $previous ?? "__unset__";

	return $data;
