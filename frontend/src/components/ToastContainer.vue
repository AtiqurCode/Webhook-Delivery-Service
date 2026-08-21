<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toasts">
    <TransitionGroup name="toast">
      <div v-for="toast in toasts" :key="toast.id" class="toast" :class="`toast--${toast.type}`">
        <span>{{ toast.message }}</span>
        <button type="button" class="toast-dismiss" aria-label="Dismiss" @click="dismiss(toast.id)">×</button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toasts {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: min(90vw, 360px);
}

.toast {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  color: white;
}

.toast--success {
  background: var(--color-success);
}

.toast--error {
  background: var(--color-failed);
}

.toast-dismiss {
  background: none;
  border: none;
  color: inherit;
  font-size: 1.1rem;
  line-height: 1;
  opacity: 0.85;
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
