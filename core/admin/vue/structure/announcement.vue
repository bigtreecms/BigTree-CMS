<script>
	Vue.component("Announcement", {
		props: ["context", "message", "visible", "type"]
	});
</script>

<template>
	<div class="announcement" :class="visible ? 'announcement_visible' : ''" aria-live="polite">
		<span class="announcement_context" v-if="context">{{ context }}</span>
		<h2 class="announcement_message" v-if="message">{{ message }}</h2>
	</div>
</template>