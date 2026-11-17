import { Box, Paper, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'

export function AuthShell({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
  return (
    <Box sx={{ minHeight: '100vh', display: 'grid', gridTemplateColumns: { xs: '1fr', md: '1.1fr 1fr' } }}>
      <Box
        sx={{
          display: { xs: 'none', md: 'flex' },
          flexDirection: 'column',
          justifyContent: 'space-between',
          p: 6,
          color: '#fff',
          background: 'linear-gradient(140deg, #13345c 0%, #1d4e89 55%, #8d1b3d 140%)',
        }}
      >
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
          <Box sx={{ width: 40, height: 40, borderRadius: 2, bgcolor: 'rgba(255,255,255,0.15)', display: 'grid', placeItems: 'center', fontWeight: 800 }}>MI</Box>
          <Typography sx={{ fontWeight: 700 }}>Ministry of Provincial Councils and Local Government</Typography>
        </Stack>
        <Box>
          <Typography variant="h3" sx={{ fontWeight: 700, mb: 2, lineHeight: 1.15 }}>
            Inventory & Asset
            <br />
            Management System
          </Typography>
          <Typography sx={{ opacity: 0.85, maxWidth: 460 }}>
            Every item from receipt into Stores through issue, custody, maintenance and disposal — with a named officer and an approval trail for each step.
          </Typography>
        </Box>
        <Typography sx={{ fontSize: 12, opacity: 0.7 }}>
          Authorised ministry staff only. All activity is recorded in the audit trail.
        </Typography>
      </Box>

      <Box sx={{ display: 'grid', placeItems: 'center', p: 3, bgcolor: 'background.default' }}>
        <Paper variant="outlined" sx={{ width: '100%', maxWidth: 420, p: { xs: 3, sm: 4 } }}>
          <Typography sx={{ fontWeight: 700 }} variant="h5">
            {title}
          </Typography>
          {subtitle && (
            <Typography color="text.secondary" sx={{ fontSize: 14, mt: 0.5, mb: 3 }}>
              {subtitle}
            </Typography>
          )}
          {children}
        </Paper>
      </Box>
    </Box>
  )
}
