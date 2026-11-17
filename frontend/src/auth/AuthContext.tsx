import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, useMemo, type ReactNode } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { get, onApiError, send, tokenStore } from '../api/client'
import type { Me, User } from '../api/types'

interface LoginResult {
  token: string
  expires_at: string
  must_change_password: boolean
  user: User
}

interface AuthApi {
  me: Me | null
  loading: boolean
  /** Hide/show helpers only. The API re-checks every permission. */
  can: (code: string) => boolean
  canAny: (...codes: string[]) => boolean
  moduleEnabled: (code: string) => boolean
  login: (username: string, password: string) => Promise<LoginResult>
  logout: () => Promise<void>
  refresh: () => Promise<unknown>
}

const AuthContext = createContext<AuthApi | null>(null)
const PUBLIC_PATHS = ['/login', '/forgot-password', '/reset-password']

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const location = useLocation()
  const hasToken = tokenStore.get() !== null

  const meQuery = useQuery({
    queryKey: ['me'],
    queryFn: () => get<Me>('/auth/me'),
    enabled: hasToken,
    staleTime: 60_000,
    retry: false,
  })

  const signOutLocally = useCallback(
    (reason?: string) => {
      tokenStore.set(null)
      queryClient.clear()
      if (!PUBLIC_PATHS.includes(window.location.pathname)) {
        navigate('/login', { replace: true, state: reason ? { reason } : undefined })
      }
    },
    [navigate, queryClient],
  )

  // React to auth-related API errors from anywhere in the app.
  useEffect(
    () =>
      onApiError((error) => {
        if (error.code === 'UNAUTHENTICATED' && tokenStore.get()) {
          signOutLocally('Your session has ended. Please sign in again.')
        } else if (error.code === 'ACCOUNT_DISABLED' || error.code === 'ACCOUNT_LOCKED') {
          if (tokenStore.get()) signOutLocally(error.message)
        } else if (error.code === 'PASSWORD_CHANGE_REQUIRED') {
          navigate('/change-password', { replace: true })
        }
      }),
    [navigate, signOutLocally],
  )

  // Users with a temporary password may only use the change-password screen.
  useEffect(() => {
    if (meQuery.data?.user.must_change_password && location.pathname !== '/change-password') {
      navigate('/change-password', { replace: true })
    }
  }, [meQuery.data, location.pathname, navigate])

  const api = useMemo<AuthApi>(() => {
    const permissions = new Set(meQuery.data?.permissions ?? [])
    const modules = new Map((meQuery.data?.modules ?? []).map((m) => [m.code, m.is_enabled]))

    return {
      me: meQuery.data ?? null,
      loading: hasToken && meQuery.isLoading,
      can: (code) => permissions.has(code),
      canAny: (...codes) => codes.some((c) => permissions.has(c)),
      moduleEnabled: (code) => modules.get(code) ?? false,
      login: async (username, password) => {
        const res = await send<LoginResult>('post', '/auth/login', {
          username,
          password,
          device_name: navigator.userAgent.slice(0, 100),
        })
        tokenStore.set(res.data.token)
        queryClient.clear()
        await queryClient.fetchQuery({ queryKey: ['me'], queryFn: () => get<Me>('/auth/me') })
        return res.data
      },
      logout: async () => {
        try {
          await send('post', '/auth/logout')
        } finally {
          tokenStore.set(null)
          queryClient.clear()
          navigate('/login', { replace: true })
        }
      },
      refresh: () => meQuery.refetch(),
    }
  }, [meQuery, hasToken, queryClient, navigate])

  return <AuthContext.Provider value={api}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthApi {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
