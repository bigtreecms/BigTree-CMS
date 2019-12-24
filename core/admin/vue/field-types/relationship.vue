<script>
	Vue.component("FieldTypeRelationship", {
		extends: BigTreeFieldType,
		props: [
			"draggable",
			"maximum",
			"minimum",
			"options"
		],
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
			} else {
				unused_options = this.options;
			}

			return {
				button: false,
				existing: existing,
				max_message: false,
				select: false,
				unused_options: unused_options
			};
		},
		computed: {
			below_maximum: function() {
				return (!this.maximum || this.existing.length < this.maximum);
			}
		},
		methods: {
			add: function(ev) {
				ev.preventDefault();

				let option = this.select.find("option:selected");

				if (option.length) {
					this.existing.push({ value: this.select.val(), title: option.text() });
					option.remove();
				}
			},

			remove: async function(index, ev) {
				ev.preventDefault();

				if (!await BigTree.confirm(this.translate("Are you sure you want to remove this relationship?"))) {
					return;
				}

				let option = $('<option value="' + this.existing[index].value + '">').text(this.existing[index].title);
				this.existing.splice(index, 1);
				this.select.append(option);
			},

			validate: function() {
				if (this.minimum && this.existing.length < this.minimum) {
					this.error = this.translate("Enter at least :count:", { ":count:": this.minimum });
					this.select.addClass("invalid");
					this.$parent.$emit("field-error");

					return;
				}

				if (!this.required || this.existing.length) {
					this.error = null;
					this.select.removeClass("invalid");
					this.$parent.$emit("validated");

					return;
				}

				this.select.addClass("invalid");
				this.error = this.translate("Required");
				this.$parent.$emit("field-error");
			}
		},
		mounted: function() {
			let el = $(this.$el);
			this.button = el.find(".field_relationship_add");
			this.select = el.find("select");
		}
	});
</script>

<template>
	<field :title="title" :subtitle="subtitle" set="true" :required="required" :error="error">
		<div class="field_matrix_list layout_items">
			<div class="field_matrix">
				<div class="field_matrix_headings">
					<span class="field_matrix_heading">Title</span>
					<span class="field_matrix_heading actions">Actions</span>
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
						<template v-if="below_maximum">
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
						</template>
						
						<p v-if="!below_maximum" class="field_relationship_max_message">
							{{ translate("The maximum number of relationships has been reached.") }}
						</p>
					</div>
				</div>
			</div>
		</div>
	</field>
</template>