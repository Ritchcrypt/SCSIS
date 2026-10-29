<?php

namespace App\Http\Controllers;

use App\Services\DashboardDaoNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\RateLimiter;

final class DashboardDaoNewsLinksController extends Controller
{
    public function store(Request $request, DashboardDaoNewsService $news): RedirectResponse
    {
        $this->guardAdminChanges($request);
        // Existing route is already protected by auth, active.user, role:admin and CSRF.
        if ($request->filled('check_id')) {
            $data = $request->validate(['check_id' => ['required', 'uuid']]);
            $state = $news->checkCuratedLink($data['check_id']);
            return redirect()->route('admin.dashboard')
                ->with('dao_news_status', 'Link HEAD check: '.$state.'. Redirects and confirmed missing pages are hidden; HEAD-blocked sources require manual review.');
        }
        if ($request->filled('flag_id')) {
            $data = $request->validate(['flag_id' => ['required', 'uuid']]);
            $news->flagCuratedLink($data['flag_id']);
            return redirect()->route('admin.dashboard')
                ->with('dao_news_status', 'Incorrect or changed article unpublished pending review.');
        }
        if ($request->filled('edit_id')) {
            $data = $request->validate([
                'edit_id' => ['required', 'uuid'],
                'url' => ['required', 'string', 'max:1800', 'url'],
                'topic' => ['required', Rule::in(DashboardDaoNewsService::TOPICS)],
                'published' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'source_checked' => ['accepted'],
                'dao_relevance_checked' => ['accepted'],
            ]);
            $news->editCuratedLink($data['edit_id'], $data['url'], $data['topic'], $data['published']);
            return redirect()->route('admin.dashboard')
                ->with('dao_news_status', 'External link edited and recorded in audit history.');
        }
        if ($request->filled('review_id')) {
            $data = $request->validate([
                'review_id' => ['required', 'uuid'],
                'published' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'source_checked' => ['accepted'],
                'dao_relevance_checked' => ['accepted'],
            ]);
            $news->reviewCuratedLink($data['review_id'], $data['published']);
            return redirect()->route('admin.dashboard')
                ->with('dao_news_status', 'External link verified for another 60 days.');
        }
        $data = $request->validate([
            'url' => ['required', 'string', 'max:1800', 'url'],
            'topic' => ['required', Rule::in(DashboardDaoNewsService::TOPICS)],
            'published' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'source_checked' => ['accepted'],
            'dao_relevance_checked' => ['accepted'],
        ]);
        $news->addCuratedLink($data['url'], $data['topic'], $data['published'] ?? null);
        return redirect()->route('admin.dashboard')
            ->with('dao_news_status', 'Approved external link added for 60 days.');
    }

    public function destroy(string $id, DashboardDaoNewsService $news): RedirectResponse
    {
        $this->guardAdminChanges(request());
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id), 404);
        $news->removeCuratedLink($id);
        return redirect()->route('admin.dashboard')
            ->with('dao_news_status', 'External source link removed.');
    }
    /** Additional rate limit complements existing Admin RBAC and CSRF defenses. */
    private function guardAdminChanges(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403);
        $key = 'dao-news-admin:'.(string) $user->getAuthIdentifier();
        abort_if(RateLimiter::tooManyAttempts($key, 25), 429, 'Too many news management actions. Try again shortly.');
        RateLimiter::hit($key, 60);
    }
}
