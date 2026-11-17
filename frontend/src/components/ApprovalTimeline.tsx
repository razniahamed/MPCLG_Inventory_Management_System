import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import HourglassEmptyIcon from '@mui/icons-material/HourglassEmpty'
import ReplayIcon from '@mui/icons-material/Replay'
import { Box, Chip, Stack, Typography } from '@mui/material'
import { formatDateTime, humanize } from '../utils/format'

export interface ApprovalStep {
  version_no: number
  step_no: number
  step: string
  permission: string
  status: string
  decisions: { decision: string; designation: string; department: string | null; under_delegation: boolean; remarks: string | null; decided_at: string }[]
}

const ICON: Record<string, React.ReactNode> = {
  approved: <CheckCircleIcon color="success" />,
  reduced: <CheckCircleIcon color="warning" />,
  pending: <HourglassEmptyIcon color="disabled" />,
  returned: <ReplayIcon color="warning" />,
  rejected: <CancelOutlinedIcon color="error" />,
  superseded: <HourglassEmptyIcon color="disabled" />,
}

/** Approval steps with who decided, in what capacity, and why. */
export function ApprovalTimeline({ steps }: { steps?: ApprovalStep[] }) {
  if (!steps?.length) return <Typography color="text.secondary" sx={{ fontSize: 14 }}>No approval steps yet.</Typography>

  return (
    <Stack spacing={0}>
      {steps.map((s, i) => (
        <Stack key={`${s.version_no}-${s.step_no}`} direction="row" spacing={1.5} sx={{ position: 'relative', pb: i === steps.length - 1 ? 0 : 2 }}>
          <Box sx={{ position: 'relative', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
            {ICON[s.status] ?? ICON.pending}
            {i < steps.length - 1 && <Box sx={{ flex: 1, width: 2, bgcolor: 'divider', mt: 0.5 }} />}
          </Box>
          <Box sx={{ flex: 1, pb: 0.5 }}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <Typography sx={{ fontWeight: 700, fontSize: 14 }}>{s.step}</Typography>
              <Chip label={humanize(s.status)} variant="outlined" color={s.status === 'approved' ? 'success' : s.status === 'pending' ? 'default' : s.status === 'rejected' ? 'error' : 'warning'} />
              {s.version_no > 1 && <Chip label={`v${s.version_no}`} />}
            </Stack>
            {s.decisions.map((d, j) => (
              <Box key={j} sx={{ mt: 0.75, fontSize: 13, color: 'text.secondary' }}>
                <b>{humanize(d.decision)}</b> by {d.designation}
                {d.department ? `, ${d.department}` : ''} · {formatDateTime(d.decided_at)}
                {d.under_delegation && <Chip label="under delegation" sx={{ ml: 1, height: 18 }} />}
                {d.remarks && <Box sx={{ mt: 0.25, fontStyle: 'italic', color: 'text.primary' }}>“{d.remarks}”</Box>}
              </Box>
            ))}
            {s.decisions.length === 0 && s.status === 'pending' && (
              <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>Waiting for someone with {s.permission}</Typography>
            )}
          </Box>
        </Stack>
      ))}
    </Stack>
  )
}
