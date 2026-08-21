import { reactive } from 'vue'

const toasts = reactive([])
let nextId = 1

function dismiss(id) {
  const index = toasts.findIndex((toast) => toast.id === id)
  if (index !== -1) {
    toasts.splice(index, 1)
  }
}

function push(message, { type = 'success', timeout = 4000 } = {}) {
  const id = nextId++
  toasts.push({ id, message, type })

  if (timeout) {
    setTimeout(() => dismiss(id), timeout)
  }

  return id
}

export function useToast() {
  return {
    toasts,
    success: (message) => push(message, { type: 'success' }),
    error: (message) => push(message, { type: 'error', timeout: 6000 }),
    dismiss,
  }
}
