<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Services\PartyStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyOutreachController extends Controller
{
    /**
     * Build Call / WhatsApp / statement link for a party (outstanding follow-up).
     * Uses master WhatsApp template; optional statement link via encrypted token.
     */
    public function compose(Request $request, Ledger $ledger, PartyStatementService $svc): JsonResponse
    {
        abort_unless(
            $request->user()->canMenu('report_outstanding', 'view')
            || $request->user()->canMenu('clients', 'view')
            || $request->user()->canMenu('suppliers', 'view'),
            403
        );

        $data = $request->validate([
            'with_link' => ['sometimes', 'boolean'],
            'include_bills' => ['sometimes', 'boolean'],
            'allow_payment' => ['sometimes', 'boolean'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $payload = $svc->issue($ledger, $data, $request->user()?->id);

        return response()->json([
            'ok' => true,
            'party' => $ledger->display_name,
            'phone' => $ledger->phone,
            'message' => $payload['message'],
            'url' => $payload['url'],
            'wa_url' => $payload['wa_url'],
            'tel_url' => $payload['tel_url'],
            'balance' => $payload['balance'],
            'balance_fmt' => money($payload['balance']),
            'expires_at' => $payload['token']?->expires_at?->toDateTimeString(),
        ]);
    }
}
