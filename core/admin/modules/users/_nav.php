<nav class="sub">
	<ul>
		<li><a href="<?=ADMIN_ROOT?>users/view/"<? if (end($bigtree["path"]) == "view") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Users</a></li>
		<li><a href="<?=ADMIN_ROOT?>users/add/"<? if (end($bigtree["path"]) == "add") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>Add User</a></li>
	</ul>
</nav>