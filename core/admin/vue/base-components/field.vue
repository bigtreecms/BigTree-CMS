<script>
	Vue.component("field", {
		props: [
			"title",
			"subtitle",
			"link",
			"help_text",
			"help_text_class",
			"help_text_style",
			"label_for",
			"set"
		]
	});
</script>

<template>
	<div class="block">
		<fieldset v-if="set" class="field">
			<div class="field_header">
				<legend class="field_header_group">
					<span class="field_title">{{ title }}</span>
					<span class="field_hint">{{ subtitle }}</span>
				</legend>
				
				<div class="field_header_group" v-if="help_text">
					<span class="field_status" :class="help_text_class" :style="help_text_style">{{ help_text }}</span>
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
				
				<div class="field_header_group" v-if="help_text">
					<span class="field_status" :class="help_text_class" :style="help_text_style">{{ help_text }}</span>
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