<script>
	Vue.component("field-html", {
		props: [
			"title",
			"subtitle",
			"name",
			"value",
			"required",
			"type"
		],

		data: function() {
			return {
				current_value: this.value,
				uid: this._uid
			}
		},

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
				tinymce.get('field_tinymce_' + uid).focus();
			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" :label_for="'field_' + uid">
		<textarea :id="'field_' + uid" style="position: absolute; left: -10000px; width: 1px; height: 1px;"
				  :name="name" :required="required" v-model="current_value" v-on:focus="focus"></textarea>
		<tinymce-editor :init="init" :id="'field_tinymce_' + uid" v-model="current_value"></tinymce-editor>
	</field>
</template>