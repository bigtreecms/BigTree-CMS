<?php
	/**
	 * Audit #18 E4: what a failed turn leaves behind.
	 *
	 * A provider error on round N used to delete every proposal the turn had staged
	 * on rounds 1..N-1. That is finished work — validated, fingerprinted, given a
	 * 24-hour TTL and its own approval gate — thrown away because a *later*, unrelated
	 * round hit the context window, and thrown away in the one situation where the
	 * retry fails identically (the same reads produce the same payloads). D2 keeps
	 * the cards; this asserts it, rather than leaving it incidental.
	 *
	 * The other half of the decision matters just as much: a kept proposal must not
	 * become an orphan. A card with no message anywhere in the conversation is
	 * invisible in the UI and approvable through the API for a day — exactly the
	 * shape chatStream's shutdown handler was written to prevent — so a failed turn
	 * that staged something persists a turn explaining it, and only a turn that
	 * staged nothing rolls its (new, empty) conversation back.
	 */

	use BigTree\Services\AIChatService;
	use BigTree\Services\AI\ProposalStore;

	/**
	 * Invoke AIChatService's private failure path directly.
	 *
	 * @return bool Whether the turn was kept (persisted) rather than rolled back.
	 */
	function ai_rollback_failed_turn(
		array $turn,
		array $proposals,
		array $tool_activity = [],
		string $reason = AIChatService::FAILED_PROVIDER
	): bool {
		$method = new ReflectionMethod(AIChatService::class, "rollbackFailedTurn");
		$method->setAccessible(true);

		return (bool)$method->invoke(new AIChatService(), $turn, $proposals, $tool_activity, $reason);
	}

	/**
	 * @param object|array $user
	 * @return array<string,mixed>
	 */
	function ai_rollback_turn_fixture($user, int $conversation_id, bool $new_conversation): array {

		return [
			"user" => $user,
			"message" => "Rename the gala page",
			"conversation" => ["id" => $conversation_id, "title" => "Rename the gala page"],
			"conversation_id" => $conversation_id,
			"new_conversation" => $new_conversation,
		];
	}

	/** E4: the failure path contains no proposal delete at all. */
	function test_failed_turn_never_deletes_a_proposal() {
		$body = ai_surface_method_body(AIChatService::class, "rollbackFailedTurn");
		T::ok($body !== "", "rollbackFailedTurn source was read");
		T::ok(
			strpos($body, ProposalStore::class) === false && strpos($body, "ProposalStore::TABLE") === false,
			"a failed turn does not touch the proposal store"
		);
		T::ok(
			strpos($body, "persistFailedTurn") !== false,
			"a turn that staged something persists a message explaining the cards it left"
		);

		// The client is told which of the two happened. Without it, reconciling means
		// guessing: reload and a turn that staged nothing loses the user's own message
		// along with the error explaining it; don't, and kept cards show up nowhere
		// but the history list.
		$stream = ai_surface_method_body(AIChatService::class, "chatStream");
		T::ok(
			strpos($stream, "proposals_kept") !== false,
			"the stream's error event says whether anything survived on the server"
		);
	}

	/**
	 * E4: the message names what actually happened.
	 *
	 * The same path settles a provider failure and a client that closed the tab
	 * mid-stream, and the two are not the same event. Telling someone whose browser
	 * dropped the connection that "the AI service returned an error" sends them
	 * looking for an outage that never happened.
	 */
	function test_failed_turn_message_matches_the_failure() {
		$reasons = [
			AIChatService::FAILED_PROVIDER => "the AI service returned an error",
			AIChatService::FAILED_INTERRUPTED => "the connection closed",
		];
		$method = new ReflectionMethod(AIChatService::class, "persistFailedTurn");
		$body = ai_surface_method_body(AIChatService::class, "persistFailedTurn");

		T::equals($method->getNumberOfParameters(), 4, "persistFailedTurn is told which failure it is settling");

		foreach ($reasons as $reason => $wording) {
			T::ok(strpos($body, $wording) !== false, "a {$reason} failure says: {$wording}");
		}

		// The one caller that isn't a provider error has to say so, or the wording
		// above is written and never used.
		$stream = ai_surface_method_body(AIChatService::class, "chatStream");
		T::ok(
			strpos($stream, "FAILED_INTERRUPTED") !== false,
			"the shutdown handler — a closed connection, not a failed provider — settles under its own reason"
		);
	}

	/** True when the chat + proposal tables are reachable in this harness. */
	function ai_rollback_db_available(): bool {
		try {
			AIChatService::ensureTables();
			(new ProposalStore())->ensureTable();

			return true;
		} catch (\Throwable $e) {
			echo "  (skipped — database unavailable in this harness: " . $e->getMessage() . ")\n";

			return false;
		}
	}

	/** E4: staged cards survive a provider error, with a message that explains them. */
	function test_failed_turn_keeps_staged_proposals() {
		if (!ai_rollback_db_available()) {

			return;
		}

		$user = ["id" => 987660];
		$store = new ProposalStore();
		$conversation_id = (int)SQL::insert(AIChatService::CONVERSATIONS_TABLE, [
			"user" => 987660,
			"title" => "Rename the gala page",
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		]);
		$proposal = $store->create($user, $conversation_id, "update_page", "Rename page 42.", [], ["id" => 42]);
		$proposal_id = (string)$proposal["id"];

		try {
			$reported = ai_rollback_failed_turn(
				ai_rollback_turn_fixture($user, $conversation_id, true),
				[["proposal_id" => $proposal_id, "tool" => "update_page", "summary" => "Rename page 42."]],
				[["name" => "get_page", "arguments" => ["id" => 42], "status" => "ok"]]
			);

			// What the stream tells the client (`proposals_kept`), so it can reconcile
			// on a fact instead of reloading a thread that may not exist.
			T::ok($reported, "the settlement reports that it kept the turn");

			$kept = $store->loadOwned($proposal_id, $user);
			T::ok($kept !== null, "the staged proposal survived the failed turn");
			T::equals($kept["status"], ProposalStore::PENDING, "and is still approvable");

			$conversation = SQL::fetch(
				"SELECT id FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?",
				$conversation_id
			);
			T::ok(!empty($conversation), "the conversation holding it was not deleted");

			$messages = SQL::fetchAll(
				"SELECT role, content, tool_calls, tool_results FROM " . AIChatService::MESSAGES_TABLE
					. " WHERE conversation = ? ORDER BY id ASC",
				$conversation_id
			);
			T::equals(count($messages), 2, "the turn was persisted: the user's message and an assistant reply");
			T::equals($messages[0]["role"], "user", "the user's message is first");
			T::equals($messages[1]["role"], "assistant", "and the assistant's explanation second");
			T::ok(
				strpos((string)$messages[1]["content"], "didn't finish") !== false,
				"the reply says the turn failed"
			);
			T::ok(
				strpos((string)$messages[1]["content"], "waiting for you") !== false,
				"and points at the card that is waiting"
			);

			$results = json_decode((string)$messages[1]["tool_results"], true);
			T::ok(
				is_array($results) && in_array($proposal_id, $results["proposals"] ?? [], true),
				"the card is attached to that message, so the SPA can render it"
			);

			$activity = json_decode((string)$messages[1]["tool_calls"], true);
			T::equals(
				is_array($activity) ? (string)($activity[0]["name"] ?? "") : "",
				"get_page",
				"what the turn had already run is recorded too"
			);
		} finally {
			SQL::query("DELETE FROM " . ProposalStore::TABLE . " WHERE id = ?", $proposal_id);
			SQL::query("DELETE FROM " . AIChatService::MESSAGES_TABLE . " WHERE conversation = ?", $conversation_id);
			SQL::query("DELETE FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?", $conversation_id);
		}
	}

	/** E4: a turn that staged nothing still leaves no empty thread behind. */
	function test_failed_turn_rolls_back_an_empty_new_conversation() {
		if (!ai_rollback_db_available()) {

			return;
		}

		$user = ["id" => 987661];
		$conversation_id = (int)SQL::insert(AIChatService::CONVERSATIONS_TABLE, [
			"user" => 987661,
			"title" => "What templates do we have?",
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		]);

		try {
			$reported = ai_rollback_failed_turn(ai_rollback_turn_fixture($user, $conversation_id, true), []);
			T::ok(!$reported, "the settlement reports that nothing was kept");

			$conversation = SQL::fetch(
				"SELECT id FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?",
				$conversation_id
			);
			T::ok(empty($conversation), "a new conversation with nothing in it is removed");
		} finally {
			SQL::query("DELETE FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?", $conversation_id);
		}
	}

	/** E4: an existing conversation is never deleted by a turn that failed inside it. */
	function test_failed_turn_leaves_an_existing_conversation_alone() {
		if (!ai_rollback_db_available()) {

			return;
		}

		$user = ["id" => 987662];
		$conversation_id = (int)SQL::insert(AIChatService::CONVERSATIONS_TABLE, [
			"user" => 987662,
			"title" => "Ongoing thread",
			"created_at" => date("Y-m-d H:i:s"),
			"updated_at" => date("Y-m-d H:i:s"),
		]);

		try {
			ai_rollback_failed_turn(ai_rollback_turn_fixture($user, $conversation_id, false), []);

			$conversation = SQL::fetch(
				"SELECT id FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?",
				$conversation_id
			);
			T::ok(!empty($conversation), "the thread the user was already in survives");

			$messages = SQL::fetchAll(
				"SELECT id FROM " . AIChatService::MESSAGES_TABLE . " WHERE conversation = ?",
				$conversation_id
			);
			T::equals(count($messages), 0, "and a turn with nothing to show persists nothing");
		} finally {
			SQL::query("DELETE FROM " . AIChatService::MESSAGES_TABLE . " WHERE conversation = ?", $conversation_id);
			SQL::query("DELETE FROM " . AIChatService::CONVERSATIONS_TABLE . " WHERE id = ?", $conversation_id);
		}
	}
