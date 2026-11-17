import AddIcon from '@mui/icons-material/Add'
import { Box, Button, MenuItem, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { send } from '../../api/client'
import type { Officer } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog } from '../../components/FormDialog'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ServerTable } from '../../components/ServerTable'
import { StatusChip } from '../../components/StatusChip'
import { useLookup } from '../../hooks/useLookup'
import { officerFields } from './officerFields'

const columns: GridColDef<Officer>[] = [
  { field: 'officer_no', headerName: 'Officer no.', width: 130, renderCell: ({ value }) => <span style={{ fontFamily: 'monospace' }}>{value}</span> },
  {
    field: 'full_name',
    headerName: 'Name',
    flex: 1,
    minWidth: 220,
    renderCell: ({ row }) => (
      <Box sx={{ py: 1, lineHeight: 1.3 }}>
        <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
          {row.full_name}
        </Typography>
        <Typography sx={{ fontSize: 12 }} color="text.secondary">
          {row.work_email ?? row.name_with_initials ?? ''}
        </Typography>
      </Box>
    ),
  },
  { field: 'designation', headerName: 'Designation', width: 220, sortable: false, valueGetter: (_, r) => r.designation?.name ?? '—' },
  { field: 'department', headerName: 'Department', width: 220, sortable: false, valueGetter: (_, r) => r.department?.name_en ?? '—' },
  { field: 'employment_status', headerName: 'Status', width: 120, renderCell: ({ row }) => <StatusChip value={row.employment_status} /> },
  { field: 'has_user_account', headerName: 'Account', width: 100, type: 'boolean' },
]

export function OfficersPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [department, setDepartment] = useState('')
  const [status, setStatus] = useState('active')
  const [creating, setCreating] = useState(false)
  const departments = useLookup('/departments', { sort: 'name_en' })

  return (
    <>
      <PageHeader
        title="Officers"
        subtitle="The people on the staff register. A sign-in account is separate and optional."
        actions={can('officer.manage') && <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>New officer</Button>}
      />
      <ServerTable<Officer>
        queryKey={['/officers']}
        endpoint="/officers"
        columns={columns}
        sortFields={['officer_no', 'full_name']}
        defaultSort={[{ field: 'full_name', sort: 'asc' }]}
        filters={{ department_id: department, employment_status: status }}
        searchPlaceholder="Search name, number or email"
        onRowClick={(row) => navigate(`/organisation/officers/${row.id}`)}
        toolbar={
          <>
            <TextField select label="Department" value={department} onChange={(e) => setDepartment(e.target.value)} sx={{ width: { xs: '100%', sm: 240 } }}>
              <MenuItem value="">All departments</MenuItem>
              {(departments.data ?? []).map((d) => (
                <MenuItem key={String(d.id)} value={String(d.id)}>
                  {String(d.name_en)}
                </MenuItem>
              ))}
            </TextField>
            <TextField select label="Employment" value={status} onChange={(e) => setStatus(e.target.value)} sx={{ width: { xs: '100%', sm: 160 } }}>
              <MenuItem value="">All</MenuItem>
              {['active', 'transferred', 'retired', 'resigned', 'suspended'].map((s) => (
                <MenuItem key={s} value={s}>
                  {s.charAt(0).toUpperCase() + s.slice(1)}
                </MenuItem>
              ))}
            </TextField>
          </>
        }
      />
      <FormDialog
        open={creating}
        title="New officer"
        fields={officerFields(true)}
        submitLabel="Create officer"
        onClose={() => setCreating(false)}
        onSubmit={async (values) => {
          const res = await send<Officer>('post', '/officers', values)
          await queryClient.invalidateQueries({ queryKey: ['/officers'] })
          await queryClient.invalidateQueries({ queryKey: ['lookup', '/officers'] })
          notify.success('Officer created.')
          navigate(`/organisation/officers/${res.data.id}`)
        }}
      />
    </>
  )
}
