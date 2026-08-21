<script setup>
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import BaseSpinner from '@/components/BaseSpinner.vue'

const stats = ref(null)
const loading = ref(true)
const loadError = ref('')

async function loadStats() {
  loading.value = true
  loadError.value = ''

  try {
    const { data } = await api.stats.get()
    stats.value = data
  } catch (error) {
    loadError.value = error instanceof ApiError ? error.message : 'Could not load stats.'
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)
</script>

<template>
  <section>
    <h1>Dashboard</h1>
    <p class="hint">A quick look at what's registered and how deliveries are going.</p>

    <BaseSpinner v-if="loading" label="Loading stats…" />
    <p v-else-if="loadError" class="error">{{ loadError }}</p>

    <div v-else-if="stats" class="cards">
      <div class="card">
        <h2>Webhook Endpoints</h2>
        <p class="figure">{{ stats.webhook_endpoints.total }}</p>
        <p class="breakdown">
          <span class="dot dot--success"></span> {{ stats.webhook_endpoints.active }} active
          <span class="sep">·</span>
          {{ stats.webhook_endpoints.total - stats.webhook_endpoints.active }} disabled
        </p>
        <RouterLink to="/webhooks" class="card-link">Manage endpoints →</RouterLink>
      </div>

      <div class="card">
        <h2>Events</h2>
        <p class="figure">{{ stats.events.total }}</p>
        <p class="breakdown">received so far</p>
        <RouterLink to="/events/new" class="card-link">Send an event →</RouterLink>
      </div>

      <div class="card">
        <h2>Deliveries</h2>
        <p class="figure">{{ stats.deliveries.total }}</p>
        <p class="breakdown">
          <span class="dot dot--success"></span> {{ stats.deliveries.success }} success
          <span class="sep">·</span>
          <span class="dot dot--pending"></span> {{ stats.deliveries.pending }} pending
          <span class="sep">·</span>
          <span class="dot dot--failed"></span> {{ stats.deliveries.failed }} failed
        </p>
        <RouterLink to="/deliveries" class="card-link">View history →</RouterLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hint {
  color: var(--color-text);
  opacity: 0.7;
  margin: 0.25rem 0 1.5rem;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.card {
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface);
}

.card h2 {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  opacity: 0.65;
  margin-bottom: 0.4rem;
}

.figure {
  font-size: 2.25rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.5rem;
}

.breakdown {
  font-size: 0.82rem;
  opacity: 0.75;
  margin-bottom: 0.9rem;
}

.sep {
  opacity: 0.5;
  margin: 0 0.15rem;
}

.dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 0.2rem;
}

.dot--success {
  background: var(--color-success);
}

.dot--pending {
  background: var(--color-pending);
}

.dot--failed {
  background: var(--color-failed);
}

.card-link {
  font-size: 0.85rem;
  font-weight: 600;
}

.error {
  color: var(--color-failed);
}
</style>
