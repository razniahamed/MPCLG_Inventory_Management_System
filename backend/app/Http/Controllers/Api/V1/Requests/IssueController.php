<?php

namespace App\Http\Controllers\Api\V1\Requests;

use App\Http\Controllers\Api\V1\Concerns\ScopesListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\MasterRecordResource;
use App\Models\GoodsRequest;
use App\Models\Issue;
use App\Services\Access\Actor;
use App\Services\Requests\IssueService;
use App\Support\ApiResponse;
use App\Support\Idempotency;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IssueController extends Controller
{
    use ScopesListings;

    private const WITH = ['request:id,public_id,request_no,department_id,department_name_snapshot,status', 'store:id,code,name', 'issuedBy:id,full_name', 'acknowledgement.receivedBy:id,full_name'];

    public function __construct(private readonly IssueService $issues, private readonly Actor $actor) {}

    public function index(Request $request): JsonResponse
    {
        $query = Issue::query()->with(self::WITH)->withCount('lines');
        if ($this->actor->can('request.view_all')) {
            $this->limitToStores($query, 'request.view_all');
        } else {
            $ids = $this->departmentIds('issue.acknowledge');
            if ($ids !== null) {
                $query->whereHas('request', fn ($q) => $q->whereIn('department_id', $ids ?: [0]));
            }
        }

        $page = ListQuery::paginate($query, $request,
            filters: [
                'status' => 'status',
                'store_id' => 'store_id',
                'request_id' => 'request_id',
                'unacknowledged' => fn ($q, $v) => $v === 'true' ? $q->where('status', 'posted')->whereDoesntHave('acknowledgement') : $q,
            ],
            sorts: ['id', 'issue_no', 'issue_on'],
            search: ['issue_no'],
            defaultSort: '-id',
        );

        return ApiResponse::paginated($page, MasterRecordResource::class);
    }

    public function show(Issue $issue): JsonResponse
    {
        return ApiResponse::success(new MasterRecordResource($issue->load([...self::WITH, 'lines.item:id,item_code,name_en', 'lines.unit:id,code', 'lines.asset:id,public_id,asset_no'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required'],
            'issue_on' => ['nullable', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.request_line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
        ]);
        $data['request_id'] = GoodsRequest::where('public_id', $data['request_id'])->orWhere('id', $data['request_id'])->valueOrFail('id');

        $issue = $this->issues->create($data);

        return ApiResponse::created(new MasterRecordResource($issue->load(self::WITH)), "Issue {$issue->issue_no} prepared. Post it to reduce stock.");
    }

    public function post(Request $request, Issue $issue): JsonResponse
    {
        $issue = $this->issues->post($issue, Idempotency::keyFrom($request));

        return ApiResponse::success(new MasterRecordResource($issue->load(self::WITH)), "Issue {$issue->issue_no} posted.");
    }

    public function reverse(Request $request, Issue $issue): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']])['reason'];

        return ApiResponse::success(new MasterRecordResource($this->issues->reverse($issue, $reason)->load(self::WITH)), 'Issue reversed.');
    }

    public function acknowledge(Request $request, Issue $issue): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'discrepancy'])],
            'remarks' => ['nullable', 'string', 'max:255'],
            'lines' => ['nullable', 'array'],
            'lines.*.issue_line_id' => ['required', 'integer'],
            'lines.*.received_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $issue = $this->issues->acknowledge($issue, $data['status'], $data['remarks'] ?? null, $data['lines'] ?? []);

        return ApiResponse::success(new MasterRecordResource($issue->load(self::WITH)), $data['status'] === 'confirmed' ? 'Receipt confirmed.' : 'Discrepancy recorded for review.');
    }
}
