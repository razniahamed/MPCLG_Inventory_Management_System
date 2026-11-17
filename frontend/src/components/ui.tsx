import { alpha, Box, Card, CardContent, Chip, Grid, Stack, Typography, type SxProps, type Theme } from '@mui/material'
import type { ReactNode } from 'react'

type Tone = 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'

/** Small rounded square with a tinted background holding an icon. */
export function IconTile({ icon, tone = 'primary', size = 36 }: { icon: ReactNode; tone?: Tone; size?: number }) {
  return (
    <Box
      sx={(t) => ({
        width: size,
        height: size,
        borderRadius: 2.5,
        display: 'grid',
        placeItems: 'center',
        flexShrink: 0,
        color: t.palette[tone].main,
        bgcolor: alpha(t.palette[tone].main, 0.12),
        '& svg': { fontSize: size * 0.55 },
      })}
    >
      {icon}
    </Box>
  )
}

/** Rounded content card with an optional icon-tile header. */
export function SectionCard({
  title,
  icon,
  tone,
  action,
  children,
  sx,
  noPadding,
}: {
  title?: ReactNode
  icon?: ReactNode
  tone?: Tone
  action?: ReactNode
  children: ReactNode
  sx?: SxProps<Theme>
  noPadding?: boolean
}) {
  return (
    <Card sx={sx}>
      {title && (
        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', px: 3, pt: 2.5, pb: noPadding ? 2 : 0 }}>
          {icon && <IconTile icon={icon} tone={tone} />}
          <Typography variant="h6" sx={{ flex: 1 }}>
            {title}
          </Typography>
          {action}
        </Stack>
      )}
      {noPadding ? children : <CardContent sx={{ px: 3, pb: '24px !important' }}>{children}</CardContent>}
    </Card>
  )
}

/** Label / value pairs in a responsive grid of soft tiles. */
export function InfoGrid({ items, columns = 3 }: { items: { label: string; value: ReactNode; wide?: boolean }[]; columns?: 2 | 3 | 4 }) {
  const size = { xs: 12, sm: 6, md: 12 / columns }

  return (
    <Grid container spacing={1.5}>
      {items.map((item) => (
        <Grid key={item.label} size={item.wide ? 12 : size}>
          <Box sx={(t) => ({ p: 1.5, borderRadius: 3, bgcolor: alpha(t.palette.primary.main, 0.04), border: `1px solid ${t.palette.divider}`, height: '100%' })}>
            <Typography sx={{ fontSize: 11, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'text.secondary' }}>{item.label}</Typography>
            <Box sx={{ fontSize: 14, mt: 0.5, wordBreak: 'break-word' }}>{item.value ?? '—'}</Box>
          </Box>
        </Grid>
      ))}
    </Grid>
  )
}

/** Headline number tile. */
export function StatCard({ label, value, hint, icon, tone = 'primary', onClick }: { label: string; value: ReactNode; hint?: ReactNode; icon: ReactNode; tone?: Tone; onClick?: () => void }) {
  return (
    <Card
      onClick={onClick}
      sx={{ height: '100%', cursor: onClick ? 'pointer' : 'default', transition: 'transform .15s, box-shadow .15s', '&:hover': onClick ? { transform: 'translateY(-2px)', boxShadow: '0 10px 30px rgba(10,30,90,0.12)' } : undefined }}
    >
      <CardContent sx={{ p: 2.5 }}>
        <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
          <IconTile icon={icon} tone={tone} size={46} />
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 13, color: 'text.secondary', fontWeight: 600 }}>{label}</Typography>
            <Typography sx={{ fontSize: 28, fontWeight: 800, lineHeight: 1.1, fontVariantNumeric: 'tabular-nums' }}>{value}</Typography>
            {hint && <Typography sx={{ fontSize: 12, color: 'text.secondary', mt: 0.25 }}>{hint}</Typography>}
          </Box>
        </Stack>
      </CardContent>
    </Card>
  )
}

/** Pill showing the quantity with its unit. */
export function Qty({ value, unit }: { value: string | number | null | undefined; unit?: string | null }) {
  if (value === null || value === undefined) return <>—</>
  const n = Number(value)
  const text = Number.isInteger(n) ? n.toLocaleString() : n.toLocaleString(undefined, { maximumFractionDigits: 3 })

  return (
    <Box component="span" sx={{ fontVariantNumeric: 'tabular-nums', fontWeight: 600 }}>
      {text}
      {unit && (
        <Box component="span" sx={{ color: 'text.secondary', fontWeight: 500, ml: 0.5, fontSize: '0.85em' }}>
          {unit}
        </Box>
      )}
    </Box>
  )
}

export function Money({ value }: { value: string | number | null | undefined }) {
  if (value === null || value === undefined || value === '') return <>—</>

  return <Box component="span" sx={{ fontVariantNumeric: 'tabular-nums' }}>Rs. {Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</Box>
}

export function CodeText({ children }: { children: ReactNode }) {
  return (
    <Box component="span" sx={{ fontFamily: '"JetBrains Mono", ui-monospace, monospace', fontSize: '0.92em', fontWeight: 600 }}>
      {children}
    </Box>
  )
}

export function FlagChips({ flags }: { flags?: string[] | null }) {
  if (!flags?.length) return null

  return (
    <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
      {flags.map((f) => (
        <Chip key={f} label={f.replace(/_/g, ' ')} color="warning" variant="outlined" />
      ))}
    </Stack>
  )
}
