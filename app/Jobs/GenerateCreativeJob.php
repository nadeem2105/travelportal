<?php

namespace App\Jobs;

use App\Models\MarketingCreative;
use App\Services\Marketing\Creative\CreativeCompositor;
use App\Services\Marketing\Creative\CreativeFactCheckService;
use App\Services\Marketing\Creative\CreativeQualityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Renders a creative off the request thread (image generation + GD compositing
 * can be slow). Updates generation_status so the UI can poll. Retryable without
 * losing the creative record. Never throws to the user — failures are recorded
 * on the creative with a human-readable message.
 */
class GenerateCreativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 20;
    public int $timeout = 180;

    public function __construct(public int $creativeId)
    {
    }

    public function handle(
        CreativeCompositor $compositor,
        CreativeFactCheckService $factCheck,
        CreativeQualityService $quality,
    ): void {
        $creative = MarketingCreative::find($this->creativeId);
        if (! $creative) {
            return;
        }

        $creative->update(['generation_status' => 'processing', 'generation_error' => null]);

        try {
            $result = $compositor->compose($creative);

            $creative->fill([
                'render_path' => $result['render_path'],
                'thumb_path' => $result['thumb_path'],
                'width' => $result['width'],
                'height' => $result['height'],
                'generation_status' => 'completed',
            ]);
            $creative->save();

            // Post-generation validation (non-blocking warnings).
            $warnings = array_merge(
                $result['warnings'] ?? [],
                $factCheck->check($creative),
                $quality->check($creative),
            );
            $creative->update(['warnings' => array_values(array_unique($warnings))]);
        } catch (\Throwable $e) {
            Log::warning('Creative generation failed', ['creative' => $this->creativeId, 'error' => $e->getMessage()]);
            $creative->update([
                'generation_status' => 'failed',
                'generation_error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        MarketingCreative::where('id', $this->creativeId)->update([
            'generation_status' => 'failed',
            'generation_error' => 'Generation could not be completed. You can retry without losing your creative.',
        ]);
    }
}
