import VisibilityIcon from '@mui/icons-material/Visibility'
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff'
import { Alert, Button, IconButton, InputAdornment, Link, Stack, TextField } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Navigate, Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import { tokenStore } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage } from '../../components/Notify'
import { AuthShell } from './AuthShell'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const state = location.state as { from?: string; reason?: string } | null

  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [show, setShow] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (tokenStore.get()) return <Navigate to="/" replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const result = await login(username.trim(), password)
      navigate(result.must_change_password ? '/change-password' : state?.from ?? '/', { replace: true })
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell title="Sign in" subtitle="Use your ministry username (or email) and password.">
      <form onSubmit={submit} noValidate>
        <Stack spacing={2}>
          {state?.reason && !error && <Alert severity="info">{state.reason}</Alert>}
          {error && <Alert severity="error">{error}</Alert>}
          <TextField label="Username or email" value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus required size="medium" />
          <TextField
            label="Password"
            type={show ? 'text' : 'password'}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
            size="medium"
            slotProps={{
              input: {
                endAdornment: (
                  <InputAdornment position="end">
                    <IconButton onClick={() => setShow((s) => !s)} edge="end" aria-label={show ? 'Hide password' : 'Show password'}>
                      {show ? <VisibilityOffIcon /> : <VisibilityIcon />}
                    </IconButton>
                  </InputAdornment>
                ),
              },
            }}
          />
          <Button type="submit" variant="contained" size="large" disabled={busy || !username || !password}>
            {busy ? 'Signing in…' : 'Sign in'}
          </Button>
          <Link component={RouterLink} to="/forgot-password" sx={{ fontSize: 14, alignSelf: 'center' }}>
            Forgot your password?
          </Link>
        </Stack>
      </form>
    </AuthShell>
  )
}
