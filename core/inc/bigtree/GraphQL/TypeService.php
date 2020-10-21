<?php
	namespace BigTree\GraphQL;

	use GraphQL\Type\Definition\ObjectType;
	
	/*
		Class: BigTree\GraphQL\TypeService
			Manages type definitions for the GraphQL API.
	*/

	class TypeService
	{

		public static $ScalarTypeRefs = [
			"int" => [
				"tinyint",
				"smallint",
				"mediumint",
				"int",
				"bigint",
			],
			"float" => [
				"float",
				"double",
				"double precision",
				"real",
				"decimal",
			],
			"boolean" => [
				"bool",
				"boolean",
			]
		];

		public static $Types = [];

		public static function get(string $name): ?ObjectType
		{
			if (isset(static::$Types[$name])) {
				return static::$Types[$name];
			}

			return null;
		}

		public static function set(string $name, ObjectType $type): void
		{
			static::$Types[$name] = $type;
		}

	}
