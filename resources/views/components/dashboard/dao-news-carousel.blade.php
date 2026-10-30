@php
    // Admin-curated external publisher links only. No automatic feed fetching.
    $daoExternalStories = app(\App\Services\DashboardDaoNewsService::class)->forUser(auth()->user());
@endphp

{{-- TN_C21_NEWS_FEEDBACK_FIX_V1: surface errors above collapsed manager, without modifying source validation --}}
@if (auth()->user()?->role === 'admin')
    @if ($errors->any())
        <div role="alert" style="border:1px solid #b91c1c;border-radius:8px;padding:10px 14px;margin:10px 0;color:inherit;background:rgba(185,28,28,.08)">
            <strong>External news link was not saved.</strong>
            <span>{{ $errors->first() }}</span>
            <span>Use an approved direct HTTPS publisher link and its actual publication date within the last 90 days.</span>
        </div>
    @elseif (session('dao_news_status') === 'Approved external link added for 60 days.')
        <div data-tn-c21-news-add-success role="status" style="border:1px solid #16803b;border-radius:8px;padding:10px 14px;margin:10px 0;color:inherit;background:rgba(22,128,59,.08)">
            <strong>News link saved.</strong> Showing the newest approved news slide now. Select the center of the slide to open its publisher.
        </div>
    @elseif (session('dao_news_status'))
        <div role="status" style="border:1px solid #64748b;border-radius:8px;padding:10px 14px;margin:10px 0;color:inherit;background:rgba(100,116,139,.08)">
            {{ session('dao_news_status') }}
        </div>
    @endif
@endif
@if ($daoExternalStories->isNotEmpty())
<style>
    .tn-dao-ext-content[hidden] { display: none !important; }
    .tn-dao-ext-heading { position: relative; z-index: 4; max-width: min(72%, 720px);
        padding: 30px 38px 44px; color: white; text-shadow: 0 2px 5px rgba(0,0,0,.8);
        pointer-events: none; }
    .tn-dao-ext-heading h2 { font-size: clamp(20px, 2.25vw, 32px); line-height: 1.18; font-weight: 800;
        margin: 0 0 7px; max-height: 3.6em; overflow: hidden; }
    .tn-dao-ext-heading p { margin: 0 0 7px; line-height: 1.38; font-size: 13px;
        display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
    .tn-dao-ext-heading__source { font-size: 12px; font-weight: 650; }
    /* Original TabangNow gradients: no external photos or copyright assets. */
    .tn-c21-dashboard-welcome.tn-dao-ext-active::before {
        background-image: var(--tn-dao-news-background, linear-gradient(135deg,#183454,#1f5a70)) !important;
        background-size: cover !important;
    }
    .tn-c21-dashboard-welcome.tn-dao-ext-active::after {
        background: linear-gradient(90deg,rgba(4,14,31,.94),rgba(4,14,31,.68) 62%,rgba(4,14,31,.12)) !important;
    }
    .tn-dao-ext-navigation { position:absolute; z-index: 12; top:0; bottom:0; width: min(14%, 110px);
        opacity:0; background:transparent; border:0; cursor:pointer; padding:0; }
    .tn-dao-ext-navigation--left { left:0; }
    .tn-dao-ext-navigation--right { right:0; }
    .tn-dao-ext-navigation:focus-visible { outline: 3px solid #38bdf8; outline-offset:-3px; opacity:1; background:rgba(8,36,61,.18); }
    .tn-dao-ext-reader { position:absolute; z-index: 8; inset:0; width:100%; border:0; background:transparent; cursor:pointer; }
    .tn-dao-ext-reader:focus-visible { outline:3px solid #38bdf8; outline-offset:-4px; }
    .tn-dao-ext-counter { position:absolute; z-index: 9; bottom:11px; right:18px;
        font-size:11px; color:white; border-radius:16px; padding:4px 9px; background:rgba(7,23,46,.7); pointer-events:none; }
    .tn-dao-ext-hint { position:absolute; z-index:9; bottom:12px; left:18px; color:#fff;
        background:rgba(7,23,46,.72); font-size:11px; padding:4px 9px; border-radius:6px; pointer-events:none; }
    @media(max-width:640px) {
        .tn-dao-ext-heading { max-width:100%; padding:24px 20px 46px; }
        .tn-dao-ext-heading h2 { font-size:19px; }
        .tn-dao-ext-heading p { -webkit-line-clamp:1; }
        .tn-dao-ext-navigation { width:18%; }
    }
</style>
{{-- Structured data stays private to the authenticated rendered page; no API secrets included. --}}
<div data-dao-ext-records hidden>
    @foreach($daoExternalStories as $story)
        <div data-dao-ext-record
            data-title="{{ $story['title'] }}"
            data-url="{{ $story['url'] }}"
            data-source="{{ $story['source'] }}"
            data-published="{{ $story['published'] ?? '' }}"></div>
    @endforeach
</div>
<script>
(() => {
    const shell = document.currentScript?.closest('.tn-c21-dashboard-welcome-shell');
    if (!shell || shell.dataset.daoExternalReady === '1') return;
    shell.dataset.daoExternalReady = '1';
    const banner = shell.querySelector('.tn-c21-dashboard-welcome');
    const greeting = banner?.querySelector('.tn-c21-dashboard-welcome__text');
    const records = [...shell.querySelectorAll('[data-dao-ext-record]')].map(el => el.dataset);
    if (!banner || !greeting || !records.length) return;
    const heading = document.createElement('div'); heading.className = 'tn-dao-ext-heading tn-dao-ext-content'; heading.hidden = true;
    const title = document.createElement('h2');
    const source = document.createElement('div'); source.className = 'tn-dao-ext-heading__source';
    heading.append(title,source); banner.append(heading);
    const reader = document.createElement('button'); reader.type = 'button'; reader.className = 'tn-dao-ext-reader';
    reader.setAttribute('aria-label','Read Dao news at the publisher (opens in a new tab)'); banner.append(reader);
    const left = document.createElement('button'); left.type='button'; left.className = 'tn-dao-ext-navigation tn-dao-ext-navigation--left';
    left.setAttribute('aria-label','Previous news slide'); banner.append(left);
    const right = document.createElement('button'); right.type='button'; right.className = 'tn-dao-ext-navigation tn-dao-ext-navigation--right';
    right.setAttribute('aria-label','Next news slide'); banner.append(right);
    const counter = document.createElement('span'); counter.className = 'tn-dao-ext-counter'; banner.append(counter);
    const hint = document.createElement('span'); hint.className = 'tn-dao-ext-hint'; hint.textContent='Tap edges to browse · Tap center to open'; banner.append(hint);
    // Artwork is generated locally from CSS colors. No publisher images loaded.
    const backgrounds = [
        'linear-gradient(115deg,#123456 0%,#225f68 58%,#2a806a 100%)',
        'linear-gradient(115deg,#202953 0%,#485c85 60%,#2f6385 100%)',
        'linear-gradient(115deg,#253451 0%,#496754 60%,#7a7551 100%)',
        'linear-gradient(115deg,#432b4e 0%,#554476 58%,#2f6570 100%)',
        'linear-gradient(115deg,#21424e 0%,#456a70 58%,#5d896b 100%)'
    ];
    let active = 0;
    const apply = (index) => {
        active = (index + records.length + 1) % (records.length + 1);
        const story = records[active - 1];
        const showNews = !!story;
        greeting.hidden = showNews;
        heading.hidden = !showNews;
        banner.classList.toggle('tn-dao-ext-active', showNews);
        if (showNews) {
            banner.style.setProperty('--tn-dao-news-background', backgrounds[(active - 1) % backgrounds.length]);
        } else {
            banner.style.removeProperty('--tn-dao-news-background');
        }
        if (showNews) {
            title.textContent = story.title || 'Dao update';
            source.textContent = [story.source,story.published].filter(Boolean).join(' · ');
        }
        counter.textContent = `${active + 1} / ${records.length + 1}`;
    };
    const change = (delta) => apply(active + delta);
    const read = () => {
        if (active === 0) return; // Welcome photo is not an article link.
        const story = records[active - 1];
        if (!story?.url) return;
        try {
            const link = new URL(story.url);
            if (link.protocol !== 'https:' || link.username || link.password) return;
            // No blocking browser popup: the slide itself clearly marks this
            // as an external publisher. Keep HTTPS and safe new-tab isolation.
            window.open(link.href, '_blank', 'noopener,noreferrer');
        } catch (_) { /* Invalid external URLs are not opened. */ }
    };
    reader.addEventListener('click', read);
    left.addEventListener('click', () => change(-1));
    right.addEventListener('click', () => change(1));
    let gesture=null;
    banner.addEventListener('touchstart', e=> { if(e.touches.length === 1) gesture={x:e.touches[0].clientX,y:e.touches[0].clientY}; },{passive:true});
    banner.addEventListener('touchend', e=>{
        if(!gesture || !e.changedTouches.length) return;
        const dx=e.changedTouches[0].clientX-gesture.x;
        const dy=e.changedTouches[0].clientY-gesture.y;
        gesture=null;
        if(Math.abs(dx)>50 && Math.abs(dx)>Math.abs(dy)*1.3) change(dx<0 ? 1 : -1);
    },{passive:true});
    banner.addEventListener('keydown', e=>{
        if(e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            e.preventDefault();change(e.key === 'ArrowRight' ? 1 : -1);
        }
    });
    apply(document.querySelector('[data-tn-c21-news-add-success]') ? 1 : 0);
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
    if (!reduceMotion) {
        const timer = window.setInterval(()=>{
            if(document.visibilityState==='visible' && !banner.matches(':hover')
               && !banner.contains(document.activeElement)) change(1);
        },8000);
        window.addEventListener('pagehide',()=>window.clearInterval(timer),{once:true});
    }
})();
</script>
@endif


@if (auth()->user()?->role === 'admin')
<style>
.tn-dao-curated-manager { margin: 12px 0 22px; padding: 12px 16px; border: 1px solid #94a3b850;
 border-radius: 10px; color: inherit; background: rgba(100,116,139,.07); }
/* Explicit accessible disclosure buttons avoid nested <details> click issues. */
.tn-dao-curated-manager summary { cursor: pointer; font-weight: 700; }
.tn-dao-curated-manager [data-tn-news-panel][hidden] { display: none !important; }
.tn-dao-curated-manager__toggle { width: 100%; text-align: left; font-weight: 700;
    padding: 7px 10px !important; background: transparent; border: 1px solid #94a3b850 !important;
    color: inherit; border-radius: 7px; cursor: pointer; }
.tn-dao-curated-manager__toggle:focus-visible { outline: 3px solid #38bdf8; outline-offset: 2px; }
.tn-dao-curated-manager__panel { width: 100%; margin: 8px 0 0; padding: 8px 10px;
    border-left: 2px solid #94a3b880; }
.tn-dao-curated-manager p { font-size: 13px; margin: 10px 0; }
.tn-dao-curated-manager__form { display:flex; flex-wrap:wrap; gap:9px; align-items:end; }
.tn-dao-curated-manager label { display:flex; flex-direction:column; font-size:12px; font-weight:600; gap:4px; }
.tn-dao-curated-manager input:not([type="checkbox"]), .tn-dao-curated-manager select {
 padding:8px 10px; border:1px solid #94a3b8; border-radius:7px; background:var(--color-background,#fff); color:#111827; }
.tn-dao-curated-manager__url { flex:1 1 280px; }
.tn-dao-curated-manager button { padding:8px 12px; border:1px solid #64748b; border-radius:7px; cursor:pointer; }
.tn-dao-curated-manager__agreement { display:flex!important; flex-direction:row!important; width:100%; align-items:center; }
.tn-dao-curated-manager__list { margin:12px 0 0; padding:0; list-style:none; }
.tn-dao-curated-manager__list li { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:6px; font-size:12px; }
.tn-dao-curated-manager__list span { min-width:0; overflow-wrap:anywhere; flex:1; }

.tn-dao-curated-manager .tn-dao-friendly-audit-item {
      display: block;
      padding: 10px 0;
      border-bottom: 1px solid rgba(100,116,139,.22);
      overflow-wrap: anywhere;
    }
    .tn-dao-curated-manager .tn-dao-friendly-audit-item strong {
      display: block;
      font-size: 13px;
      margin-bottom: 2px;
    }
    .tn-dao-curated-manager .tn-dao-friendly-audit-item span {
      display: block;
      font-size: 12px;
      color: inherit;
      opacity: .78;
      min-width: 0;
      overflow-wrap: anywhere;
    }
</style>
<details class="tn-dao-curated-manager" @if($errors->any()) open @endif>
  <summary>Manage external news links (Admin only)</summary>
  @php
    $daoNewsLinkService = app(\App\Services\DashboardDaoNewsService::class);
    $daoNewsStorageError = false;
    try { $daoNewsStoredLinks = $daoNewsLinkService->entries(); $daoNewsAudit = $daoNewsLinkService->auditHistory(); }
    catch (\RuntimeException $e) { $daoNewsStoredLinks = []; $daoNewsAudit = []; $daoNewsStorageError = true; }
  @endphp
  @if($daoNewsStorageError)
    <p role="alert">The private news-links file needs recovery. Contact the administrator to verify and restore a matching versioned snapshot; never overwrite the audit key without its matching data file.</p>
  @endif

  @if(session('dao_news_status'))<p role="status">{{ session('dao_news_status') }}</p>@endif
  @if($errors->any())
    <p role="alert">{{ $errors->first() }}</p>
  @endif
  @unless($daoNewsStorageError)
  <form class="tn-dao-curated-manager__form" method="POST" action="{{ route('admin.dao-news-links.store') }}">
    @csrf
    <label class="tn-dao-curated-manager__url">Original source URL (HTTPS)
      <input type="url" name="url" maxlength="1800" placeholder="https://pia.gov.ph/your-story" required></label>
    <label>Update category
      <select name="topic" required>
        @foreach(\App\Services\DashboardDaoNewsService::TOPICS as $topic)
          <option value="{{ $topic }}">{{ $topic }}</option>
        @endforeach
      </select></label>
    <label>Original publication date (required)<input type="date" name="published" max="{{ now('UTC')->toDateString() }}" required></label>
    <label class="tn-dao-curated-manager__agreement">
      <input type="checkbox" name="source_checked" value="1" required>
      I checked the original publisher, article URL, accuracy, and that the site is still safe and publicly accessible. No article content is copied.
    </label>
    <label class="tn-dao-curated-manager__agreement">
      <input type="checkbox" name="dao_relevance_checked" value="1" required>
      I confirmed this original article specifically concerns Dao, Capiz or a regional event directly affecting its residents; the date is correct.
    </label>
    <button type="submit">Add external link</button>
  </form>
  @endunless
  @if($daoNewsStoredLinks !== [])
    <ul class="tn-dao-curated-manager__list">
    @foreach($daoNewsStoredLinks as $entry)
      @php
          $approved = $daoNewsLinkService->isApprovedAndCurrent($entry);
          $trusted = $daoNewsLinkService->isTrustedUrl($entry['url']);
      @endphp
      <li>
        <span>
          {{ $entry['topic'] }} — {{ parse_url($entry['url'], PHP_URL_HOST) }} — {{ $entry['url'] }}
          <strong>({{ $approved ? 'Approved / visible' : ($trusted ? 'Pending review / hidden' : 'Source not approved / hidden') }})</strong>
          @if(!empty($entry['reviewed_at'])) · Last reviewed {{ $entry['reviewed_at'] }} @endif
          @if(!empty($entry['reviewed_by'])) · Reviewer user #{{ $entry['reviewed_by'] }} @endif
          @if(!empty($entry['last_checked_at'])) · Last HEAD check: {{ $entry['last_checked_at'] }} ({{ $entry['link_status'] }}{{ $entry['last_http_status'] ? ' HTTP '.$entry['last_http_status'] : '' }}) @endif
        </span>
        @unless($daoNewsStorageError)
        <div style="width:100%;margin-left:10px">
          <button type="button" class="tn-dao-curated-manager__toggle" data-tn-news-toggle
            aria-expanded="false" aria-controls="tn-dao-news-edit-{{ $entry['id'] }}">Edit external article link</button>
          <div class="tn-dao-curated-manager__panel" id="tn-dao-news-edit-{{ $entry['id'] }}" data-tn-news-panel hidden>
          <form class="tn-dao-curated-manager__form" method="POST" action="{{ route('admin.dao-news-links.store') }}">
            @csrf
            <input type="hidden" name="edit_id" value="{{ $entry['id'] }}">
            <label class="tn-dao-curated-manager__url">Correct source URL
              <input type="url" name="url" maxlength="1800" value="{{ $entry['url'] }}" required></label>
            <label>Category
              <select name="topic" required>
                @foreach(\App\Services\DashboardDaoNewsService::TOPICS as $topic)
                  <option value="{{ $topic }}" @selected($topic === $entry['topic'])>{{ $topic }}</option>
                @endforeach
              </select></label>
            <label>Publication date
              <input type="date" name="published" max="{{ now('UTC')->toDateString() }}" value="{{ $entry['published'] }}" required></label>
            <label class="tn-dao-curated-manager__agreement"><input type="checkbox" name="source_checked" value="1" required>
              I rechecked this exact publisher URL, the article's accuracy and public accessibility.</label>
            <label class="tn-dao-curated-manager__agreement"><input type="checkbox" name="dao_relevance_checked" value="1" required>
              I confirmed that the corrected article is relevant to Dao, Capiz.</label>
            <button type="submit">Save edited link</button>
          </form>
          </div>
        </div>
        <form method="POST" action="{{ route('admin.dao-news-links.store') }}">
          @csrf
          <input type="hidden" name="check_id" value="{{ $entry['id'] }}">
          <button type="submit" title="Checks HTTP headers only; redirects and 404/410 unpublish, inconclusive HEAD results require manual review">Check link (HEAD)</button>
        </form>
        <form method="POST" action="{{ route('admin.dao-news-links.store') }}">
          @csrf
          <input type="hidden" name="flag_id" value="{{ $entry['id'] }}">
          <button type="submit" onclick="return confirm('Unpublish this article until an Admin verifies it again?')">Unpublish / flag changed</button>
        </form>
        @endunless
        @if(!$approved && $trusted)
          <form method="POST" action="{{ route('admin.dao-news-links.store') }}">
            @csrf
            <input type="hidden" name="review_id" value="{{ $entry['id'] }}">
            <label class="tn-dao-curated-manager__agreement">
              <input type="checkbox" name="source_checked" value="1" required>
              I reopened and verified this original article, publisher, accuracy and safety.
            </label>
            <label>Verified article publication date
              <input type="date" name="published" max="{{ now('UTC')->toDateString() }}" value="{{ $entry['published'] }}" required>
            </label>
            <label class="tn-dao-curated-manager__agreement">
              <input type="checkbox" name="dao_relevance_checked" value="1" required>
              I confirmed this article directly concerns Dao, Capiz or a regional event affecting Dao residents.
            </label>
            <button type="submit">Review and approve</button>
          </form>
        @endif
        <form method="POST" action="{{ route('admin.dao-news-links.destroy', ['id' => $entry['id']]) }}">
          @csrf @method('DELETE')
          <button type="submit" onclick="return confirm('Remove this external link?')">Remove</button>
        </form>
      </li>
    @endforeach
    </ul>
  @endif

  @if(!$daoNewsStorageError)
  <div style="margin-top:14px">
    <button type="button" class="tn-dao-curated-manager__toggle" data-tn-news-toggle
      aria-expanded="false" aria-controls="tn-dao-news-audit-panel">External news audit history (latest 40)</button>
    <div id="tn-dao-news-audit-panel" class="tn-dao-curated-manager__panel" data-tn-news-panel hidden>
      <p>Private Admin-only history of additions, edits, reviews, link checks, unpublishing and removals. Signed audit records and recovery snapshots remain protected.</p>
      @if($daoNewsAudit === [])
        <p>No activity recorded yet; pre-upgrade edits were not historically logged.</p>
      @else
        <ul class="tn-dao-curated-manager__list">
          @foreach($daoNewsAudit as $event)
          @php
            // Presentation only. The original signed event and full history stay intact.
            $auditAction = (string) ($event['action'] ?? '');
            $auditRecord = is_array($event['after'] ?? null) ? $event['after'] :
                (is_array($event['before'] ?? null) ? $event['before'] : []);
            $auditStatus = (string) ($auditRecord['link_status'] ?? '');
            $auditLabel = match ($auditAction) {
                'added' => 'Article added',
                'edited' => 'Article updated',
                'reviewed' => 'Article approved',
                'unpublished' => 'Article hidden',
                'removed' => 'Article removed',
                'link_checked' => match ($auditStatus) {
                    'ok' => 'Link checked: available',
                    'broken' => 'Link checked: unavailable',
                    'redirected' => 'Link checked: redirected',
                    'manual_check' => 'Link checked: needs review',
                    default => 'Link checked',
                },
                default => 'News link updated',
            };
            $auditWhen = 'Date unavailable';
            try {
                if (!empty($event['at'])) {
                    $auditWhen = \Illuminate\Support\Carbon::parse($event['at'])
                        ->timezone('Asia/Manila')->format('M j, Y · g:i A');
                }
            } catch (\Throwable $ignored) { /* A malformed date must not break the dashboard. */ }
            $auditUrl = (string) ($auditRecord['url'] ?? '');
            $auditHost = (string) (parse_url($auditUrl, PHP_URL_HOST) ?: '');
            $auditHost = preg_replace('/^www\./i', '', $auditHost);
            $auditPath = (string) (parse_url($auditUrl, PHP_URL_PATH) ?: '');
            $auditSlug = trim(str_replace(['-', '_'], ' ', basename(rtrim($auditPath, '/'))));
            $auditDetail = $auditSlug !== '' ? $auditSlug : (string) ($auditRecord['topic'] ?? '');
            $auditArticle = $auditHost !== ''
                ? $auditHost.($auditDetail !== '' ? ' · '.\Illuminate\Support\Str::limit($auditDetail, 55) : '')
                : ($auditDetail !== '' ? $auditDetail : 'Article details unavailable');
          @endphp
          <li class="tn-dao-friendly-audit-item">
            <strong>{{ $auditLabel }}</strong>
            <span>{{ $auditWhen }}</span>
            <span>{{ $auditArticle }}</span>
          </li>
        @endforeach
        </ul>
      @endif
    </div>
  </div>
  @endif

</details>
<script>
/* Scoped to this dashboard manager; does not affect sitewide click handling. */
(() => {
  const manager = document.currentScript?.previousElementSibling;
  if (!manager?.classList.contains('tn-dao-curated-manager')) return;
  manager.addEventListener('click', event => {
    const toggle = event.target.closest('button[data-tn-news-toggle]');
    if (!toggle || !manager.contains(toggle)) return;
    const id = toggle.getAttribute('aria-controls');
    const panel = id ? document.getElementById(id) : null;
    if (!panel || !manager.contains(panel) || !panel.hasAttribute('data-tn-news-panel')) return;
    const expanding = panel.hidden;
    panel.hidden = !expanding;
    toggle.setAttribute('aria-expanded', String(expanding));
  });
})();
</script>
@endif
