import { Box, Breadcrumbs, Link, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Link as RouterLink } from 'react-router-dom'

export function PageHeader({
  title,
  subtitle,
  actions,
  crumbs,
}: {
  title: ReactNode
  subtitle?: ReactNode
  actions?: ReactNode
  crumbs?: { label: string; to?: string }[]
}) {
  return (
    <Box sx={{ mb: 3 }}>
      {crumbs && (
        <Breadcrumbs sx={{ mb: 1, fontSize: 13 }}>
          {crumbs.map((c) =>
            c.to ? (
              <Link key={c.label} component={RouterLink} to={c.to} underline="hover" color="inherit" sx={{ fontWeight: 600 }}>
                {c.label}
              </Link>
            ) : (
              <Typography key={c.label} color="text.primary" sx={{ fontSize: 13, fontWeight: 600 }}>
                {c.label}
              </Typography>
            ),
          )}
        </Breadcrumbs>
      )}
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' } }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography variant="h4" component="h1">
            {title}
          </Typography>
          {subtitle && (
            <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5, maxWidth: 900 }}>
              {subtitle}
            </Typography>
          )}
        </Box>
        {actions && (
          <Stack direction="row" spacing={1} useFlexGap sx={{ flexShrink: 0, flexWrap: 'wrap' }}>
            {actions}
          </Stack>
        )}
      </Stack>
    </Box>
  )
}
