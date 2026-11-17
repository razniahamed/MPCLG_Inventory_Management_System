import { Alert, Button, Stack, TextField } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiError, send } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage, useNotify } from '../../components/Notify'
import { AuthShell } from './AuthShell'
import { PasswordFields, passwordOk } from './PasswordFields'

export function ChangePasswordPage() {
  const { me, refresh, logout } = useAuth()
  const notify = useNotify()
  const navigate = useNavigate()
  const forced = me?.user.must_change_password ?? false

  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await send('post', '/auth/change-password', { current_password: current, password, password_confirmation: confirm })
      await refresh()
      notify.success('Password changed.')
      navigate('/', { replace: true })
    } catch (err) {
      setError(err)
    } finally {
      setBusy(false)
    }
  }

  const fieldErr = (name: string) => (error instanceof ApiError ? error.field(name) : undefined)

  return (
    <AuthShell
      title={forced ? 'Set your own password' : 'Change password'}
      subtitle={forced ? 'You signed in with a temporary password. Choose a new one to continue.' : 'Other signed-in devices will be signed out.'}
    >
      <form onSubmit={submit}>
        <Stack spacing={2}>
          {error !== null && !fieldErr('current_password') && !fieldErr('password') && <Alert severity="error">{errorMessage(error)}</Alert>}
          <TextField
            label={forced ? 'Temporary password' : 'Current password'}
            type="password"
            value={current}
            onChange={(e) => setCurrent(e.target.value)}
            autoComplete="current-password"
            required
            size="medium"
            error={Boolean(fieldErr('current_password'))}
            helperText={fieldErr('current_password')}
          />
          <PasswordFields password={password} confirm={confirm} onPassword={setPassword} onConfirm={setConfirm} serverError={fieldErr('password')} />
          <Button type="submit" variant="contained" size="large" disabled={busy || !current || !passwordOk(password, confirm)}>
            {busy ? 'Saving…' : 'Change password'}
          </Button>
          {forced ? (
            <Button onClick={() => void logout()}>Sign out</Button>
          ) : (
            <Button onClick={() => navigate(-1)}>Cancel</Button>
          )}
        </Stack>
      </form>
    </AuthShell>
  )
}
