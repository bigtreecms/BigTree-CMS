			<p style="float: left; margin: 0 20px 0 0;"><span class="icon_small icon_small_export"></span> = with data</p>
			<p><span class="icon_small icon_small_list"></span> = structure only &nbsp; &nbsp; <small>(click to switch)</small></p>
			<ul>
				<li class="package_column">
					<strong>Tables</strong>
					<ul class="package_tables">
						<?
							foreach ($tables as $tinfo) {
								list($table,$type) = explode("#",$tinfo);
						?>
						<li>
							<input type="hidden" name="tables[]" value="<?=$tinfo?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<a href="#<?=$table?>" class="icon_small <? if ($type == "with-data") { ?>icon_small_export<? } else { ?>icon_small_list<? } ?>"></a>
							<?=$table?>
						</li>
						<?
							}
						?>
					</ul>
					<div class="add_table adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_table">
							<? BigTree::getTableSelectOptions(); ?>
						</select>
					</div>
				</li>
				<li class="package_column">
					<strong>Templates</strong>
					<ul class="package_tables">
						<? foreach ($templates as $template) { ?>
						<li>
							<input type="hidden" name="templates[]" value="<?=$template?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<?=$template?>
						</li>
						<? } ?>
					</ul>
					<div class="add_template adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_template">
							<?
								$t_list = $admin->getTemplates("name ASC");
								foreach ($t_list as $t) {
							?>
							<option value="<?=$t["id"]?>"><?=$t["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</li>
				<li class="package_column package_column_last">
					<strong>Callouts</strong>
					<ul class="package_tables">
					</ul>
					<div class="add_callout adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_callout">
							<?
								$q = sqlquery("select * from bigtree_callouts order by id");
								while ($f = sqlfetch($q)) {
							?>
							<option value="<?=$f["id"]?>"><?=$f["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</li>
				<li class="package_column clear">
					<strong>Settings</strong>
					<ul class="package_tables">
						<? foreach ($settings as $setting) { ?>
						<li>
							<input type="hidden" name="settings[]" value="<?=$setting?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<?=$setting?>
						</li>
						<? } ?>
					</ul>
					<div class="add_setting adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_setting">
							<?
								$q = sqlquery("SELECT * FROM bigtree_settings WHERE system = '' ORDER BY name");
								while ($f = sqlfetch($q)) {
							?>
							<option value="<?=$f["id"]?>"><?=$f["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</li>
				<li class="package_column">
					<strong>Feeds</strong>
					<ul class="package_tables">
						<? foreach ($feeds as $feed => $name) { ?>
						<li>
							<input type="hidden" name="feeds[]" value="<?=$feed?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<?=$name?>
						</li>
						<? } ?>
					</ul>
					<div class="add_feed adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_feed">
							<?
								$q = sqlquery("select * from bigtree_feeds order by name");
								while ($f = sqlfetch($q)) {
							?>
							<option value="<?=$f["id"]?>"><?=$f["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</li>
				<li class="package_column package_column_last">
					<strong>Field Types</strong>
					<ul class="package_tables">
						<? foreach ($field_types as $type) { ?>
						<li>
							<input type="hidden" name="field_types[]" value="<?=$type["id"]?>" />
							<a href="#" class="icon_small icon_small_delete"></a>
							<?=$type["name"]?>
						</li>
						<? } ?>
					</ul>
					<div class="add_feed adder">
						<a class="icon_small icon_small_add" href="#"></a>
						<select class="custom_control" id="add_feed">
							<?
								$q = sqlquery("select * from bigtree_field_types order by name");
								while ($f = sqlfetch($q)) {
							?>
							<option value="<?=$f["id"]?>"><?=$f["name"]?></option>
							<?
								}
							?>
						</select>
					</div>
				</li>
				<li class="package_column clear" id="class_files">
					<strong>Class Files</strong>
					<ul class="package_files">
						<? foreach ($class_files as $mid => $file) { ?>
						<li>
							<input type="hidden" name="class_files[<?=$mid?>]" value="<?=htmlspecialchars($file)?>" />
							<a href="#<?=$table?>" class="icon_small icon_small_delete"></a>
							<span><?=$file?></span>
						</li>
						<? } ?>
					</ul>
				</li>
				<li class="package_column" id="required_files">
					<strong>Required Includes</strong>
					<ul class="package_files">
						<? foreach ($required_files as $file) { ?>
						<li>
							<input type="hidden" name="required_files[]" value="<?=htmlspecialchars($file)?>" />
							<a href="#<?=$table?>" class="icon_small icon_small_delete"></a>
							<span><?=$file?></span>
						</li>
						<? } ?>
					</ul>
					<div class="add_file adder">
						<a class="required_browse" href="#"><span class="icon_small icon_small_folder"></span>Browse For File</a>
					</div>
				</li>
				<li class="package_column package_column_last" id="other_files">
					<strong>Other Files</strong>
					<ul class="package_files">
						<? foreach ($other_files as $file) { ?>
						<li>
							<input type="hidden" name="other_files[]" value="<?=htmlspecialchars($file)?>" />
							<a href="#<?=$table?>" class="icon_small icon_small_delete"></a>
							<span><?=$file?></span>
						</li>
						<? } ?>
					</ul>
					<div class="add_file adder">
						<a class="other_browse" href="#"><span class="icon_small icon_small_folder"></span>Browse For File</a>
					</div>
				</li>
			</ul>
		</section>
		<section class="sub">
			<fieldset>
				<label>Package Name</label>
				<input type="text" name="package_name" value="<?=$default_name?>" />
			</fieldset>
			<fieldset>
				<label>Created By</label>
				<input type="text" name="created_by" value="<?=$admin->Name?>" />
			</fieldset>
			<div class="left">
				<fieldset>
					<label>Pre-Install Instructions</label>
					<textarea name="pre_instructions"></textarea>
				</fieldset>
			</div>
			<div class="right">
				<fieldset>
					<label>Post-Install Instructions</label>
					<textarea name="post_instructions"></textarea>
				</fieldset>
			</div>
			<fieldset>
				<label>PHP Install Code (runs after successful install)</label>
				<textarea name="install_code"></textarea>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Build Package" />
		</footer>
	</form>
</div>

<script>
	$(".add_table a").click(function(ev) {
		li = $("<li>");
		table = $("#add_table").val();
		li.html('<input type="hidden" name="tables[]" value="' + table + '#structure" /><a href="#" class="icon_small icon_small_delete"></a><a href="#' + table + '" class="icon_small icon_small_list"></a>' + table);
		$(this).parent().parent().find("ul").append(li);
		packageHooks();
		return false;
	});
	
	$(".add_callout a").click(function(ev) {
		li = $("<li>");
		callout = $("#add_callout").val();
		li.html('<input type="hidden" name="callouts[]" value="' + callout + '" /><a href="#" class="icon_small icon_small_delete"></a>' + callout);
		$(this).parent().parent().find("ul").append(li);
		packageHooks();
		return false;
	});
	
	$(".add_template a").click(function(ev) {
		li = $("<li>");
		template = $("#add_template").val();
		li.html('<input type="hidden" name="templates[]" value="' + template + '" /><a href="#" class="icon_small icon_small_delete"></a>' + template);
		$(this).parent().parent().find("ul").append(li);
		packageHooks();
		return false;
	});
	
	$(".add_setting a").click(function(ev) {
		li = $("<li>");
		setting = $("#add_setting").val();
		li.html('<input type="hidden" name="settings[]" value="' + setting + '" /><a href="#" class="icon_small icon_small_delete"></a>' + setting);
		$(this).parent().parent().find("ul").append(li);
		packageHooks();
		return false;
	});
	
	$(".add_feed a").click(function(ev) {
		li = $("<li>");
		feed = $("#add_feed").val();
		feed_text = $("#add_feed").get(0).options[$("#add_feed").get(0).selectedIndex].text;
		li.html('<input type="hidden" name="feeds[]" value="' + feed + '" /><a href="#" class="icon_small icon_small_delete"></a>' + feed_text);
		$(this).parent().parent().find("ul").append(li);
		packageHooks();
		return false;
	});
	
	$(".class_browse").click(function(ev) {
		new BigTreeFoundryBrowser("custom/inc/modules/",function(data) {
			doneSelectFile("class_files",data);
		});
		return false;
	});
	
	$(".required_browse").click(function(ev) {
		new BigTreeFoundryBrowser("custom/inc/required/",function(data) {
			doneSelectFile("required_files",data);
		});
		return false;
	});
	
	$(".other_browse").click(function(ev) {
		new BigTreeFoundryBrowser("",function(data) {
			doneSelectFile("other_files",data);
		});
		return false;
	});
	
	function doneSelectFile(column,data) {
		li = $("<li>");
		li.html('<input type="hidden" name="' + column + '[]" value="' + data.directory + data.file + '" /><a href="#" class="icon_small icon_small_delete"></a>' + data.directory + data.file);
		$("#" + column).find("ul").append(li);
		packageHooks();
	}
	
	function packageHooks() {
		$(".package_column .icon_small_delete").click(function(ev) {
			$(this).parent().remove();
			return false;
		});
		$(".package_column .icon_small_export").click(swapData);
		$(".package_column .icon_small_list").click(swapStructure);
	}
	
	function swapData(ev) {
		table = $(this).attr("href").substr(1);
		$(this).prev("input").val(table + "#structure");
		$(this).removeClass("icon_small_export").addClass("icon_small_list").unbind("click",swapData).bind("click",swapStructure);
		return false;
	}
	
	function swapStructure(ev) {
		table = $(this).attr("href").substr(1);
		$(this).prev("input").val(table + "#with-data");
		$(this).removeClass("icon_small_list").addClass("icon_small_export").unbind("click",swapStructure).bind("click",swapData);
		return false;
	}
	
	packageHooks();
</script>