import { Alert, Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, Stack, TextField } from '@mui/material'
import { useEffect, useState, type ReactNode } from 'react'
import { ApiError } from '../api/client'
import { errorMessage } from './Notify'

export interface ActionSpec {
  title: string
  description?: ReactNode
  confirmLabel?: string
  danger?: boolean
  /** Ask for remarks: 'required' (min 3 chars), 'optional', or none. */
  remarks?: 'required' | 'optional'
  remarksLabel?: string
  /** Extra inputs rendered above the remarks. */
  body?: ReactNode
  /** Disable the confirm button (e.g. extra inputs incomplete). */
  disabled?: boolean
  run: (remarks: string) => Promise<unknown>
}

/** Generic confirm / remarks dialog used for workflow actions. */
export function ActionDialog({ action, onClose }: { action: ActionSpec | null; onClose: () => void }) {
  const [remarks, setRemarks] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  // Specs are rebuilt on every parent render (so inputs in `body` stay live);
  // reset only when a different dialog opens.
  const openKey = action?.title ?? null
  useEffect(() => {
    if (openKey) {
      setRemarks('')
      setError(null)
    }
  }, [openKey])

  if (!action) return null

  const submit = async () => {
    setBusy(true)
    setError(null)
    try {
      await action.run(remarks.trim())
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const tooShort = action.remarks === 'required' && remarks.trim().length < 3

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>{action.title}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 0.5 }}>
          {action.description && <DialogContentText component="div">{action.description}</DialogContentText>}
          {action.body}
          {action.remarks && (
            <TextField
              label={action.remarksLabel ?? (action.remarks === 'required' ? 'Reason / remarks (required)' : 'Remarks (optional)')}
              value={remarks}
              onChange={(e) => setRemarks(e.target.value)}
              multiline
              minRows={2}
              autoFocus={!action.body}
              error={error instanceof ApiError && Boolean(error.field('remarks') ?? error.field('reason'))}
            />
          )}
          {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2 }}>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" color={action.danger ? 'error' : 'primary'} onClick={submit} disabled={busy || tooShort || action.disabled}>
          {busy ? 'Working…' : action.confirmLabel ?? 'Confirm'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
