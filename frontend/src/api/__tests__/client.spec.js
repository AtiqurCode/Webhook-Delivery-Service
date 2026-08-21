import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { api, ApiError } from '../client'

function jsonResponse(body, { status = 200 } = {}) {
  return {
    status,
    ok: status >= 200 && status < 300,
    json: () => Promise.resolve(body),
  }
}

describe('api client', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('resolves with the parsed JSON body on success', async () => {
    fetch.mockResolvedValueOnce(jsonResponse({ data: { id: 1, name: 'CRM' } }))

    const result = await api.webhooks.create({ name: 'CRM', url: 'https://example.com' })

    expect(result).toEqual({ data: { id: 1, name: 'CRM' } })
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/webhooks'),
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('throws an ApiError carrying the status and field errors on a 422 response', async () => {
    fetch.mockResolvedValueOnce(
      jsonResponse(
        { message: 'The name field is required.', errors: { name: ['The name field is required.'] } },
        { status: 422 },
      ),
    )

    await expect(api.webhooks.create({ url: 'https://example.com' })).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
      errors: { name: ['The name field is required.'] },
    })
  })

  it('returns null for a 204 No Content response instead of parsing a body', async () => {
    fetch.mockResolvedValueOnce({ status: 204, ok: true, json: () => Promise.reject(new Error('no body')) })

    await expect(api.webhooks.remove(1)).resolves.toBeNull()
  })

  it('drops empty filter values when listing deliveries', async () => {
    fetch.mockResolvedValueOnce(jsonResponse({ data: [] }))

    await api.deliveries.list({ status: '', event_id: 'evt_123' })

    const [url] = fetch.mock.calls[0]
    expect(url).toContain('event_id=evt_123')
    expect(url).not.toContain('status=')
  })
})

describe('ApiError', () => {
  it('defaults errors to an empty object when none are provided', () => {
    const error = new ApiError('boom', { status: 500 })

    expect(error.errors).toEqual({})
    expect(error.status).toBe(500)
  })
})
