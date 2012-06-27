<nav class="sub">
	<ul>
		<li><a href="<?=$section_root?>view/"<? if (end($bigtree["path"]) == "view") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Templates</a></li>
		<li><a href="<?=$section_root?>add/"<? if (end($bigtree["path"]) == "add") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>Add Template</a></li>
	</ul>
</nav>