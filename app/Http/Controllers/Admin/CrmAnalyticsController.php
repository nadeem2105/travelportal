<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CRM analytics — leads, funnel, quotation win-rate, revenue, WhatsApp and
 * agent performance over a selectable date range. All aggregates are computed
 * server-side; the view renders them with cards + CSS bars (no JS dependency).
 */
class CrmAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $data = [
            'from' => $from,
            'to' => $to,
            'preset' => $request->query('preset', '30d'),

            'leadTotals' => $this->leadTotals($from, $to),
            'leadsBySource' => $this->leadsBySource($from, $to),
            'leadsByStatus' => $this->leadsByStatus($from, $to),
            'leadsByStage' => $this->leadsByStage($from, $to),
            'funnel' => $this->funnel($from, $to),
            'quotationStats' => $this->quotationStats($from, $to),
            'revenue' => $this->revenue($from, $to),
            'whatsapp' => $this->whatsappStats($from, $to),
            'agents' => $this->agentPerformance($from, $to),
        ];

        return view('admin.crm.analytics.index', $data);
    }

    /** Resolve [from, to] Carbon range from preset or explicit dates. */
    private function range(Request $request): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $from = match ($request->query('preset', '30d')) {
            '7d' => now()->subDays(7)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            'ytd' => now()->startOfYear(),
            'all' => Carbon::createFromTimestamp(0),
            'custom' => $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };

        return [$from, $to];
    }

    private function leadTotals(Carbon $from, Carbon $to): array
    {
        $base = CrmLead::whereBetween('created_at', [$from, $to]);

        return [
            'total' => (clone $base)->count(),
            'converted' => (clone $base)->where('status', 'converted')->count(),
            'lost' => (clone $base)->where('status', 'lost')->count(),
            'open' => (clone $base)->whereNotIn('status', ['converted', 'lost'])->count(),
            'avg_score' => round((float) (clone $base)->avg('score'), 1),
        ];
    }

    private function leadsBySource(Carbon $from, Carbon $to)
    {
        return CrmLead::whereBetween('crm_leads.created_at', [$from, $to])
            ->leftJoin('lead_sources', 'crm_leads.source_id', '=', 'lead_sources.id')
            ->selectRaw('COALESCE(lead_sources.name, crm_leads.source, "Unknown") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(12)
            ->pluck('total', 'label');
    }

    private function leadsByStatus(Carbon $from, Carbon $to)
    {
        return CrmLead::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status');
    }

    private function leadsByStage(Carbon $from, Carbon $to)
    {
        return CrmLead::whereBetween('crm_leads.created_at', [$from, $to])
            ->leftJoin('pipeline_stages', 'crm_leads.stage_id', '=', 'pipeline_stages.id')
            ->selectRaw('COALESCE(pipeline_stages.name, "Unassigned") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->pluck('total', 'label');
    }

    private function funnel(Carbon $from, Carbon $to): array
    {
        $leads = CrmLead::whereBetween('created_at', [$from, $to])->count();
        $quoted = CrmQuotation::whereBetween('created_at', [$from, $to])->distinct('lead_id')->count('lead_id');
        $accepted = CrmQuotation::whereBetween('created_at', [$from, $to])->whereIn('status', ['accepted', 'converted'])->count();
        $converted = CrmLead::whereBetween('created_at', [$from, $to])->where('status', 'converted')->count();

        return compact('leads', 'quoted', 'accepted', 'converted');
    }

    private function quotationStats(Carbon $from, Carbon $to): array
    {
        $base = CrmQuotation::whereBetween('created_at', [$from, $to]);
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $sent = (int) $byStatus->except(['draft'])->sum();
        $won = (int) ($byStatus['accepted'] ?? 0) + (int) ($byStatus['converted'] ?? 0);

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => $byStatus,
            'sent' => $sent,
            'won' => $won,
            'win_rate' => $sent > 0 ? round($won / $sent * 100, 1) : 0.0,
            'value_sent' => (float) (clone $base)->whereNotIn('status', ['draft'])->sum('total_amount'),
            'value_won' => (float) (clone $base)->whereIn('status', ['accepted', 'converted'])->sum('total_amount'),
        ];
    }

    private function revenue(Carbon $from, Carbon $to): array
    {
        // Confirmed booking revenue in range (authoritative), and the slice that
        // originated from a CRM quotation conversion.
        $confirmed = Booking::where('status', 'confirmed')->whereBetween('booked_at', [$from, $to]);
        $fromQuotes = (float) CrmQuotation::where('status', 'converted')
            ->whereBetween('updated_at', [$from, $to])
            ->sum('total_amount');

        return [
            'confirmed_total' => (float) (clone $confirmed)->sum('total_amount'),
            'confirmed_count' => (clone $confirmed)->count(),
            'from_quotations' => $fromQuotes,
        ];
    }

    private function whatsappStats(Carbon $from, Carbon $to): array
    {
        $msgs = WhatsAppMessage::whereBetween('created_at', [$from, $to]);

        return [
            'inbound' => (clone $msgs)->where('direction', 'inbound')->count(),
            'outbound' => (clone $msgs)->where('direction', 'outbound')->count(),
            'failed' => (clone $msgs)->where('status', 'failed')->count(),
        ];
    }

    private function agentPerformance(Carbon $from, Carbon $to)
    {
        return CrmLead::whereBetween('crm_leads.created_at', [$from, $to])
            ->whereNotNull('assigned_to')
            ->leftJoin('admins', 'crm_leads.assigned_to', '=', 'admins.id')
            ->selectRaw('admins.name as agent, COUNT(*) as leads, SUM(CASE WHEN crm_leads.status = "converted" THEN 1 ELSE 0 END) as converted')
            ->groupBy('agent')
            ->orderByDesc('leads')
            ->limit(15)
            ->get();
    }
}
