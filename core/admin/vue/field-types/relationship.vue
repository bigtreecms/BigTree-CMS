<script>
	Vue.component("FieldTypeRelationship", {
		extends: BigTreeFieldType,
		props: ["options", "draggable"],
		data: function() {
			let existing = [];
			let unused_options = [];
			
			// Provide titles to the existing values
			if (typeof this.value === "object") {
				for (let x = 0; x < this.value.length; x++) {
					for (let y = 0; y < this.options.length; y++) {
						if (String(this.options[y].value) === String(this.value[x])) {
							existing.push({ title: this.options[y].title, value: this.value[x] });
						}
					}
				}
			}
			
			// Calculate which options are not used
			for (let x = 0; x < this.options.length; x++) {
				let used = false;
				
				for (let y = 0; y < this.value.length; y++) {
					if (String(this.value[y]) === String(this.options[x].value)) {
						used = true;
					}
				}
				
				if (!used) {
					unused_options.push(this.options[x]);
				}
			}
				
			return {
				existing: existing,
				select: "",
				unused_options: unused_options
			};
		},
		methods: {
			add: function(ev) {
				ev.preventDefault();
				
				let option = this.select.find("option:selected");
				this.existing.push({ value: this.select.val(), title: option.text() });
				option.remove();
			},
			
			remove: async function(index, ev) {
				ev.preventDefault();
				
				if (!await BigTree.confirm(this.translate("Are you sure you want to remove this relationship?"))) {
					return;
				}
				
				let option = $('<option value="' + this.existing[index].value + '">').text(this.existing[index].title);
				this.existing.splice(index, 1);
				this.select.append(option);
			}
		},
		mounted: function() {
			this.select = $(this.$el).find("select");
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_matrix_list layout_items">
			<div class="field_matrix">
				<div class="field_matrix_headings">
					<span class="field_matrix_heading">Title</span>
				</div>
				
				<div class="field_matrix_body">
					<draggable v-model="existing" draggable=".field_matrix_item" handle=".field_matrix_icon"
							   tag="div" class="field_matrix_items">
						<div v-for="(item, index) in existing" class="field_matrix_item" :draggable="draggable">
							<input type="hidden" :name="name + '[]'" :value="item.value">
							
							<div class="field_matrix_row">
								<div class="field_matrix_column">
									<icon v-if="draggable" icon="drag_handle" wrapper="field_matrix"></icon>
									<span class="field_matrix_detail">{{ item.title }}</span>
								</div>
								
								<div class="field_matrix_column">
									<button class="field_matrix_action" v-on:click="remove(index, $event)">Delete</button>
								</div>
							</div>
						</div>
					</draggable>
					
					<div class="field_matrix_tools">
						<label for="'field_new_' + uid" class="visually_hide">New Relationship</label>
						
						<select :id="'field_new_' + uid" class="field_select field_relationship_select">
							<option v-for="option in unused_options" :value="option.value">{{ option.title }}</option>
						</select>
						
						<button class="field_matrix_tool field_relationship_add" v-on:click="add">
							<span class="field_matrix_tool_inner">
								<icon icon="add_circle_outline" wrapper="field_matrix_tool"></icon>
								<span class="field_matrix_tool_label">Add</span>
							</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</field>
</template>