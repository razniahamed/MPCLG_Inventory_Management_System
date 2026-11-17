import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  FormControlLabel,
  Grid,
  Paper,
  Skeleton,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material'
import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { get, send } from '../../api/client'
import type { Module, Role } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog } from '../../components/FormDialog'
import { errorMessage, useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { BoolChip } from '../../components/StatusChip'

const NON_APPROVING = ['system_admin', 'internal_auditor']

export function RoleDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { can } = useAuth()
  const notify = useNotify()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const manage = can('role.manage')

  const role = useQuery({ queryKey: ['roles', id], queryFn: () => get<Role>(`/roles/${id}`) })
  const catalogue = useQuery({ queryKey: ['permission-catalogue'], queryFn: () => get<Module[]>('/permissions'), enabled: can('permission.view') })

  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [saving, setSaving] = useState(false)
  const [editing, setEditing] = useState(false)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    if (role.data?.permissions) setSelected(new Set(role.data.permissions))
  }, [role.data])

  const original = useMemo(() => new Set(role.data?.permissions ?? []), [role.data])
  const dirty = selected.size !== original.size || [...selected].some((c) => !original.has(c))
  const nonApproving = role.data ? NON_APPROVING.includes(role.data.code) : false

  if (role.isLoading) return <Skeleton height={300} />
  if (role.isError || !role.data) return <Alert severity="error">{errorMessage(role.error)}</Alert>
  const r = role.data

  const toggle = (code: string, on: boolean) =>
    setSelected((s) => {
      const next = new Set(s)
      if (on) next.add(code)
      else next.delete(code)
      return next
    })

  const save = async (reason: string) => {
    await send('put', `/roles/${r.id}/permissions`, { permissions: [...selected], reason })
    await queryClient.invalidateQueries({ queryKey: ['roles'] })
    notify.success('Permissions saved. Every holder of this role is updated immediately.')
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Roles', to: '/roles' }, { label: r.name }]}
        title={
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <span>{r.name}</span>
            <BoolChip value={r.is_active} />
            {r.is_system && <Chip label="Seeded role" variant="outlined" />}
          </Stack>
        }
        subtitle={
          <>
            <Box component="span" sx={{ fontFamily: 'monospace' }}>
              {r.code}
            </Box>{' '}
            · {r.active_assignments_count ?? 0} user(s) · {r.description}
          </>
        }
        actions={
          manage && (
            <>
              <Button variant="outlined" startIcon={<EditOutlinedIcon />} onClick={() => setEditing(true)}>
                Edit
              </Button>
              {!r.is_system && (
                <Button variant="outlined" color="error" startIcon={<DeleteOutlineIcon />} onClick={() => setDeleting(true)}>
                  Delete
                </Button>
              )}
            </>
          )
        }
      />

      {nonApproving && (
        <Alert severity="info" sx={{ mb: 2 }}>
          This role never receives approval or financial permissions (they are locked below).
        </Alert>
      )}

      <Paper variant="outlined" sx={{ p: 2, mb: 2, position: 'sticky', top: 72, zIndex: 2, display: 'flex', alignItems: 'center', gap: 2 }}>
        <Typography sx={{ fontSize: 14, flex: 1 }}>
          <b>{selected.size}</b> permissions selected{dirty && ' · unsaved changes'}
        </Typography>
        {manage && (
          <>
            <Button disabled={!dirty} onClick={() => setSelected(new Set(original))}>
              Undo
            </Button>
            <Button variant="contained" disabled={!dirty} onClick={() => setSaving(true)}>
              Save permissions
            </Button>
          </>
        )}
      </Paper>

      {catalogue.isLoading && <Skeleton height={300} />}
      {catalogue.data?.map((m) => {
        const codes = m.permissions?.map((p) => p.code) ?? []
        const count = codes.filter((c) => selected.has(c)).length
        const selectable = (m.permissions ?? []).filter((p) => !(nonApproving && p.is_sensitive))
        const allOn = selectable.length > 0 && selectable.every((p) => selected.has(p.code))
        return (
          <Accordion key={m.code} defaultExpanded={codes.some((c) => original.has(c))} disableGutters variant="outlined" sx={{ '&:before': { display: 'none' }, mb: 1 }}>
            <AccordionSummary expandIcon={<ExpandMoreIcon />}>
              <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', flex: 1 }}>
                <Typography sx={{ fontWeight: 600 }}>{m.name}</Typography>
                <Chip label={`${count}/${codes.length}`} color={count ? 'primary' : 'default'} variant={count ? 'filled' : 'outlined'} />
                {!m.is_enabled && <Chip label="module off" color="warning" variant="outlined" />}
              </Stack>
            </AccordionSummary>
            <AccordionDetails>
              {manage && (
                <FormControlLabel
                  sx={{ mb: 1 }}
                  control={
                    <Checkbox
                      checked={allOn}
                      indeterminate={count > 0 && !allOn}
                      onChange={(e) => selectable.forEach((p) => toggle(p.code, e.target.checked))}
                    />
                  }
                  label={<Typography sx={{ fontSize: 13 }}>Select all in {m.name}</Typography>}
                />
              )}
              <Grid container spacing={0.5}>
                {m.permissions?.map((p) => {
                  const locked = nonApproving && p.is_sensitive
                  return (
                    <Grid key={p.code} size={{ xs: 12, sm: 6, lg: 4 }}>
                      <Tooltip title={locked ? 'Approval permission: not allowed for this role' : p.code} placement="top-start">
                        <FormControlLabel
                          disabled={!manage || locked}
                          control={<Checkbox size="small" checked={selected.has(p.code)} onChange={(e) => toggle(p.code, e.target.checked)} />}
                          label={
                            <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
                              <Typography sx={{ fontSize: 14 }}>{p.name.split(': ')[1] ?? p.name}</Typography>
                              <Typography sx={{ fontSize: 12 }} color="text.secondary">
                                {p.resource}
                              </Typography>
                              {p.is_sensitive && <Chip label="approval" color="secondary" variant="outlined" sx={{ height: 18, fontSize: 10 }} />}
                            </Stack>
                          }
                        />
                      </Tooltip>
                    </Grid>
                  )
                })}
              </Grid>
            </AccordionDetails>
          </Accordion>
        )
      })}

      <ReasonDialog
        open={saving}
        title="Save role permissions"
        description={`Changes apply immediately to the ${r.active_assignments_count ?? 0} user(s) holding this role.`}
        confirmLabel="Save"
        onClose={() => setSaving(false)}
        onConfirm={save}
      />
      <ReasonDialog
        open={deleting}
        title="Delete role"
        description="Only roles nobody holds can be deleted. The record is kept for history."
        confirmLabel="Delete"
        danger
        onClose={() => setDeleting(false)}
        onConfirm={async (reason) => {
          await send('delete', `/roles/${r.id}`, { reason })
          await queryClient.invalidateQueries({ queryKey: ['roles'] })
          notify.success('Role deleted.')
          navigate('/roles')
        }}
      />
      <FormDialog
        open={editing}
        title="Edit role"
        fields={[
          { name: 'name', label: 'Name', required: true },
          { name: 'description', label: 'Description', type: 'textarea' },
          { name: 'is_active', label: 'Active', type: 'switch', helper: 'Inactive roles give no permissions.' },
          { name: 'reason', label: 'Reason' },
        ]}
        initial={r as unknown as Record<string, unknown>}
        onClose={() => setEditing(false)}
        onSubmit={async (values) => {
          await send('patch', `/roles/${r.id}`, values)
          await queryClient.invalidateQueries({ queryKey: ['roles'] })
          notify.success('Role updated.')
        }}
      />
    </>
  )
}
