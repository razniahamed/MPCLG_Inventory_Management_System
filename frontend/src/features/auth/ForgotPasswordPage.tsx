import { Alert, Button, Link, Stack, TextField } from '@mui/material'
import { useState, type FormEvent } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { send } from '../../api/client'
import { errorMessage } from '../../components/Notify'
import { AuthShell } from './AuthShell'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const res = await send('post', '/auth/forgot-password', { email: email.trim() })
      setDone(res.message)
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell title="Reset your password" subtitle="Enter the email on your account. If it belongs to an active account, we will send a reset link.">
      <form onSubmit={submit}>
        <Stack spacing={2}>
          {done && <Alert severity="success">{done}</Alert>}
          {error && <Alert severity="error">{error}</Alert>}
          <TextField label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required size="medium" autoFocus />
          <Button type="submit" variant="contained" size="large" disabled={busy || !email}>
            {busy ? 'Sending…' : 'Send reset link'}
          </Button>
          <Link component={RouterLink} to="/login" sx={{ fontSize: 14, alignSelf: 'center' }}>
            Back to sign in
          </Link>
        </Stack>
      </form>
    </AuthShell>
  )
}
