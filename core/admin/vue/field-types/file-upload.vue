<script>
	Vue.component("FieldTypeFileUpload", {
		extends: BigTreeFieldType,
		props: ["placeholder", "valid_extensions"],
		computed: {
			current_file_name: function() {
				if (this.current_value) {
					let parts = this.current_value.split("/");
					
					if (parts.length === 1) {
						parts = this.current_value.split("\\");
					}
					
					return parts.pop();
				}
				
				return "";
			}
		},
		methods: {
			blur: function() {
				$(this.$el).find(".field_upload_label").removeClass("focused");
			},
			
			file_chosen: function() {
				this.current_value = $(this.$el).find("input[type=file]").val();
			},
			
			focus: function() {
				$(this.$el).find(".field_upload_label").addClass("focused");
			},
			
			remove: function() {
				this.current_value = "";
				$(this.$el).find("input").val("");
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid" :error="error" :required="required">
		<div class="field_upload">
			<input type="hidden" :name="name" :value="value">
			<input class="field_input field_input_upload" :id="'field_' + uid" :name="name" type="file"
				   :disabled="disabled" :accept="valid_extensions"
				   v-on:change="file_chosen" v-on:focus="focus" v-on:blur="blur">
			<label class="field_upload_label" :for="'field_' + uid">{{ translate('Select a File') }}</label>
			<div class="field_upload_info" v-if="current_value">
				<span class="field_upload_hint">{{ translate('Currently:') }}</span>
				<a class="field_upload_file" :href="this.value">{{ current_file_name }}</a>
				<button class="field_upload_remove" v-on:click="remove">{{ translate('Remove') }}</button>
			</div>
		</div>
	</field>
</template>