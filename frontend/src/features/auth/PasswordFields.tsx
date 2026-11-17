import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import RadioButtonUncheckedIcon from '@mui/icons-material/RadioButtonUnchecked'
import { Box, Stack, TextField, Typography } from '@mui/material'
import { glassField } from './AuthShell'

const RULES: { label: string; test: (p: string) => boolean }[] = [
  { label: 'At least 10 characters', test: (p) => p.length >= 10 },
  { label: 'Upper and lower case letters', test: (p) => /[a-z]/.test(p) && /[A-Z]/.test(p) },
  { label: 'A number', test: (p) => /\d/.test(p) },
  { label: 'A symbol', test: (p) => /[^A-Za-z0-9]/.test(p) },
]

export function passwordOk(password: string, confirm: string): boolean {
  return RULES.every((r) => r.test(password)) && password === confirm
}

/** New password + confirmation with the policy checklist (server re-validates). */
export function PasswordFields({
  password,
  confirm,
  onPassword,
  onConfirm,
  serverError,
}: {
  password: string
  confirm: string
  onPassword: (v: string) => void
  onConfirm: (v: string) => void
  serverError?: string
}) {
  return (
    <Stack spacing={2}>
      <TextField label="New password" type="password" value={password} onChange={(e) => onPassword(e.target.value)} autoComplete="new-password" required size="medium" sx={glassField} error={Boolean(serverError)} helperText={serverError} />
      <Box>
        {RULES.map((r) => {
          const ok = r.test(password)
          return (
            <Stack key={r.label} direction="row" spacing={1} sx={{ alignItems: 'center', color: ok ? '#4ade80' : 'rgba(220,232,255,0.6)' }}>
              {ok ? <CheckCircleIcon sx={{ fontSize: 16 }} /> : <RadioButtonUncheckedIcon sx={{ fontSize: 16 }} />}
              <Typography sx={{ fontSize: 13 }}>{r.label}</Typography>
            </Stack>
          )
        })}
      </Box>
      <TextField
        label="Confirm new password"
        type="password"
        value={confirm}
        onChange={(e) => onConfirm(e.target.value)}
        autoComplete="new-password"
        required
        size="medium"
        sx={glassField}
        error={confirm.length > 0 && confirm !== password}
        helperText={confirm.length > 0 && confirm !== password ? 'Passwords do not match.' : undefined}
      />
    </Stack>
  )
}
