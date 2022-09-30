<?php
	namespace BigTree\GraphQL;
	
	use BigTreeAdmin, BigTreeCMS, SQL;
	use GraphQL\Error\UserError;
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
			TypeService::set("NavigationItem", new ObjectType([
				"name" => "NavigationItem",
				"fields" => [
					"id" => Type::int(),
					"title" => Type::string(),
					"url" => Type::string(),
					"new_window" => Type::boolean(),
					"children" => TypeService::get("JSON"),
				]
			]));

			TypeService::set("Setting", new ObjectType([
				"name" => "Setting",
				"fields" => [
					"id" => Type::string(),
					"value" => TypeService::get("JSON"),
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
					"breadcrumb" => TypeService::get("JSON"),
					"subnav" => TypeService::get("JSON"),
					"commands" => Type::listOf(Type::string()),
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
			
			TypeService::set("Tag", new ObjectType([
				"name" => "Tag",
				"fields" => [
					"id" => Type::id(),
					"name" => Type::string(),
					"route" => Type::string(),
				]
			]));
		}
		
		public static function registerQueries() {
			QueryService::register("query", [
				"getBreadcrumb" => [
					"type" => TypeService::get("JSON"),
					"args" => [
						"page" => Type::int()
					],
					"resolve" => function($root, $args, $context) {
						$page = SQL::fetch("SELECT path, template FROM bigtree_pages WHERE id = ?", $args["page"]);
						
						return $page ? BigTreeCMS::getBreadcrumbByPage($page) : null;
					}
				],
				"getNavigation" => [
					"type" => Type::listOf(TypeService::get("NavigationItem")),
					"args" => [
						"parent" => Type::int(),
						"levels" => Type::int()
					],
					"resolve" => function($root, $args, $context) {
						$levels = empty($args["levels"]) ? 1 : intval($args["levels"]);

						return BigTreeCMS::getNavByParent($args["parent"], $levels);
					}
				],
				"getPage" => [
					"type" => TypeService::get("Page"),
					"args" => [
						"path" => Type::string(),
						"id" => Type::int()
					],
					"resolve" => function($root, $args, $context, $info) {
						$requested = $info->getFieldSelection();
						$commands = [];
						$id = false;
						$path = explode("/", trim($args["path"], "/"));
						
						if (isset($args["path"])) {
							if ($args["path"] === "") {
								$id = 0;
							} else {
								[$id, $commands] = BigTreeCMS::getNavId($path);
								
								if ($id === false) {
									throw new UserError("Page not found.");
								}
							}
						} elseif (isset($args["id"])) {
							$id = $args["id"];
						}

						$page = BigTreeCMS::getPage($id);
						
						if (empty($page)) {
							throw new UserError("Page not found.");
						}
						
						if (!empty($requested["breadcrumb"])) {
							$page["breadcrumb"] = BigTreeCMS::getBreadcrumbByPage($page);
						}
						
						if (!empty($requested["subnav"])) {
							$depth = !empty($args["subnav_depth"]) ? $args["subnav_depth"] : 1;
							$page["subnav"] = array_values(BigTreeCMS::getNavByParent($page["id"], $depth));
						}

						$page["commands"] = $commands;
						
						return $page;
					}
				],
				"getSettings" => [
					"type" => TypeService::get("JSON"),
					"args" => [
						"ids" => Type::listOf(Type::string())
					],
					"resolve" => function($root, $args, $context) {
						return BigTreeCMS::getSettings($args["ids"]);
					}
				],
				"getWWWRoot" => [
					"type" => Type::string(),
					"args" => [],
					"resolve" => function() {
						return WWW_ROOT;
					}
				]
			]);
		}
	}