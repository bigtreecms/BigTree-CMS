<?php
	namespace BigTree;
?>
<div id="analytics_tab"></div>
<script>
	BigTreeTable({
		container: "#analytics_tab",
		title: "Keywords",
		columns: {
			name: { title: "Keyword" },
			visits: { title: "Visits", size: 115, center: true },
			views: { title: "Views", size: 115, center: true }
		},
		data: <?=JSON::encodeColumns($cache["keywords"],array("name","visits","views"))?>,
		searchable: true,
		sortable: true
	});
</script>