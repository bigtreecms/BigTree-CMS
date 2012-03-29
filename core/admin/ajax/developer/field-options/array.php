<h4>Fields <a href="#" class="add_option"><img src="<?=$admin_root?>images/add.png" alt="" /></a></h4>
<fieldset>
	<div class="list_attr" id="pop_option_list">
		<ul>
			<li>Title</li>
			<li>Key</li>
		</ul>
		<?
			// If we have fields already, show them.
			if (!empty($d["fields"])) {
				$x = 0;
				foreach ($d["fields"] as $option) {
		?>
		<ul>
			<li>
				<input type="text" name="fields[<?=$x?>][title]" value="<?=htmlspecialchars($option["title"])?>" />
			</li>
			<li>
				<input type="text" name="fields[<?=$x?>][key]" value="<?=htmlspecialchars($option["key"])?>" />
			</li>
			<li class="del"><a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a></li>
		</ul>
		<?
					$x++;
				}
			
			// Otherwise, let's draw a single entry so they don't need to click the + already.
			} else {
		?>
		<ul>
			<li>
				<input type="text" name="fields[<?=$x?>][title]" value="" />
			</li>
			<li>
				<input type="text" name="fields[<?=$x?>][key]" value="" />
			</li>
			<li class="del"><a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a></li>
		</ul>
		<?
			}
		?>
	</div>
</fieldset>

<script type="text/javascript">
	var option_count = <?=$x?>;
	
	$(".list_attr").on("click",".del a",function() {
		$(this).parents("ul").remove();
		return false;
	});
	
	$(".add_option").click(_local_addOptionClick);
	
	$(".list_attr input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			_local_addOptionClick();
			return false;
		}
	});
	
	function _local_addOptionClick() {
		option_count++;

		ul = $('<ul>');
		
		li_value = $('<li>');
		li_value.html('<input type="text" name="fields[' + option_count + '][title]" value="" />');
		ul.append(li_value);
		
		li_description = $('<li>');
		li_description.html('<input type="text" name="fields[' + option_count + '][key]" value="" />');
		ul.append(li_description);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a>');
		li_del.find("a").click(function() {
			$(this).parents("ul").remove();
			return false;
		});		
		ul.append(li_del);
		
		$("#pop_option_list").append(ul);
		
		return false;
	}
</script>