import { Alert, Button, Link, Stack } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError, send } from '../../api/client'
import { errorMessage, useNotify } from '../../components/Notify'
import { AuthShell } from './AuthShell'
import { PasswordFields, passwordOk } from './PasswordFields'

/** Target of the emailed link: /reset-password?token=…&email=… */
export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const notify = useNotify()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await send('post', '/auth/reset-password', { email, token, password, password_confirmation: confirm })
      notify.success('Password reset. Please sign in.')
      navigate('/login', { replace: true })
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  if (!token || !email) {
    return (
      <AuthShell title="Reset link" accent="incomplete">
        <Alert severity="warning">This link is missing information. Request a new one.</Alert>
        <Link component={RouterLink} to="/forgot-password" sx={{ display: 'block', mt: 2 }}>
          Request a new reset link
        </Link>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Choose a new" accent="password" subtitle={`For ${email}`}>
      <form onSubmit={submit}>
        <Stack spacing={2}>
          {error !== null && <Alert severity="error">{error instanceof ApiError ? error.field('token') ?? errorMessage(error) : errorMessage(error)}</Alert>}
          <PasswordFields password={password} confirm={confirm} onPassword={setPassword} onConfirm={setConfirm} serverError={error instanceof ApiError ? error.field('password') : undefined} />
          <Button type="submit" variant="contained" size="large" disabled={busy || !passwordOk(password, confirm)}>
            {busy ? 'Saving…' : 'Reset password'}
          </Button>
        </Stack>
      </form>
    </AuthShell>
  )
}
