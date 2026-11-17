import AddIcon from '@mui/icons-material/Add'
import { Box, Button, Chip, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { send } from '../../api/client'
import type { Role } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog } from '../../components/FormDialog'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ServerTable } from '../../components/ServerTable'
import { BoolChip } from '../../components/StatusChip'

const columns: GridColDef<Role>[] = [
  {
    field: 'name',
    headerName: 'Role',
    flex: 1,
    minWidth: 220,
    renderCell: ({ row }) => (
      <Box sx={{ lineHeight: 1.3, py: 1 }}>
        <Typography component="div" sx={{ fontSize: 14, fontWeight: 600 }}>
          {row.name} {row.is_system && <Chip label="seeded" variant="outlined" sx={{ ml: 0.5, height: 18, fontSize: 10 }} />}
        </Typography>
        <Typography sx={{ fontSize: 12, fontFamily: 'monospace' }} color="text.secondary">
          {row.code}
        </Typography>
      </Box>
    ),
  },
  { field: 'description', headerName: 'Description', flex: 2, minWidth: 260 },
  { field: 'active_assignments_count', headerName: 'Users', width: 90, type: 'number' },
  { field: 'is_active', headerName: 'Status', width: 110, renderCell: ({ row }) => <BoolChip value={row.is_active} /> },
]

export function RolesPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title="Roles"
        subtitle="Named bundles of permissions. Assign them to users on the user's page, optionally limited to a division, department or store."
        actions={can('role.manage') && <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>New role</Button>}
      />
      <ServerTable<Role>
        queryKey={['roles']}
        endpoint="/roles"
        columns={columns}
        sortFields={['code', 'name']}
        defaultSort={[{ field: 'name', sort: 'asc' }]}
        searchPlaceholder="Search roles"
        onRowClick={(row) => navigate(`/roles/${row.id}`)}
      />
      <FormDialog
        open={creating}
        title="New role"
        fields={[
          { name: 'code', label: 'Code', required: true, helper: 'Permanent. Lowercase letters, numbers and underscores, e.g. stores_viewer.' },
          { name: 'name', label: 'Name', required: true },
          { name: 'description', label: 'Description', type: 'textarea' },
          { name: 'reason', label: 'Reason', helper: 'Recorded in the audit trail.' },
        ]}
        submitLabel="Create and choose permissions"
        onClose={() => setCreating(false)}
        onSubmit={async (values) => {
          const res = await send<Role>('post', '/roles', values)
          await queryClient.invalidateQueries({ queryKey: ['roles'] })
          notify.success('Role created. Now choose its permissions.')
          navigate(`/roles/${res.data.id}`)
        }}
      />
    </>
  )
}
