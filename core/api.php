<?php
	use BigTree\Cache;
	use BigTree\RedisCache;
	use BigTree\GraphQL\TypeService;
	use BigTree\GraphQL\QueryService;
	use BigTree\GraphQL\CMS;
	use GraphQL\GraphQL;
	use GraphQL\Type\Schema;
	use GraphQL\Type\Definition\ObjectType;
	use GraphQL\Type\Definition\Type;

	header("Content-Type: application/json");
	
	// Register core GraphQL queries
	CMS::registerTypes();
	CMS::registerQueries();
	
	// Allow modules to register their own queries
	$modules = BigTreeJSONDB::getAll("modules");
	
	foreach ($modules as $module) {
		if (!empty($module["graphql"])) {
			if (method_exists($module["class"], "registerGraphQLTypes")) {
				call_user_func([$module["class"], "registerGraphQLTypes"]);
			}
			
			if (method_exists($module["class"], "registerGraphQLMethods")) {
				call_user_func([$module["class"], "registerGraphQLMethods"]);
			}
		}
	}
	
	// Include required API files that can define their own queries
	$custom_api = BigTree::directoryContents(SERVER_ROOT."custom/inc/api/", true, "php");
	
	foreach ($custom_api as $file) {
		include $file;
	}
	
	$queryType = new ObjectType([
		'name' => 'Query',
		'fields' => QueryService::$Queries["query"],
	]);

	$output = null;
	$schema = new Schema(['query' => $queryType]);
	$rawInput = file_get_contents('php://input');
	$input = json_decode($rawInput, true);
	$query = $input['query'] ?? "";
	$variableValues = $input['variables'] ?? null;

	if (empty($bigtree["config"]["debug"])) {
		$hash = sha1($rawInput);

		if (!empty($bigtree["config"]["redis"])) {
			$cache = new RedisCache($bigtree["config"]["redis"], "graphql");
		} else {
			$cache = new Cache("graphql");
		}

		if ($cache->has($hash)) {
			header("BigTree-Cache-Hit: hit");
			die(json_encode($cache->get($hash)));
		}
	}

	try {
		header("BigTree-Cache-Hit: miss");

		$result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
		$output = $result->toArray();
		
		if (empty($bigtree["config"]["debug"])) {
			$cache->set($hash, $output);
		}
	} catch (Exception $e) {
		$output = ['errors' => [['message' => $e->getMessage()]]];
	}
	
	echo json_encode($output);
