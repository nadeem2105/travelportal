<?php

namespace App\Services\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Meta Marketing API adapter. OAuth (Facebook Login) is implemented with plain
 * HTTP against the Graph API. Ad-account listing and campaign writes use the
 * Graph Marketing API; write operations are gated behind isEnabled() and create
 * campaigns in PAUSED status.
 */
class MetaAdsService implements AdPlatformInterface
{
    public function __construct(private ProviderSettingsResolver $settings)
    {
    }

    public function provider(): string
    {
        return 'meta_ads';
    }

    public function isEnabled(): bool
    {
        $c = $this->settings->config('meta_ads');

        return (bool) ($c['enabled'] ?? false) && ! empty($c['app_id']) && ! empty($c['app_secret']);
    }

    private function version(): string
    {
        return (string) ($this->settings->config('meta_ads')['api_version'] ?? 'v21.0');
    }

    public function authorizationUrl(string $state): string
    {
        $c = $this->settings->config('meta_ads');
        $params = http_build_query([
            'client_id' => $c['app_id'],
            'redirect_uri' => $c['redirect_uri'],
            'state' => $state,
            'response_type' => 'code',
            'scope' => 'ads_management,ads_read,business_management,leads_retrieval,pages_show_list',
        ]);

        return "https://www.facebook.com/{$this->version()}/dialog/oauth?" . $params;
    }

    public function handleOAuthCallback(string $code): MarketingConnection
    {
        $this->assertEnabled();
        $c = $this->settings->config('meta_ads');

        $response = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/oauth/access_token", [
            'client_id' => $c['app_id'],
            'client_secret' => $c['app_secret'],
            'redirect_uri' => $c['redirect_uri'],
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Meta OAuth failed: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        $tok = $response->json();

        return MarketingConnection::create([
            'provider' => 'meta_ads',
            'name' => 'Meta Ads',
            'credentials' => ['access_token' => $tok['access_token'] ?? null],
            'status' => 'connected',
            'token_expires_at' => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
            'connected_by' => auth('admin')->id(),
        ]);
    }

    public function listAccounts(MarketingConnection $connection): array
    {
        $this->assertEnabled();
        $token = data_get($connection->credentials, 'access_token');
        if (! $token) {
            throw new AdApiUnavailableException('Meta connection has no access token.');
        }

        $response = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/me/adaccounts", [
            'fields' => 'account_id,name,currency,business,timezone_name',
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Meta account listing failed: ' . ($response->json('error.message') ?? ('HTTP ' . $response->status())));
        }

        return $response->json('data', []);
    }

    /**
     * Build the full campaign tree on Meta, all PAUSED (no spend):
     *   Campaign → Ad Set (targeting + budget) → Ad Creative (image OR video) → Ad.
     *
     * The build is RESUMABLE: ids already stored on campaign->meta['meta_ids'] are
     * reused instead of re-created, so if a video is still processing and publish
     * is retried, we pick up where we left off (no duplicate campaigns/ad sets).
     * Returns the external campaign id.
     */
    public function createCampaign(MarketingCampaign $campaign): string
    {
        [$account, $token] = $this->accountAndToken($campaign);
        $accountId = $this->actId($account->external_account_id);
        $meta = (array) ($campaign->meta ?? []);
        $adsetCfg = (array) ($meta['adset'] ?? []);
        $creativeCfg = (array) ($meta['creative'] ?? []);
        $objective = $this->mapObjective($campaign->objective);
        $ids = (array) ($meta['meta_ids'] ?? []);
        $type = $creativeCfg['type'] ?? 'image';

        // 0) For video creatives, upload + wait for processing BEFORE creating
        //    anything else, so a timeout leaves no half-built campaign.
        if ($type === 'video' && ! empty($creativeCfg['video_path']) && empty($ids['ad_id'])) {
            if (empty($ids['video_id'])) {
                $bytes = $this->creativeVideoBytes($creativeCfg);
                if (! $bytes) {
                    throw new AdApiUnavailableException('The selected creative video could not be read.');
                }
                $ids['video_id'] = $this->uploadVideo($accountId, $token, $bytes);
                $this->persist($campaign, $meta, $ids);
            }
            // Blocks until ready or throws a "still processing, retry" message.
            $this->assertVideoReady($ids['video_id'], $token);
        }

        // 1) Campaign (no budget here — budget lives on the ad set)
        if (empty($ids['campaign_id'])) {
            $ids['campaign_id'] = $this->post($accountId . '/campaigns', $token, [
                'name' => $campaign->name,
                'objective' => $objective,
                'status' => 'PAUSED',
                'special_ad_categories' => json_encode([]),
            ], 'campaign creation');
            $this->persist($campaign, $meta, $ids);
        }
        $campaignId = $ids['campaign_id'];

        // If there is no ad-set/creative spec, stop at the campaign shell.
        if (empty($adsetCfg) && empty($creativeCfg)) {
            return $campaignId;
        }

        // 2) Ad Set — targeting + budget
        if (empty($ids['adset_id'])) {
            $budgetMinor = (int) round(((float) $campaign->daily_budget) * 100);
            if ($budgetMinor <= 0) {
                throw new AdApiUnavailableException('A daily budget is required to build the ad set.');
            }

            $targeting = [
                'age_min' => (int) ($adsetCfg['age_min'] ?? 18),
                'age_max' => (int) ($adsetCfg['age_max'] ?? 65),
                'geo_locations' => ['countries' => $adsetCfg['countries'] ?? ['IN']],
            ];
            if (! empty($adsetCfg['genders'])) {
                $targeting['genders'] = array_map('intval', (array) $adsetCfg['genders']);
            }
            $interests = $this->resolveInterests($adsetCfg['interests'] ?? [], $token);
            if ($interests) {
                $targeting['flexible_spec'] = [['interests' => $interests]];
            }

            $ids['adset_id'] = $this->post($accountId . '/adsets', $token, [
                'name' => $campaign->name . ' — Ad Set',
                'campaign_id' => $campaignId,
                'daily_budget' => $budgetMinor,
                'billing_event' => $adsetCfg['billing_event'] ?? 'IMPRESSIONS',
                'optimization_goal' => $adsetCfg['optimization_goal'] ?? $this->defaultOptimization($objective),
                'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
                'targeting' => json_encode($targeting),
                'status' => 'PAUSED',
            ], 'ad set creation');
            $this->persist($campaign, $meta, $ids);
        }

        // 3) Ad Creative + 4) Ad — only when a Page ID + media are present.
        $pageId = $creativeCfg['page_id'] ?? $account->page_id;
        $hasMedia = $type === 'video'
            ? ! empty($ids['video_id'])
            : (! empty($creativeCfg['image_path']) || ! empty($creativeCfg['image_url']));

        if ($pageId && $hasMedia && empty($ids['ad_id'])) {
            if (empty($ids['creative_id'])) {
                $storySpec = $type === 'video'
                    ? $this->videoStorySpec($accountId, $token, $pageId, $creativeCfg, $campaign, $ids)
                    : $this->imageStorySpec($accountId, $token, $pageId, $creativeCfg, $campaign, $ids);

                $ids['creative_id'] = $this->post($accountId . '/adcreatives', $token, [
                    'name' => $campaign->name . ' — Creative',
                    'object_story_spec' => json_encode($storySpec),
                ], 'ad creative creation');
                $this->persist($campaign, $meta, $ids);
            }

            $ids['ad_id'] = $this->post($accountId . '/ads', $token, [
                'name' => $campaign->name . ' — Ad',
                'adset_id' => $ids['adset_id'],
                'creative' => json_encode(['creative_id' => $ids['creative_id']]),
                'status' => 'PAUSED',
            ], 'ad creation');
            $this->persist($campaign, $meta, $ids);
        }

        return $campaignId;
    }

    /** Build object_story_spec.link_data for an IMAGE creative (uploads image). */
    private function imageStorySpec(string $accountId, string $token, string $pageId, array $cfg, MarketingCampaign $campaign, array &$ids): array
    {
        $bytes = $this->creativeImageBytes($cfg);
        if (! $bytes) {
            throw new AdApiUnavailableException('The selected creative image could not be read.');
        }
        $ids['image_hash'] = $this->uploadImage($accountId, $token, $bytes);
        $link = $cfg['link'] ?? $campaign->landing_page;

        return [
            'page_id' => $pageId,
            'link_data' => array_filter([
                'message' => $cfg['message'] ?? null,
                'name' => $cfg['headline'] ?? null,
                'description' => $cfg['description'] ?? null,
                'link' => $link,
                'image_hash' => $ids['image_hash'],
                'call_to_action' => ['type' => $cfg['cta'] ?? 'LEARN_MORE', 'value' => ['link' => $link]],
            ]),
        ];
    }

    /** Build object_story_spec.video_data for a VIDEO creative (needs a thumbnail). */
    private function videoStorySpec(string $accountId, string $token, string $pageId, array $cfg, MarketingCampaign $campaign, array &$ids): array
    {
        $link = $cfg['link'] ?? $campaign->landing_page;

        // Thumbnail: use an uploaded/URL image if given, else Meta's auto thumbnail.
        $thumb = [];
        if (! empty($cfg['image_path']) || ! empty($cfg['image_url'])) {
            $bytes = $this->creativeImageBytes($cfg);
            if ($bytes) {
                $ids['image_hash'] = $this->uploadImage($accountId, $token, $bytes);
                $thumb['image_hash'] = $ids['image_hash'];
            }
        }
        if (empty($thumb)) {
            $auto = $this->autoThumbnail($ids['video_id'], $token);
            if ($auto) {
                $thumb['image_url'] = $auto;
            }
        }

        return [
            'page_id' => $pageId,
            'video_data' => array_filter([
                'video_id' => $ids['video_id'],
                'title' => $cfg['headline'] ?? null,
                'message' => $cfg['message'] ?? null,
                'link_description' => $cfg['description'] ?? null,
                'image_url' => $thumb['image_url'] ?? null,
                'image_hash' => $thumb['image_hash'] ?? null,
                'call_to_action' => ['type' => $cfg['cta'] ?? 'LEARN_MORE', 'value' => ['link' => $link]],
            ]),
        ];
    }

    public function pauseCampaign(MarketingCampaign $campaign): void
    {
        $this->setStatus($campaign, 'PAUSED');
    }

    public function activateCampaign(MarketingCampaign $campaign): void
    {
        // This is what starts spending — caller must have authorized it.
        $this->setStatus($campaign, 'ACTIVE');
    }

    public function updateBudget(MarketingCampaign $campaign, float $dailyBudget): void
    {
        [, $token] = $this->accountAndToken($campaign);
        $this->assertExternal($campaign);

        $response = Http::acceptJson()->asForm()
            ->post("https://graph.facebook.com/{$this->version()}/{$campaign->external_campaign_id}", [
                'daily_budget' => (int) round($dailyBudget * 100),
                'access_token' => $token,
            ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Meta budget update failed: ' . $this->error($response));
        }
    }

    public function fetchMetrics(MarketingCampaign $campaign, string $from, string $to): array
    {
        [, $token] = $this->accountAndToken($campaign);
        $this->assertExternal($campaign);

        $response = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/{$campaign->external_campaign_id}/insights", [
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
            'fields' => 'impressions,clicks,spend,actions,cpc,ctr,reach',
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Meta Insights failed: ' . $this->error($response));
        }

        return $response->json('data', []);
    }

    // ---- helpers ------------------------------------------------------------

    private function setStatus(MarketingCampaign $campaign, string $status): void
    {
        [, $token] = $this->accountAndToken($campaign);
        $this->assertExternal($campaign);

        $response = Http::acceptJson()->asForm()
            ->post("https://graph.facebook.com/{$this->version()}/{$campaign->external_campaign_id}", [
                'status' => $status,
                'access_token' => $token,
            ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException("Meta status change to {$status} failed: " . $this->error($response));
        }
    }

    /** Resolve the connected account and its access token, or fail clearly. */
    private function accountAndToken(MarketingCampaign $campaign): array
    {
        $this->assertEnabled();

        $account = $campaign->account;
        if (! $account) {
            throw new AdApiUnavailableException('Link a connected Meta ad account to this campaign first.');
        }

        $token = data_get(optional($account->connection)->credentials, 'access_token');
        if (! $token) {
            throw new AdApiUnavailableException('The linked Meta account has no valid connection/token — reconnect it on Ad Accounts.');
        }

        return [$account, $token];
    }

    private function assertExternal(MarketingCampaign $campaign): void
    {
        if (! $campaign->external_campaign_id) {
            throw new AdApiUnavailableException('This campaign has not been published to Meta yet.');
        }
    }

    /** Ensure the ad-account id is in act_{id} form for the Graph edge. */
    private function actId(string $externalId): string
    {
        return str_starts_with($externalId, 'act_') ? $externalId : 'act_' . $externalId;
    }

    /** Map our human objective to a Meta ODAX objective enum. */
    private function mapObjective(?string $objective): string
    {
        return match (strtolower(trim((string) $objective))) {
            'leads', 'whatsapp conversations' => 'OUTCOME_LEADS',
            'sales', 'conversions' => 'OUTCOME_SALES',
            'awareness' => 'OUTCOME_AWARENESS',
            'website traffic', 'traffic' => 'OUTCOME_TRAFFIC',
            'engagement' => 'OUTCOME_ENGAGEMENT',
            default => 'OUTCOME_TRAFFIC',
        };
    }

    private function error(\Illuminate\Http\Client\Response $response): string
    {
        return (string) ($response->json('error.message') ?? ('HTTP ' . $response->status()));
    }

    /** POST a Graph edge, return the created object's id, or throw with context. */
    private function post(string $edge, string $token, array $payload, string $what): string
    {
        $payload['access_token'] = $token;
        $response = Http::acceptJson()->asForm()
            ->post("https://graph.facebook.com/{$this->version()}/{$edge}", $payload);

        if (! $response->successful() || ! $response->json('id')) {
            throw new AdApiUnavailableException("Meta {$what} failed: " . $this->error($response));
        }

        return (string) $response->json('id');
    }

    /** Upload raw image bytes to the ad account, return the image_hash. */
    private function uploadImage(string $accountId, string $token, string $bytes): string
    {
        $response = Http::acceptJson()
            ->attach('file', $bytes, 'creative.jpg')
            ->post("https://graph.facebook.com/{$this->version()}/{$accountId}/adimages", [
                'access_token' => $token,
            ]);

        if (! $response->successful()) {
            throw new AdApiUnavailableException('Meta image upload failed: ' . $this->error($response));
        }

        // Response shape: { "images": { "<filename>": { "hash": "...", ... } } }
        $images = $response->json('images', []);
        $first = is_array($images) ? reset($images) : null;
        $hash = $first['hash'] ?? null;
        if (! $hash) {
            throw new AdApiUnavailableException('Meta image upload returned no hash.');
        }

        return (string) $hash;
    }

    /** Get the creative image bytes from an upload path or a package image URL. */
    private function creativeImageBytes(array $creativeCfg): ?string
    {
        $path = $creativeCfg['image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        $url = $creativeCfg['image_url'] ?? null;
        if ($url) {
            $r = Http::get($url);
            if ($r->successful()) {
                return $r->body();
            }
        }

        return null;
    }

    /**
     * Resolve interest keywords to Meta interest {id,name} via the Targeting
     * Search API. Unknown keywords are skipped (never fabricated).
     *
     * @param  array<int,string>  $keywords
     * @return array<int,array{id:string,name:string}>
     */
    private function resolveInterests(array $keywords, string $token): array
    {
        $out = [];
        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') {
                continue;
            }
            $r = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/search", [
                'type' => 'adinterest',
                'q' => $kw,
                'limit' => 1,
                'access_token' => $token,
            ]);
            $hit = $r->successful() ? ($r->json('data.0') ?? null) : null;
            if ($hit && ! empty($hit['id'])) {
                $out[] = ['id' => (string) $hit['id'], 'name' => (string) ($hit['name'] ?? $kw)];
            }
        }

        return $out;
    }

    /** A compatible ad-set optimization goal for a given campaign objective. */
    private function defaultOptimization(string $objective): string
    {
        return match ($objective) {
            'OUTCOME_AWARENESS' => 'REACH',
            'OUTCOME_ENGAGEMENT' => 'POST_ENGAGEMENT',
            default => 'LINK_CLICKS', // traffic / leads / sales without a pixel/form
        };
    }

    /** Persist the created Meta object ids back onto the campaign meta (resumable). */
    private function persist(MarketingCampaign $campaign, array $meta, array $ids): void
    {
        $meta['meta_ids'] = $ids;
        $campaign->forceFill(['meta' => $meta])->save();
    }

    /** Read creative video bytes from the stored upload path. */
    private function creativeVideoBytes(array $cfg): ?string
    {
        $path = $cfg['video_path'] ?? null;

        return ($path && Storage::disk('public')->exists($path))
            ? Storage::disk('public')->get($path)
            : null;
    }

    /** Upload a video to the ad account; returns the video id (still processing). */
    private function uploadVideo(string $accountId, string $token, string $bytes): string
    {
        $response = Http::acceptJson()->timeout(120)
            ->attach('source', $bytes, 'creative.mp4')
            ->post("https://graph.facebook.com/{$this->version()}/{$accountId}/advideos", [
                'access_token' => $token,
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new AdApiUnavailableException('Meta video upload failed: ' . $this->error($response));
        }

        return (string) $response->json('id');
    }

    /**
     * Poll the video's processing status. Meta processes asynchronously; short
     * promo clips are usually ready within a minute. If it isn't ready inside the
     * window we throw a clear "retry publish shortly" message — the build resumes
     * from the stored video_id, so retrying does not duplicate anything.
     */
    private function assertVideoReady(string $videoId, string $token, int $maxSeconds = 75): void
    {
        $deadline = microtime(true) + $maxSeconds;
        do {
            $r = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/{$videoId}", [
                'fields' => 'status',
                'access_token' => $token,
            ]);
            $status = $r->json('status.video_status');
            if ($status === 'ready') {
                return;
            }
            if ($status === 'error') {
                throw new AdApiUnavailableException('Meta could not process the uploaded video. Try a different file (MP4 recommended).');
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            sleep(5);
        } while (true);

        throw new AdApiUnavailableException('Video is still processing on Meta. Click Publish again in a minute — it will resume without duplicating the campaign.');
    }

    /** Fetch a preferred/auto thumbnail URL for a processed video, if any. */
    private function autoThumbnail(string $videoId, string $token): ?string
    {
        $r = Http::acceptJson()->get("https://graph.facebook.com/{$this->version()}/{$videoId}/thumbnails", [
            'fields' => 'uri,is_preferred',
            'access_token' => $token,
        ]);
        if (! $r->successful()) {
            return null;
        }
        $thumbs = $r->json('data', []);
        foreach ($thumbs as $t) {
            if (! empty($t['is_preferred']) && ! empty($t['uri'])) {
                return $t['uri'];
            }
        }

        return $thumbs[0]['uri'] ?? null;
    }

    private function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new AdApiUnavailableException('Meta Ads is not enabled/configured — add credentials on Manage credentials.');
        }
    }
}
