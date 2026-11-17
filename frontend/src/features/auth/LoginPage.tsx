import ArrowForwardIcon from '@mui/icons-material/ArrowForward'
import VisibilityIcon from '@mui/icons-material/Visibility'
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff'
import { Alert, Box, Button, IconButton, InputAdornment, Link, Stack, TextField, Typography } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Navigate, Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import { tokenStore } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage } from '../../components/Notify'
import { brand } from '../../theme'
import { AuthShell, glassField } from './AuthShell'

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
    <AuthShell title="Welcome Back" accent="Officer" subtitle="Sign in with your ministry username or email">
      <form onSubmit={submit} noValidate>
        <Stack spacing={2}>
          {state?.reason && !error && <Alert severity="info">{state.reason}</Alert>}
          {error && <Alert severity="error">{error}</Alert>}
          <TextField label="Username or email" value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus required size="medium" sx={glassField} />
          <TextField
            label="Password"
            type={show ? 'text' : 'password'}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
            size="medium"
            sx={glassField}
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
          <Button
            type="submit"
            size="large"
            disabled={busy || !username || !password}
            endIcon={!busy && <ArrowForwardIcon />}
            sx={{
              py: 1.4,
              borderRadius: 3,
              fontWeight: 800,
              letterSpacing: '0.04em',
              color: brand.deep,
              bgcolor: '#fff',
              '&:hover': { bgcolor: '#e8f0ff' },
              '&.Mui-disabled': { bgcolor: 'rgba(255,255,255,0.25)', color: 'rgba(255,255,255,0.6)' },
            }}
          >
            {busy ? 'SIGNING IN…' : 'SIGN IN'}
          </Button>
          <Stack direction="row" sx={{ alignItems: 'center', gap: 1.5, color: 'rgba(220,232,255,0.5)' }}>
            <Box sx={{ flex: 1, height: '1px', bgcolor: 'rgba(255,255,255,0.15)' }} />
            <Typography sx={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.1em' }}>OR</Typography>
            <Box sx={{ flex: 1, height: '1px', bgcolor: 'rgba(255,255,255,0.15)' }} />
          </Stack>
          <Link
            component={RouterLink}
            to="/forgot-password"
            underline="none"
            sx={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              px: 2,
              py: 1.5,
              borderRadius: 3,
              color: 'rgba(230,238,255,0.9)',
              fontSize: 14,
              fontWeight: 600,
              bgcolor: 'rgba(255,255,255,0.05)',
              border: '1px solid rgba(255,255,255,0.12)',
              '&:hover': { bgcolor: 'rgba(255,255,255,0.1)' },
            }}
          >
            Forgot your password?
            <ArrowForwardIcon sx={{ fontSize: 18, opacity: 0.6 }} />
          </Link>
        </Stack>
      </form>
    </AuthShell>
  )
}
