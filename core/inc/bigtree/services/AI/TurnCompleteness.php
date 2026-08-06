<?php
	namespace BigTree\Services\AI;

	/**
	 * Did the provider finish, or did it stop?
	 *
	 * A 2xx says the request was accepted, not that the turn is whole. Every
	 * provider says which of the two happened — OpenAI/xAI in
	 * `choices[0].finish_reason`, Anthropic in `stop_reason` (and, streaming, in the
	 * `message_delta` event) — and until audit #18 neither string appeared anywhere
	 * in this codebase. The streaming path inferred truncation from unparseable
	 * argument JSON (audit #6) and the two buffered paths inferred nothing at all:
	 * `json_decode(...) ?: []` turned a tool call cut mid-arguments into the same
	 * call with every argument dropped — "update page 42, changing nothing" — and a
	 * final answer cut at the token ceiling was returned as though the model had
	 * finished speaking.
	 *
	 * Two different failures, two different answers:
	 *
	 *   - Cut inside a **tool call**: refuse the turn. The arguments are not the
	 *     ones the model meant to send, and validating them produces a message
	 *     ("Missing required argument") that describes the wrong problem, so the
	 *     model retries the same oversized call until the turn dies.
	 *   - Cut inside a **final answer**: keep it and say so. Half an answer is
	 *     worth reading; half an answer presented as whole is not.
	 */
	class TurnCompleteness {
		/** What both providers call "I stopped because I hit the token ceiling". */
		const LENGTH_REASONS = ["length", "max_tokens"];

		/** Shared wording, so the streaming and buffered paths refuse identically. */
		const TRUNCATED_TOOL_CALL = "The response was cut off part-way through a tool call.";

		const TRUNCATED_STREAM = "The response was cut off before it finished.";

		/**
		 * Appended to an answer the provider stopped mid-sentence. Written as visible
		 * prose rather than a status key because it has to survive into the persisted
		 * message and the SPA's markdown rendering, neither of which reads metadata.
		 */
		const TRUNCATED_ANSWER_NOTE = "[This answer stopped here because it reached the reply-length limit — "
			. "ask me to continue for the rest.]";

		/**
		 * The provider's own stop signal from a buffered response, or "".
		 *
		 * @param array<string,mixed> $response
		 */
		public static function stopSignal(string $service, array $response): string {
			if ($service === "anthropic") {

				return (string)($response["stop_reason"] ?? "");
			}

			return (string)($response["choices"][0]["finish_reason"] ?? "");
		}

		/** Whether a stop signal means "cut off at the token ceiling". */
		public static function isLength(string $reason): bool {

			return in_array($reason, self::LENGTH_REASONS, true);
		}

		/**
		 * An answer the provider cut short, with the note that says so. Handles the
		 * empty case: a turn that produced nothing but a length stop is still an
		 * answer of a kind — "I ran out of room before I said anything" — and is far
		 * better than the blank bubble resolveBlankAnswer would otherwise report.
		 */
		public static function markTruncatedAnswer(?string $content): string {
			$content = rtrim((string)$content);

			if ($content === "") {

				return self::TRUNCATED_ANSWER_NOTE;
			}

			return $content . "\n\n" . self::TRUNCATED_ANSWER_NOTE;
		}
	}
