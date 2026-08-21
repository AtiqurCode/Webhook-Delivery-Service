<script setup>
import { onMounted, ref, watch } from 'vue'
import { api, ApiError } from '@/api/client'
import { useToast } from '@/composables/useToast'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseSpinner from '@/components/BaseSpinner.vue'
import BasePagination from '@/components/BasePagination.vue'

const toast = useToast()

const deliveries = ref([])
const meta = ref(null)
const loading = ref(true)
const loadError = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const retryingId = ref(null)

async function loadDeliveries(page = 1) {
  loading.value = true
  loadError.value = ''
  currentPage.value = page

  try {
    const { data, meta: pageMeta } = await api.deliveries.list({ status: statusFilter.value, page })
    deliveries.value = data
    meta.value = pageMeta
  } catch (error) {
    loadError.value = error instanceof ApiError ? error.message : 'Could not load delivery history.'
  } finally {
    loading.value = false
  }
}

function refresh() {
  loadDeliveries(currentPage.value)
  toast.success('Delivery history refreshed.')
}

function formatTime(value) {
  return new Date(value).toLocaleString()
}

async function retry(delivery) {
  retryingId.value = delivery.id

  try {
    await api.deliveries.retry(delivery.id)
    toast.success(`Retrying delivery for “${delivery.event.event_type}”…`)
    await loadDeliveries(currentPage.value)
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : 'Could not retry this delivery.')
  } finally {
    retryingId.value = null
  }
}

watch(statusFilter, () => loadDeliveries(1))
onMounted(() => loadDeliveries())
</script>

<template>
  <section>
    <div class="heading-row">
      <div>
        <h1>Delivery History</h1>
        <p class="hint">Every event/endpoint pair gets its own delivery record.</p>
      </div>
      <button type="button" class="refresh" :disabled="loading" @click="refresh">↻ Refresh</button>
    </div>

    <div class="filters">
      <label for="status">Status</label>
      <select id="status" v-model="statusFilter">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="success">Success</option>
        <option value="failed">Failed</option>
      </select>
    </div>

    <BaseSpinner v-if="loading" label="Loading deliveries…" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>
    <p v-else-if="deliveries.length === 0" class="hint">No deliveries recorded yet.</p>

    <template v-else>
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Endpoint</th>
            <th>Status</th>
            <th>HTTP</th>
            <th>Attempts</th>
            <th>Time</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="delivery in deliveries" :key="delivery.id">
            <td>
              <RouterLink :to="`/deliveries/${delivery.id}`">{{ delivery.event.event_type }}</RouterLink>
            </td>
            <td>{{ delivery.webhook_endpoint.name }}</td>
            <td><StatusBadge :status="delivery.status" /></td>
            <td>{{ delivery.http_status ?? '—' }}</td>
            <td>{{ delivery.attempt_count }}</td>
            <td>{{ formatTime(delivery.created_at) }}</td>
            <td>
              <button
                v-if="delivery.status === 'failed'"
                type="button"
                class="retry-button"
                :disabled="retryingId === delivery.id"
                @click="retry(delivery)"
              >
                {{ retryingId === delivery.id ? 'Retrying…' : 'Retry' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <BasePagination :meta="meta" @change="loadDeliveries" />
    </template>
  </section>
</template>

<style scoped>
.heading-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.hint {
  color: var(--color-text);
  opacity: 0.7;
  margin: 0.25rem 0 1.5rem;
}

.refresh {
  padding: 0.4rem 0.85rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
}

.refresh:disabled {
  opacity: 0.5;
  cursor: default;
}

.filters {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.25rem;
}

.filters select {
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
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
  font-size: 0.9rem;
}

.table tr:last-child td {
  border-bottom: none;
}

.table a {
  font-weight: 600;
}

.retry-button {
  padding: 0.3rem 0.65rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
}

.retry-button:disabled {
  opacity: 0.5;
  cursor: default;
}

.error {
  color: var(--color-failed);
}
</style>
