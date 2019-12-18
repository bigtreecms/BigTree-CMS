<script>
	Vue.component("FieldTypeName", {
		extends: BigTreeFieldType,
		data: function() {
			return {
				first: (typeof this.value === "object") ? this.value.first : "",
				last: (typeof this.value === "object") ? this.value.last : ""
			}
		},
		calculated: {
			current_value: function() {
				return {
					first: this.first,
					last: this.last
				}
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_name">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_first_name_' + uid">{{ translate("First Name") }}</label>
					<input class="field_input" :id="'field_first_name_' + uid" :name="name + '[first]'"
						   v-model="first" :placeholder="translate('First Name')" type="text"
						   :disabled="disabled" :required="required">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_last_name_' + uid">{{ translate("Last Name") }}</label>
					<input class="field_input" :id="'field_last_name_' + uid" :name="name + '[last]'"
						   v-model="last" :placeholder="translate('Last Name')" type="text"
						   :disabled="disabled" :required="required">
				</div>
			</div>
		</div>
	</field>
</template>