import axios, { AxiosError } from 'axios'
import type { Envelope, Paged } from './types'

const TOKEN_KEY = 'mpclg.token'

export const tokenStore = {
  get(): string | null {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token: string | null) {
    try {
      if (token) localStorage.setItem(TOKEN_KEY, token)
      else localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* storage unavailable: token lives only for this page */
    }
  },
}

/** Error from the API error envelope (Section 4). */
export class ApiError extends Error {
  status: number
  code: string
  errors: Record<string, string[]>
  requestId?: string

  constructor(status: number, code: string, message: string, errors: Record<string, string[]> = {}, requestId?: string) {
    super(message)
    this.status = status
    this.code = code
    this.errors = errors
    this.requestId = requestId
  }

  /** First message for a field, e.g. form helper text. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

type Listener = (error: ApiError) => void
const listeners = new Set<Listener>()

/** The auth layer subscribes to react to 401 / disabled / password-change errors. */
export function onApiError(listener: Listener): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; code?: string; errors?: Record<string, string[]>; meta?: { request_id?: string } }>) => {
    const body = error.response?.data
    const apiError = new ApiError(
      error.response?.status ?? 0,
      body?.code ?? (error.response ? 'HTTP_ERROR' : 'NETWORK_ERROR'),
      body?.message ?? (error.response ? error.message : 'Cannot reach the server. Check your connection.'),
      body?.errors ?? {},
      body?.meta?.request_id,
    )
    listeners.forEach((l) => l(apiError))
    return Promise.reject(apiError)
  },
)

// ---- Thin helpers returning the envelope's payload ------------------------

export async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const { data } = await http.get<Envelope<T>>(url, { params })
  return data.data
}

export async function getPage<T>(url: string, params?: Record<string, unknown>): Promise<Paged<T>> {
  const { data } = await http.get<Paged<T>>(url, { params })
  return data
}

export async function send<T>(
  method: 'post' | 'put' | 'patch' | 'delete',
  url: string,
  body?: unknown,
): Promise<Envelope<T>> {
  const { data } = await http.request<Envelope<T>>({ method, url, data: body })
  return data
}

/** Turn a list query object into Laravel-style params: filter[x]=y. */
export function listParams(q: {
  page?: number
  perPage?: number
  sort?: string
  search?: string
  filters?: Record<string, string | number | boolean | undefined | null>
}): Record<string, unknown> {
  const params: Record<string, unknown> = {}
  if (q.page) params.page = q.page
  if (q.perPage) params.per_page = q.perPage
  if (q.sort) params.sort = q.sort
  if (q.search) params.search = q.search
  Object.entries(q.filters ?? {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') params[`filter[${k}]`] = String(v)
  })
  return params
}
