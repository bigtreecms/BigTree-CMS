<?php
	namespace BigTree;
	
	function include_with($path, $variables = []) {
		foreach ($variables as $key => $value) {
			$$key = $value;
		}
		
		if (file_exists($path)) {
			include $path;
		} else {
			include Router::getIncludePath("layouts/partials/".$path.".php");
		}
	}
	
	function icon($class, $name) {
?>
<span class="<?=$class?>_icon"><?php include SERVER_ROOT."core/admin/images/icons/".$name.".svg"; ?></span>
<?php
	}
	
	function button($class, $title, $url, $icon) {
?>
<a class="<?=$class?>_button" href="<?=$url?>">
	<span class="<?=$class?>_button_inner">
		<span class="<?=$class?>_button_icon"><?php SERVER_ROOT."core/admin/images/icons/".$icon.".svg"; ?></span>
		<span class="<?=$class?>_button_label"><?=Text::translate($title)?></span>
	</span>
</a>
<?php
	}
