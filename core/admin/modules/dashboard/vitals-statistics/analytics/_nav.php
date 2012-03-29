<nav class="sub">
	<ul>
		<li><a href="<?=$mroot?>"<? if (end($path) == "analytics") { ?> class="active"<? } ?>><span class="icon_small icon_small_clock"></span>Traffic Report</a></li>
		<li><a href="<?=$mroot?>service-providers/"<? if (end($path) == "service-providers") { ?> class="active"<? } ?>><span class="icon_small icon_small_broadcast"></span>Service Providers</a></li>
		<li><a href="<?=$mroot?>traffic-sources/"<? if (end($path) == "traffic-sources") { ?> class="active"<? } ?>><span class="icon_small icon_small_car"></span>Traffic Sources</a></li>
		<li><a href="<?=$mroot?>keywords/"<? if (end($path) == "keywords") { ?> class="active"<? } ?>><span class="icon_small icon_small_key"></span>Keywords</a></li>
	</ul>
</div>