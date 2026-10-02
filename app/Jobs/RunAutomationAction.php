<?php

namespace App\Jobs;

use App\Models\AutomationAction;
use App\Models\CrmLead;
use App\Services\Crm\AutomationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a single delayed automation action after its wait period. Kept separate
 * from the immediate path so scheduled ("wait N days") steps survive restarts.
 */
class RunAutomationAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $actionId, public int $leadId)
    {
    }

    public function handle(AutomationEngine $engine): void
    {
        $action = AutomationAction::with('workflow')->find($this->actionId);
        $lead = CrmLead::find($this->leadId);

        if (! $action || ! $lead || ! $action->workflow?->is_active) {
            return;
        }

        $engine->runAction($action, $lead);
    }
}
