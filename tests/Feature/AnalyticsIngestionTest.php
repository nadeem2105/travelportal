<?php

namespace Tests\Feature;

use App\Jobs\ProcessAnalyticsBatch;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsPageView;
use App\Models\AnalyticsSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_event_batch_is_dispatched_to_queue(): void
    {
        Queue::fake();

        $payload = [
            'events' => [
                [
                    'name' => 'page_view',
                    'event_id' => 'evt_test_1',
                    'params' => ['url' => '/packages'],
                ],
                [
                    'name' => 'view_item',
                    'event_id' => 'evt_test_2',
                    'product_type' => 'package',
                    'product_id' => 123,
                    'value' => 45000,
                    'currency' => 'INR',
                ],
            ],
            'session_id' => 'test-session-123',
            'anonymous_id' => 'test-anon-456',
        ];

        $response = $this->postJson('/api/v1/analytics/event', $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'queued' => 2,
            ],
        ]);

        Queue::assertPushed(ProcessAnalyticsBatch::class, function ($job) {
            return count($job->events) === 2
                && $job->sessionId === 'test-session-123'
                && $job->anonymousId === 'test-anon-456';
        });
    }

    public function test_process_analytics_batch_job_inserts_events_and_deduplicates(): void
    {
        $events = [
            [
                'name' => 'button_click',
                'event_id' => 'evt_unique_1',
                'params' => ['button' => 'book_now'],
            ],
            [
                'name' => 'scroll_milestone',
                'event_id' => 'evt_unique_2',
                'params' => ['milestone' => 50],
            ],
        ];

        $job = new ProcessAnalyticsBatch(
            events: $events,
            sessionId: 'test-session-xyz',
            anonymousId: 'test-anon-xyz',
            ip: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            userId: null,
        );

        $job->handle();

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'button_click',
            'event_id' => 'evt_unique_1',
            'session_id' => 'test-session-xyz',
        ]);

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'scroll_milestone',
            'event_id' => 'evt_unique_2',
            'session_id' => 'test-session-xyz',
        ]);

        // Running again with identical event_ids should not duplicate rows
        $job->handle();

        $count = AnalyticsEvent::whereIn('event_id', ['evt_unique_1', 'evt_unique_2'])->count();
        $this->assertEquals(2, $count);
    }

    public function test_page_view_creation_and_engagement_patch_with_client_page_view_id(): void
    {
        $clientPvId = 'pv_client_uuid_999';

        // 1. Initial page view
        $res1 = $this->postJson('/api/v1/analytics/page-view', [
            'session_id' => 'session_abc',
            'anonymous_id' => 'anon_def',
            'page_view_id' => $clientPvId,
            'url' => 'https://example.com/packages/kashmir',
            'path' => '/packages/kashmir',
            'title' => 'Kashmir Tour',
        ]);

        $res1->assertOk();
        $res1->assertJson([
            'success' => true,
            'data' => [
                'page_view_id' => $clientPvId,
            ],
        ]);

        $this->assertDatabaseHas('analytics_page_views', [
            'page_view_id' => $clientPvId,
            'path' => '/packages/kashmir',
            'time_on_page' => null,
        ]);

        // 2. Engagement patch on unload
        $res2 = $this->postJson('/api/v1/analytics/page-view', [
            'session_id' => 'session_abc',
            'page_view_id' => $clientPvId,
            'time_on_page' => 45,
            'scroll_depth' => 75,
            'is_exit' => true,
        ]);

        $res2->assertOk();

        $this->assertDatabaseHas('analytics_page_views', [
            'page_view_id' => $clientPvId,
            'time_on_page' => 45,
            'scroll_depth' => 75,
            'is_exit' => 1,
        ]);
    }

    public function test_session_enrichment_updates_existing_session(): void
    {
        AnalyticsSession::create([
            'session_id' => 'sess_enrich_123',
            'device_type' => 'desktop',
            'channel' => 'direct',
        ]);

        $response = $this->postJson('/api/v1/analytics/session', [
            'session_id' => 'sess_enrich_123',
            'anonymous_id' => 'anon_enrich_456',
            'screen' => '1920x1080',
            'language' => 'en-US',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('analytics_sessions', [
            'session_id' => 'sess_enrich_123',
            'anonymous_id' => 'anon_enrich_456',
            'screen' => '1920x1080',
            'language' => 'en-US',
        ]);
    }
}
