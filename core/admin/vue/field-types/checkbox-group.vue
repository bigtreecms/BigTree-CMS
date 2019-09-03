<script>
	Vue.component("field-checkbox-group", {
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
				current_value: this.value ? this.value : [],
				uid: this._uid
			}
		},

		methods: {
			recalculate: function() {
				let value = [];

				$(this.$el).find("input").each(function() {
					if ($(this).prop("checked")) {
						value.push($(this).val());
					}
				});

				this.current_value = value;
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true">
		<div class="field_choices">
			<div class="field_choice" v-for="(option, index) in options">
				<input class="field_choice_input" :name="name + '[]'" v-on:change="recalculate"
					   :checked="current_value.indexOf(option.value) > -1"
					   :value="option.value" type="checkbox" :id="'field_' + uid + '_' + index">
				<span class="field_choice_indicator field_choice_indicator_checkbox"></span>
				<label class="field_choice_label" :for="'field_' + uid + '_' + index">{{ option.title }}</label>
			</div>
		</div>
	</field>
</template>