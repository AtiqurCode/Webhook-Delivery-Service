const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')

export class ApiError extends Error {
  constructor(message, { status, errors } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors ?? {}
  }
}

async function request(path, { method = 'GET', body } = {}) {
  const response = await fetch(`${baseUrl}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  })

  if (response.status === 204) {
    return null
  }

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(payload?.message ?? `Request to ${path} failed with status ${response.status}`, {
      status: response.status,
      errors: payload?.errors,
    })
  }

  return payload
}

function withQuery(path, params = {}) {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
  const suffix = query.toString() ? `?${query}` : ''
  return `${path}${suffix}`
}

export const api = {
  stats: {
    get: () => request('/stats'),
  },
  webhooks: {
    list: (params = {}) => request(withQuery('/webhooks', params)),
    create: (data) => request('/webhooks', { method: 'POST', body: data }),
    update: (id, data) => request(`/webhooks/${id}`, { method: 'PATCH', body: data }),
    remove: (id) => request(`/webhooks/${id}`, { method: 'DELETE' }),
  },
  events: {
    create: (data) => request('/events', { method: 'POST', body: data }),
  },
  deliveries: {
    list: (params = {}) => request(withQuery('/deliveries', params)),
    show: (id) => request(`/deliveries/${id}`),
  },
}
