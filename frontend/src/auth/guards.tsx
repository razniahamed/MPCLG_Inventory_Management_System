import { Box, CircularProgress } from '@mui/material'
import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { tokenStore } from '../api/client'
import { EmptyState } from '../components/EmptyState'
import { useAuth } from './AuthContext'

export function FullPageSpinner() {
  return (
    <Box sx={{ display: 'grid', placeItems: 'center', minHeight: '100vh' }}>
      <CircularProgress />
    </Box>
  )
}

/** Signed-in area. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { me, loading } = useAuth()
  const location = useLocation()

  if (!tokenStore.get()) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (loading || !me) return <FullPageSpinner />
  // A temporary password unlocks nothing but the change-password screen.
  if (me.user.must_change_password && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />
  }

  return <>{children}</>
}

/** Page-level permission gate (convenience only; the API enforces the real rule). */
export function RequirePermission({ anyOf, children }: { anyOf: string[]; children: ReactNode }) {
  const { canAny } = useAuth()

  if (!canAny(...anyOf)) {
    return (
      <EmptyState
        title="You do not have access to this page"
        description={`It needs one of: ${anyOf.join(', ')}. Ask a System Administrator if you should have it.`}
      />
    )
  }

  return <>{children}</>
}
