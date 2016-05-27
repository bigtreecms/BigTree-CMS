<?php
	namespace BigTree;

	/**
	 * @global int $id
	 * @global string $table
	 */

	if (is_numeric($id)) {
		ModuleView::cacheForAll($id, $table);
	} else {
		ModuleView::cacheForAll(substr($id, 1), $table, true);
	}