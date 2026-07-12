<?php
	namespace BigTree\Api;

	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\ConflictException;
	use BigTreeJSONDB;

	/**
	 * Thin helper bound to a single JSONDB store name + human label, encapsulating
	 * the mechanical CRUD boilerplate the resource services (Callout, Template,
	 * Feed, FieldType) otherwise repeat verbatim: the create-path id prologue,
	 * the duplicate-id conflict guard, the exists-or-404 delete guard, and the
	 * position-- reorder loop.
	 *
	 * Response mapping deliberately stays out — each service keeps its own
	 * present(), since the response shape is entity-specific. This only collapses
	 * the surrounding guards/loops to one-liners.
	 *
	 * The store name + label are always code-level string literals supplied by the
	 * service, never request input.
	 */
	class JsonStore {
		public function __construct(
			private readonly string $store,
			private readonly string $label
		) {
		}

		/**
		 * Create-path prologue: pull `id` from the request body, validate it as a
		 * record key (Sanitize::isValidId), assert it is not already taken, and
		 * return the validated string. One place so every JSONDB entity create
		 * (including groups) shares the same invalid_id wording and guard.
		 *
		 * @param array $d Request body.
		 */
		public function requireNewId(array $d): string {
			$id = (string)($d["id"] ?? "");

			if (!Sanitize::isValidId($id)) {
				throw new BadRequestException(
					"{$this->label} id must be alphanumeric (with - or _) and ≤ 127 chars",
					"invalid_id"
				);
			}

			$this->assertAbsent($id);

			return $id;
		}

		/**
		 * Assert that no record with this id exists yet, or throw a 409 — the
		 * create-path guard against a duplicate id.
		 *
		 * @param mixed $id Record id (from request input).
		 */
		public function assertAbsent($id): void {
			if (BigTreeJSONDB::exists($this->store, $id)) {
				throw new ConflictException("{$this->label} $id already exists", "duplicate_id");
			}
		}

		/**
		 * Assert that a record with this id exists, or throw a 404.
		 *
		 * @param mixed $id Record id.
		 */
		public function assertExists($id): void {
			Entity::assertExistsJson($this->store, $id, $this->label);
		}

		/**
		 * Delete a record, throwing a 404 first when it is missing — assertExists()
		 * plus the delete in one call.
		 *
		 * @param mixed $id Record id.
		 */
		public function deleteOrFail($id): void {
			$this->assertExists($id);

			BigTreeJSONDB::delete($this->store, $id);
		}

		/**
		 * Apply a new ordering. Records are assigned descending positions in the
		 * given order (first id gets the highest position) so a list sorted by
		 * position DESC reflects the supplied sequence. Ids that no longer exist are
		 * skipped rather than erroring.
		 *
		 * @param array $ids Record ids in their desired display order.
		 */
		public function reorder(array $ids): void {
			$position = count($ids);

			foreach ($ids as $id) {
				if (BigTreeJSONDB::exists($this->store, $id)) {
					BigTreeJSONDB::update($this->store, $id, ["position" => $position--]);
				}
			}
		}
	}
