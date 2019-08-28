<script>
	Vue.component("field-text", {
		props: [
			"id",
			"title",
			"subtitle",
			"name",
			"value",
			"placeholder",
			"required",
			"maxlength",
			"title",
			"subtitle",
			"type"
		],
		
		data: function() {
			return {
				current_value: this.value,
				max_length_int: parseInt(this.maxlength)
			}
		},
		
		computed: {
			characters_left: function() {
				if (!this.max_length_int) {
					return null;
				}
				
				const length = this.current_value.length;
				const left = this.max_length_int - length;
				
				return left > 0 ? left : 0;
			},
			
			help_text: function() {
				if (this.characters_left !== null) {
					return this.characters_left + " characters remaining";
				}
				
				return "";
			},
			
			help_text_style: function() {
				const percentage = this.characters_left / this.max_length_int;
				let color = "#00825a";
				
				if (percentage < 0.5) {
					color = this.color_mesh("#d32f2f", "#fd9725", percentage / 0.5);
				} else {
					color = this.color_mesh("#fd9725", "#00825a", (percentage - 0.5) / 0.5);
				}
				
				return "color: " + color + ";";
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + this._uid"
		   :help_text="help_text" :help_text_style="help_text_style">
		<textarea v-if="type === 'textarea'" class="field_input field_input_textarea" :type="type ? type : 'text'"
				  :name="name" v-model="current_value" :id="'field_' + this._uid" :placeholder="placeholder"
				  :required="required" :maxlength="maxlength"></textarea>
		<input v-else class="field_input" :type="type ? type : 'text'" :name="name" v-model="current_value"
			   :id="'field_' + this._uid" :placeholder="placeholder" :required="required" :maxlength="maxlength">
	</field>
</template>