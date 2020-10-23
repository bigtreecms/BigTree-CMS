<?php
	namespace BigTree\GraphQL;
	
	use BigTreeCMS, SQL;
	use GraphQL\Type\Definition\ObjectType;
	use GraphQL\Type\Definition\Type;
	
	/*
		Class: BigTree\GraphQL\CMS
			Registers GraphQL queries for data normally retrieved via the BigTreeCMS class.
	*/
	
	class CMS
	{
	
		public static function registerTypes(): void
		{
			TypeService::set("BreadcrumbItem", new ObjectType([
				"name" => "BreadcrumbItem",
				"fields" => [
					"id" => Type::string(),
					"title" => Type::string(),
					"link" => Type::string(),
				]
			]));
			
			TypeService::set("Tag", new ObjectType([
				"name" => "Tag",
				"fields" => [
					"id" => Type::id(),
					"name" => Type::string(),
					"route" => Type::string(),
				]
			]));
			
			TypeService::set("Setting", new ObjectType([
				"name" => "Setting",
				"fields" => [
					"id" => Type::string(),
					"value" => Type::string(),
					"encrypted" => Type::boolean()
				]
			]));
			
			TypeService::set("Page", new ObjectType([
				"name" => "Page",
				"fields" => [
					"id" => Type::id(),
					"trunk" => Type::boolean(),
					"parent" => Type::int(),
					"in_nav" => Type::boolean(),
					"nav_title" => Type::string(),
					"path" => Type::string(),
					"title" => Type::string(),
					"meta_description" => Type::string(),
					"open_graph" => TypeService::get("JSON"),
					"seo_invisible" => Type::boolean(),
					"template" => Type::string(),
					"external" => Type::boolean(),
					"new_window" => Type::boolean(),
					"archived" => Type::boolean(),
					"publish_at" => Type::string(),
					"expire_at" => Type::string(),
					"position" => Type::int(),
					"resources" => TypeService::get("JSON"),
					"breadcrumb" => Type::listOf(TypeService::get("BreadcrumbItem")),
					"commands" => Type::listOf(Type::string()),
				]
			]));
		}
		
		public static function registerQueries() {
			QueryService::register("query", [
				"getBreadcrumb" => [
					"type" => Type::listOf(TypeService::get("BreadcrumbItem")),
					"args" => [
						"page" => Type::int()
					],
					"resolve" => function($root, $args) {
						$page = SQL::fetch("SELECT path FROM bigtree_pages WHERE id = ?", $args["page"]);
						
						return $page ? BigTreeCMS::getBreadcrumbByPage($page) : null;
					}
				],
				"getPage" => [
					"type" => TypeService::get("Page"),
					"args" => [
						"path" => Type::string(),
						"id" => Type::int()
					],
					"resolve" => function($root, $args) {
						if (!empty($args["path"])) {
							[$id, $commands] = BigTreeCMS::getNavId($args["path"]);
						} else {
							$id = $args["id"];
							$commands = [];
						}
						
						$page = BigTreeCMS::getPage($id);
						$page["breadcrumb"] = BigTreeCMS::getBreadcrumbByPage($page);
						$page["commands"] = $commands;
						
						return $page;
					}
				]
			]);
		}
	}