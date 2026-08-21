<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseSpinner from '@/components/BaseSpinner.vue'

const route = useRoute()
const delivery = ref(null)
const loadError = ref('')
const loading = ref(true)

function formatTime(value) {
  return value ? new Date(value).toLocaleString() : '—'
}

onMounted(async () => {
  try {
    const { data } = await api.deliveries.show(route.params.id)
    delivery.value = data
  } catch (error) {
    loadError.value = error instanceof ApiError ? error.message : 'Could not load this delivery.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section>
    <RouterLink to="/deliveries" class="back-link">← Back to history</RouterLink>
    <h1>Delivery Details</h1>

    <BaseSpinner v-if="loading" label="Loading delivery…" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <div v-else-if="delivery" class="card">
      <dl>
        <dt>Event</dt>
        <dd>{{ delivery.event.event_type }} <span class="muted">({{ delivery.event.event_id }})</span></dd>

        <dt>Endpoint</dt>
        <dd>{{ delivery.webhook_endpoint.name }} <span class="muted">{{ delivery.webhook_endpoint.url }}</span></dd>

        <dt>Status</dt>
        <dd><StatusBadge :status="delivery.status" /></dd>

        <dt>HTTP Status</dt>
        <dd>{{ delivery.http_status ?? '—' }}</dd>

        <dt>Attempts</dt>
        <dd>{{ delivery.attempt_count }}</dd>

        <dt v-if="delivery.last_error">Error</dt>
        <dd v-if="delivery.last_error" class="error-text">{{ delivery.last_error }}</dd>

        <dt>Created</dt>
        <dd>{{ formatTime(delivery.created_at) }}</dd>

        <dt>Delivered At</dt>
        <dd>{{ formatTime(delivery.delivered_at) }}</dd>
      </dl>

      <h2>Payload</h2>
      <pre class="payload">{{ JSON.stringify(delivery.event.payload, null, 2) }}</pre>
    </div>
  </section>
</template>

<style scoped>
.back-link {
  display: inline-block;
  margin-bottom: 0.75rem;
  font-size: 0.85rem;
}

.card {
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  max-width: 640px;
}

dl {
  display: grid;
  grid-template-columns: 140px 1fr;
  row-gap: 0.6rem;
  column-gap: 1rem;
}

dt {
  font-weight: 600;
  opacity: 0.75;
}

dd {
  margin: 0;
}

.muted {
  opacity: 0.6;
  font-size: 0.85rem;
}

.error-text {
  color: var(--color-failed);
}

h2 {
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
  font-size: 1rem;
}

.payload {
  background: var(--color-background-mute);
  padding: 0.85rem;
  border-radius: 6px;
  font-size: 0.85rem;
  overflow-x: auto;
}

.error {
  color: var(--color-failed);
}
</style>
