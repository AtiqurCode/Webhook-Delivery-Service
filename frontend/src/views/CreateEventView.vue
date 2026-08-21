<script setup>
import { reactive, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const form = reactive({
  event_type: 'user.created',
  event_id: '',
  payload: '{\n  "id": 123,\n  "name": "John Doe",\n  "email": "john@example.com"\n}',
})

const submitting = ref(false)
const formErrors = ref({})
const payloadError = ref('')
const result = ref(null)

function parsePayload() {
  payloadError.value = ''

  try {
    const parsed = JSON.parse(form.payload)

    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
      payloadError.value = 'Payload must be a JSON object.'
      return null
    }

    return parsed
  } catch {
    payloadError.value = 'Payload is not valid JSON.'
    return null
  }
}

async function submit() {
  const data = parsePayload()
  if (!data) {
    return
  }

  submitting.value = true
  formErrors.value = {}
  result.value = null

  try {
    const response = await api.events.create({
      event_type: form.event_type,
      event_id: form.event_id || undefined,
      data,
    })

    result.value = response.data
    form.event_id = ''
    toast.success(`Event sent — ${response.data.deliveries_count} ${response.data.deliveries_count === 1 ? 'delivery' : 'deliveries'} queued.`)
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      formErrors.value = error.errors
    } else {
      payloadError.value = error instanceof ApiError ? error.message : 'Could not send the event.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section>
    <h1>Create Event</h1>
    <p class="hint">
      Submitting an event delivers it to every currently active webhook endpoint. Re-submitting the
      same event ID is treated as a duplicate and will not create new deliveries.
    </p>

    <form class="card" @submit.prevent="submit">
      <div class="field">
        <label for="event_type">Event Type</label>
        <input id="event_type" v-model="form.event_type" type="text" placeholder="user.created" required />
        <p v-if="formErrors.event_type" class="field-error">{{ formErrors.event_type[0] }}</p>
      </div>

      <div class="field">
        <label for="event_id">Event ID <span class="optional">(optional — generated if left blank)</span></label>
        <input id="event_id" v-model="form.event_id" type="text" placeholder="evt_01JABC123" />
      </div>

      <div class="field">
        <label for="payload">Payload (JSON)</label>
        <textarea id="payload" v-model="form.payload" rows="8" required></textarea>
        <p v-if="payloadError" class="field-error">{{ payloadError }}</p>
        <p v-else-if="formErrors.data" class="field-error">{{ formErrors.data[0] }}</p>
      </div>

      <button type="submit" :disabled="submitting">
        {{ submitting ? 'Sending…' : 'Send Event' }}
      </button>
    </form>

    <div v-if="result" class="result">
      <p>Event created successfully.</p>
      <p><strong>Event ID:</strong> <code>{{ result.event_id }}</code></p>
      <p>{{ result.deliveries_count }} webhook {{ result.deliveries_count === 1 ? 'delivery' : 'deliveries' }} created.</p>
      <RouterLink to="/deliveries">View delivery history →</RouterLink>
    </div>
  </section>
</template>

<style scoped>
.hint {
  color: var(--color-text);
  opacity: 0.7;
  margin: 0.25rem 0 1.5rem;
  max-width: 60ch;
}

.card {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  padding: 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  max-width: 560px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field label {
  font-size: 0.85rem;
  font-weight: 600;
}

.optional {
  font-weight: 400;
  opacity: 0.6;
}

.field input,
.field textarea {
  padding: 0.5rem 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  background: var(--color-surface);
  color: var(--color-text);
}

.field textarea {
  font-family: ui-monospace, monospace;
  font-size: 0.85rem;
  resize: vertical;
}

.field-error {
  color: var(--color-failed);
  font-size: 0.8rem;
}

button[type='submit'] {
  align-self: flex-start;
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

.result {
  margin-top: 1.5rem;
  padding: 1rem 1.25rem;
  border-radius: 8px;
  background: var(--color-success-bg);
  color: var(--color-success);
  max-width: 560px;
}

.result a {
  color: inherit;
  font-weight: 600;
}
</style>
