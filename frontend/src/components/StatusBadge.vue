<script setup>
defineProps({
  status: {
    type: String,
    required: true,
  },
})
</script>

<template>
  <span class="badge" :class="`badge--${status}`">{{ status }}</span>
</template>

<style scoped>
.badge {
  display: inline-block;
  padding: 0.15rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.badge--success {
  color: var(--color-success);
  background: var(--color-success-bg);
}

.badge--pending {
  color: var(--color-pending);
  background: var(--color-pending-bg);
}

.badge--failed {
  color: var(--color-failed);
  background: var(--color-failed-bg);
}
</style>
