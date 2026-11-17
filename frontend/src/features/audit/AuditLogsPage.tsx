import { PageHeader } from '../../components/PageHeader'
import { AuditTable } from './AuditTable'

export function AuditLogsPage() {
  return (
    <>
      <PageHeader title="Audit trail" subtitle="Every change, who made it, when and why. Entries can never be edited or deleted." />
      <AuditTable showFilters />
    </>
  )
}
