<?php

namespace App\Events\Crm;

use App\Models\CrmLead;
use App\Models\PipelineStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StageChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public CrmLead $lead,
        public ?PipelineStage $from = null,
        public ?PipelineStage $to = null,
    ) {
    }
}
