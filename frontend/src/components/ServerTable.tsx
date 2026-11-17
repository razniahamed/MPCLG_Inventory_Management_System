import SearchIcon from '@mui/icons-material/Search'
import { Box, InputAdornment, Paper, Stack, TextField } from '@mui/material'
import { DataGrid, type GridColDef, type GridPaginationModel, type GridSortModel, type GridValidRowModel } from '@mui/x-data-grid'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { getPage, listParams } from '../api/client'
import { errorMessage } from './Notify'

interface Props<T extends GridValidRowModel> {
  /** React Query key prefix; invalidate it to refresh the table. */
  queryKey: unknown[]
  endpoint: string
  columns: GridColDef<T>[]
  /** Fields the API accepts in ?sort= (others are not sortable). */
  sortFields?: string[]
  defaultSort?: GridSortModel
  filters?: Record<string, string | number | boolean | undefined | null>
  searchPlaceholder?: string
  getRowId?: (row: T) => string | number
  onRowClick?: (row: T) => void
  toolbar?: ReactNode
  height?: number | 'auto'
  /** Send the search box as filter[searchAs] instead of ?search= */
  searchAs?: string
}

/** DataGrid wired to the API's page / per_page / sort / search / filter[] params. */
export function ServerTable<T extends GridValidRowModel>({
  queryKey,
  endpoint,
  columns,
  sortFields = [],
  defaultSort = [],
  filters,
  searchPlaceholder = 'Search…',
  getRowId,
  onRowClick,
  toolbar,
  searchAs,
}: Props<T>) {
  const [pagination, setPagination] = useState<GridPaginationModel>({ page: 0, pageSize: 25 })
  const [sortModel, setSortModel] = useState<GridSortModel>(defaultSort)
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  useEffect(() => {
    const t = setTimeout(() => {
      setSearch(searchInput.trim())
      setPagination((p) => ({ ...p, page: 0 }))
    }, 350)
    return () => clearTimeout(t)
  }, [searchInput])

  const filtersKey = JSON.stringify(filters ?? {})
  useEffect(() => setPagination((p) => ({ ...p, page: 0 })), [filtersKey])

  const sort = sortModel.map((s) => (s.sort === 'desc' ? '-' : '') + s.field).join(',')

  const query = useQuery({
    queryKey: [...queryKey, 'list', { page: pagination.page, pageSize: pagination.pageSize, sort, search, filtersKey }],
    queryFn: () =>
      getPage<T>(endpoint, listParams({ page: pagination.page + 1, perPage: pagination.pageSize, sort, search: searchAs ? undefined : search, filters: searchAs ? { ...filters, [searchAs]: search } : filters })),
    placeholderData: keepPreviousData,
  })

  // The server may sort by a field that is not a visible column; only give the
  // grid entries it knows, or it rewrites the model during render.
  const gridSort = useMemo(() => sortModel.filter((s) => columns.some((c) => c.field === s.field)), [sortModel, columns])

  const cols = useMemo(
    () => columns.map((c) => ({ ...c, sortable: c.sortable ?? sortFields.includes(c.field) })),
    [columns, sortFields],
  )

  return (
    <Paper sx={(t) => ({ minWidth: 0, overflow: 'hidden', borderRadius: 5, border: `1px solid ${t.palette.divider}`, boxShadow: '0 6px 24px rgba(10, 30, 90, 0.06)' })}>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ p: 1.5, alignItems: { md: 'center' } }}>
        <TextField
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={searchPlaceholder}
          sx={{ maxWidth: { md: 320 } }}
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <SearchIcon fontSize="small" />
                </InputAdornment>
              ),
            },
          }}
        />
        <Box sx={{ flex: 1, display: 'flex', gap: 1.5, flexWrap: 'wrap', alignItems: 'center' }}>{toolbar}</Box>
      </Stack>
      {query.isError && (
        <Box sx={{ px: 2, pb: 1, color: 'error.main', fontSize: 14 }}>{errorMessage(query.error)}</Box>
      )}
      <DataGrid<T>
        rows={query.data?.data ?? []}
        columns={cols}
        getRowId={getRowId}
        rowCount={query.data?.meta.total ?? 0}
        loading={query.isFetching}
        paginationMode="server"
        sortingMode="server"
        filterMode="server"
        paginationModel={pagination}
        onPaginationModelChange={setPagination}
        sortModel={gridSort}
        onSortModelChange={setSortModel}
        pageSizeOptions={[10, 25, 50, 100]}
        disableRowSelectionOnClick
        disableColumnFilter
        onRowClick={onRowClick ? (p) => onRowClick(p.row) : undefined}
        autoHeight
        sx={{
          border: 0,
          borderTop: 1,
          borderColor: 'divider',
          borderRadius: 0,
          '& .MuiDataGrid-row': { cursor: onRowClick ? 'pointer' : 'default' },
          '& .MuiDataGrid-columnHeaders': { bgcolor: 'action.hover' },
          '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.04em', color: 'text.secondary' },
          '& .MuiDataGrid-cell': { display: 'flex', alignItems: 'center' },
        }}
      />
    </Paper>
  )
}
