<?php
	/**
	 * Test fixture hook. Registered by AIEventContractTest under "api.page.updated"
	 * so a test can observe that the event fired and with what payload. One file per
	 * event, because the dispatcher hoists $data and the call's context into scope but
	 * not the event name — the file is the only thing that knows which event it is.
	 */
	if (function_exists("ai_event_log_record")) {
		ai_event_log_record("page.updated", $data);
	}

	return $data;
