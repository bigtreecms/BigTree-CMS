<?php
	namespace BigTree;
?>
<div id="analytics_tab"></div>
<script>
	BigTreeTable({
		container: "#analytics_tab",
		title: "<?=Text::translate("Traffic sources")?>",
		columns: {
			name: { title: "<?=Text::translate("Referrer")?>" },
			visits: { title: "<?=Text::translate("Visits")?>", size: 115, center: true },
			views: { title: "<?=Text::translate("Views")?>", size: 115, center: true }
		},
		data: <?=JSON::encodeColumns($cache["referrers"],array("name","visits","views"))?>,
		searchable: true,
		sortable: true
	});
</script>