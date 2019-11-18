<script>
	Vue.component("FieldTypePhoneNumber", {
		extends: BigTreeFieldType,
		data: function() {
			return {
				area_code: (typeof this.value === "object") ? this.value.phone_1 : "",
				prefix: (typeof this.value === "object") ? this.value.phone_2 : "",
				line_number: (typeof this.value === "object") ? this.value.phone_3 : ""
			}
		},
		calculated: {
			current_value: function() {
				return {
					area_code: this.area_code,
					prefix: this.prefix,
					line_number: this.line_number
				}
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_phone">
			<div class="field_group">
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_1_' + uid">{{ translate("First 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_1_' + uid" :name="name + '[phone_1]'"
						   v-model="area_code" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_2_' + uid">{{ translate("Second 3 Digits") }}</label>
					<input class="field_input" :id="'field_phone_2_' + uid" :name="name + '[phone_2]'"
						   v-model="prefix" :required="required" placeholder="***" type="tel">
				</div>
				
				<div class="field_wrapper">
					<label class="field_label" :for="'field_phone_3_' + uid">{{ translate("Last 4 Digits") }}</label>
					<input class="field_input" :id="'field_phone_3_' + uid" :name="name + '[phone_3]'"
						   v-model="line_number" :required="required" placeholder="****" type="tel">
				</div>
			</div>
		</div>
	</field>
</template>