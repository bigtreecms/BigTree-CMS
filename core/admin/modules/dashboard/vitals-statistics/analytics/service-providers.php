<?php
	namespace BigTree;
	
	/**
	 * @global array $cache
	 */
?>
<div id="analytics_tab"></div>
<script>
	BigTreeTable({
		container: "#analytics_tab",
		title: "<?=Text::translate("Service Providers")?>",
		columns: {
			name: { title: "<?=Text::translate("Service Provider")?>" },
			visits: { title: "<?=Text::translate("Visits")?>", size: 115, center: true },
			views: { title: "<?=Text::translate("Views")?>", size: 115, center: true }
		},
		data: <?=JSON::encodeColumns($cache["service_providers"], ["name","visits","views"])?>,
		searchable: true,
		sortable: true
	});
</script>