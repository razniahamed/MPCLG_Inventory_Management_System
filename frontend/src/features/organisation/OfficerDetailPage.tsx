import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import SwapHorizIcon from '@mui/icons-material/SwapHoriz'
import { Alert, Box, Button, Card, CardContent, Chip, Grid, Skeleton, Stack, Step, StepContent, StepLabel, Stepper, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { get, send } from '../../api/client'
import type { Officer } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog } from '../../components/FormDialog'
import { errorMessage, useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { StatusChip } from '../../components/StatusChip'
import { formatDate } from '../../utils/format'
import { officerFields } from './officerFields'

const labelOf = (r: Record<string, unknown>) => `${r.code} — ${(r.name_en ?? r.name) as string}`

function Item({ label, children }: { label: string; children: ReactNode }) {
  return (
    <Box>
      <Typography sx={{ fontSize: 12 }} color="text.secondary">
        {label}
      </Typography>
      <Box sx={{ fontSize: 14 }}>{children}</Box>
    </Box>
  )
}

export function OfficerDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { can } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [moving, setMoving] = useState(false)

  const query = useQuery({ queryKey: ['/officers', id], queryFn: () => get<Officer>(`/officers/${id}`) })
  const officer = query.data

  if (query.isLoading) return <Skeleton height={300} />
  if (query.isError || !officer) return <Alert severity="error">{errorMessage(query.error)}</Alert>

  const manage = can('officer.manage')
  const left = ['transferred', 'retired', 'resigned'].includes(officer.employment_status)
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['/officers'] })

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Officers', to: '/organisation/officers' }, { label: officer.full_name }]}
        title={
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <span>{officer.full_name}</span>
            <StatusChip value={officer.employment_status} />
          </Stack>
        }
        subtitle={`${officer.officer_no} · ${officer.designation?.name ?? ''} · ${officer.department?.name_en ?? ''}`}
        actions={
          manage && (
            <>
              <Button variant="outlined" startIcon={<EditOutlinedIcon />} onClick={() => setEditing(true)}>
                Edit
              </Button>
              {!left && (
                <Button variant="contained" startIcon={<SwapHorizIcon />} onClick={() => setMoving(true)}>
                  Transfer / new post
                </Button>
              )}
            </>
          )
        }
      />
      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 5 }}>
          <Card>
            <CardContent>
              <Grid container spacing={2}>
                <Grid size={6}><Item label="Officer number">{officer.officer_no}</Item></Grid>
                <Grid size={6}><Item label="Name with initials">{officer.name_with_initials ?? '—'}</Item></Grid>
                <Grid size={6}><Item label="Designation">{officer.designation?.name ?? '—'}</Item></Grid>
                <Grid size={6}><Item label="Department">{officer.department?.name_en ?? '—'}</Item></Grid>
                <Grid size={6}><Item label="Work phone">{officer.work_phone ?? '—'}</Item></Grid>
                <Grid size={6}><Item label="Work email">{officer.work_email ?? '—'}</Item></Grid>
                <Grid size={6}><Item label="Joined">{formatDate(officer.joined_on)}</Item></Grid>
                <Grid size={6}><Item label="Left">{formatDate(officer.left_on)}</Item></Grid>
                <Grid size={12}>
                  <Item label="Sign-in account">{officer.has_user_account ? <Chip label="Has an account" color="primary" variant="outlined" /> : 'None'}</Item>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card>
            <CardContent>
              <Typography sx={{ fontSize: 16 }} variant="h6" gutterBottom>
                Assignment history
              </Typography>
              <Typography color="text.secondary" sx={{ fontSize: 13, mb: 2 }}>
                Each post the officer has held. Old transactions keep the responsibility that applied at the time.
              </Typography>
              <Stepper orientation="vertical" activeStep={-1} nonLinear>
                {(officer.assignments ?? []).map((a) => (
                  <Step key={a.id} active>
                    <StepLabel optional={<Typography sx={{ fontSize: 12 }} color="text.secondary">{formatDate(a.from_on)} – {a.to_on ? formatDate(a.to_on) : 'present'}</Typography>}>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <span>{a.designation?.name}</span>
                        {a.is_current && <Chip label="current" color="success" sx={{ height: 18 }} />}
                      </Stack>
                    </StepLabel>
                    <StepContent>
                      <Typography sx={{ fontSize: 13 }}>{a.department?.name_en}</Typography>
                      {a.authority_ref && (
                        <Typography sx={{ fontSize: 12 }} color="text.secondary">
                          Authority: {a.authority_ref}
                        </Typography>
                      )}
                    </StepContent>
                  </Step>
                ))}
              </Stepper>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <FormDialog
        open={editing}
        title="Edit officer"
        fields={officerFields(false)}
        initial={officer as unknown as Record<string, unknown>}
        onClose={() => setEditing(false)}
        onSubmit={async (values) => {
          const res = await send('patch', `/officers/${officer.id}`, values)
          await refresh()
          notify.success('Officer updated.')
          res.meta.warnings?.forEach((w) => notify.warning(w))
        }}
      />
      <FormDialog
        open={moving}
        title="Transfer / new post"
        fields={[
          { name: 'from_on', label: 'Starts on', type: 'date', required: true, width: 6, defaultValue: new Date().toISOString().slice(0, 10) },
          { name: 'authority_ref', label: 'Transfer / appointment letter', width: 6 },
          { name: 'designation_id', label: 'Designation', required: true, lookup: { endpoint: '/designations', label: labelOf, sort: 'name' }, defaultValue: officer.designation_id },
          { name: 'department_id', label: 'Department', required: true, lookup: { endpoint: '/departments', label: labelOf, sort: 'name_en' }, defaultValue: officer.department_id },
          { name: 'public_service_id', label: 'Public service', width: 6, lookup: { endpoint: '/public-services', label: labelOf, sort: 'code' }, defaultValue: officer.public_service_id },
          { name: 'service_grade_id', label: 'Grade', width: 6, lookup: { endpoint: '/service-grades', label: (r) => `${(r.public_service as { code?: string } | undefined)?.code ?? ''} ${r.code}`, sort: 'rank' }, defaultValue: officer.service_grade_id },
          { name: 'reason', label: 'Reason (optional)' },
        ]}
        submitLabel="Record assignment"
        onClose={() => setMoving(false)}
        onSubmit={async (values) => {
          await send('post', `/officers/${officer.id}/assignments`, values)
          await refresh()
          notify.success('Assignment recorded; the previous one was closed.')
        }}
      />
    </>
  )
}
