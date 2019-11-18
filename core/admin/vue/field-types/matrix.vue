<script>
	Vue.component("FieldTypeMatrix", {
		extends: BigTreeFieldType,
		props: ["limit", "columns"],
		data: function() {
			return {
				mutable_data: this.value
			}
		},
		methods: {
			add: function() {

			},
			edit: function(index) {

			},
			remove: function(index) {

			}
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_matrix_list layout_items">
			<div class="field_matrix">
				<div class="field_matrix_headings">
					<span class="field_matrix_heading">{{ translate("Title") }}</span>
					<span class="field_matrix_heading">{{ translate("Actions") }}</span>
				</div>

				<div class="field_matrix_body">
					<draggable v-model="mutable_data" draggable=".field_matrix_item" handle=".field_matrix_icon"
							   tag="div" class="field_matrix_items">
						<div class="field_matrix_item" draggable="true" v-for="(item, index) in mutable_data">
							<div class="field_matrix_row">
								<div class="field_matrix_column">
									<icon wrapper="field_matrix" icon="drag_handle"></icon>
									<span class="field_matrix_detail">{{ item['__internal-title'] }}</span>
								</div>
								<div class="field_matrix_column">
									<span class="field_matrix_detail">{{ item['__internal-subtitle'] }}</span>
								</div>
								<div class="field_matrix_column">
									<button class="field_matrix_action" v-on:click="edit(index)">{{ translate('Edit') }}</button>
									<button class="field_matrix_action" v-on:click="remove(index)">{{ translate('Delete') }}</button>
								</div>
							</div>

							<div class="field_matrix_form">
								<slot :name="'matrix_form_elements_' + index"></slot>
								<button class="field_matrix_commit field_matrix_commit_save">Save</button>
								<button class="field_matrix_commit field_matrix_commit_cancel">Cancel</button>
							</div>
						</div>
					</draggable>

					<div class="field_matrix_tools">
						<button class="field_matrix_tool" v-on:click="add">
							<span class="field_matrix_tool_inner">
								<icon wrapper="field_matrix_tool" icon="add_circle_outline"></icon>
								<span class="field_matrix_tool_label">{{ translate('Add Item') }}</span>
							</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</field>
</template>