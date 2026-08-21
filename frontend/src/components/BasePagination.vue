<script setup>
const props = defineProps({
  meta: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['change'])

function go(page) {
  if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
    emit('change', page)
  }
}
</script>

<template>
  <div v-if="meta && meta.last_page > 1" class="pagination">
    <button type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">← Prev</button>
    <span>Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</span>
    <button type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">
      Next →
    </button>
  </div>
</template>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1rem;
  font-size: 0.85rem;
}

.pagination span {
  opacity: 0.7;
}

.pagination button {
  padding: 0.35rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: default;
}
</style>
