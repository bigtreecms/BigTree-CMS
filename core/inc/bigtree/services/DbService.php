<?php
	namespace BigTree\Services;

	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree;

	/**
	 * Database introspection for the field-settings designer. Powers the
	 * db_table / db_column / db_column_sort controls (list "db" source, route /
	 * geocoding source fields, one-to-many, many-to-many). Mirrors the legacy
	 * BigTree::getTableSelectOptions / getFieldSelectOptions helpers but returns
	 * structured [{value, label}] instead of echoing <option> HTML.
	 *
	 * Developer-gated (level 2) — table/column introspection is only exposed in
	 * the developer designers.
	 */
	class DbService {
		public function tables(Request $request) {
			global $bigtree;

			$show_all = isset($bigtree["config"]["show_all_tables_in_dropdowns"]);
			$options = [];

			$q = sqlquery("SHOW TABLES");

			while ($f = sqlfetch($q)) {
				$table_name = current($f);

				if ($show_all || substr($table_name, 0, 8) !== "bigtree_") {
					$options[] = ["value" => (string)$table_name, "label" => (string)$table_name];
				}
			}

			$r = Response::ok($options);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
		}

		public function columns(Request $request) {
			$table = $request->routeParam("table");
			$sort = !empty($request->query["sort"]);
			$description = BigTree::describeTable($table);

			if (!$description) {
				throw new NotFoundException("Table $table not found");
			}
			$options = [];

			foreach ($description["columns"] as $col) {
				$name = (string)$col["name"];

				if ($sort) {
					$options[] = ["value" => "`".$name."` ASC", "label" => $name." ASC"];
					$options[] = ["value" => "`".$name."` DESC", "label" => $name." DESC"];
				} else {
					// "type" lets the report designer default a filter type from the
					// column's SQL type (date columns → date-range, etc.). Other
					// consumers (db_column controls) simply ignore the extra field.
					$options[] = ["value" => $name, "label" => $name, "type" => (string)$col["type"]];
				}
			}

			$r = Response::ok($options);
			$r->header("Cache-Control", "private, max-age=60");

			return $r;
		}
	}
