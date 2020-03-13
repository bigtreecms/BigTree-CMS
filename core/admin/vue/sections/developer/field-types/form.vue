<script>
	Vue.component("FieldTypesForm", {
		props: [
			"action",
			"id",
			"name",
			"use_cases",
			"self_draw"
		],
		data: function() {
			return {
				buttons: [
					{ title: this.translate(this.action === "create" ? "Create" : "Update"), primary: true }
				],
				form_action: WWW_ROOT + "api/field-types/" + (this.action) + "/"
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
						context: this.translate("Field Types"),
						message: this.translate((this.action === "create" ? "Created" : "Updated") + " Field Type")
					};

					BigTree.request_partial(ADMIN_ROOT + "developer/field-types/");
				}
			}
		}
	});
</script>

<template>
	<form-block v-on:response="submit" :action="form_action" :buttons="buttons">
		<field-type-hidden-value name="id" :value="id"></field-type-hidden-value>

		<div class="fields_wrapper">
			<div class="block">
				<field-type-text required="true" name="id" :value="id" :disabled="action === 'update'"
								 title="ID" subtitle="(used for filename — alphanumeric, dash, and underscore only)">
				</field-type-text>
			</div>
			
			<div class="block">
				<field-type-text required="true" name="name" :value="name" title="Name"></field-type-text>
			</div>
			
			<div class="block">
				<field-type-checkbox-group required="true" name="use_cases" :value="use_cases" title="Use Cases" :options="[
					{ 'value': 'templates', 'title': translate('Templates') },
					{ 'value': 'modules', 'title': translate('Modules') },
					{ 'value': 'callouts', 'title': translate('Callouts') },
					{ 'value': 'settings', 'title': translate('Settings') },
				]"></field-type-checkbox-group>
			</div>
			
			<div class="block">
				<field-type-checkbox name="self_draw" :value="self_draw" title="Self Draw"
									 subtitle="(if checked, you will need to draw your field wrapper, title, and subtitle manually)">
				</field-type-checkbox>
			</div>
		</div>
	</form-block>
</template>