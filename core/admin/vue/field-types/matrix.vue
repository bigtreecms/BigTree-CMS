<script>
	Vue.component("FieldTypeMatrix", {
		extends: BigTreeFieldType,
		props: ["limit", "columns"],
		data: function() {
			return {
				mutable_data: this.value,
				count: this.value ? this.value.length : 0,
				did_add: false
			}
		},
		methods: {
			add: async function(event) {
				event.preventDefault();
				
				this.count++;
				this.did_add = true;
				this.mutable_data.push({
					"__internal-title": this.translate("New Item")
				});
			},
			
			cancel: function(event, index) {
				event.preventDefault();

				const $target = $(event.target);
				const $item = $target.parents('.field_matrix_item');
				const $form = $item.find(".field_matrix_form");
				const $field_wrapper = $form.find(".field_matrix_form_fields");

				$form.hide();
				$field_wrapper.replaceWith('<div class="field_matrix_form_' + index + '"></div>');
				$item.find(".field_matrix_action").eq(0).show();
			},
			
			edit: async function(event, index) {
				event.preventDefault();
				
				const $target = $(event.target);
				const $item = $target.parents('.field_matrix_item');
				
				this.open($item, index);
			},
			
			open: async function($item, index) {
				const $form = $item.find(".field_matrix_form");
				const $field_wrapper = $form.find(".field_matrix_form_" + index);

				$item.find(".field_matrix_action").eq(0).hide();

				if ($field_wrapper.html() !== "") {
					$form.show();

					return;
				}

				BigTree.toggle_busy();

				const fields = await $.ajax("admin_root/ajax/matrix-field/", {
					method: "POST",
					data: {
						columns: this.columns,
						data: this.mutable_data[index],
						key: this.name,
						index: index
					}
				});

				BigTree.toggle_busy();

				let res = Vue.compile('<div class="field_matrix_form_fields">' + fields + '</div>');
				new Vue({
					render: res.render,
					staticRenderFns: res.staticRenderFns
				}).$mount('.field_matrix_form_' + index);

				$form.show();
			},
			
			remove: function(event, index) {
				event.preventDefault();
				
				this.mutable_data.splice(index, 1);
			},
			
			save: function(event, index) {
				event.preventDefault();

				const $target = $(event.target);
				const $item = $target.parents('.field_matrix_item');

				$item.find(".field_matrix_form").hide();
				$item.find(".field_matrix_action").eq(0).show();
			}
		},
		updated: function() {
			if (this.did_add) {
				this.open($(this.$el).find('.field_matrix_item:last-child'), this.count - 1);
				this.did_add = false;
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
					<span class="field_matrix_heading actions">{{ translate("Actions") }}</span>
				</div>

				<div class="field_matrix_body">
					<draggable draggable=".field_matrix_item" handle=".field_matrix_icon"
							   tag="div" class="field_matrix_items">
						<div class="field_matrix_item" draggable="true" v-for="(item, index) in mutable_data">
							<input type="hidden" :value="JSON.stringify(item)" :name="name + '[existing_' + index + ']'">
							
							<div class="field_matrix_row">
								<div class="field_matrix_column">
									<icon wrapper="field_matrix" icon="drag_handle"></icon>
									<span class="field_matrix_detail">{{ item['__internal-title'] }}</span>
									<span class="field_matrix_detail_secondary">{{ item['__internal-subtitle'] }}</span>
								</div>
								
								<div class="field_matrix_column">
									<button class="field_matrix_action" v-on:click="edit($event, index)">
										{{ translate('Edit') }}
									</button>
									
									<button class="field_matrix_action" v-on:click="remove($event, index)">
										{{ translate('Delete') }}
									</button>
								</div>
							</div>

							<div class="field_matrix_form">
								<div :class="'field_matrix_form_' + index"></div>
								
								<button class="field_matrix_commit field_matrix_commit_save" v-on:click="save($event, index)">
									{{ translate('Save') }}
								</button>
								
								<button class="field_matrix_commit field_matrix_commit_cancel" v-on:click="cancel($event, index)">
									{{ translate('Cancel') }}
								</button>
							</div>
						</div>
					</draggable>
					
					<div class="field_matrix_new">
					
					</div>

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