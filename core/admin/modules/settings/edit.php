<?php
	namespace BigTree;
	
	$setting = new Setting(Router::$Commands[0], ['BigTree\Admin', 'catch404']);
	$value = $setting->Encrypted ? "" : $setting->Value;
?>
<settings-value name="<?=$setting->Name?>" id="<?=Text::htmlEncode($setting->ID)?>">
	<?php
		$field = new Field([
			"type" => $setting->Type,
			"title" => $setting->Name,
			"subtitle" => "",
			"key" => "value",
			"tabindex" => 1,
			"settings" => $setting->Settings,
			"has_value" => !is_null($value),
			"value" => $value
		]);
		
		$field->draw();
	?>
</settings-value>