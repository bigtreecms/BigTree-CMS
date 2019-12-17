<script>
	Vue.component("Field", {
		props: [
			"title",
			"subtitle",
			"link",
			"help_text",
			"help_text_class",
			"help_text_style",
			"label_for",
			"set",
			"required",
			"validation",
			"error"
		],

		methods: {
			focus: function() {
				const for_id = $(this.$el).find("label").attr("for");

				if (for_id) {
					$("#" + for_id).focus();
				}
			}
		}
	});
</script>

<template>
	<div class="block">
		<fieldset v-if="set" class="field">
			<div class="field_header">
				<legend class="field_header_group" v-on:click="focus">
					<span class="field_title">{{ title }}</span>
					<span class="field_hint">{{ subtitle }}</span>
				</legend>

				<div class="field_header_group" v-if="help_text || error || required">
					<span class="field_message field_error" v-if="error && error != translate('Required')">{{ error }}</span>
					<span class="field_message field_status" v-if="help_text" :class="help_text_class" :style="help_text_style">{{ help_text }}</span>
					<span class="field_message field_required" v-if="required" :class="error ? 'field_error' : ''">{{ translate('Required') }}</span>
				</div>
				
				<div class="field_header_group" v-if="link">
					<a class="field_link" :href="link.url">
						<icon v-if="link.icon" :icon="link.icon" wrapper="field_link"></icon>
						<span class="field_link_label">{{ link.title }}</span>
					</a>
				</div>
			</div>
			
			<slot></slot>
		</fieldset>
		
		<div v-else class="field">
			<div class="field_header">
				<label class="field_header_group" :for="label_for ? label_for : ''">
					<span class="field_title">{{ title }}</span>
					<span class="field_hint">{{ subtitle }}</span>
				</label>
				
				<div class="field_header_group" v-if="help_text || error || required">
					<span class="field_message field_error" v-if="error && error != translate('Required')">{{ error }}</span>
					<span class="field_message field_status" v-if="help_text" :class="help_text_class" :style="help_text_style">{{ help_text }}</span>
					<span class="field_message field_required" v-if="required" :class="error ? 'field_error' : ''">{{ translate('Required') }}</span>
				</div>
				
				<div class="field_header_group" v-if="link">
					<a class="field_link" :href="link.url">
						<icon v-if="link.icon" :icon="link.icon" wrapper="field_link"></icon>
						<span class="field_link_label">{{ link.title }}</span>
					</a>
				</div>
			</div>
			
			<slot></slot>
		</div>
	</div>
</template>