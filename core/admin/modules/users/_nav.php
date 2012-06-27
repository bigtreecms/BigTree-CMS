<nav class="sub">
	<ul>
		<li><a href="<?=ADMIN_ROOT?>users/view/"<? if (end($bigtree["path"]) == "view") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View Users</a></li>
		<li><a href="<?=ADMIN_ROOT?>users/add/"<? if (end($bigtree["path"]) == "add") { ?> class="active"<? } ?>><span class="icon_small icon_small_add"></span>Add User</a></li>
		<li><a href="<?=ADMIN_ROOT?>users/tokens/"<? if (end($bigtree["path"]) == "tokens") { ?> class="active"<? } ?>><span class="icon_small icon_small_list"></span>View API Tokens</a></li>
		<li><a href="<?=ADMIN_ROOT?>users/tokens/add-token/"<? if (end($bigtree["path"]) == "add-token") { ?> class="active"<? } ?>><span class="icon_small icon_small_token"></span>Add API Token</a></li>
	</ul>
</nav>