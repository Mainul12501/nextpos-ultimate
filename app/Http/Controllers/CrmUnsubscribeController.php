<?php

namespace App\Http\Controllers;

use App\Models\CrmLead;
use App\Models\CrmLeadEvent;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * The unsubscribe link in marketing emails sent to CRM leads (signed URL,
 * see CrmLeadSegmentResolver::unsubscribeUrl). One click opts the lead out
 * of every future campaign; the lead's history records it.
 */
class CrmUnsubscribeController extends Controller
{
    public function show(Request $request, int $lead)
    {
        $company = optional(Setting::whereNull('deleted_at')->first())->CompanyName;
        if (! $request->hasValidSignature()) {
            return response()->view('crm.unsubscribe', ['ok' => false, 'company' => $company], 403);
        }

        $row = CrmLead::withTrashed()->find($lead);
        if ($row && ! $row->marketing_opt_out) {
            CrmLead::whereKey($row->id)->update(['marketing_opt_out' => true, 'marketing_opt_out_at' => now()]);
            CrmLeadEvent::create(['crm_lead_id' => $row->id, 'type' => 'unsubscribed', 'meta' => ['via' => 'email link']]);
        }

        return response()->view('crm.unsubscribe', ['ok' => true, 'company' => $company]);
    }
}
