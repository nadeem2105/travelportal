<?php

namespace App\Events\Crm;

use App\Models\CrmLead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CrmLead $lead, public ?int $assignedTo = null)
    {
    }
}
