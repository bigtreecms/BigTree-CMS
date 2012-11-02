<nav class="sub">
	<ul>
		<li><a href="<?=$mroot?>"<? if (end($bigtree["path"]) == "analytics") { ?> class="active"<? } ?>><span class="icon_small icon_small_bar_graph"></span>Traffic Report</a></li>
		<li><a href="<?=$mroot?>service-providers/"<? if (end($bigtree["path"]) == "service-providers") { ?> class="active"<? } ?>><span class="icon_small icon_small_network"></span>Service Providers</a></li>
		<li><a href="<?=$mroot?>traffic-sources/"<? if (end($bigtree["path"]) == "traffic-sources") { ?> class="active"<? } ?>><span class="icon_small icon_small_car"></span>Traffic Sources</a></li>
		<li><a href="<?=$mroot?>keywords/"<? if (end($bigtree["path"]) == "keywords") { ?> class="active"<? } ?>><span class="icon_small icon_small_key"></span>Keywords</a></li>
		<li><a href="<?=$mroot?>configure/"<? if (end($bigtree["path"]) == "configure") { ?> class="active"<? } ?>><span class="icon_small icon_small_setup"></span>Configure</a></li>
	</ul>
</nav>