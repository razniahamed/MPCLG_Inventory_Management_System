import AddIcon from '@mui/icons-material/Add'
import ArchiveOutlinedIcon from '@mui/icons-material/ArchiveOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import { Box, Button, IconButton, MenuItem, Stack, TextField, Tooltip } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { send } from '../../api/client'
import type { MasterRecord } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog, type FieldDef } from '../../components/FormDialog'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { ServerTable } from '../../components/ServerTable'
import { BoolChip } from '../../components/StatusChip'

export interface MasterDataConfig {
  title: string
  singular: string
  subtitle: string
  endpoint: string
  managePermission: string
  columns: GridColDef<MasterRecord>[]
  fields: FieldDef[]
  sortFields: string[]
  defaultSort?: { field: string; sort: 'asc' | 'desc' }
  /** Extra per-row actions (e.g. store officers). */
  rowActions?: (row: MasterRecord) => ReactNode
  /** Extra toolbar filters rendered next to search. */
  filters?: { name: string; label: string; options: { value: string; label: string }[] }[]
}

export function MasterDataPage({ config }: { config: MasterDataConfig }) {
  const { can } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const manage = can(config.managePermission)

  const [active, setActive] = useState('true')
  const [extra, setExtra] = useState<Record<string, string>>({})
  const [editing, setEditing] = useState<MasterRecord | null>(null)
  const [creating, setCreating] = useState(false)
  const [retiring, setRetiring] = useState<MasterRecord | null>(null)

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: [config.endpoint] })
    await queryClient.invalidateQueries({ queryKey: ['lookup', config.endpoint] })
  }

  const columns: GridColDef<MasterRecord>[] = [
    ...config.columns,
    { field: 'is_active', headerName: 'Status', width: 100, renderCell: ({ row }) => <BoolChip value={row.is_active} /> },
    ...(manage || config.rowActions
      ? [
          {
            field: '__actions',
            headerName: '',
            width: config.rowActions ? 140 : 100,
            sortable: false,
            align: 'right',
            renderCell: ({ row }) => (
              <Stack direction="row" spacing={0.5} sx={{ justifyContent: 'flex-end', height: '100%', alignItems: 'center' }}>
                {config.rowActions?.(row)}
                {manage && (
                  <>
                    <Tooltip title="Edit">
                      <IconButton size="small" onClick={() => setEditing(row)}>
                        <EditOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title="Retire">
                      <IconButton size="small" onClick={() => setRetiring(row)}>
                        <ArchiveOutlinedIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </>
                )}
              </Stack>
            ),
          } satisfies GridColDef<MasterRecord>,
        ]
      : []),
  ]

  return (
    <>
      <PageHeader
        title={config.title}
        subtitle={config.subtitle}
        actions={
          manage && (
            <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>
              New {config.singular.toLowerCase()}
            </Button>
          )
        }
      />
      <ServerTable<MasterRecord>
        queryKey={[config.endpoint]}
        endpoint={config.endpoint}
        columns={columns}
        sortFields={config.sortFields}
        defaultSort={config.defaultSort ? [config.defaultSort] : []}
        filters={{ is_active: active, ...extra }}
        toolbar={
          <>
            <TextField select label="Status" value={active} onChange={(e) => setActive(e.target.value)} sx={{ width: { xs: '100%', sm: 140 } }}>
              <MenuItem value="true">Active</MenuItem>
              <MenuItem value="false">Inactive</MenuItem>
              <MenuItem value="">All</MenuItem>
            </TextField>
            {config.filters?.map((f) => (
              <TextField key={f.name} select label={f.label} value={extra[f.name] ?? ''} onChange={(e) => setExtra((x) => ({ ...x, [f.name]: e.target.value }))} sx={{ width: { xs: '100%', sm: 220 } }}>
                <MenuItem value="">All</MenuItem>
                {f.options.map((o) => (
                  <MenuItem key={o.value} value={o.value}>
                    {o.label}
                  </MenuItem>
                ))}
              </TextField>
            ))}
          </>
        }
      />
      <Box sx={{ fontSize: 12, color: 'text.secondary', mt: 1 }}>Records are never deleted. Retiring hides a record and is refused while it is still in use.</Box>

      <FormDialog
        open={creating}
        title={`New ${config.singular.toLowerCase()}`}
        fields={config.fields}
        submitLabel="Create"
        onClose={() => setCreating(false)}
        onSubmit={async (values) => {
          await send('post', config.endpoint, values)
          await invalidate()
          notify.success(`${config.singular} created.`)
        }}
      />
      <FormDialog
        open={editing !== null}
        title={`Edit ${config.singular.toLowerCase()}`}
        fields={config.fields}
        initial={editing}
        onClose={() => setEditing(null)}
        onSubmit={async (values) => {
          await send('patch', `${config.endpoint}/${editing!.id}`, values)
          await invalidate()
          notify.success(`${config.singular} updated.`)
        }}
      />
      {retiring && (
        <ReasonDialog
          open
          title={`Retire ${config.singular.toLowerCase()}`}
          description="The record is hidden from new use but kept for history."
          confirmLabel="Retire"
          danger
          onClose={() => setRetiring(null)}
          onConfirm={async (reason) => {
            await send('delete', `${config.endpoint}/${retiring.id}`, { reason })
            await invalidate()
            notify.success(`${config.singular} retired.`)
          }}
        />
      )}
    </>
  )
}
