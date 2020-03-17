<script>
	Vue.component("FieldTypeDatetime", {
		extends: BigTreeFieldType,
		mounted: function() {
			$(this.$el).find("input").datetimepicker({
				dateFormat: DateFormat,
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
		<span class="field_date">
			<icon wrapper="field_date" icon="calendar_today"></icon>
			<input class="field_input field_input_date" type="text" :name="name" v-model="current_value"
				   :id="'field_' + uid" :disabled="disabled" :required="required" autocomplete="off">
		</span>
	</field>
</template>