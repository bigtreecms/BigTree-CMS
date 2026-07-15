<?php
	/**
	 * Phase 5 extensibility: extension-registered AI tools. Covers the pure
	 * normalizer (toolInstances), the include→register path against a fixture
	 * provider file (with $proposal_store injected), "core names win", and the
	 * ApprovableTool contract used by the two-phase approval flow.
	 *
	 * DB-free: the fixture provider returns hand-built fake tools and a real
	 * ProposalStore is only passed through (never touched). ai_fake_user is defined
	 * in AIToolFrameworkTest; all *Test.php files are required before any test_* runs.
	 */

	use BigTree\Services\AI\AIToolContext;
	use BigTree\Services\AI\AIToolInterface;
	use BigTree\Services\AI\AIToolResult;
	use BigTree\Services\AI\AIToolRegistry;
	use BigTree\Services\AI\ExtensionTools;
	use BigTree\Services\AI\ProposalStore;
	use BigTree\Services\AI\Tools\ApprovableTool;

	if (!class_exists("FakeExtensionReadTool")) {
		/**
		 * A minimal read tool an extension might ship. isAvailable() honors a level
		 * floor so the registry-filtering contract can be exercised.
		 */
		class FakeExtensionReadTool implements AIToolInterface {
			/** @var string */
			private $tool_name;
			/** @var int */
			private $min_level;

			public function __construct(string $name = "ext_weather", int $min_level = 0) {
				$this->tool_name = $name;
				$this->min_level = $min_level;
			}

			public function name(): string {

				return $this->tool_name;
			}

			public function kind(): string {

				return "read";
			}

			public function isAvailable($user): bool {
				$level = is_object($user) ? (int)($user->level ?? 0) : (int)($user["level"] ?? 0);

				return $level >= $this->min_level;
			}

			public function definition($user): array {

				return [
					"type" => "function",
					"function" => ["name" => $this->tool_name, "description" => "x", "parameters" => ["type" => "object", "properties" => new stdClass()]],
				];
			}

			public function execute(array $args, AIToolContext $context): AIToolResult {

				return AIToolResult::ok(["temp" => 21]);
			}
		}
	}

	if (!class_exists("FakeExtensionMutatingTool")) {
		/**
		 * An extension mutating tool implementing ApprovableTool so the approval flow
		 * can dispatch it by name without a core switch entry.
		 */
		class FakeExtensionMutatingTool implements AIToolInterface, ApprovableTool {
			/** @var array<string,mixed>|null Captured at approval so tests can assert. */
			public static $approved_with = null;

			public function name(): string {

				return "ext_send_postcard";
			}

			public function kind(): string {

				return "mutate";
			}

			public function isAvailable($user): bool {

				return true;
			}

			public function definition($user): array {

				return [
					"type" => "function",
					"function" => ["name" => "ext_send_postcard", "description" => "x", "parameters" => ["type" => "object", "properties" => new stdClass()]],
				];
			}

			public function execute(array $args, AIToolContext $context): AIToolResult {

				return AIToolResult::proposal("Send a postcard", ["to" => $args["to"] ?? ""], "prop-fake");
			}

			public function executeApproved(array $payload, $user): array {
				self::$approved_with = $payload;

				return ["status" => "sent", "to" => (string)($payload["to"] ?? "")];
			}
		}
	}

	function ext_tools_fixture_file(): string {
		$dir = sys_get_temp_dir() . "/bigtree-ai-ext-" . getmypid();

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}

		$file = $dir . "/provider.php";
		// The provider returns a list of tool instances; $proposal_store is in scope
		// exactly as ExtensionTools injects it for real extensions.
		file_put_contents($file, <<<'PHP'
<?php
	return [
		new FakeExtensionReadTool("ext_weather", 0),
		new FakeExtensionReadTool("ext_admin_only", 1),
		new FakeExtensionMutatingTool(),
		"not a tool",
	];
PHP);

		return $file;
	}

	function test_extension_tools_normalizer() {
		$single = ExtensionTools::toolInstances(new FakeExtensionReadTool());
		T::equals(count($single), 1, "a single tool instance is wrapped into a list");

		$mixed = ExtensionTools::toolInstances([new FakeExtensionReadTool(), "junk", 42, new FakeExtensionMutatingTool()]);
		T::equals(count($mixed), 2, "non-tool entries are dropped");

		T::equals(ExtensionTools::toolInstances("nope"), [], "a scalar return yields no tools");
	}

	function test_extension_tools_register_from_file() {
		$registry = new AIToolRegistry();
		ExtensionTools::registerFiles($registry, [ext_tools_fixture_file()], new ProposalStore());

		T::ok($registry->has("ext_weather"), "read tool from the provider file is registered");
		T::ok($registry->has("ext_send_postcard"), "mutating tool from the provider file is registered");

		// Per-user filtering still applies to extension tools.
		$editor_defs = array_column(array_map(function ($d) {

			return $d["function"];
		}, $registry->definitions(ai_fake_user(0))), "name");
		T::ok(in_array("ext_weather", $editor_defs, true), "level-0 tool offered to an editor");
		T::ok(!in_array("ext_admin_only", $editor_defs, true), "level-1 extension tool hidden from an editor");

		$admin_defs = array_column(array_map(function ($d) {

			return $d["function"];
		}, $registry->definitions(ai_fake_user(1))), "name");
		T::ok(in_array("ext_admin_only", $admin_defs, true), "level-1 extension tool offered to an admin");
	}

	function test_extension_tools_core_names_win() {
		$registry = new AIToolRegistry();
		// Pre-register a "core" tool under a name the extension also claims.
		$core = new FakeExtensionReadTool("ext_weather", 0);
		$registry->register($core);

		// Provider file returns a different instance for the same name.
		$dir = sys_get_temp_dir() . "/bigtree-ai-ext-shadow-" . getmypid();

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}

		$file = $dir . "/provider.php";
		file_put_contents($file, "<?php\n\treturn [new FakeExtensionReadTool(\"ext_weather\", 2)];\n");

		ExtensionTools::registerFiles($registry, [$file], new ProposalStore());

		// The core instance (min_level 0) must remain — not the shadow (min_level 2).
		T::ok($registry->get("ext_weather")->isAvailable(ai_fake_user(0)), "core tool is not shadowed by an extension of the same name");
	}

	function test_extension_tools_bad_provider_is_skipped() {
		$registry = new AIToolRegistry();
		$dir = sys_get_temp_dir() . "/bigtree-ai-ext-bad-" . getmypid();

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}

		$bad = $dir . "/bad.php";
		file_put_contents($bad, "<?php\n\tthrow new RuntimeException(\"boom\");\n");
		$good = $dir . "/good.php";
		file_put_contents($good, "<?php\n\treturn new FakeExtensionReadTool(\"ext_survivor\", 0);\n");

		ExtensionTools::registerFiles($registry, [$bad, $good], new ProposalStore());

		T::ok(!$registry->has("boom"), "a throwing provider registers nothing");
		T::ok($registry->has("ext_survivor"), "a later good provider still registers after a bad one");
	}

	function test_extension_approvable_contract() {
		FakeExtensionMutatingTool::$approved_with = null;
		$tool = new FakeExtensionMutatingTool();
		$out = $tool->executeApproved(["to" => "grandma"], ai_fake_user(0));

		T::equals($out["status"], "sent", "executeApproved returns its outcome");
		T::equals(FakeExtensionMutatingTool::$approved_with["to"], "grandma", "approval runs from the stored payload");
	}
