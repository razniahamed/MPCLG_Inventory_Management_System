<?php

namespace Database\Seeders;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Designation;
use App\Models\GoodsRequest;
use App\Models\Grn;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\Location;
use App\Models\MeterObservation;
use App\Models\Officer;
use App\Models\OfficerAssignment;
use App\Models\Permission;
use App\Models\PublicService;
use App\Models\Role;
use App\Models\ServiceGrade;
use App\Models\ServiceSchedule;
use App\Models\Site;
use App\Models\Store;
use App\Models\StoreOfficer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
use App\Models\Valuation;
use App\Services\Assets\AssetLifecycleService;
use App\Services\Assets\AssetService;
use App\Services\Assets\CustodyService;
use App\Services\Assets\RepairService;
use App\Services\Assets\TransferService;
use App\Services\Catalogue\CatalogueService;
use App\Services\Paper\MeterService;
use App\Services\Paper\PaperService;
use App\Services\Procurement\GrnService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Requests\IssueService;
use App\Services\Requests\RequestService;
use App\Services\Stock\AdjustmentService;
use App\Services\Stock\CountService;
use App\Services\System\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Realistic demo data for trying the system end to end.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Every transaction goes through the real services as a named officer, so
 * ledger entries, approvals, reservations, custody history and the audit
 * trail are exactly what production would produce. NOT for production use.
 *
 * All demo accounts use the password in DEMO_PASSWORD (default Demo@12345).
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<string, Store> */
    private array $stores = [];

    private string $password;

    public function run(): void
    {
        if (Item::where('item_code', 'PAP-A4-80')->exists()) {
            $this->command?->warn('Demo data already present — skipping.');

            return;
        }

        $this->password = (string) env('DEMO_PASSWORD', 'Demo@12345');

        $this->organisation();
        $this->people();
        $this->catalogue();
        $this->receiving();
        $this->assets();
        $this->requests();
        $this->paper();
        $this->maintenance();
        $this->counts();

        Auth::logout();
        $this->command?->info('Demo data loaded. Sign in with any demo account below (password: '.$this->password.').');
        $this->command?->table(['Username', 'Role', 'Officer'], collect($this->users)->map(fn (User $u, $k) => [
            $u->username, UserRole::where('user_id', $u->id)->with('role')->get()->pluck('role.name')->join(', '), $u->officer?->full_name,
        ])->values()->all());
    }

    // ---------------------------------------------------------------------------

    private function organisation(): void
    {
        $hq = Site::where('code', 'HQ')->firstOrFail();
        $bungalow = Site::firstOrCreate(['code' => 'KDY-BNG'], ['name' => 'Circuit Bungalow, Kandy', 'site_type' => 'bungalow', 'address' => 'No. 12, Lake Road, Kandy']);
        $hq->update(['address' => 'No. 330, Union Place, Colombo 02', 'phone' => '011 2 300 000']);

        $this->stores['main'] = Store::firstOrCreate(['code' => 'ST-MAIN'], ['name' => 'Main Stores', 'site_id' => $hq->id, 'store_type' => 'general']);
        $this->stores['stat'] = Store::firstOrCreate(['code' => 'ST-STAT'], ['name' => 'Stationery Store', 'site_id' => $hq->id, 'store_type' => 'stationery']);
        $this->stores['it'] = Store::firstOrCreate(['code' => 'ST-IT'], ['name' => 'IT Store', 'site_id' => $hq->id, 'store_type' => 'it']);

        $rooms = [
            ['G-STR', 'Ground floor store room', 'room', 'main'],
            ['G-STR-S1', 'Shelf 1 – stationery', 'shelf', 'stat'],
            ['2F-201', 'IT Division office (2F-201)', 'room', null],
            ['2F-205', 'Server room (2F-205)', 'room', null],
            ['3F-301', 'Legal Division (3F-301)', 'room', null],
            ['3F-310', 'Accounts – Supply (3F-310)', 'room', null],
            ['4F-401', 'Secretary\'s office (4F-401)', 'room', null],
        ];
        foreach ($rooms as [$code, $name, $type, $store]) {
            Location::firstOrCreate(['site_id' => $hq->id, 'code' => $code], [
                'name' => $name, 'location_type' => $type, 'store_id' => $store ? $this->stores[$store]->id : null,
            ]);
        }
        Location::firstOrCreate(['site_id' => $bungalow->id, 'code' => 'BNG-LNG'], ['name' => 'Bungalow lounge', 'location_type' => 'room']);
    }

    private function people(): void
    {
        $slas = PublicService::where('code', 'SLAS')->first();
        $grade = fn (string $code) => ServiceGrade::where(['public_service_id' => $slas->id, 'code' => $code])->value('id');

        // username => [full name, initials, designation, department, roles (role => [scope type, scope key])]
        $people = [
            'stores.keeper' => ['Nimal Perera', 'N. Perera', 'MSO', 'ACC_SUP', ['storekeeper' => ['store', 'main'], 'storekeeper#stat' => ['store', 'stat'], 'storekeeper#it' => ['store', 'it']]],
            'receiving.officer' => ['Sunil Fernando', 'S. Fernando', 'DO', 'ACC_SUP', ['receiving_officer' => null]],
            'accountant.supply' => ['Dilani Jayawardena', 'D. Jayawardena', 'ACC_SUP', 'ACC_SUP', ['accountant_supply' => null]],
            'chief.accountant' => ['Ruwan Silva', 'R. Silva', 'CA', 'ACC_PAY', ['chief_accountant' => null]],
            'cfo' => ['Anoma Wickramasinghe', 'A. Wickramasinghe', 'CFO', 'ACC_MON', ['cfo' => null]],
            'it.clerk' => ['Kasun Bandara', 'K. Bandara', 'MSO', 'IT', ['chief_clerk' => ['department', 'IT']]],
            'it.director' => ['Chamari Rathnayake', 'C. Rathnayake', 'DIR', 'IT', ['department_head' => ['department', 'IT']]],
            'legal.clerk' => ['Tharindu Herath', 'T. Herath', 'MSO', 'LEGAL', ['chief_clerk' => ['department', 'LEGAL']]],
            'legal.head' => ['Priyanka Senanayake', 'P. Senanayake', 'CLO', 'LEGAL', ['department_head' => ['department', 'LEGAL']]],
            'repair.coord' => ['Pradeep Kumara', 'P. Kumara', 'ICTO', 'IT', ['repair_coordinator' => null]],
            'auditor' => ['Malini Gunasekara', 'M. Gunasekara', 'IA', 'IA', ['internal_auditor' => null]],
            'staff.user' => ['Saman Kumara', 'S. Kumara', 'DO', 'IT', ['staff' => null]],
        ];

        $n = 100;
        foreach ($people as $username => [$name, $initials, $designation, $department, $roles]) {
            $deptId = Department::where('code', $department)->value('id');
            $designationId = Designation::where('code', $designation)->value('id');

            $officer = Officer::create([
                'officer_no' => 'EMP-'.str_pad((string) ++$n, 5, '0', STR_PAD_LEFT),
                'full_name' => $name,
                'name_with_initials' => $initials,
                'designation_id' => $designationId,
                'department_id' => $deptId,
                'public_service_id' => in_array($designation, ['DIR', 'CFO', 'CA', 'CLO'], true) ? $slas->id : null,
                'service_grade_id' => in_array($designation, ['DIR', 'CFO', 'CA', 'CLO'], true) ? $grade('I') : null,
                'work_email' => $username.'@mpclg.gov.lk',
                'work_phone' => '011 2 30'.random_int(1000, 9999),
                'employment_status' => 'active',
                'joined_on' => now()->subYears(random_int(2, 12))->toDateString(),
            ]);
            OfficerAssignment::create([
                'officer_id' => $officer->id, 'designation_id' => $designationId, 'department_id' => $deptId,
                'from_on' => $officer->joined_on, 'authority_ref' => 'APPT/'.now()->year.'/'.$n,
            ]);

            $user = User::create([
                'username' => $username,
                'email' => $username.'@mpclg.gov.lk',
                'officer_id' => $officer->id,
                'password' => $this->password,
                'status' => UserStatus::Active,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);

            foreach ($roles as $roleKey => $scope) {
                $roleCode = Str::before($roleKey, '#');
                $scopeId = null;
                if ($scope) {
                    $scopeId = $scope[0] === 'store' ? $this->stores[$scope[1]]->id : Department::where('code', $scope[1])->value('id');
                }
                UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => Role::where('code', $roleCode)->value('id'),
                    'scope_type' => $scope ? ScopeType::from($scope[0]) : ScopeType::Global,
                    'scope_id' => $scopeId,
                    'valid_from' => now()->subMonth(),
                    'granted_at' => now()->subMonth(),
                ]);
            }

            $this->users[$username] = $user->load('officer');
        }

        // Department heads / coordinators and store responsibility.
        Department::where('code', 'IT')->update(['head_officer_id' => $this->officer('it.director'), 'coordinator_officer_id' => $this->officer('it.clerk')]);
        Department::where('code', 'LEGAL')->update(['head_officer_id' => $this->officer('legal.head'), 'coordinator_officer_id' => $this->officer('legal.clerk')]);
        foreach ($this->stores as $store) {
            $store->update(['responsible_officer_id' => $this->officer('stores.keeper')]);
            StoreOfficer::firstOrCreate(['store_id' => $store->id, 'officer_id' => $this->officer('stores.keeper'), 'role' => 'storekeeper'], ['valid_from' => now()->subYear()->toDateString()]);
        }

        // Verification, valuation and disposal are given to the auditor's
        // colleague in Supply for the demo (they are not in any seeded role).
        $this->grant('accountant.supply', ['verification.perform', 'valuation.manage', 'disposal.manage', 'loan.manage', 'machine.manage']);
    }

    private function catalogue(): void
    {
        $unit = fn (string $c) => Unit::where('code', $c)->value('id');
        $cat = fn (string $c) => ItemCategory::where('code', $c)->value('id');

        // code => [name, category, tracking, base, purchase, issue, reorder, extra]
        $items = [
            'PAP-A4-80' => ['A4 copy paper 80gsm', 'PAPER', 'quantity', 'SHT', 'REAM', 'REAM', 5000, ['is_paper' => true, 'brand' => 'Double A']],
            'PAP-A4-70' => ['A4 copy paper 70gsm', 'PAPER', 'quantity', 'SHT', 'REAM', 'REAM', 2500, ['is_paper' => true, 'brand' => 'Paper One']],
            'PAP-LGL-80' => ['Legal size paper 80gsm', 'PAPER', 'quantity', 'SHT', 'REAM', 'REAM', 1000, ['is_paper' => true]],
            'STA-PEN-BLU' => ['Ballpoint pen, blue', 'STAT', 'quantity', 'PCS', 'BOX', 'PCS', 100, ['brand' => 'Atlas']],
            'STA-PEN-RED' => ['Ballpoint pen, red', 'STAT', 'quantity', 'PCS', 'BOX', 'PCS', 50, ['brand' => 'Atlas']],
            'STA-STAPLER' => ['Stapler, medium', 'STAT', 'quantity', 'PCS', 'PCS', 'PCS', 10, ['brand' => 'Kangaro']],
            'STA-PINS' => ['Staple pins No. 10', 'STAT', 'quantity', 'BOX', 'BOX', 'BOX', 20, []],
            'STA-FILE' => ['Box file, A4', 'STAT', 'quantity', 'PCS', 'PCS', 'PCS', 40, []],
            'STA-NOTE' => ['Notebook, 200 pages', 'STAT', 'quantity', 'PCS', 'PCS', 'PCS', 30, ['brand' => 'Atlas']],
            'TON-HP85A' => ['Toner cartridge HP 85A', 'TONER', 'quantity', 'PCS', 'PCS', 'PCS', 4, ['brand' => 'HP']],
            'TON-CNC-EXV' => ['Toner Canon C-EXV 42', 'TONER', 'quantity', 'PCS', 'PCS', 'PCS', 2, ['brand' => 'Canon']],
            'CLN-HAND' => ['Hand sanitiser 500ml', 'CLEAN', 'quantity', 'BTL', 'BOX', 'BTL', 12, []],
            'IT-LAPTOP' => ['Laptop computer, 14"', 'ITEQ', 'asset', 'PCS', 'PCS', 'PCS', null, ['brand' => 'Lenovo']],
            'IT-DESKTOP' => ['Desktop computer', 'ITEQ', 'asset', 'PCS', 'PCS', 'PCS', null, ['brand' => 'Dell']],
            'IT-PRINTER' => ['Laser printer, A4 mono', 'OFFEQ', 'asset', 'PCS', 'PCS', 'PCS', null, ['brand' => 'HP']],
            'FUR-CHAIR' => ['Office chair, ergonomic', 'FURN', 'asset', 'PCS', 'PCS', 'PCS', null, ['brand' => 'Damro']],
            'FUR-CABINET' => ['Filing cabinet, 4-drawer', 'FURN', 'asset', 'PCS', 'PCS', 'PCS', null, ['brand' => 'Damro']],
        ];

        $this->as('accountant.supply', function () use ($items, $unit, $cat) {
            foreach ($items as $code => [$name, $category, $tracking, $base, $purchase, $issue, $reorder, $extra]) {
                $this->items[$code] = Item::create($extra + [
                    'item_code' => $code, 'name_en' => $name, 'category_id' => $cat($category), 'tracking_method' => $tracking,
                    'base_unit_id' => $unit($base), 'purchase_unit_id' => $unit($purchase), 'issue_unit_id' => $unit($issue),
                    'reorder_level' => $reorder,
                ]);
            }

            $conversions = [
                ['PAP-A4-80', 'REAM', 'SHT', 500], ['PAP-A4-70', 'REAM', 'SHT', 500], ['PAP-LGL-80', 'REAM', 'SHT', 500],
                ['STA-PEN-BLU', 'BOX', 'PCS', 50], ['STA-PEN-RED', 'BOX', 'PCS', 50], ['CLN-HAND', 'BOX', 'BTL', 12],
            ];
            $catalogue = app(CatalogueService::class);
            $created = [];
            foreach ($conversions as [$item, $from, $to, $factor]) {
                $created[] = $catalogue->addConversion($this->items[$item], ['from_unit_id' => $unit($from), 'to_unit_id' => $unit($to), 'factor' => $factor]);
            }

            $suppliers = [
                ['SUP-LST', 'Lanka Stationers (Pvt) Ltd', 'goods', 'Ajith Peiris', 'No. 45, Main Street, Colombo 11', '011 2 431 222', ['STAT', 'PAPER']],
                ['SUP-ABN', 'Abans PLC – Corporate Sales', 'both', 'Rohan Dias', 'No. 498, Galle Road, Colombo 03', '011 2 574 574', ['ITEQ', 'OFFEQ']],
                ['SUP-DMR', 'Damro Furniture', 'goods', 'Kamal Jayasinghe', 'Nugegoda', '011 2 811 811', ['FURN']],
                ['SUP-CAN', 'Canon Lanka (Authorised Service)', 'both', 'Service Desk', 'No. 21, Duplication Road, Colombo 04', '011 7 545 545', ['TONER', 'OFFEQ']],
                ['SUP-CLN', 'CleanCare Distributors', 'goods', 'Nadeesha', 'Battaramulla', '011 2 888 120', ['CLEAN']],
            ];
            foreach ($suppliers as [$code, $name, $type, $contact, $address, $phone, $cats]) {
                $catalogue->saveSupplier(null, [
                    'code' => $code, 'name' => $name, 'supplier_type' => $type, 'contact_person' => $contact,
                    'address' => $address, 'phone' => $phone, 'email' => Str::slug($code).'@example.lk',
                    'category_ids' => ItemCategory::whereIn('code', $cats)->pluck('id')->all(),
                ]);
            }

            return $created;
        });

        // Conversions are approved by a second officer.
        $this->as('chief.accountant', function () {
            foreach (ItemUnitConversion::whereNull('approved_at')->get() as $conversion) {
                app(CatalogueService::class)->approveConversion($conversion, 'Verified against supplier packaging');
            }
        });
    }

    private function receiving(): void
    {
        $unit = fn (string $c) => Unit::where('code', $c)->value('id');
        $supplier = fn (string $c) => Supplier::where('code', $c)->value('id');

        // Opening balances from the old register (Phase 9 style), posted through a GRN.
        $opening = $this->as('stores.keeper', fn () => app(GrnService::class)->create([
            'receipt_authority_type' => 'opening_balance',
            'receipt_authority_ref' => 'Opening count '.now()->subMonths(2)->format('M Y').' – register book 14',
            'store_id' => $this->stores['stat']->id,
            'receipt_on' => now()->subMonths(2)->toDateString(),
            'lines' => [
                ['item_id' => $this->items['PAP-A4-80']->id, 'unit_id' => $unit('REAM'), 'delivered_qty' => 40],
                ['item_id' => $this->items['PAP-A4-70']->id, 'unit_id' => $unit('REAM'), 'delivered_qty' => 25],
                ['item_id' => $this->items['STA-PEN-BLU']->id, 'unit_id' => $unit('BOX'), 'delivered_qty' => 6],
                ['item_id' => $this->items['STA-PEN-RED']->id, 'unit_id' => $unit('BOX'), 'delivered_qty' => 2],
                ['item_id' => $this->items['STA-STAPLER']->id, 'unit_id' => $unit('PCS'), 'delivered_qty' => 8],
                ['item_id' => $this->items['STA-PINS']->id, 'unit_id' => $unit('BOX'), 'delivered_qty' => 35],
                ['item_id' => $this->items['STA-FILE']->id, 'unit_id' => $unit('PCS'), 'delivered_qty' => 60],
                ['item_id' => $this->items['TON-HP85A']->id, 'unit_id' => $unit('PCS'), 'delivered_qty' => 3],
            ],
        ]));
        $this->postGrn($opening, null);

        // Stationery purchase order, fully received.
        $po1 = $this->as('accountant.supply', fn () => app(PurchaseOrderService::class)->create([
            'po_number' => 'PO/SUP/2026/0418', 'po_date' => now()->subDays(40)->toDateString(), 'supplier_id' => $supplier('SUP-LST'),
            'approval_doc_ref' => 'DPC/2026/22', 'expected_delivery_on' => now()->subDays(30)->toDateString(),
            'lines' => [
                ['item_id' => $this->items['PAP-A4-80']->id, 'unit_id' => $unit('REAM'), 'ordered_qty' => 100, 'unit_price' => 1850],
                ['item_id' => $this->items['STA-NOTE']->id, 'unit_id' => $unit('PCS'), 'ordered_qty' => 50, 'unit_price' => 320],
                ['item_id' => $this->items['CLN-HAND']->id, 'unit_id' => $unit('BOX'), 'ordered_qty' => 4, 'unit_price' => 7200],
            ],
        ]));
        $grn1 = $this->as('stores.keeper', fn () => app(GrnService::class)->create([
            'receipt_authority_type' => 'po', 'purchase_order_id' => $po1->id, 'store_id' => $this->stores['stat']->id,
            'receipt_on' => now()->subDays(28)->toDateString(), 'invoice_no' => 'LST/INV/88341', 'invoice_on' => now()->subDays(28)->toDateString(),
            'delivery_note_no' => 'DN-55120',
            'lines' => $po1->lines->map(fn ($l) => ['item_id' => $l->item_id, 'unit_id' => $l->unit_id, 'po_line_id' => $l->id, 'delivered_qty' => $l->ordered_qty, 'unit_price' => $l->unit_price])->all(),
        ]));
        $this->postGrn($grn1, fn ($line) => ['accepted_qty' => $line->delivered_qty, 'rejected_qty' => 0]);

        // IT purchase order, partly received with a rejection.
        $po2 = $this->as('accountant.supply', fn () => app(PurchaseOrderService::class)->create([
            'po_number' => 'PO/IT/2026/0107', 'po_date' => now()->subDays(25)->toDateString(), 'supplier_id' => $supplier('SUP-ABN'),
            'approval_doc_ref' => 'TEC/2026/IT-04', 'expected_delivery_on' => now()->subDays(10)->toDateString(),
            'lines' => [
                ['item_id' => $this->items['IT-LAPTOP']->id, 'unit_id' => $unit('PCS'), 'ordered_qty' => 10, 'unit_price' => 285000],
                ['item_id' => $this->items['IT-PRINTER']->id, 'unit_id' => $unit('PCS'), 'ordered_qty' => 3, 'unit_price' => 78500],
            ],
        ]));
        $grn2 = $this->as('stores.keeper', fn () => app(GrnService::class)->create([
            'receipt_authority_type' => 'po', 'purchase_order_id' => $po2->id, 'store_id' => $this->stores['it']->id,
            'receipt_on' => now()->subDays(15)->toDateString(), 'invoice_no' => 'ABN/CS/2026/7714', 'delivery_note_no' => 'ABN-DN-3391',
            'lines' => [
                ['item_id' => $this->items['IT-LAPTOP']->id, 'unit_id' => $unit('PCS'), 'po_line_id' => $po2->lines[0]->id, 'delivered_qty' => 7, 'unit_price' => 285000],
                ['item_id' => $this->items['IT-PRINTER']->id, 'unit_id' => $unit('PCS'), 'po_line_id' => $po2->lines[1]->id, 'delivered_qty' => 3, 'unit_price' => 78500],
            ],
        ]));
        $this->postGrn($grn2, fn ($line) => $line->line_no === 1
            ? ['accepted_qty' => 6, 'rejected_qty' => 1, 'rejection_reason' => 'Cracked screen on delivery', 'condition_note' => 'One unit returned to supplier']
            : ['accepted_qty' => 3, 'rejected_qty' => 0]);

        // A toner delivery waiting for inspection (shows on the dashboard queue).
        $grn3 = $this->as('stores.keeper', fn () => app(GrnService::class)->create([
            'receipt_authority_type' => 'donation', 'receipt_authority_ref' => 'Donation letter UNDP/LK/2026/31', 'supplier_id' => $supplier('SUP-CAN'),
            'store_id' => $this->stores['main']->id, 'receipt_on' => now()->subDay()->toDateString(), 'delivery_note_no' => 'CAN-DN-1201',
            'lines' => [['item_id' => $this->items['TON-CNC-EXV']->id, 'unit_id' => $unit('PCS'), 'delivered_qty' => 6]],
        ]));
        $this->as('stores.keeper', fn () => app(GrnService::class)->submit($grn3));
    }

    private function assets(): void
    {
        $grn2Lines = Grn::where('invoice_no', 'ABN/CS/2026/7714')->firstOrFail()->lines;

        $this->as('stores.keeper', function () use ($grn2Lines) {
            $service = app(AssetService::class);
            foreach (range(1, 6) as $i) {
                $service->create(['item_id' => $this->items['IT-LAPTOP']->id, 'grn_line_id' => $grn2Lines[0]->id, 'serial_no' => 'PF4K'.str_pad((string) (3300 + $i), 4, '0', STR_PAD_LEFT), 'model' => 'ThinkPad T14 Gen 5', 'condition' => 'new']);
            }
            foreach (range(1, 3) as $i) {
                $service->create(['item_id' => $this->items['IT-PRINTER']->id, 'grn_line_id' => $grn2Lines[1]->id, 'serial_no' => 'VNB3R'.(5000 + $i), 'model' => 'LaserJet Pro M404dn', 'condition' => 'new']);
            }

            // Existing furniture and computers taken on from the old register (dates unknown).
            $it = Department::where('code', 'IT')->value('id');
            $legal = Department::where('code', 'LEGAL')->value('id');
            $room = fn (string $code) => Location::where('code', $code)->value('id');
            foreach ([['FUR-CHAIR', $it, '2F-201', 'good'], ['FUR-CHAIR', $it, '2F-201', 'fair'], ['FUR-CHAIR', $legal, '3F-301', 'good'], ['FUR-CABINET', $legal, '3F-301', 'good'], ['IT-DESKTOP', $legal, '3F-301', 'fair'], ['IT-DESKTOP', $it, '2F-205', 'poor']] as $i => [$item, $dept, $loc, $cond]) {
                $service->create([
                    'item_id' => $this->items[$item]->id, 'source_type' => 'existing_in_house', 'responsible_department_id' => $dept,
                    'location_id' => $room($loc), 'condition' => $cond, 'legacy_code' => 'OLD/'.(400 + $i), 'book_no' => 'INV-REG-3', 'page_no' => (string) (20 + $i),
                ]);
            }
        });
    }

    private function requests(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $legal = Department::where('code', 'LEGAL')->firstOrFail();
        $ream = Unit::where('code', 'REAM')->value('id');

        // 1) IT: stationery approved with a reduction, issued in two parts, confirmed.
        $r1 = $this->as('it.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $it->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Monthly stationery – '.now()->subMonth()->format('F'),
            'required_on' => now()->subDays(18)->toDateString(), 'staffing_count' => 14,
            'lines' => [
                ['item_id' => $this->items['PAP-A4-80']->id, 'unit_id' => $ream, 'requested_qty' => 10],
                ['item_id' => $this->items['STA-PEN-BLU']->id, 'requested_qty' => 40],
                ['item_id' => $this->items['STA-NOTE']->id, 'requested_qty' => 10],
            ],
        ]));
        $this->as('it.clerk', fn () => app(RequestService::class)->submit($r1));
        $this->as('it.director', fn () => app(RequestService::class)->certify($r1, 'Needed for the e-filing roll-out'));
        $lines = $r1->currentLines()->get();
        $this->as('accountant.supply', fn () => app(RequestService::class)->approve($r1->fresh(), [
            ['id' => $lines[0]->id, 'approved_qty' => 8], ['id' => $lines[1]->id, 'approved_qty' => 30], ['id' => $lines[2]->id, 'approved_qty' => 10],
        ], 'Reduced to the monthly allowance'));
        $this->issue($r1, [[$lines[0]->id, 5], [$lines[1]->id, 30], [$lines[2]->id, 10]], 'confirmed');
        $this->issue($r1, [[$lines[0]->id, 3]], null);

        // 2) IT: laptops for new staff (assets), issued and acknowledged.
        $r2 = $this->as('it.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $it->id, 'store_id' => $this->stores['it']->id, 'purpose' => 'Laptops for three new ICT Assistants',
            'lines' => [['item_id' => $this->items['IT-LAPTOP']->id, 'requested_qty' => 3]],
        ]));
        $this->as('it.clerk', fn () => app(RequestService::class)->submit($r2));
        $this->as('it.director', fn () => app(RequestService::class)->certify($r2, null));
        $this->as('accountant.supply', fn () => app(RequestService::class)->approve($r2->fresh(), [], null));
        $laptops = Asset::where('item_id', $this->items['IT-LAPTOP']->id)->where('operational_status', 'in_store')->orderBy('id')->take(3)->get();
        $line = $r2->currentLines()->first();
        $this->issue($r2, [[$line->id, 1, $laptops[0]->id], [$line->id, 1, $laptops[1]->id], [$line->id, 1, $laptops[2]->id]], 'confirmed');
        // The department hands one laptop to a staff member (custody history records it).
        $this->as('it.clerk', fn () => app(CustodyService::class)->move($laptops[0]->fresh(), [
            'custodian_officer_id' => $this->officer('staff.user'), 'location_id' => Location::where('code', '2F-201')->value('id'),
        ], 'Handed to user'));

        // 3) Legal: approved, waiting for the Storekeeper to issue.
        $r3 = $this->as('legal.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $legal->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Case files and paper',
            'lines' => [
                ['item_id' => $this->items['STA-FILE']->id, 'requested_qty' => 20],
                ['item_id' => $this->items['PAP-A4-70']->id, 'unit_id' => $ream, 'requested_qty' => 6],
            ],
        ]));
        $this->as('legal.clerk', fn () => app(RequestService::class)->submit($r3));
        $this->as('legal.head', fn () => app(RequestService::class)->certify($r3, null));
        $this->as('accountant.supply', fn () => app(RequestService::class)->approve($r3->fresh(), [], null));

        // 4) Legal: under review (certified, waiting for Supply approval).
        $r4 = $this->as('legal.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $legal->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Staplers for the new section',
            'lines' => [['item_id' => $this->items['STA-STAPLER']->id, 'requested_qty' => 4], ['item_id' => $this->items['STA-PINS']->id, 'requested_qty' => 10]],
        ]));
        $this->as('legal.clerk', fn () => app(RequestService::class)->submit($r4));
        $this->as('legal.head', fn () => app(RequestService::class)->certify($r4, null));

        // 5) IT: submitted, waiting for the Director's certification.
        $r5 = $this->as('it.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $it->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Toner for the 2nd floor printer',
            'lines' => [['item_id' => $this->items['TON-HP85A']->id, 'requested_qty' => 2]],
        ]));
        $this->as('it.clerk', fn () => app(RequestService::class)->submit($r5));

        // 6) Legal: returned for clarification.
        $r6 = $this->as('legal.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $legal->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Red pens',
            'lines' => [['item_id' => $this->items['STA-PEN-RED']->id, 'requested_qty' => 100]],
        ]));
        $this->as('legal.clerk', fn () => app(RequestService::class)->submit($r6));
        $this->as('legal.head', fn () => app(RequestService::class)->returnForClarification($r6, 'Quantity looks high – please justify 100 red pens.'));

        // 7) A draft that has not been submitted yet.
        $this->as('it.clerk', fn () => app(RequestService::class)->create([
            'department_id' => $it->id, 'store_id' => $this->stores['stat']->id, 'purpose' => 'Box files for archive project',
            'lines' => [['item_id' => $this->items['STA-FILE']->id, 'requested_qty' => 15]],
        ]));
    }

    private function paper(): void
    {
        $it = Department::where('code', 'IT')->firstOrFail();
        $legal = Department::where('code', 'LEGAL')->firstOrFail();
        app(SettingsService::class)->update(['a4.photo_required' => false], 'Demo: photos optional');

        [$shared, $owned] = $this->as('accountant.supply', function () use ($it, $legal) {
            $printer = Asset::where('item_id', $this->items['IT-PRINTER']->id)->where('operational_status', 'in_store')->first();
            $meters = app(MeterService::class);

            return [
                $meters->createMachine([
                    'code' => 'PRN-2F-01', 'leased_reference' => 'Canon lease CL/2025/118', 'model' => 'Canon imageRUNNER 2630i',
                    'location_id' => Location::where('code', '2F-201')->value('id'), 'responsible_department_id' => $it->id, 'is_shared' => true,
                    'counter_types' => ['printed_sides', 'physical_sheets', 'a4_mono'], 'shared_department_ids' => [$it->id, $legal->id],
                ]),
                $meters->createMachine([
                    'code' => 'PRN-3F-01', 'asset_id' => $printer->id, 'model' => 'HP LaserJet Pro M404dn',
                    'location_id' => Location::where('code', '3F-301')->value('id'), 'responsible_department_id' => $legal->id,
                    'counter_types' => ['printed_sides'],
                ]),
            ];
        });

        $read = function (string $user, $machine, array $values, int $daysAgo, array $extra = []) {
            return $this->as($user, fn () => app(MeterService::class)->record($extra + [
                'machine_id' => $machine->id,
                'observed_at' => now()->subDays($daysAgo)->setTime(9, 30)->toDateTimeString(),
                'readings' => collect($values)->map(fn ($v, $type) => [
                    'counter_id' => $machine->counters->firstWhere('counter_type', $type)->id,
                    'reading_value' => $v, 'is_unknown' => $v === null,
                ])->values()->all(),
            ]));
        };

        $read('it.clerk', $shared, ['printed_sides' => 120400, 'physical_sheets' => 70210, 'a4_mono' => 118900], 60);
        $read('it.clerk', $shared, ['printed_sides' => 126950, 'physical_sheets' => 73480, 'a4_mono' => 125300], 30);
        $latest = $read('it.clerk', $shared, ['printed_sides' => 132700, 'physical_sheets' => 76310, 'a4_mono' => null], 2, ['remarks' => 'A4 mono counter not shown on the panel today']);
        $read('legal.clerk', $owned, ['printed_sides' => 8400], 45);
        $read('legal.clerk', $owned, ['printed_sides' => 8100], 5, ['remarks' => 'Reading lower than last time – check']); // flagged: decreased

        $this->as('accountant.supply', function () {
            foreach (MeterObservation::where('status', 'submitted')->get() as $obs) {
                app(MeterService::class)->verify($obs, 'accept', 'Checked against photo');
            }
        });

        $this->as('it.clerk', fn () => app(PaperService::class)->allocate([
            'machine_id' => $shared->id, 'period_from_on' => now()->subDays(61)->toDateString(), 'period_to_on' => now()->subDays(29)->toDateString(),
            'lines' => [['department_id' => $it->id, 'allocated_usage' => 4200, 'basis' => 'measured'], ['department_id' => $legal->id, 'allocated_usage' => 2350, 'basis' => 'declared']],
        ]));

        // A4 request with statement + meter evidence, waiting for certification.
        $statement = $this->as('it.clerk', fn () => app(PaperService::class)->createStatement([
            'department_id' => $it->id, 'item_id' => $this->items['PAP-A4-80']->id,
            'period_from_on' => now()->subDays(30)->toDateString(), 'period_to_on' => now()->subDays(2)->toDateString(),
            'opening_balance' => 1500, 'receipts' => 4000, 'usage_or_loss' => 5100, 'counted_closing' => 400,
            'explanation' => 'Usage matches the shared printer readings.',
        ]));
        $a4 = $this->as('it.clerk', fn () => app(RequestService::class)->create([
            'request_type' => 'a4_paper', 'department_id' => $it->id, 'store_id' => $this->stores['stat']->id,
            'period_from_on' => now()->toDateString(), 'period_to_on' => now()->addMonth()->toDateString(),
            'purpose' => 'A4 paper for '.now()->format('F'), 'staffing_count' => 14, 'paper_statement_id' => $statement->id,
            'observation_ids' => [$latest->id],
            'lines' => [['item_id' => $this->items['PAP-A4-80']->id, 'unit_id' => Unit::where('code', 'REAM')->value('id'), 'requested_qty' => 12]],
        ]));
        $this->as('it.clerk', fn () => app(RequestService::class)->submit($a4));
    }

    private function maintenance(): void
    {
        $it = Department::where('code', 'IT')->value('id');
        $legal = Department::where('code', 'LEGAL')->value('id');
        $laptops = Asset::where('item_id', $this->items['IT-LAPTOP']->id)->orderBy('id')->get();

        // Warranties for the new laptops (3 years from receipt).
        $this->as('repair.coord', function () use ($laptops) {
            foreach ($laptops as $laptop) {
                app(AssetLifecycleService::class)->addWarranty([
                    'asset_id' => $laptop->id, 'provider_supplier_id' => Supplier::where('code', 'SUP-ABN')->value('id'),
                    'coverage_type' => 'Manufacturer – parts & labour', 'starts_on' => now()->subDays(15)->toDateString(), 'ends_on' => now()->addYears(3)->toDateString(),
                ]);
            }
        });

        // A repair under warranty, currently with the provider.
        $this->as('repair.coord', function () use ($laptops) {
            $repairs = app(RepairService::class);
            $case = $repairs->create(['asset_id' => $laptops[1]->id, 'fault_description' => 'Keyboard: several keys not responding']);
            $repairs->assess($case, ['assessment' => 'Keyboard replacement under warranty', 'expected_return_on' => now()->addDays(7)->toDateString()]);
            $repairs->dispatch($case->fresh(), ['accessories_sent' => 'Charger']);
            $repairs->addUpdate($case, ['note' => 'Provider confirmed part ordered', 'update_source' => 'provider']);
        });

        // An old desktop with a paid repair waiting for expenditure approval.
        $oldDesktop = Asset::where('item_id', $this->items['IT-DESKTOP']->id)->where('condition', 'poor')->first();
        $this->as('repair.coord', function () use ($oldDesktop) {
            $repairs = app(RepairService::class);
            $case = $repairs->create(['asset_id' => $oldDesktop->id, 'fault_description' => 'Does not power on']);
            $repairs->assess($case, ['assessment' => 'Power supply unit failed', 'provider_supplier_id' => Supplier::where('code', 'SUP-ABN')->value('id'), 'quotation_amount' => 18500]);
        });

        // A laptop transfer from IT to Legal: dispatched and in transit.
        $spare = $laptops[2]; // issued to IT above
        $transfer = $this->as('it.clerk', fn () => app(TransferService::class)->create([
            'asset_id' => $spare->id, 'to_department_id' => $legal, 'to_location_id' => Location::where('code', '3F-301')->value('id'),
            'to_custodian_officer_id' => $this->officer('legal.clerk'), 'reason' => 'Legal Division needs a laptop for court work', 'accessories' => 'Charger, bag',
        ]));
        $this->as('accountant.supply', fn () => app(TransferService::class)->approve($transfer, 'Approved'));
        $this->as('stores.keeper', fn () => app(TransferService::class)->dispatch($transfer->fresh(), ['condition' => 'new']));

        // A chair transfer waiting for approval.
        $chair = Asset::where('item_id', $this->items['FUR-CHAIR']->id)->where('responsible_department_id', $it)->first();
        $this->as('it.clerk', fn () => app(TransferService::class)->create([
            'asset_id' => $chair->id, 'to_department_id' => $legal, 'to_location_id' => Location::where('code', '3F-301')->value('id'), 'reason' => 'Surplus chair',
        ]));

        // Service schedule for the shared printer asset, valuation and a disposal proposal.
        $printerAsset = Asset::where('item_id', $this->items['IT-PRINTER']->id)->whereNotNull('id')->first();
        ServiceSchedule::create(['asset_id' => $printerAsset->id, 'service_type' => 'Preventive maintenance', 'interval_days' => 90, 'last_service_on' => now()->subDays(80)->toDateString(), 'next_due_on' => now()->addDays(10)->toDateString(), 'responsible_officer_id' => $this->officer('repair.coord')]);

        $this->as('accountant.supply', function () use ($oldDesktop, $laptops) {
            $life = app(AssetLifecycleService::class);
            $life->proposeValuation(['asset_id' => $laptops[0]->id, 'new_value' => 285000, 'effective_on' => now()->subDays(15)->toDateString(), 'decision_ref' => 'Invoice ABN/CS/2026/7714']);
            $life->proposeDisposal(['asset_id' => $oldDesktop->id, 'method' => 'auction', 'decision_ref' => 'Board of Survey 2026/BS-07', 'reason' => 'Beyond economical repair']);
        });
        $this->as('cfo', function () {
            $valuation = Valuation::first();
            app(AssetLifecycleService::class)->decideValuation($valuation, true, 'Purchase value accepted');
        });

        // Annual verification in progress.
        $this->as('accountant.supply', function () {
            $life = app(AssetLifecycleService::class);
            $session = $life->openVerification(['scope_note' => 'Board of Survey '.now()->year.' – 2nd floor']);
            foreach (Asset::whereNotNull('location_id')->where('operational_status', 'in_use')->take(3)->get() as $asset) {
                $life->verify($session, ['asset_id' => $asset->id, 'found_location_id' => $asset->location_id, 'condition_found' => $asset->condition]);
            }
        });
    }

    private function counts(): void
    {
        // A count of the stationery store with one confirmed variance.
        $session = $this->as('stores.keeper', fn () => app(CountService::class)->create([
            'store_id' => $this->stores['stat']->id, 'scope_note' => 'Monthly spot check – pens and pins',
            'item_ids' => [$this->items['STA-PINS']->id, $this->items['STA-PEN-RED']->id],
        ]));
        $lines = $session->lines;
        $this->as('stores.keeper', function () use ($session, $lines) {
            $count = app(CountService::class);
            $count->record($session, $lines->map(fn ($l) => [
                'id' => $l->id,
                'counted_qty' => $l->item_id === $this->items['STA-PINS']->id ? bcsub($l->expected_qty, '2', 3) : $l->expected_qty,
            ])->all());
            $pins = $lines->firstWhere('item_id', $this->items['STA-PINS']->id);
            $count->record($session->fresh(), [['id' => $pins->id, 'recount_qty' => bcsub($pins->expected_qty, '2', 3), 'remarks' => 'Two boxes water-damaged in the leak, disposed']]);
            $count->submit($session->fresh('lines'));
        });
        $this->as('chief.accountant', fn () => app(CountService::class)->approve($session->fresh('lines'), 'Variance accepted'));

        // A manual adjustment waiting for approval.
        $this->as('stores.keeper', fn () => app(AdjustmentService::class)->create([
            'store_id' => $this->stores['stat']->id, 'item_id' => $this->items['STA-STAPLER']->id, 'qty_delta' => 1,
            'reason' => 'Stapler found in returned box, not recorded on return note',
        ]));
    }

    // ---------------------------------------------------------------------------

    private function issue(GoodsRequest $request, array $lines, ?string $acknowledge): void
    {
        $issue = $this->as('stores.keeper', fn () => app(IssueService::class)->create([
            'request_id' => $request->id,
            'lines' => array_map(fn ($l) => ['request_line_id' => $l[0], 'qty' => $l[1], 'asset_id' => $l[2] ?? null], $lines),
        ]));
        $this->as('stores.keeper', fn () => app(IssueService::class)->post($issue, (string) Str::uuid()));

        if ($acknowledge) {
            $clerk = $request->department->code === 'IT' ? 'it.clerk' : 'legal.clerk';
            $this->as($clerk, fn () => app(IssueService::class)->acknowledge($issue->fresh(), $acknowledge, 'Received in good order'));
        }
    }

    private function postGrn(Grn $grn, ?callable $inspection): void
    {
        $this->as('stores.keeper', fn () => app(GrnService::class)->submit($grn));
        $this->as('receiving.officer', fn () => app(GrnService::class)->inspect($grn->fresh(), $grn->lines()->get()->map(fn ($line) => ['id' => $line->id] + ($inspection ? $inspection($line) : ['accepted_qty' => $line->delivered_qty]))->all(), 'Inspected on delivery'));
        $this->as('chief.accountant', fn () => app(GrnService::class)->approvePost($grn->fresh(), (string) Str::uuid(), 'Approved for posting'));
    }

    private function grant(string $username, array $codes): void
    {
        foreach ($codes as $code) {
            UserPermission::create([
                'user_id' => $this->users[$username]->id,
                'permission_id' => Permission::where('code', $code)->value('id'),
                'effect' => 'allow', 'scope_type' => 'global', 'valid_from' => now()->subMonth(),
                'reason' => 'Demo: additional duty', 'granted_at' => now()->subMonth(),
            ]);
        }
        $this->users[$username]->bumpPermissionVersion();
    }

    private function officer(string $username): int
    {
        return $this->users[$username]->officer_id;
    }

    private function as(string $username, callable $callback): mixed
    {
        Auth::setUser($this->users[$username]->fresh());

        return $callback();
    }
}
