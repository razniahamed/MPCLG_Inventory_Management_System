import ContentCopyIcon from '@mui/icons-material/ContentCopy'
import { Alert, Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, IconButton, Tooltip, Typography } from '@mui/material'
import { useState } from 'react'

/** Shows a one-time temporary password so the admin can hand it over. */
export function TemporaryPasswordDialog({ password, username, onClose }: { password: string | null; username?: string; onClose: () => void }) {
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    if (!password) return
    try {
      await navigator.clipboard.writeText(password)
      setCopied(true)
    } catch {
      setCopied(false)
    }
  }

  return (
    <Dialog open={password !== null} onClose={onClose} maxWidth="xs" fullWidth>
      <DialogTitle>Temporary password</DialogTitle>
      <DialogContent>
        <Alert severity="warning" sx={{ mb: 2 }}>
          This is shown only once. Give it to {username ?? 'the user'} in person or by a secure channel. They must change it at first sign-in.
        </Alert>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, p: 1.5, border: 1, borderColor: 'divider', borderRadius: 1, bgcolor: 'action.hover' }}>
          <Typography sx={{ fontFamily: 'monospace', fontSize: 18, flex: 1, letterSpacing: 1 }}>{password}</Typography>
          <Tooltip title={copied ? 'Copied' : 'Copy'}>
            <IconButton onClick={copy}>
              <ContentCopyIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </Box>
      </DialogContent>
      <DialogActions>
        <Button variant="contained" onClick={() => { setCopied(false); onClose() }}>
          Done
        </Button>
      </DialogActions>
    </Dialog>
  )
}
