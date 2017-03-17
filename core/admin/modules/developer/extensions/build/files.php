<div class="container">
	<header><p>Add additional files and tables to your extension.</p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>extensions/build/save-files/" class="module">
		<? $admin->drawCSRFToken() ?>
		<section>
			<article class="package_column package_column_double">
				<strong>Files</strong>
				<ul id="package_files">
					<?
						foreach ((array)$_SESSION["bigtree_admin"]["developer"]["package"]["files"] as $file) {
							if (file_exists($file)) {
					?>
					<li>
						<input type="hidden" name="files[]" value="<?=htmlspecialchars($file)?>" />
						<a href="#" class="icon_small icon_small_delete"></a>
						<span><?=str_replace(SERVER_ROOT,"",$file)?></span>
					</li>
					<?
							}
						}
					?>
				</ul>
				<div class="add_file adder">
					<a href="#"><span class="icon_small icon_small_folder"></span>Browse For File</a>
				</div>
			</article>
			<article class="package_column package_column_double package_column_last">
				<strong>Tables</strong>
				<ul>
					<?
						$used_tables = array();
						foreach ((array)$_SESSION["bigtree_admin"]["developer"]["package"]["tables"] as $table) {
							list($table) = explode("#",$table);
							$used_tables[] = $table;
					?>
					<li>
						<input type="hidden" name="tables[]" value="<?=$table?>" />
						<a href="#<?=$table?>" class="icon_small icon_small_delete"></a>
						<?=$table?>
					</li>
					<?
						}
					?>
				</ul>
				<div class="add_table adder">
					<a class="icon_small icon_small_add" href="#"></a>
					<select class="custom_control" id="add_table_select">
						<?
							$q = sqlquery("SHOW TABLES");
							while ($f = sqlfetch($q)) {
								$table = $f["Tables_in_".$bigtree["config"]["db"]["name"]];
								if (substr($table,0,8) != "bigtree_" && !in_array($table,$used_tables)) {
						?>
						<option value="<?=$table?>"><?=$table?></option>
						<?
								}
							}
						?>
					</select>
				</div>
			</article>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Continue" />
		</footer>
	</form>
</div>
<script>
	$(".add_table a").click(function(ev) {
		var table_select = $("#add_table_select");
		var table = table_select.val();
		if (table) {
			var li = $("<li>");
			li.html('<input type="hidden" name="tables[]" value="' + table + '" /><a href="#' + table + '" class="icon_small icon_small_delete"></a>' + table);
			$(this).parent().parent().find("ul").append(li);
			// Remove from the select
			table_select.find("option[value='" + table + "']").remove();
		}
		return false;
	});

	$(".add_file a").click(function(ev) {
		BigTreeFilesystemBrowser({
			directory: "",
			callback: function(data) {
				var li = $("<li>");
				li.html('<input type="hidden" name="files[]" value="<?=SERVER_ROOT?>' + data.directory + data.file + '" /><a href="#" class="icon_small icon_small_delete"></a>' + data.directory + data.file);
				$("#package_files").append(li);
			},
			disableCloud: true
		});
	});

	$(".package_column").on("click",".icon_small_delete",function() {
		// Get table name, add back to the dropdown
		var table = $(this).attr("href").substr(1);
		var option = $('<option value="' + table + '">' + table + '</option>');
		$("#add_table_select").append(option).sortSelect();
		// Remove it from the list
		$(this).parent().remove();
		return false;
	});
</script>