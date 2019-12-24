<script>
	Vue.component("TagsForm", {
		props: ["other_tags"],
		data: function() {
			return {
				buttons: [
					{ title: this.translate("Create"), primary: true }
				],
				form_action: WWW_ROOT + "api/tags/create/"
			}
		},
		methods: {
			submit: async function(api) {
				if (api.error) {
					BigTree.notification = {
						type: "error",
						context: this.translate("Submission Failed"),
						message: this.translate(api.message)
					};
				} else {
					BigTree.notification = {
						type: "success",
						context: this.translate("Tags"),
						message: this.translate("Created Tag")
					};

					BigTree.request_partial(ADMIN_ROOT + "tags/");
				}
			}
		}
	});
</script>

<template>
	<form-block v-on:response="submit" :action="form_action" :buttons="buttons">
		<div class="fields_wrapper theme_grid">
			<field-type-text required="true" name="tag" :title="translate('Tag Name')"></field-type-text>
			<field-type-relationship name="to_merge" :title="translate('Tags to Merge In')" :options="other_tags"></field-type-relationship>
		</div>
	</form-block>
</template>