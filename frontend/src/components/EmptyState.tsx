import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import { Box, Typography } from '@mui/material'
import type { ReactNode } from 'react'

export function EmptyState({ title, description, icon, action }: { title: string; description?: string; icon?: ReactNode; action?: ReactNode }) {
  return (
    <Box sx={{ textAlign: 'center', py: 8, px: 2, color: 'text.secondary' }}>
      <Box sx={{ fontSize: 48, mb: 1, opacity: 0.6 }}>{icon ?? <LockOutlinedIcon fontSize="inherit" />}</Box>
      <Typography variant="h6" color="text.primary" gutterBottom>
        {title}
      </Typography>
      {description && <Typography variant="body2" sx={{ maxWidth: 520, mx: 'auto' }}>{description}</Typography>}
      {action && <Box sx={{ mt: 2 }}>{action}</Box>}
    </Box>
  )
}
