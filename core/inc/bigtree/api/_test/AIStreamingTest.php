<?php
	/**
	 * Phase 5 streaming: the provider SSE assembler (StreamAccumulator) for both the
	 * OpenAI-compatible and Anthropic stream formats, and AgentLoop::runStreaming's
	 * event emission + tool loop.
	 *
	 * DB-free: StreamAccumulator is pure, and a fake BigTreeAI scripts chatStream so
	 * no provider is hit. chat_loop_registry() / ai_fake_user() come from the chat +
	 * tool framework test files (all *Test.php are required before any test_* runs).
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AgentLoop;
	use BigTree\Services\AI\StreamAccumulator;

	if (!class_exists("FakeStreamAI")) {
		/**
		 * Scripts BigTreeAI::chatStream(). Each script entry is
		 *   ["text" => string, "result" => array]  — emit text via on_delta, return result
		 * or `false` to simulate a provider stream failure.
		 */
		class FakeStreamAI extends \BigTreeAI {
			/** @var list<mixed> */
			public $script = [];
			/** @var list<array<string,mixed>> */
			public $stream_calls = [];

			public function __construct(array $script = []) {
				$this->script = $script;
			}

			public function isConfigured(): bool {

				return true;
			}

			public function chatStream(array $messages, array $tools, array $options, callable $on_delta) {
				$this->stream_calls[] = ["messages" => $messages, "tools" => $tools, "options" => $options];

				if (!$this->script) {
					$this->Error = "no scripted stream";

					return false;
				}

				$next = array_shift($this->script);

				if ($next === false) {
					$this->Error = "stream boom";

					return false;
				}

				foreach (str_split((string)($next["text"] ?? "")) as $ch) {
					$on_delta($ch);
				}

				return $next["result"];
			}
		}
	}

	function test_stream_accumulator_openai_text_and_tools() {
		$deltas = [];
		$acc = new StreamAccumulator("openai", function (string $c) use (&$deltas): void {
			$deltas[] = $c;
		});

		// Text tokens.
		$acc->feedLine('data: {"choices":[{"delta":{"content":"Hel"}}]}');
		$acc->feedLine('data: {"choices":[{"delta":{"content":"lo"}}]}');

		// A tool call whose id/name arrive first and arguments stream in fragments.
		$acc->feedLine('data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"call_1","function":{"name":"search_pages","arguments":"{\"que"}}]}}]}');
		$acc->feedLine('data: {"choices":[{"delta":{"tool_calls":[{"index":0,"function":{"arguments":"ry\":\"home\"}"}}]}}]}');
		$acc->feedLine("data: [DONE]");

		T::equals(implode("", $deltas), "Hello", "text deltas were forwarded token by token");

		$result = $acc->result();
		T::equals($result["content"], "Hello", "assembled content matches");
		T::equals(count($result["tool_calls"]), 1, "one tool call assembled");
		T::equals($result["tool_calls"][0]["name"], "search_pages", "tool name assembled");
		T::equals($result["tool_calls"][0]["arguments"]["query"], "home", "streamed argument fragments parsed into JSON");
		T::ok($acc->isDone(), "[DONE] marks the stream complete");
		T::ok(is_array($result["raw"]["_openai_tool_calls"]), "openai-shaped re-feed calls present");
	}

	function test_stream_accumulator_anthropic() {
		$deltas = [];
		$acc = new StreamAccumulator("anthropic", function (string $c) use (&$deltas): void {
			$deltas[] = $c;
		});

		$acc->feedLine('data: {"type":"content_block_start","index":0,"content_block":{"type":"text"}}');
		$acc->feedLine('data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hi "}}');
		$acc->feedLine('data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"there"}}');
		// A tool_use block with streamed input JSON.
		$acc->feedLine('data: {"type":"content_block_start","index":1,"content_block":{"type":"tool_use","id":"toolu_1","name":"get_page"}}');
		$acc->feedLine('data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"{\"id\":"}}');
		$acc->feedLine('data: {"type":"content_block_delta","index":1,"delta":{"type":"input_json_delta","partial_json":"5}"}}');
		$acc->feedLine('data: {"type":"message_stop"}');

		T::equals(implode("", $deltas), "Hi there", "anthropic text deltas forwarded");

		$result = $acc->result();
		T::equals($result["content"], "Hi there", "anthropic content assembled");
		T::equals($result["tool_calls"][0]["name"], "get_page", "anthropic tool_use name assembled");
		T::equals((int)$result["tool_calls"][0]["arguments"]["id"], 5, "anthropic input_json_delta fragments parsed");
		T::ok($acc->isDone(), "message_stop marks completion");
	}

	function test_stream_accumulator_ignores_noise() {
		$acc = new StreamAccumulator("openai", function (string $c): void {});
		$acc->feedLine(": keep-alive comment");
		$acc->feedLine("event: ping");
		$acc->feedLine("data: not-json");
		$acc->feedLine("");

		$result = $acc->result();
		T::equals($result["content"], null, "no content from noise-only stream");
		T::equals($result["tool_calls"], [], "no tool calls from noise-only stream");
	}

	function test_run_streaming_streams_answer() {
		$ai = new FakeStreamAI([
			["text" => "The answer is 42.", "result" => fake_answer_response("The answer is 42.")],
		]);
		$loop = new AgentLoop($ai, chat_loop_registry(), 4);
		$context = new AIToolContext(ai_fake_user(0), 8);

		$events = [];
		$run = $loop->runStreaming([
			["role" => "system", "content" => "sys"],
			["role" => "user", "content" => "q"],
		], $context, [], function (array $e) use (&$events): void {
			$events[] = $e;
		});

		$text = implode("", array_map(function ($e) {

			return $e["type"] === "text" ? $e["text"] : "";
		}, $events));

		T::equals($text, "The answer is 42.", "streamed tokens reconstruct the answer");
		T::equals($run["answer"], "The answer is 42.", "authoritative answer returned");
		T::equals($run["rounds"], 1, "single round when the model answers directly");
		T::equals($run["error"], null, "no error");
	}

	function test_run_streaming_tool_round_then_answer() {
		$ai = new FakeStreamAI([
			// First round: a tool call (no user-facing text).
			["text" => "", "result" => fake_tool_call_response("c1", "search_pages", ["query" => "home"])],
			// Second round: the streamed answer.
			["text" => "Found Home.", "result" => fake_answer_response("Found Home.")],
		]);
		$loop = new AgentLoop($ai, chat_loop_registry(), 4);
		$context = new AIToolContext(ai_fake_user(0), 8);

		$events = [];
		$run = $loop->runStreaming([
			["role" => "system", "content" => "sys"],
			["role" => "user", "content" => "where is home"],
		], $context, [], function (array $e) use (&$events): void {
			$events[] = $e;
		});

		$tool_events = array_values(array_filter($events, function ($e) {

			return $e["type"] === "tool";
		}));
		T::equals(count($tool_events), 1, "a tool event was emitted");
		T::equals($tool_events[0]["name"], "search_pages", "tool event names the tool");
		T::equals($run["answer"], "Found Home.", "final answer follows the tool round");
		T::equals($run["rounds"], 2, "two rounds: tool then answer");
	}

	function test_run_streaming_provider_error() {
		$ai = new FakeStreamAI([false]);
		$loop = new AgentLoop($ai, chat_loop_registry(), 4);
		$context = new AIToolContext(ai_fake_user(0), 8);

		$run = $loop->runStreaming([
			["role" => "system", "content" => "sys"],
			["role" => "user", "content" => "hi"],
		], $context, [], function (array $e): void {});

		T::equals($run["answer"], null, "no answer on stream failure");
		T::ok($run["error"] !== null, "stream error surfaced");
	}
