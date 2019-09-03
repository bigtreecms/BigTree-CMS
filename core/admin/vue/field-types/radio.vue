<script>
	Vue.component("field-radio", {
		props: [
			"title",
			"subtitle",
			"name",
			"options",
			"value",
			"required"
		],

		data: function() {
			return {
				current_value: this.value,
				uid: this._uid
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true">
		<div class="field_choices">
			<div class="field_choice" v-for="(option, index) in options">
				<input class="field_choice_input" :name="name" :value="option.value" type="radio" :required="required"
					   :id="'field_' + uid + '_' + index" v-model="current_value">
				<span class="field_choice_indicator field_choice_indicator_radio"></span>
				<label class="field_choice_label" :for="'field_' + uid + '_' + index">{{ option.title }}</label>
			</div>
		</div>
	</field>
</template>