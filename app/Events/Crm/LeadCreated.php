<?php

namespace App\Events\Crm;

use App\Models\CrmLead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CrmLead $lead)
    {
    }
}
