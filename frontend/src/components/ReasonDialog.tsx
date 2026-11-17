import { Alert, Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, TextField } from '@mui/material'
import { useState, type ReactNode } from 'react'
import { ApiError } from '../api/client'
import { errorMessage } from './Notify'

/**
 * Confirmation that captures the mandatory audit reason (Section 5.4 rule 8).
 */
export function ReasonDialog({
  open,
  title,
  description,
  confirmLabel = 'Confirm',
  danger,
  onClose,
  onConfirm,
  children,
}: {
  open: boolean
  title: string
  description?: ReactNode
  confirmLabel?: string
  danger?: boolean
  onClose: () => void
  onConfirm: (reason: string) => Promise<unknown>
  children?: ReactNode
}) {
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const close = () => {
    if (busy) return
    setReason('')
    setError(null)
    onClose()
  }

  const submit = async () => {
    setBusy(true)
    setError(null)
    try {
      await onConfirm(reason.trim())
      setReason('')
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const fieldError = error instanceof ApiError ? error.field('reason') : undefined

  return (
    <Dialog open={open} onClose={close} maxWidth="sm" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent>
        {description && <DialogContentText sx={{ mb: 2 }}>{description}</DialogContentText>}
        {children}
        <TextField
          autoFocus
          label="Reason (recorded in the audit trail)"
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          multiline
          minRows={2}
          required
          error={Boolean(fieldError)}
          helperText={fieldError ?? 'At least 3 characters.'}
          sx={{ mt: 1 }}
        />
        {error !== null && !fieldError && (
          <Alert severity="error" sx={{ mt: 2 }}>
            {errorMessage(error)}
          </Alert>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" color={danger ? 'error' : 'primary'} onClick={submit} disabled={busy || reason.trim().length < 3}>
          {busy ? 'Saving…' : confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
