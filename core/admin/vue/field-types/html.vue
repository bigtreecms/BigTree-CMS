<script>
	Vue.component("FieldTypeHtml", {
		extends: BigTreeFieldType,
		props: ["type"],
		computed: {
			init: function() {
				let type = "full";

				if (typeof this.type !== "undefined") {
					type = this.type;
				}

				if (TinyMCEConfig.hasOwnProperty(type)) {
					return TinyMCEConfig[type];
				} else {
					return {};
				}
			}
		},
		methods: {
			focus: function() {
				tinymce.get('field_tinymce_' + this.uid).focus();
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid" :required="required" :error="error">
		<textarea :id="'field_' + uid" style="position: absolute; left: -10000px; width: 1px; height: 1px;" :disabled="disabled" :required="required"
				  :name="name" v-model="current_value" v-on:focus="focus"></textarea>
		<tinymce-editor :init="init" :id="'field_tinymce_' + uid" v-model="current_value"></tinymce-editor>
	</field>
</template>