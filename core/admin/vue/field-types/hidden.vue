<script>
	Vue.component("FieldTypeHiddenValue", {
		extends: FieldType
	});
</script>

<template>
	<input type="hidden" :name="name" :value="value">
</template>