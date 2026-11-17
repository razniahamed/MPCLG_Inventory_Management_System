<?php

namespace Database\Seeders;

use App\Enums\DesignationCategory as Cat;
use App\Enums\SiteType;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Division;
use App\Models\PublicService;
use App\Models\ServiceGrade;
use App\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Organisation starting data from Section 9 (org chart). The department list
 * is a DRAFT: the ministry must confirm it (Section 16, decision 12).
 */
class OrganisationSeeder extends Seeder
{
    /** code => [name, category, is_head_post] */
    private const DESIGNATIONS = [
        'SEC' => ['Secretary', Cat::Executive, true],
        'ADD_SEC' => ['Additional Secretary', Cat::Executive, true],
        'CFO' => ['Chief Finance Officer', Cat::Executive, true],
        'DG' => ['Director General', Cat::Executive, true],
        'DIR' => ['Director', Cat::Executive, true],
        'DY_DIR' => ['Deputy/Assistant Director', Cat::Executive, false],
        'SAS' => ['Senior Assistant Secretary', Cat::Executive, true],
        'AS' => ['Assistant Secretary', Cat::Executive, false],
        'CA' => ['Chief Accountant', Cat::Professional, true],
        'ACC_SUP' => ['Accountant (Supply)', Cat::Professional, false],
        'ACC_PAY' => ['Accountant (Payment)', Cat::Professional, false],
        'CLO' => ['Chief Legal Officer', Cat::Professional, true],
        'LO' => ['Legal Officer', Cat::Professional, false],
        'CIA' => ['Chief Internal Auditor', Cat::Professional, true],
        'IA' => ['Internal Auditor', Cat::Professional, false],
        'ENG' => ['Engineer', Cat::Professional, false],
        'CONS_ENG' => ['Consultant Engineer (contract)', Cat::Contract, false],
        'PROC_SPEC' => ['Procurement Specialist (contract)', Cat::Contract, false],
        'AO' => ['Administrative Officer', Cat::Executive, false],
        'ICTO' => ['ICT Officer', Cat::Technical, false],
        'ICTA' => ['ICT Assistant', Cat::Technical, false],
        'TRANS' => ['Translator', Cat::Clerical, false],
        'DO' => ['DO (Development Officer, to confirm)', Cat::Clerical, false],
        'MSO' => ['MSO (Management Service Officer, to confirm)', Cat::Clerical, false],
        'TO' => ['TO (Technical Officer, to confirm)', Cat::Technical, false],
        'KKS' => ['KKS (office assistant grade, to confirm)', Cat::Minor, false],
        'PHOTO' => ['Photographer', Cat::Technical, false],
        'VIDEO' => ['Videographer', Cat::Technical, false],
        'DRIVER' => ['Driver', Cat::Minor, false],
        'ELEC' => ['Electrician', Cat::Technical, false],
        'BUNG_KEEP' => ['Bungalow Keeper', Cat::Minor, false],
        'BUNG_ASST' => ['Bungalow Assistant Keeper', Cat::Minor, false],
    ];

    /** code => [name, head designation code, sort] */
    private const DIVISIONS = [
        'PCLG' => ['Provincial Councils and Local Government', 'ADD_SEC', 1],
        'ADMIN' => ['Administration and Training', 'ADD_SEC', 2],
        'ACC' => ['Accounts', 'CFO', 3],
        'DEV' => ['Development', 'ADD_SEC', 4],
        'PLAN' => ['Planning', 'DG', 5],
        'PM' => ['Project and Monitoring', 'ADD_SEC', 6],
        'IA' => ['Internal Audit', 'CIA', 7],
    ];

    /** code => [name, division code] */
    private const DEPARTMENTS = [
        'LGR' => ['Local Government and Reform', 'PCLG'],
        'LG' => ['Local Government', 'PCLG'],
        'PC' => ['Provincial Councils', 'PCLG'],
        'NSWMSC' => ['N.S.W.M.S.C. (Engineering and Management)', 'PCLG'],
        'ADM_TRG' => ['Administration and Training', 'ADMIN'],
        'IT' => ['IT Division', 'ADMIN'],
        'LEGAL' => ['Legal', 'ADMIN'],
        'ACC_PAY' => ['Accounts: Payment', 'ACC'],
        'ACC_SUP' => ['Accounts: Supply', 'ACC'],
        'ACC_MON' => ['Accounts: Monitoring', 'ACC'],
        'DEV_ENG' => ['Development (Engineering)', 'DEV'],
        'PLAN_DEV' => ['Planning (Development)', 'PLAN'],
        'PLAN_DG' => ['Planning (Director General)', 'PLAN'],
        'PM' => ['Project and Monitoring', 'PM'],
        'IA' => ['Internal Audit', 'IA'],
    ];

    /** code => [name, has grades] */
    private const SERVICES = [
        'SLAS' => ['Sri Lanka Administrative Service (S.L.A.S.)', true],
        'SLACS' => ['Sri Lanka Accountants\' Service (S.L.Ac.S.)', true],
        'SLENGS' => ['Sri Lanka Engineering Service (S.L.Eng.S.)', true],
        'SLPS' => ['Sri Lanka Planning Service (S.L.P.S.)', true],
        'SLICTS' => ['Sri Lanka Information & Communication Technology Service (S.L.I.C.T.S.)', true],
        'OTHER' => ['Other / Contract', false],
    ];

    private const GRADES = ['SPECIAL' => 0, 'I' => 1, 'II' => 2, 'III' => 3];

    public function run(): void
    {
        foreach (self::DESIGNATIONS as $code => [$name, $category, $isHead]) {
            Designation::updateOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'is_head_post' => $isHead,
            ]);
        }

        $designationIds = Designation::pluck('id', 'code');

        foreach (self::DIVISIONS as $code => [$name, $head, $sort]) {
            Division::updateOrCreate(['code' => $code], [
                'name_en' => $name, 'head_designation_id' => $designationIds[$head], 'sort_order' => $sort,
            ]);
        }

        $site = Site::updateOrCreate(['code' => 'HQ'], [
            'name' => 'Ministry Head Office', 'site_type' => SiteType::MainOffice,
        ]);

        $divisionIds = Division::pluck('id', 'code');

        foreach (self::DEPARTMENTS as $code => [$name, $division]) {
            Department::updateOrCreate(['code' => $code], [
                'name_en' => $name,
                'division_id' => $divisionIds[$division],
                'default_site_id' => $site->id,
                'active_from' => '2026-01-01',
            ]);
        }

        foreach (self::SERVICES as $code => [$name, $hasGrades]) {
            $service = PublicService::updateOrCreate(['code' => $code], ['name' => $name]);
            if ($hasGrades) {
                foreach (self::GRADES as $grade => $rank) {
                    ServiceGrade::updateOrCreate(
                        ['public_service_id' => $service->id, 'code' => $grade],
                        ['rank' => $rank],
                    );
                }
            }
        }
    }
}
