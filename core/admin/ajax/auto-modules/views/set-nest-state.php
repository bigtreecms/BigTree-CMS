<?php
	namespace BigTree;

	Cookie::create(
		'bigtree_admin[nested_views]['.intval($_POST["view"])."][".intval($_POST["id"])."]",
		($_POST["expanded"] == "true" ? true : false),
		"+1 year"
	);