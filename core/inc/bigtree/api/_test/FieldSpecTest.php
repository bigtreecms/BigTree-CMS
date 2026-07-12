<?php
	/**
	 * FieldSpec — the closed verb vocabulary for JSONDB entity create/update
	 * payloads (API consolidation round 6, finding #2). ModuleSubResourceSupport
	 * delegates to these static methods; the JSON-DB entity services (Callout,
	 * Template, Feed, FieldType) call them directly.
	 */

	use BigTree\Api\FieldSpec;

	function test_fieldspec_insert_applies_all_verbs() {
		$d = [
			"title" => "Hello <b>World</b>",
			"slug" => "hello-world",
			"items" => ["a", "b"],
			"flag" => "on",
			"maybe" => "value",
			"on" => 1,
			"count" => "7",
			"resources" => [
				["id" => "x", "type" => "text", "title" => "X", "subtitle" => "", "settings" => []],
			],
		];
		$spec = [
			"title" => "encode",
			"slug" => "string",
			"items" => "array",
			"flag" => "checkbox",
			"maybe" => "nullable",
			"on" => "bool",
			"count" => "int",
			"resources" => "clean",
		];

		$out = FieldSpec::insert($d, $spec);

		T::equals($out["title"], \BigTree::safeEncode("Hello <b>World</b>"), "encode verb safeEncodes");
		T::equals($out["slug"], "hello-world", "string verb casts to string");
		T::equals($out["items"], ["a", "b"], "array verb preserves arrays");
		T::equals($out["flag"], "on", "checkbox verb normalizes yes");
		T::equals($out["maybe"], "value", "nullable verb keeps truthy values");
		T::equals($out["on"], true, "bool verb is !empty()");
		T::equals($out["count"], 7, "int verb casts to int");
		T::ok(is_array($out["resources"]), "clean verb returns an array");
	}

	function test_fieldspec_insert_defaults_for_absent_keys() {
		$out = FieldSpec::insert([], [
			"title" => "encode",
			"slug" => "string",
			"items" => "array",
			"flag" => "checkbox",
			"maybe" => "nullable",
			"on" => "bool",
			"count" => "int",
			"resources" => "clean",
		]);

		T::equals($out["title"], "", "encode defaults to empty string");
		T::equals($out["slug"], "", "string defaults to empty string");
		T::equals($out["items"], [], "array defaults to empty array");
		T::equals($out["flag"], "", "checkbox defaults to empty (off)");
		T::equals($out["maybe"], null, "nullable defaults to null");
		T::equals($out["on"], false, "bool defaults to false");
		T::equals($out["count"], 0, "int defaults to 0");
		T::equals($out["resources"], [], "clean defaults to empty array");
	}

	function test_fieldspec_update_presence_rules() {
		// encode/string/int: isset only.
		$partial = FieldSpec::update(
			["title" => "Hi", "count" => 3],
			["title" => "encode", "slug" => "string", "count" => "int", "flag" => "checkbox"]
		);
		T::ok(array_key_exists("title", $partial), "update includes present encode key");
		T::ok(!array_key_exists("slug", $partial), "update skips absent string key");
		T::ok(array_key_exists("count", $partial), "update includes present int key");
		T::ok(!array_key_exists("flag", $partial), "update skips absent checkbox key");

		// array/clean: isset + is_array.
		$arr = FieldSpec::update(
			["items" => ["x"], "bad" => "not-array", "resources" => []],
			["items" => "array", "bad" => "array", "resources" => "clean"]
		);
		T::ok(array_key_exists("items", $arr), "update includes array when value is array");
		T::ok(!array_key_exists("bad", $arr), "update skips array key when value is not array");
		T::ok(array_key_exists("resources", $arr), "update includes clean when value is array");

		// checkbox/nullable/bool: array_key_exists so explicit null/false is honored.
		$explicit = FieldSpec::update(
			["flag" => null, "on" => false, "maybe" => null],
			["flag" => "checkbox", "on" => "bool", "maybe" => "nullable"]
		);
		T::ok(array_key_exists("flag", $explicit), "update honors explicit null for checkbox");
		T::ok(array_key_exists("on", $explicit), "update honors explicit false for bool");
		T::equals($explicit["on"], false, "bool false stays false");
		T::ok(array_key_exists("maybe", $explicit), "update honors explicit null for nullable");
		T::equals($explicit["maybe"], null, "nullable null stays null");
	}

	function test_fieldspec_unknown_verb_throws() {
		T::throws(function () {
			FieldSpec::transform("x", "nope");
		}, LogicException::class, "unknown verb throws LogicException");
	}

	function test_fieldspec_module_subresource_delegation() {
		// ModuleSubResourceSupport::buildInsert/buildUpdate must stay byte-compatible
		// with FieldSpec so the trait's one-line delegates don't drift.
		$d = ["title" => "T", "settings" => ["a" => 1], "tagging" => "on"];
		$spec = ["title" => "encode", "settings" => "array", "tagging" => "checkbox", "table" => "string"];

		$insert = FieldSpec::insert($d, $spec);
		T::equals($insert["title"], \BigTree::safeEncode("T"), "sub-resource encode matches FieldSpec");
		T::equals($insert["settings"], ["a" => 1], "sub-resource array matches FieldSpec");
		T::equals($insert["tagging"], "on", "sub-resource checkbox matches FieldSpec");
		T::equals($insert["table"], "", "sub-resource absent string is empty");

		$update = FieldSpec::update(["title" => "U"], $spec);
		T::equals(array_keys($update), ["title"], "sub-resource update only includes present keys");
	}
