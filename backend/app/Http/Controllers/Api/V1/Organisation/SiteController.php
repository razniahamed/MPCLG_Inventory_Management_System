<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Requests\Organisation\SiteRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends MasterDataController
{
    protected string $model = Site::class;

    protected array $filters = ['site_type' => 'site_type'];

    protected array $sorts = ['id', 'code', 'name', 'created_at'];

    protected array $search = ['code', 'name', 'address'];

    protected string $label = 'Site';

    public function index(Request $request): JsonResponse
    {
        return $this->listing($request);
    }

    public function show(Site $site): JsonResponse
    {
        return $this->showRecord($site);
    }

    public function store(SiteRequest $request): JsonResponse
    {
        return $this->createRecord($request);
    }

    public function update(SiteRequest $request, Site $site): JsonResponse
    {
        return $this->updateRecord($request, $site);
    }

    public function destroy(Request $request, Site $site): JsonResponse
    {
        return $this->retireRecord($request, $site, [
            'stores' => fn () => $site->stores()->exists(),
            'locations' => fn () => $site->locations()->exists(),
        ]);
    }
}
