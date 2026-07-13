<?php
	/**
	 * BigTreeAI allowlists + feature helpers (no live provider calls).
	 */

	function test_bigtree_ai_model_allowlists() {
		T::ok(BigTreeAI::isValidModel("xai", "grok-4-latest"), "xAI grok-4-latest is allowlisted");
		T::ok(BigTreeAI::isValidModel("openai", "gpt-4.1"), "OpenAI gpt-4.1 is allowlisted");
		T::ok(BigTreeAI::isValidModel("anthropic", "claude-sonnet-4-20250514"), "Anthropic sonnet is allowlisted");
		T::ok(!BigTreeAI::isValidModel("openai", "not-a-real-model"), "unknown model rejected");
		T::ok(!BigTreeAI::isValidModel("", "gpt-4.1"), "empty service rejects models");

		$xai = BigTreeAI::modelsForService("xai");
		T::ok(is_array($xai) && count($xai) >= 1, "xAI model list is non-empty");
		T::equals($xai[0]["id"] ?? null, "grok-4-latest", "first xAI model is grok-4-latest");
	}

	function test_bigtree_ai_services_constant() {
		T::ok(in_array("xai", BigTreeAI::SERVICES, true), "xai service present");
		T::ok(in_array("openai", BigTreeAI::SERVICES, true), "openai service present");
		T::ok(in_array("anthropic", BigTreeAI::SERVICES, true), "anthropic service present");
	}

	function test_bigtree_ai_embedding_allowlists() {
		T::ok(BigTreeAI::isValidEmbeddingModel("text-embedding-3-small"), "openai embedding model allowed");
		T::ok(!BigTreeAI::isValidEmbeddingModel("not-a-model"), "unknown embedding model rejected");
		T::equals(BigTreeAI::EMBEDDING_DIMENSIONS, 1536, "default embedding dimensions are 1536");
		T::equals(BigTreeAI::EMBEDDING_ENDPOINT, "https://api.openai.com/v1/embeddings", "embeddings always use OpenAI");
		T::ok(count(BigTreeAI::embeddingModels()) >= 1, "embedding models list non-empty");
		// Any chat service can pair with OpenAI embeddings (via embedding_api_key).
		T::ok(count(BigTreeAI::embeddingModelsForService("xai")) >= 1, "xAI chat still lists embedding models");
		T::ok(count(BigTreeAI::embeddingModelsForService("anthropic")) >= 1, "Anthropic chat still lists embedding models");
	}

	function test_bigtree_ai_embedding_key_resolution() {
		$ai = (new ReflectionClass("BigTreeAI"))->newInstanceWithoutConstructor();
		$ai->Error = false;
		$ai->Service = "xai";
		$ai->Model = "grok-4-latest";
		$ai->EmbeddingModel = "text-embedding-3-small";
		$ai->Settings = [
			"service" => "xai",
			"api_key" => "xai-key",
			"model" => "grok-4-latest",
			"embedding_model" => "text-embedding-3-small",
			"embedding_api_key" => "",
			"features" => ["search" => true, "embeddings" => true],
		];

		T::ok($ai->isConfigured(), "xAI chat configured");
		T::ok(!$ai->isEmbeddingsConfigured(), "xAI chat alone is not enough for embeddings");
		T::equals($ai->embeddingApiKey(), "", "no embedding key without dedicated or OpenAI chat");

		$ai->Settings["embedding_api_key"] = "openai-embed-key";
		T::ok($ai->isEmbeddingsConfigured(), "xAI chat + OpenAI embedding key works");
		T::equals($ai->embeddingApiKey(), "openai-embed-key", "uses dedicated embedding key");

		$ai->Service = "openai";
		$ai->Model = "gpt-4.1";
		$ai->Settings = [
			"service" => "openai",
			"api_key" => "openai-chat-key",
			"model" => "gpt-4.1",
			"embedding_model" => "text-embedding-3-small",
			"embedding_api_key" => "",
			"features" => ["embeddings" => true],
		];
		T::ok($ai->isEmbeddingsConfigured(), "OpenAI chat key doubles as embedding key");
		T::equals($ai->embeddingApiKey(), "openai-chat-key", "reuses OpenAI chat key");
	}

	function test_bigtree_ai_unconfigured_feature_gate() {
		// Avoid BigTreeCMS::getSetting (needs DB) — exercise the helpers only.
		$ai = (new ReflectionClass("BigTreeAI"))->newInstanceWithoutConstructor();
		$ai->Error = false;
		$ai->Service = "";
		$ai->Model = "";
		$ai->Settings = [
			"service" => "",
			"api_key" => "",
			"model" => "",
			"features" => ["search" => true],
		];

		T::ok(!$ai->isConfigured(), "empty service is not configured");
		T::ok(!$ai->isFeatureEnabled("search"), "search feature requires configuration");

		$ai->Service = "xai";
		$ai->Model = "grok-4-latest";
		$ai->Settings = [
			"service" => "xai",
			"api_key" => "test-key",
			"model" => "grok-4-latest",
			"features" => ["search" => true],
		];

		T::ok($ai->isConfigured(), "service+key+model is configured");
		T::ok($ai->isFeatureEnabled("search"), "search enabled when configured + flagged");

		$ai->Settings["features"]["search"] = false;
		T::ok(!$ai->isFeatureEnabled("search"), "search off when flag false");
	}
