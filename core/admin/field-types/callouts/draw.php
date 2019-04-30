<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!is_array($this->Value)) {
		$this->Value = [];
	}

	$noun = $this->Settings["noun"] ? htmlspecialchars($this->Settings["noun"]) : "Callout";
	$max = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;

	// Work with older group info from 4.1 and lower
	if (!is_array($this->Settings["groups"]) && $this->Settings["group"]) {
		$this->Settings["groups"] = [$this->Settings["group"]];
	}
?>
<fieldset class="callouts<?php if (Field::$LastFieldType == "callouts") { ?> callouts_no_margin<?php } ?>" id="<?=$this->ID?>">
	<label<?php if ($this->LabelClass) { ?> class="<?=trim($this->LabelClass)?>"<?php } ?>><?=$this->Title?><?php if ($this->Subtitle) { ?> <small><?=$this->Subtitle?></small><?php } ?></label>
	<div class="contain">
		<?php
			$x = 0;

			foreach ($this->Value as $callout) {
				$callout_object = new Callout($callout["type"]);
		?>
		<article>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?php $this->drawArrayLevel([$x], $callout) ?>
			<h4>
				<?=Text::htmlEncode($callout["display_title"])?>
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][display_title]" value="<?=Text::htmlEncode($callout["display_title"])?>" />
			</h4>
			<p><?=$callout_object->Name?></p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<?php if ($callout_object->Level > Auth::user()->Level) { ?>
				<span class="icon_disabled has_tooltip" data-tooltip="<p><?=Text::translate("This callout requires a higher user level to edit.", true)?></p>"></span>
				<?php } else { ?>
				<a href="#" class="icon_edit" data-type="<?=Text::htmlEncode($callout["type"])?>"></a>
				<a href="#" class="icon_delete"></a>
				<?php } ?>
			</div>
		</article>
		<?php
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_callout add_item_button button"><span class="icon_small icon_small_add"></span><?=Text::translate("Add :noun:", true, [":noun:" => $noun])?></a>
	<?php if ($max) { ?>
	<small class="max"><?=Text::translate("LIMIT :max:", true, [":max:" => $max])?></small>
	<?php } ?>
	<script>
		BigTreeCallouts({
			selector: "#<?=$this->ID?>",
			key: "<?=$this->Key?>",
			noun: "<?=$noun?>",
			groups: <?=json_encode($this->Settings["groups"])?>,
			max: <?=$max?>
		});
	</script>
</fieldset>