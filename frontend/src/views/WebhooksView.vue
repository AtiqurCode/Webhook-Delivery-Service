<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useToast } from '@/composables/useToast'
import BaseSpinner from '@/components/BaseSpinner.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import BasePagination from '@/components/BasePagination.vue'

const toast = useToast()

const endpoints = ref([])
const meta = ref(null)
const loading = ref(true)
const loadError = ref('')
const submitting = ref(false)
const formErrors = ref({})
const pendingRemoval = ref(null)

const form = reactive({
  name: '',
  url: '',
})

async function loadEndpoints(page = 1) {
  loading.value = true
  loadError.value = ''

  try {
    const { data, meta: pageMeta } = await api.webhooks.list({ page })
    endpoints.value = data
    meta.value = pageMeta
  } catch (error) {
    loadError.value = error instanceof ApiError ? error.message : 'Could not load webhook endpoints.'
  } finally {
    loading.value = false
  }
}

async function addEndpoint() {
  submitting.value = true
  formErrors.value = {}

  try {
    await api.webhooks.create({ name: form.name, url: form.url })
    form.name = ''
    form.url = ''
    toast.success('Webhook endpoint added.')
    await loadEndpoints()
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      formErrors.value = error.errors
    } else {
      toast.error(error instanceof ApiError ? error.message : 'Could not create the endpoint.')
    }
  } finally {
    submitting.value = false
  }
}

async function toggleActive(endpoint) {
  const previous = endpoint.is_active
  endpoint.is_active = !previous

  try {
    await api.webhooks.update(endpoint.id, { is_active: endpoint.is_active })
    toast.success(`${endpoint.name} is now ${endpoint.is_active ? 'active' : 'disabled'}.`)
  } catch {
    endpoint.is_active = previous
    toast.error(`Could not update ${endpoint.name}.`)
  }
}

function confirmRemoval(endpoint) {
  pendingRemoval.value = endpoint
}

async function removeEndpoint() {
  const endpoint = pendingRemoval.value
  pendingRemoval.value = null

  try {
    await api.webhooks.remove(endpoint.id)
    toast.success(`${endpoint.name} removed.`)
    await loadEndpoints()
  } catch {
    toast.error(`Could not remove ${endpoint.name}.`)
  }
}

onMounted(() => loadEndpoints())
</script>

<template>
  <section>
    <h1>Webhook Endpoints</h1>
    <p class="hint">Only active endpoints receive newly created events.</p>

    <form class="card" @submit.prevent="addEndpoint">
      <div class="field">
        <label for="name">Name</label>
        <input id="name" v-model="form.name" type="text" placeholder="CRM" required />
        <p v-if="formErrors.name" class="field-error">{{ formErrors.name[0] }}</p>
      </div>

      <div class="field">
        <label for="url">URL</label>
        <input
          id="url"
          v-model="form.url"
          type="url"
          placeholder="https://example.com/webhooks"
          required
        />
        <p v-if="formErrors.url" class="field-error">{{ formErrors.url[0] }}</p>
      </div>

      <button type="submit" :disabled="submitting">
        {{ submitting ? 'Adding…' : '+ Add Webhook' }}
      </button>
    </form>

    <BaseSpinner v-if="loading" label="Loading endpoints…" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="endpoints.length === 0" class="hint">No webhook endpoints registered yet.</p>

    <template v-else>
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>URL</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="endpoint in endpoints" :key="endpoint.id">
            <td>{{ endpoint.name }}</td>
            <td class="url-cell">{{ endpoint.url }}</td>
            <td>
              <button class="toggle" :class="{ 'toggle--on': endpoint.is_active }" @click="toggleActive(endpoint)">
                {{ endpoint.is_active ? 'Active' : 'Disabled' }}
              </button>
            </td>
            <td>
              <button class="link-button" @click="confirmRemoval(endpoint)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>

      <BasePagination :meta="meta" @change="loadEndpoints" />
    </template>

    <ConfirmDialog
      :open="pendingRemoval !== null"
      title="Remove webhook endpoint?"
      :message="pendingRemoval ? `“${pendingRemoval.name}” will stop receiving events. This cannot be undone.` : ''"
      confirm-label="Remove"
      @confirm="removeEndpoint"
      @cancel="pendingRemoval = null"
    />
  </section>
</template>

<style scoped>
.hint {
  color: var(--color-text);
  opacity: 0.7;
  margin: 0.25rem 0 1.5rem;
}

.card {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  flex-wrap: wrap;
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  margin-bottom: 2rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  flex: 1;
  min-width: 220px;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
}

.field input {
  padding: 0.5rem 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
}

.field-error {
  color: var(--color-failed);
  font-size: 0.8rem;
}

button[type='submit'] {
  padding: 0.55rem 1.1rem;
  border: none;
  border-radius: 6px;
  background: var(--color-success);
  color: white;
  font-weight: 600;
}

button[type='submit']:disabled {
  opacity: 0.6;
  cursor: default;
}

.table {
  width: 100%;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  overflow: hidden;
}

.table th,
.table td {
  padding: 0.65rem 0.85rem;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}

.table tr:last-child td {
  border-bottom: none;
}

.url-cell {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  word-break: break-all;
}

.toggle {
  border: 1px solid var(--color-border);
  background: var(--color-pending-bg);
  color: var(--color-pending);
  border-radius: 999px;
  padding: 0.2rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.toggle--on {
  background: var(--color-success-bg);
  color: var(--color-success);
}

.link-button {
  border: none;
  background: none;
  color: var(--color-failed);
  font-size: 0.85rem;
  padding: 0;
}

.error {
  color: var(--color-failed);
}
</style>
