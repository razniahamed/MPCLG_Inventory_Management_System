import { useQuery, useQueryClient } from '@tanstack/react-query'
import { get, send } from '../api/client'
import { useNotify } from '../components/Notify'

/** Fetch one document (detail pages). */
export function useDoc<T = Record<string, unknown>>(key: unknown[], url: string | null) {
  return useQuery({ queryKey: key, queryFn: () => get<T>(url!), enabled: url !== null })
}

/** Run a mutation, invalidate the given query prefixes and toast the API message. */
export function useRun() {
  const queryClient = useQueryClient()
  const notify = useNotify()

  return async <T = unknown>(
    method: 'post' | 'put' | 'patch' | 'delete',
    url: string,
    body: unknown,
    invalidate: unknown[][],
    opts: { idempotent?: boolean } = {},
  ): Promise<T> => {
    const headers = opts.idempotent ? { 'Idempotency-Key': crypto.randomUUID() } : undefined
    const res = await send<T>(method, url, body, headers)
    await Promise.all([...invalidate, ['dashboard']].map((k) => queryClient.invalidateQueries({ queryKey: k })))
    notify.success(res.message)
    return res.data
  }
}
