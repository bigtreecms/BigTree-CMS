<script>
	Vue.component("FieldTypeRadio", {
		extends: BigTreeFieldType,
		props: ["options"],
		data: function() {
			return {
				current_value: this.value ? this.value : []
			}
		},
		methods: {
			validate: function() {
				let choices = $(this.$el).find(".field_choices");

				if (this.required && !$(this.$el).find("input:checked").length) {
					this.error = this.translate("Required");
					this.$parent.$emit("field-error");
					choices.addClass("invalid");
						
					return;
				}

				choices.removeClass("invalid");
				this.error = null;
				this.$parent.$emit("validated");
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_choices">
			<div class="field_choice" v-for="(option, index) in options">
				<input class="field_choice_input" :name="name" :value="option.value" type="radio"
					   :disabled="disabled" :id="'field_' + this._uid + '_' + index" v-model="current_value">
				<span class="field_choice_indicator field_choice_indicator_radio"></span>
				<label class="field_choice_label" :for="'field_' + this._uid + '_' + index">{{ option.title }}</label>
			</div>
		</div>
	</field>
</template>