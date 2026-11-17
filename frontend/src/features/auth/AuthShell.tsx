import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined'
import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome'
import BuildOutlinedIcon from '@mui/icons-material/BuildOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import SupportAgentOutlinedIcon from '@mui/icons-material/SupportAgentOutlined'
import { Box, Grid, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Logo } from '../../layout/AppLayout'
import { brand } from '../../theme'

const FEATURES: { icon: ReactNode; title: string; text: string; color: string }[] = [
  { icon: <Inventory2OutlinedIcon />, title: 'Stock ledger', text: 'Live balances calculated from every movement', color: '#5ab0ff' },
  { icon: <AssignmentOutlinedIcon />, title: 'Requests & issues', text: 'Certify, approve and issue with a full trail', color: '#7dd3fc' },
  { icon: <PrintOutlinedIcon />, title: 'A4 paper control', text: 'Meter readings, statements and shared printers', color: '#93c5fd' },
  { icon: <QrCode2OutlinedIcon />, title: 'Assets & QR', text: 'Permanent asset numbers and QR labels', color: '#a5b4fc' },
  { icon: <BuildOutlinedIcon />, title: 'Maintenance', text: 'Warranty, repairs, servicing and verification', color: '#67e8f9' },
  { icon: <HistoryOutlinedIcon />, title: 'Audit trail', text: 'Every action recorded with a named officer', color: '#bae6fd' },
]

/** Glass panel on the dark login background. */
export function Glass({ children, accent = false, sx }: { children: ReactNode; accent?: boolean; sx?: object }) {
  return (
    <Box
      sx={{
        position: 'relative',
        borderRadius: 5,
        bgcolor: 'rgba(255,255,255,0.06)',
        border: '1px solid rgba(255,255,255,0.12)',
        backdropFilter: 'blur(14px)',
        boxShadow: '0 20px 60px rgba(0,0,0,0.35)',
        overflow: 'hidden',
        ...(accent && { '&::before': { content: '""', position: 'absolute', inset: '0 0 auto 0', height: 4, background: 'linear-gradient(90deg, #22d3ee, #2f6fed, #6366f1)' } }),
        ...sx,
      }}
    >
      {children}
    </Box>
  )
}

/**
 * Sign-in pages: dark blue gradient, glass cards, feature overview
 * (styled after the reference login).
 */
export function AuthShell({ title, accent, subtitle, children }: { title: string; accent?: string; subtitle?: string; children: ReactNode }) {
  return (
    <Box
      sx={{
        minHeight: '100vh',
        background: brand.login,
        color: '#fff',
        display: 'flex',
        alignItems: 'center',
        position: 'relative',
        '&::before': {
          content: '""',
          position: 'absolute',
          inset: 0,
          backgroundImage: 'radial-gradient(rgba(255,255,255,0.07) 1px, transparent 1px)',
          backgroundSize: '22px 22px',
          pointerEvents: 'none',
        },
      }}
    >
      <Grid container spacing={5} sx={{ width: '100%', maxWidth: 1180, mx: 'auto', px: { xs: 2, sm: 4 }, py: 6, position: 'relative', alignItems: 'center' }}>
        <Grid size={{ xs: 12, md: 5 }}>
          <Stack direction="row" spacing={2} sx={{ alignItems: 'center', mb: 3 }}>
            <Logo size={52} />
            <Box sx={{ borderLeft: '1px solid rgba(255,255,255,0.25)', pl: 2 }}>
              <Typography sx={{ fontWeight: 800, fontSize: 26, lineHeight: 1.1 }}>MPCLG Inventory</Typography>
              <Typography sx={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.14em', color: brand.ice }}>STORES & ASSET MANAGEMENT PORTAL</Typography>
            </Box>
          </Stack>

          <Glass accent>
            <Box sx={{ p: { xs: 3, sm: 4 } }}>
              <Typography sx={{ fontWeight: 800, fontSize: { xs: 26, sm: 30 }, textAlign: 'center', lineHeight: 1.2 }}>
                {title}{' '}
                {accent && (
                  <Box component="span" sx={{ background: 'linear-gradient(90deg, #5ab0ff, #22d3ee)', backgroundClip: 'text', color: 'transparent' }}>
                    {accent}
                  </Box>
                )}
              </Typography>
              {subtitle && <Typography sx={{ textAlign: 'center', color: 'rgba(220,232,255,0.75)', fontSize: 14, mt: 1, mb: 3 }}>{subtitle}</Typography>}
              {children}
            </Box>
          </Glass>

          <Glass sx={{ mt: 2 }}>
            <Stack direction="row" spacing={1.5} sx={{ p: 2, alignItems: 'center' }}>
              <SupportAgentOutlinedIcon sx={{ color: brand.sky }} />
              <Typography sx={{ fontSize: 13, color: 'rgba(225,235,255,0.85)' }}>
                Trouble signing in? Contact the <b>IT Division</b> helpdesk, ext. 2200.
              </Typography>
            </Stack>
          </Glass>
          <Typography sx={{ textAlign: 'center', fontSize: 12, color: 'rgba(200,215,255,0.55)', mt: 2 }}>
            © {new Date().getFullYear()} Ministry of Provincial Councils and Local Government
          </Typography>
        </Grid>

        <Grid size={{ xs: 12, md: 7 }} sx={{ display: { xs: 'none', md: 'block' } }}>
          <Glass>
            <Box sx={{ p: 4 }}>
              <Stack sx={{ alignItems: 'center' }}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', px: 1.5, py: 0.5, borderRadius: 99, border: '1px solid rgba(255,255,255,0.2)', bgcolor: 'rgba(255,255,255,0.06)' }}>
                  <AutoAwesomeIcon sx={{ fontSize: 14, color: brand.sky }} />
                  <Typography sx={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.12em' }}>EVERYTHING IN ONE PLACE</Typography>
                </Stack>
                <Typography sx={{ fontWeight: 800, fontSize: 30, mt: 2, textAlign: 'center', lineHeight: 1.15 }}>
                  Ministry Stores,
                  <br />
                  <Box component="span" sx={{ background: 'linear-gradient(90deg, #5ab0ff, #22d3ee)', backgroundClip: 'text', color: 'transparent' }}>
                    Simplified
                  </Box>
                </Typography>
                <Typography sx={{ color: 'rgba(220,232,255,0.72)', fontSize: 14, mt: 1, textAlign: 'center', maxWidth: 460 }}>
                  From receipt into Stores through issue, custody, maintenance and disposal — with an approval trail for every step.
                </Typography>
              </Stack>

              <Grid container spacing={1.5} sx={{ mt: 3 }}>
                {FEATURES.map((f) => (
                  <Grid key={f.title} size={6}>
                    <Box sx={{ p: 2, borderRadius: 3, bgcolor: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', height: '100%' }}>
                      <Box sx={{ width: 34, height: 34, borderRadius: 2, display: 'grid', placeItems: 'center', bgcolor: 'rgba(255,255,255,0.08)', color: f.color, mb: 1, '& svg': { fontSize: 20 } }}>{f.icon}</Box>
                      <Typography sx={{ fontWeight: 700, fontSize: 14 }}>{f.title}</Typography>
                      <Typography sx={{ fontSize: 12, color: 'rgba(210,225,255,0.65)' }}>{f.text}</Typography>
                    </Box>
                  </Grid>
                ))}
              </Grid>

              <Stack direction="row" sx={{ justifyContent: 'space-around', mt: 3, pt: 2.5, borderTop: '1px solid rgba(255,255,255,0.1)' }}>
                {[
                  ['24/7', 'Access'],
                  ['Real-time', 'Stock'],
                  ['Audited', 'Every action'],
                ].map(([big, small]) => (
                  <Box key={big} sx={{ textAlign: 'center' }}>
                    <Typography sx={{ fontWeight: 800, fontSize: 20, color: brand.sky }}>{big}</Typography>
                    <Typography sx={{ fontSize: 12, color: 'rgba(210,225,255,0.65)' }}>{small}</Typography>
                  </Box>
                ))}
              </Stack>
            </Box>
          </Glass>
        </Grid>
      </Grid>
    </Box>
  )
}

/** Inputs styled for the dark glass card. */
export const glassField = {
  '& .MuiOutlinedInput-root': {
    color: '#fff',
    bgcolor: 'rgba(255,255,255,0.06)',
    borderRadius: 3,
    '& fieldset': { borderColor: 'rgba(255,255,255,0.18)' },
    '&:hover fieldset': { borderColor: 'rgba(255,255,255,0.35)' },
    '&.Mui-focused fieldset': { borderColor: brand.sky },
  },
  '& .MuiInputLabel-root': { color: 'rgba(220,232,255,0.7)' },
  '& .MuiInputLabel-root.Mui-focused': { color: brand.sky },
  '& .MuiIconButton-root': { color: 'rgba(220,232,255,0.7)' },
  '& .MuiFormHelperText-root': { color: '#fca5a5' },
}
