<script>
	Vue.component("FieldTypeTime", {
		extends: BigTreeFieldType,
		mounted: function() {
			$(this.$el).find("input").timepicker({
				ampm: true,
				hourGrid: 6,
				minuteGrid: 10,
				timeFormat: "hh:mm tt",
				duration: 200,
				showAnim: "fadeIn"
			});
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid" :required="required" :error="error">
		<span class="field_time">
			<icon wrapper="field_time" icon="timer"></icon>
			<input class="field_input field_input_time" type="text" :name="name" v-model="current_value"
				   :id="'field_' + uid" :disabled="disabled" :required="required">
		</span>
	</field>
</template>