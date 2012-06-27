<?
	$admin->requireLevel(2);
	$breadcrumb[] = array("link" => "users/tokens/","title" => "API Tokens");
?>
<h1><span class="users"></span>API Tokens</h1>
<? include BigTree::path("admin/modules/users/_nav.php") ?>

<div class="table">
	<summary>
		<input type="search" name="query" id="query" placeholder="Search" class="form_search" autocomplete="off" />
		<ul id="view_paging" class="view_paging"></ul>
	</summary>
	<header>
		<span class="tokens_name">User</span>
		<span class="tokens_token">Token</span>
		<span class="tokens_type">Read Only</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="results">
		<? include BigTree::path("admin/ajax/users/get-tokens-page.php") ?>	
	</ul>
</div>

<script type="text/javascript">
	var deleteTimer,searchTimer;
	
	$("#query").keyup(function() {
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		searchTimer = setTimeout("_local_search()",400);
	});

	function _local_search() {
		$("#results").load("<?=$admin_root?>ajax/users/get-tokens-page/?page=0&query=" + escape($("#query").val()));
	}
	
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete API Token",'<p class="confirm">Are you sure you want to delete this API token?',$.proxy(function() {
			$.ajax("<?=$admin_root?>ajax/users/delete-token/", { type: "POST", data: { id: $(this).attr("href").substr(1) } });
		},this),"delete",false,"OK");
		
		return false;
	});
</script>