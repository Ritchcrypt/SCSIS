<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardDaoNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/** Same curated website content, served with Sanctum auth. No article downloading. */
final class DashboardDaoNewsController extends Controller
{
    public function index(Request $request, DashboardDaoNewsService $news): JsonResponse
    {
        return response()->json([
            'data' => $news->forUser($request->user())->values()->all(),
            'max_slides' => 10, 'mode' => 'curated_links_only',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function manage(Request $request, DashboardDaoNewsService $news): JsonResponse
    {
        $this->guardAdmin($request);
        // Invalid signatures must fail closed; never treat them as an empty list.
        return response()->json([
            'entries' => collect($news->entries())->map(function (array $row) use ($news): array {
                $row['approved'] = $news->isApprovedAndCurrent($row);
                $row['trusted'] = $news->isTrustedUrl($row['url']);
                return $row;
            })->values()->all(),
            'topics' => DashboardDaoNewsService::TOPICS,
            'audit' => $news->auditHistory(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, DashboardDaoNewsService $news): JsonResponse
    {
        $this->guardAdmin($request);
        $key = 'dao-news-admin:'.(string) $request->user()->getAuthIdentifier();
        abort_if(RateLimiter::tooManyAttempts($key, 25), 429, 'Too many news management actions. Try again shortly.');
        RateLimiter::hit($key, 60);
        $action = $request->validate([
            'action' => ['required', Rule::in(['add', 'edit', 'review', 'unpublish', 'check', 'remove'])],
        ])['action'];
        if (in_array($action, ['add', 'edit', 'review'], true)) {
            $data = $request->validate([
                'id' => [$action === 'add' ? 'nullable' : 'required', 'uuid'],
                'url' => [$action === 'review' ? 'nullable' : 'required', 'string', 'max:1800', 'url'],
                'topic' => [$action === 'review' ? 'nullable' : 'required', Rule::in(DashboardDaoNewsService::TOPICS)],
                'published' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'source_checked' => ['accepted'], 'dao_relevance_checked' => ['accepted'],
            ]);
            if ($action === 'add') $news->addCuratedLink($data['url'], $data['topic'], $data['published']);
            elseif ($action === 'edit') $news->editCuratedLink($data['id'], $data['url'], $data['topic'], $data['published']);
            else $news->reviewCuratedLink($data['id'], $data['published']);
        } else {
            $id = $request->validate(['id' => ['required', 'uuid']])['id'];
            if ($action === 'unpublish') $news->flagCuratedLink($id);
            elseif ($action === 'check') $news->checkCuratedLink($id); // HEAD only, no redirects or article fetch.
            else $news->removeCuratedLink($id);
        }
        return response()->json(['message' => 'External news link '.$action.' completed.'])
            ->header('Cache-Control', 'private, no-store');
    }

    private function guardAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }
}
