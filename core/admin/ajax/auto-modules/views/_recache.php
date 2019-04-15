<?php
	namespace BigTree;

	/**
	 * @global int $id
	 * @global string $table
	 */

	if (is_numeric($id)) {
		ModuleView::cacheForAll($table, $id);
	} else {
		ModuleView::cacheForAll($table, substr($id, 1), true);
	}
