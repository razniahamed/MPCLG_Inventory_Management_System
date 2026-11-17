import type { FieldDef } from '../../components/FormDialog'

const labelOf = (r: Record<string, unknown>) => `${r.code} — ${(r.name_en ?? r.name) as string}`

/**
 * Officer form. Designation / department / service only on create: later
 * changes go through a new assignment so history is kept.
 */
export function officerFields(creating: boolean): FieldDef[] {
  return [
    { name: 'officer_no', label: 'Officer number', required: true, width: 6, helper: 'Ministry staff / employee number.' },
    { name: 'joined_on', label: 'Joined on', type: 'date', width: 6 },
    { name: 'full_name', label: 'Full name', required: true },
    { name: 'name_with_initials', label: 'Name with initials', width: 6 },
    { name: 'work_phone', label: 'Work phone', width: 6 },
    { name: 'work_email', label: 'Work email', type: 'email' },
    ...(creating
      ? ([
          { name: 'designation_id', label: 'Designation', required: true, lookup: { endpoint: '/designations', label: labelOf, sort: 'name' } },
          { name: 'department_id', label: 'Department', required: true, lookup: { endpoint: '/departments', label: labelOf, sort: 'name_en' } },
          { name: 'public_service_id', label: 'Public service', width: 6, lookup: { endpoint: '/public-services', label: labelOf, sort: 'code' } },
          { name: 'service_grade_id', label: 'Grade', width: 6, lookup: { endpoint: '/service-grades', label: (r) => `${(r.public_service as { code?: string } | undefined)?.code ?? ''} ${r.code}`, sort: 'rank' } },
          { name: 'authority_ref', label: 'Appointment letter ref.' },
        ] satisfies FieldDef[])
      : ([
          {
            name: 'employment_status',
            label: 'Employment status',
            type: 'select',
            required: true,
            width: 6,
            options: ['active', 'transferred', 'retired', 'resigned', 'suspended'].map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) })),
            helper: 'Leaving closes the current assignment.',
          },
          { name: 'left_on', label: 'Left on', type: 'date', width: 6 },
        ] satisfies FieldDef[])),
    { name: 'reason', label: 'Reason (optional)' },
  ]
}
