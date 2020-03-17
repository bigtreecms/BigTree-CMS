<script>
	Vue.component("FieldTypeCheckbox", {
		extends: BigTreeFieldType,
		props: ["checked"],
		data: function() {
			return {
				value: this.value ? this.value : "on"
			};
		}
	});
</script>

<template>
	<div class="block">
		<div class="field_choice field_choice_single">
			<input class="field_choice_input" :id="'field_' + uid" :name="name" type="checkbox" :value="value"
				   :disabled="disabled" :checked="checked">
			<span class="field_choice_indicator field_choice_indicator_checkbox"></span>
			<label class="field_choice_label" :for="'field_' + uid">{{ title }}<span class="field_choice_label_hint" v-if="subtitle">{{ subtitle }}</span></label>
		</div>
	</div>
</template>