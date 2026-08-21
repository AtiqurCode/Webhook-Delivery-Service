<script setup>
defineProps({
  open: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: 'Are you sure?',
  },
  message: {
    type: String,
    default: '',
  },
  confirmLabel: {
    type: String,
    default: 'Confirm',
  },
})

const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="overlay" @click.self="emit('cancel')">
      <div class="dialog" role="alertdialog" aria-modal="true" :aria-label="title">
        <h2>{{ title }}</h2>
        <p v-if="message">{{ message }}</p>
        <div class="actions">
          <button type="button" class="btn-secondary" @click="emit('cancel')">Cancel</button>
          <button type="button" class="btn-danger" @click="emit('confirm')">{{ confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 1rem;
}

.dialog {
  background: var(--color-surface);
  color: var(--color-text);
  border-radius: 10px;
  padding: 1.5rem;
  max-width: 380px;
  width: 100%;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.dialog h2 {
  font-size: 1.05rem;
  margin-bottom: 0.5rem;
}

.dialog p {
  opacity: 0.8;
  font-size: 0.9rem;
  margin-bottom: 1.25rem;
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
}

.btn-secondary,
.btn-danger {
  padding: 0.45rem 0.9rem;
  border-radius: 6px;
  border: 1px solid var(--color-border);
  font-weight: 600;
  font-size: 0.85rem;
}

.btn-secondary {
  background: transparent;
  color: var(--color-text);
}

.btn-danger {
  background: var(--color-failed);
  border-color: var(--color-failed);
  color: white;
}
</style>
