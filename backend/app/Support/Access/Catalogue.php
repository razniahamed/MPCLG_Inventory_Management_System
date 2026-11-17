<?php

namespace App\Support\Access;

/**
 * Seed definitions for modules, permissions and roles (Sections 5.3 and 5.5).
 * Extend by adding rows here (and seeding), never by hard-coding checks.
 */
final class Catalogue
{
    /** code => [name, sort_order] */
    public const MODULES = [
        'access' => ['Access Control', 10],
        'organisation' => ['Organisation', 20],
        'catalogue' => ['Item Catalogue', 30],
        'receiving' => ['Receiving', 40],
        'requests' => ['Requests & Issues', 50],
        'stock' => ['Stock', 60],
        'a4_paper' => ['A4 Paper Control', 70],
        'assets' => ['Assets', 80],
        'maintenance' => ['Maintenance', 90],
        'reports' => ['Reports', 100],
    ];

    /** module => permission codes */
    public const PERMISSIONS = [
        'access' => [
            'user.view', 'user.create', 'user.update', 'user.deactivate', 'user.reactivate',
            'user.reset_password', 'user.revoke_tokens', 'role.view', 'role.manage',
            'permission.view', 'permission.assign_role', 'permission.override_user',
            'permission.toggle_global', 'module.toggle', 'delegation.manage', 'audit.view',
            'settings.manage',
        ],
        'organisation' => [
            'organisation.view', 'division.manage', 'department.view', 'department.manage',
            'site.manage', 'store.manage', 'location.manage', 'officer.view', 'officer.manage',
            'designation.manage',
        ],
        'catalogue' => [
            'item.view', 'item.manage', 'category.manage', 'unit.manage', 'supplier.view', 'supplier.manage',
        ],
        'receiving' => [
            'po.view', 'po.manage', 'grn.view', 'grn.create', 'grn.inspect', 'grn.approve_post',
            'grn.reverse', 'supplier_return.manage',
        ],
        'requests' => [
            'request.view_own', 'request.view_department', 'request.view_all', 'request.create',
            'request.submit', 'request.certify', 'request.approve', 'request.reject', 'request.return',
            'request.cancel', 'issue.create', 'issue.post', 'issue.reverse', 'issue.acknowledge',
            'return.create', 'return.inspect', 'reservation.manage',
        ],
        'stock' => [
            'stock.view', 'ledger.view', 'count.create', 'count.record', 'count.approve',
            'adjustment.create', 'adjustment.approve',
        ],
        'a4_paper' => [
            'machine.manage', 'meter.record', 'meter.verify', 'paper_statement.manage',
            'shared_usage.review', 'a4.exception_approve',
        ],
        'assets' => [
            'asset.view', 'asset.create', 'asset.update', 'asset.print_qr', 'transfer.create',
            'transfer.approve', 'transfer.dispatch', 'transfer.receive', 'loan.manage',
            'verification.perform', 'valuation.manage', 'valuation.approve', 'disposal.manage',
            'disposal.approve',
        ],
        'maintenance' => [
            'warranty.manage', 'repair.create', 'repair.approve', 'repair.dispatch', 'repair.update',
            'repair.close', 'service.manage',
        ],
        'reports' => [
            'dashboard.view', 'report.stock', 'report.requests', 'report.a4', 'report.assets',
            'report.audit', 'export.pdf', 'export.spreadsheet', 'print.forms',
        ],
    ];

    /**
     * Financial / approval permissions (is_sensitive = 1). These are flagged for
     * audit review, can be delegated, and are never given to system_admin or
     * internal_auditor (Section 5.4 rule 6).
     */
    public const SENSITIVE = [
        'request.certify', 'request.approve', 'request.reject', 'request.return',
        'grn.approve_post', 'grn.reverse', 'issue.reverse',
        'count.approve', 'adjustment.approve',
        'a4.exception_approve', 'shared_usage.review',
        'transfer.approve', 'valuation.approve', 'disposal.approve',
        'repair.approve',
    ];

    /** Modules whose permissions can be limited to a division / department / store scope. */
    public const SCOPED_MODULES = ['receiving', 'requests', 'stock', 'a4_paper', 'assets', 'maintenance'];

    /** Organisation read access every signed-in officer role needs. */
    private const ORG_READ = ['organisation.view', 'department.view', 'officer.view'];

    private const REPORTS_ALL = [
        'dashboard.view', 'report.stock', 'report.requests', 'report.a4', 'report.assets',
        'export.pdf', 'export.spreadsheet', 'print.forms',
    ];

    /**
     * role code => [name, description, permission codes | '*module' for a whole module]
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    public static function roles(): array
    {
        return [
            'system_admin' => [
                'System Administrator',
                'IT Division. Access management, organisation master data and settings. No approvals.',
                ['*access', '*organisation'],
            ],
            'secretary_view' => [
                'Secretary / Additional Secretary',
                'Dashboards and reports; escalated approvals within written delegation.',
                [
                    ...self::ORG_READ, ...self::REPORTS_ALL,
                    'request.view_all', 'request.approve', 'request.reject', 'request.return',
                    'stock.view', 'item.view', 'asset.view',
                    'adjustment.approve', 'transfer.approve', 'valuation.approve', 'disposal.approve',
                ],
            ],
            'cfo' => [
                'Chief Finance Officer',
                'Finance controls, escalated approvals, valuation and disposal approval.',
                [
                    ...self::ORG_READ, ...self::REPORTS_ALL,
                    'request.view_all', 'request.approve', 'request.reject', 'request.return',
                    'po.view', 'grn.view', 'grn.approve_post', 'item.view', 'supplier.view',
                    'stock.view', 'ledger.view', 'count.approve', 'adjustment.approve',
                    'asset.view', 'transfer.approve', 'valuation.approve', 'disposal.approve',
                    'repair.approve', 'a4.exception_approve',
                ],
            ],
            'chief_accountant' => [
                'Chief Accountant',
                'Escalated approvals, GRN approval, count and adjustment approval.',
                [
                    ...self::ORG_READ, ...self::REPORTS_ALL,
                    'request.view_all', 'request.approve', 'request.reject', 'request.return',
                    'po.view', 'grn.view', 'grn.approve_post', 'grn.reverse', 'issue.reverse',
                    'item.view', 'supplier.view', 'stock.view', 'ledger.view',
                    'count.approve', 'adjustment.approve', 'asset.view', 'repair.approve',
                    'a4.exception_approve',
                ],
            ],
            'accountant_supply' => [
                'Accountant (Supply)',
                'Routine approval of requests, GRNs, exceptions and A4 exceptions; catalogue upkeep.',
                [
                    ...self::ORG_READ, ...self::REPORTS_ALL,
                    'request.view_all', 'request.approve', 'request.reject', 'request.return',
                    'reservation.manage', 'po.view', 'po.manage', 'grn.view', 'grn.approve_post',
                    'supplier_return.manage', 'item.view', 'item.manage', 'category.manage',
                    'unit.manage', 'supplier.view', 'supplier.manage', 'stock.view', 'ledger.view',
                    'a4.exception_approve', 'shared_usage.review', 'meter.verify',
                    'asset.view', 'transfer.approve',
                ],
            ],
            'storekeeper' => [
                'Storekeeper',
                'GRN preparation, issue posting, stock view, count recording, asset creation. Usually store-scoped.',
                [
                    ...self::ORG_READ, 'dashboard.view', 'report.stock', 'print.forms',
                    'request.view_all', 'reservation.manage', 'issue.create', 'issue.post',
                    'return.create', 'return.inspect', 'po.view', 'grn.view', 'grn.create',
                    'supplier_return.manage', 'item.view', 'supplier.view', 'stock.view', 'ledger.view',
                    'count.create', 'count.record', 'adjustment.create',
                    'asset.view', 'asset.create', 'asset.update', 'asset.print_qr', 'transfer.dispatch',
                ],
            ],
            'receiving_officer' => [
                'Receiving / Inspection Officer',
                'Inspects goods received and receives asset transfers.',
                [
                    ...self::ORG_READ, 'dashboard.view',
                    'po.view', 'grn.view', 'grn.inspect', 'item.view', 'stock.view',
                    'asset.view', 'transfer.receive',
                ],
            ],
            'chief_clerk' => [
                'Chief Clerk / Department Coordinator',
                'Creates and submits department requests, A4 readings and balances, acknowledges receipt. Department-scoped.',
                [
                    ...self::ORG_READ, 'dashboard.view', 'print.forms',
                    'request.view_department', 'request.create', 'request.submit', 'request.cancel',
                    'issue.acknowledge', 'return.create', 'item.view', 'stock.view',
                    'meter.record', 'paper_statement.manage',
                    'asset.view', 'transfer.create', 'transfer.receive', 'repair.create',
                ],
            ],
            'department_head' => [
                'Department Head',
                'Director / SAS / Additional Secretary: certifies department requests.',
                [
                    ...self::ORG_READ, 'dashboard.view', 'report.requests',
                    'request.view_department', 'request.certify', 'request.return',
                    'item.view', 'asset.view', 'transfer.create',
                ],
            ],
            'repair_coordinator' => [
                'Repair Coordinator',
                'IT / repair coordination: repair cases, warranty and servicing.',
                [
                    ...self::ORG_READ, 'dashboard.view',
                    'asset.view', 'asset.update', 'machine.manage', 'supplier.view',
                    'warranty.manage', 'repair.create', 'repair.dispatch', 'repair.update',
                    'repair.close', 'service.manage',
                ],
            ],
            'internal_auditor' => [
                'Internal Auditor',
                'Read-only everywhere, audit trail and exports. No approvals.',
                [
                    ...self::ORG_READ, ...self::REPORTS_ALL, 'report.audit',
                    'user.view', 'role.view', 'permission.view', 'audit.view',
                    'item.view', 'supplier.view', 'po.view', 'grn.view', 'request.view_all',
                    'stock.view', 'ledger.view', 'asset.view',
                ],
            ],
            'staff' => [
                'Staff',
                'DO / MSO / KKS and others: own requests and own custody assets.',
                ['dashboard.view', 'request.view_own', 'asset.view'],
            ],
        ];
    }

    /** @return array<int, string> all permission codes */
    public static function allCodes(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /** Expand '*module' entries into permission codes. */
    public static function expand(array $entries): array
    {
        $codes = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, '*')) {
                array_push($codes, ...self::PERMISSIONS[substr($entry, 1)]);
            } else {
                $codes[] = $entry;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function moduleOf(string $code): string
    {
        foreach (self::PERMISSIONS as $module => $codes) {
            if (in_array($code, $codes, true)) {
                return $module;
            }
        }

        throw new \InvalidArgumentException("Unknown permission code [{$code}].");
    }

    public static function displayName(string $code): string
    {
        [$resource, $action] = explode('.', $code, 2);

        return ucwords(str_replace('_', ' ', $resource)).': '.ucwords(str_replace('_', ' ', $action));
    }
}
