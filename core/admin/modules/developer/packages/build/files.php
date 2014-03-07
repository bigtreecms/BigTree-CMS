<div class="container">
	<header><p>Add additional files and tables to your package.</p></header>
	<form method="post" action="<?=DEVELOPER_ROOT?>packages/build/save-files/" class="module">
		<section>
			<article class="package_column package_column_double">
				<strong>Files</strong>
				<ul id="package_files">
					<? foreach ((array)$_SESSION["bigtree_admin"]["developer"]["package"]["files"] as $file) { ?>
					<li>
						<input type="hidden" name="files[]" value="<?=htmlspecialchars($file)?>" />
						<a href="#" class="icon_small icon_small_delete"></a>
						<span><?=str_replace(SERVER_ROOT,"",$file)?></span>
					</li>
					<? } ?>
				</ul>
				<div class="add_file adder">
					<a href="#"><span class="icon_small icon_small_folder"></span>Browse For File</a>
				</div>
			</article>
			<article class="package_column package_column_double package_column_last">
				<strong>Tables</strong>
				<ul>
					<?
						foreach ((array)$_SESSION["bigtree_admin"]["developer"]["package"]["tables"] as $table_hash) {
							list($table,$type) = explode("#",$table_hash);
					?>
					<li>
						<input type="hidden" name="tables[]" value="<?=$table_hash?>" />
						<a href="#" class="icon_small icon_small_delete"></a>
						<a href="#<?=$table?>" class="icon_small <? if ($type == "with-data") { ?>icon_small_export<? } else { ?>icon_small_list<? } ?>" title="<? if ($type == "with-data") { ?>Structure &amp; Data<? } else { ?>Structure Only<? } ?>"></a>
						<?=$table?>
					</li>
					<?
						}
					?>
				</ul>
				<div class="add_table adder">
					<a class="icon_small icon_small_add" href="#"></a>
					<select class="custom_control">
						<? BigTree::getTableSelectOptions(); ?>
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
		table = $(this).next().val();
		if (table) {
			li = $("<li>");
			li.html('<input type="hidden" name="tables[]" value="' + table + '#structure" /><a href="#" class="icon_small icon_small_delete"></a><a href="#' + table + '" class="icon_small icon_small_list"></a>' + table);
			$(this).parent().parent().find("ul").append(li);
		}
		return false;
	});

	$(".add_file a").click(function(ev) {
		new BigTreeFoundryBrowser("",function(data) {
			li = $("<li>");
			li.html('<input type="hidden" name="files[]" value="<?=SERVER_ROOT?>' + data.directory + data.file + '" /><a href="#" class="icon_small icon_small_delete"></a>' + data.directory + data.file);
			$("#package_files").append(li);
		},true);
		return false;
	});

	$(".package_column").on("click",".icon_small_export",function() {
		$(this).parent().find("input").val($(this).attr("href").substr(1) + "#structure");
		$(this).removeClass("icon_small_export").addClass("icon_small_list").attr("title","Structure Only");
		return false;
	}).on("click",".icon_small_list",function() {
		$(this).parent().find("input").val($(this).attr("href").substr(1) + "#with-data");
		$(this).removeClass("icon_small_list").addClass("icon_small_export").attr("title","Structure & Data");
		return false;
	}).on("click",".icon_small_delete",function() {
		$(this).parent().remove();
		return false;
	});
</script>