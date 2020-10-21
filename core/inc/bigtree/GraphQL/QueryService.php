<?php
	namespace BigTree\GraphQL;

	use GraphQL\Type\Definition\ObjectType;
	
	/*
		Class: BigTree\GraphQL\QueryService
			Manages query registration for the GraphQL API.
	*/

	class QueryService
	{

		public static $Queries = [];

		public static function register(string $query_type, array $queries): void
		{
			if (!isset(static::$Queries[$query_type])) {
				static::$Queries[$query_type] = [];
			}

			static::$Queries[$query_type] = array_merge(static::$Queries[$query_type], $queries);
		}

	}
