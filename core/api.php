<?php
	use BigTree\Cache;
	use BigTree\GraphQL\TypeService;
	use BigTree\GraphQL\QueryService;
	use GraphQL\GraphQL;
	use GraphQL\Type\Schema;
	use GraphQL\Type\Definition\ObjectType;
	use GraphQL\Type\Definition\Type;

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

	$queryType = new ObjectType([
		'name' => 'Query',
		'fields' => QueryService::$Queries["query"],
	]);

	$schema = new Schema([
	    'query' => $queryType
	]);

	$rawInput = file_get_contents('php://input');
	$input = json_decode($rawInput, true);
	$query = $input['query'];
	$variableValues = isset($input['variables']) ? $input['variables'] : null;

	try {
	    $rootValue = ['prefix' => 'You said: '];
	    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
	    $output = $result->toArray();
	} catch (\Exception $e) {
	    $output = [
	        'errors' => [
	            [
	                'message' => $e->getMessage()
	            ]
	        ]
	    ];
	}
	header('Content-Type: application/json');
	echo json_encode($output);