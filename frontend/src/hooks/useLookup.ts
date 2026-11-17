import { useQuery } from '@tanstack/react-query'
import { getPage, listParams } from '../api/client'

/**
 * Small reference lists for dropdowns (active records, up to 100).
 */
export function useLookup<T = Record<string, unknown>>(
  endpoint: string | null,
  options: { filters?: Record<string, string | number | boolean | undefined>; sort?: string; enabled?: boolean } = {},
) {
  return useQuery({
    queryKey: ['lookup', endpoint, options.filters ?? {}, options.sort ?? ''],
    queryFn: async () => {
      const page = await getPage<T>(
        endpoint!,
        listParams({ perPage: 100, sort: options.sort, filters: { is_active: true, ...options.filters } }),
      )
      return page.data
    },
    enabled: endpoint !== null && (options.enabled ?? true),
    staleTime: 5 * 60_000,
  })
}
