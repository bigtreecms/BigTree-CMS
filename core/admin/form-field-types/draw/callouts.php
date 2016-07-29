<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!is_array($this->Value)) {
		$this->Value = array();
	}

	$noun = $this->Settings["noun"] ? htmlspecialchars($this->Settings["noun"]) : "Callout";
	$max = !empty($this->Settings["max"]) ? $this->Settings["max"] : 0;

	// Work with older group info from 4.1 and lower
	if (!is_array($this->Settings["groups"]) && $this->Settings["group"]) {
		$this->Settings["groups"] = array($this->Settings["group"]);
	}
?>
<fieldset class="callouts<?php if ($bigtree["last_resource_type"] == "callouts") { ?> callouts_no_margin<?php } ?>" id="<?=$this->ID?>">
	<label<?php if ($this->LabelClass) { ?> class="<?=trim($this->LabelClass)?>"<?php } ?>>
		<?=$this->Title?>
		<?php if ($this->Subtitle) { ?> <small><?=$this->Subtitle?></small><?php } ?>
	</label>
	<div class="contain">
		<?php
			$x = 0;
			foreach ($this->Value as $callout) {
				$type = new Callout($callout["type"]);
		?>
		<article>
			<input type="hidden" class="callout_data" value="<?=base64_encode(json_encode($callout))?>" />
			<?php $this->drawArrayLevel(array($x),$callout) ?>
			<h4>
				<?=Text::htmlEncode($callout["display_title"])?>
				<input type="hidden" name="<?=$this->Key?>[<?=$x?>][display_title]" value="<?=Text::htmlEncode($callout["display_title"])?>" />
			</h4>
			<p><?=$type->Name?></p>
			<div class="bottom">
				<span class="icon_drag"></span>
				<?php if ($type->Level > Auth::user()->Level) { ?>
				<span class="icon_disabled has_tooltip" data-tooltip="<p>This callout requires a higher user level to edit.</p>"></span>
				<?php } else { ?>
				<a href="#" class="icon_edit"></a>
				<a href="#" class="icon_delete"></a>
				<?php } ?>
			</div>
		</article>
		<?php
				$x++;
			}
		?>
	</div>
	<a href="#" class="add_callout button"><span class="icon_small icon_small_add"></span>Add <?=$noun?></a>
	<?php if ($max) { ?>
	<small class="max">LIMIT <?=$max?></small>
	<?php } ?>
</fieldset>

<script>
	BigTreeCallouts({
		selector: "#<?=$this->ID?>",
		key: "<?=$this->Key?>",
		noun: "<?=$noun?>",
		groups: <?=json_encode($this->Settings["groups"])?>,
		max: <?=$max?>
	});
</script>