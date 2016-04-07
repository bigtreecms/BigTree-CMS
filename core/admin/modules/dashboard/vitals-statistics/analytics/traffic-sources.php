<?php
	namespace BigTree;
?>
<div id="analytics_tab"></div>
<script>
	BigTreeTable({
		container: "#analytics_tab",
		title: "Traffic sources",
		columns: {
			name: { title: "Referrer" },
			visits: { title: "Visits", size: 115, center: true },
			views: { title: "Views", size: 115, center: true }
		},
		data: <?=JSON::encodeColumns($cache["referrers"],array("name","visits","views"))?>,
		searchable: true,
		sortable: true
	});
</script>