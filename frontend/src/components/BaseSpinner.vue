<script setup>
defineProps({
  label: {
    type: String,
    default: 'Loading…',
  },
})
</script>

<template>
  <div class="spinner-row">
    <span class="spinner" aria-hidden="true"></span>
    <span>{{ label }}</span>
  </div>
</template>

<style scoped>
.spinner-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  opacity: 0.75;
}

.spinner {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-heading);
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
