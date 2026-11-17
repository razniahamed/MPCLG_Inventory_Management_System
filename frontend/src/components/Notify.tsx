import { Alert, Snackbar } from '@mui/material'
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { ApiError } from '../api/client'

type Severity = 'success' | 'error' | 'info' | 'warning'
interface Toast {
  id: number
  message: string
  severity: Severity
}

interface NotifyApi {
  success: (message: string) => void
  error: (messageOrError: unknown) => void
  info: (message: string) => void
  warning: (message: string) => void
}

const NotifyContext = createContext<NotifyApi | null>(null)

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = Object.values(error.errors)[0]?.[0]
    return first && error.code === 'VALIDATION_ERROR' ? first : error.message
  }
  if (error instanceof Error) return error.message
  return 'Something went wrong.'
}

export function NotifyProvider({ children }: { children: ReactNode }) {
  const [toast, setToast] = useState<Toast | null>(null)

  const push = useCallback((message: string, severity: Severity) => {
    setToast({ id: Date.now(), message, severity })
  }, [])

  const api: NotifyApi = {
    success: (m) => push(m, 'success'),
    info: (m) => push(m, 'info'),
    warning: (m) => push(m, 'warning'),
    error: (e) => push(errorMessage(e), 'error'),
  }

  return (
    <NotifyContext.Provider value={api}>
      {children}
      <Snackbar
        key={toast?.id}
        open={toast !== null}
        autoHideDuration={toast?.severity === 'error' ? 8000 : 4000}
        onClose={(_, reason) => reason !== 'clickaway' && setToast(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={toast?.severity ?? 'info'} variant="filled" onClose={() => setToast(null)} sx={{ maxWidth: 480 }}>
          {toast?.message}
        </Alert>
      </Snackbar>
    </NotifyContext.Provider>
  )
}

export function useNotify(): NotifyApi {
  const ctx = useContext(NotifyContext)
  if (!ctx) throw new Error('useNotify must be used inside NotifyProvider')
  return ctx
}
