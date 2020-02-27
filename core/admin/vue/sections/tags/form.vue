<script>
	Vue.component("TagsForm", {
		props: [
			"action",
			"id",
			"other_tags",
			"tag"
		],
		data: function() {
			return {
				buttons: [
					{ title: this.translate(this.action === "create" ? "Create" : "Merge"), primary: true }
				],
				form_action: WWW_ROOT + "api/tags/" + this.action + "/"
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
						message: this.translate(this.action === "create" ? "Created Tag" : "Merged Tags")
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
			<field-type-hidden-value v-if="id" name="id" :value="id"></field-type-hidden-value>
			
			<field-type-text :disabled="this.action === 'merge'" :value="tag" :required="this.action === 'create'"
							 name="tag" :title="translate('Tag Name')" :subtitle="translate('(only alphanumeric and spaces allowed)')"></field-type-text>
			
			<field-type-relationship name="to_merge" :title="translate('Tags to Merge In')"
									 :options="other_tags" :minimum="this.action === 'merge' ? 1 : 0"
									 :required="this.action === 'merge'"></field-type-relationship>
		</div>
	</form-block>
</template>