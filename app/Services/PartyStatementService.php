<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Ledger;
use App\Models\PartyStatementToken;
use App\Models\Purchase;
use App\Models\Sale;
use App\Support\LedgerBalance;
use Illuminate\Support\Str;

class PartyStatementService
{
    public function defaultWhatsappTemplate(): string
    {
        return "Namaskar {client},\n"
            ."{shop} — your outstanding balance is ₹{amount}.\n"
            ."Open your statement: {link}\n"
            .'Thank you.';
    }

    public function fillMessage(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{'.$key.'}', (string) $value, $out);
        }

        // Drop empty "Open your statement:" lines when link is blank.
        $out = preg_replace("/\n?Open your statement:\s*\n?/u", "\n", $out) ?? $out;

        return trim(preg_replace("/\n{3,}/", "\n\n", $out) ?? $out);
    }

    /**
     * Create a public statement token and compose Call / WhatsApp payloads.
     *
     * @param  array{days?:int,include_bills?:bool,allow_payment?:bool,with_link?:bool}  $opts
     * @return array{token:?PartyStatementToken,url:string,message:string,wa_url:?string,tel_url:?string,balance:float}
     */
    public function issue(Ledger $party, array $opts = [], ?int $userId = null): array
    {
        $company = CompanyProfile::current();
        $withLink = array_key_exists('with_link', $opts)
            ? (bool) $opts['with_link']
            : (bool) ($company->statement_link_enabled ?? true);
        $days = max(1, (int) ($opts['days'] ?? $company->statement_link_days ?? 7));
        $includeBills = array_key_exists('include_bills', $opts)
            ? (bool) $opts['include_bills']
            : true;
        $allowPayment = array_key_exists('allow_payment', $opts)
            ? (bool) $opts['allow_payment']
            : (bool) ($company->statement_allow_payment ?? true);

        $token = null;
        $url = '';
        if ($withLink) {
            $token = PartyStatementToken::create([
                'ledger_id' => $party->id,
                'token' => Str::random(48),
                'expires_at' => now()->addDays($days),
                'include_bills' => $includeBills,
                'allow_payment' => $allowPayment,
                'created_by' => $userId,
            ]);
            $url = route('client-statement.show', ['token' => $token->token]);
        }

        $bal = $this->balanceFor($party);
        $template = filled($company->outstanding_wa_template)
            ? (string) $company->outstanding_wa_template
            : $this->defaultWhatsappTemplate();

        $message = $this->fillMessage($template, [
            'client' => $party->display_name,
            'shop' => $company->display_name,
            'amount' => money($bal['amount']),
            'link' => $url,
            'phone' => (string) ($company->phone ?? ''),
        ]);

        $phone = $this->whatsappDigits($party->phone);
        $waUrl = $phone
            ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message)
            : null;
        $telUrl = filled($party->phone)
            ? 'tel:'.preg_replace('/[^\d+]/', '', (string) $party->phone)
            : null;

        return [
            'token' => $token,
            'url' => $url,
            'message' => $message,
            'wa_url' => $waUrl,
            'tel_url' => $telUrl,
            'balance' => $bal['amount'],
        ];
    }

    public function findValid(string $token): ?PartyStatementToken
    {
        $row = PartyStatementToken::with('ledger')->where('token', $token)->first();
        if (! $row || $row->isExpired()) {
            return null;
        }

        return $row;
    }

    public function touch(PartyStatementToken $token): void
    {
        $token->update([
            'last_opened_at' => now(),
            'open_count' => (int) $token->open_count + 1,
        ]);
    }

    /** @return array{amount: float, side: string, label: string, bills: list<array>} */
    public function statement(Ledger $party, bool $includeBills = true): array
    {
        $bal = $this->balanceFor($party);
        $bills = [];
        if ($includeBills && in_array($party->type, ['client', 'both'], true)) {
            $bills = Sale::query()
                ->where('status', 'active')
                ->where('client_ledger_id', $party->id)
                ->openDue()
                ->orderBy('invoice_date')
                ->get(['id', 'invoice_no', 'invoice_date', 'due_date', 'grand_total', 'paid_amount', 'returned_amount'])
                ->map(fn (Sale $s) => [
                    'no' => $s->invoice_no,
                    'date' => optional($s->invoice_date)->toDateString(),
                    'due' => optional($s->due_date)->toDateString(),
                    'total' => (float) $s->grand_total,
                    'paid' => (float) $s->paid_amount,
                    'returned' => (float) ($s->returned_amount ?? 0),
                    'balance' => (float) $s->balance,
                    'kind' => 'sale',
                ])->all();
        }

        return [
            'amount' => $bal['amount'],
            'side' => $bal['side'],
            'label' => $bal['label'],
            'bills' => $bills,
        ];
    }

    /** Calendar dues for dashboard (bill due dates in range). */
    public function calendarDues(string $from, string $to, bool $recv = true, bool $pay = true): array
    {
        $events = [];
        if ($recv) {
            $sales = Sale::with('client:id,name,name_mr,phone')
                ->where('status', 'active')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $from)
                ->whereDate('due_date', '<=', $to)
                ->openDue()
                ->orderBy('due_date')
                ->limit(200)
                ->get();
            foreach ($sales as $s) {
                $events[] = [
                    'date' => optional($s->due_date)->toDateString(),
                    'title' => ($s->client?->display_name ?: __('Customer')).' · '.$s->invoice_no,
                    'amount' => (float) $s->balance,
                    'amount_fmt' => money((float) $s->balance),
                    'kind' => 'receivable',
                    'url' => route('sales.show', $s),
                ];
            }
        }

        if ($pay) {
            $purchases = Purchase::with('supplier:id,name,name_mr,phone')
                ->where('status', 'active')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $from)
                ->whereDate('due_date', '<=', $to)
                ->openDue()
                ->orderBy('due_date')
                ->limit(200)
                ->get();
            foreach ($purchases as $p) {
                $events[] = [
                    'date' => optional($p->due_date)->toDateString(),
                    'title' => ($p->supplier?->display_name ?: __('Supplier')).' · '.$p->purchase_no,
                    'amount' => (float) $p->balance,
                    'amount_fmt' => money((float) $p->balance),
                    'kind' => 'payable',
                    'url' => route('purchases.show', $p),
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    /** @return list<array{label:string,number:string,valid_till:?string,expired:bool,soon:bool}> */
    public function licenceCards(CompanyProfile $company): array
    {
        $cards = [];
        $map = [
            [__('Shop Act'), $company->shop_act_licence_no ?? null, $company->shop_act_valid_till ?? null],
            [__('FSSAI'), $company->fssai_licence_no ?? null, $company->fssai_valid_till ?? null],
            [__('Seed'), $company->seed_licence_no ?? null, $company->seed_licence_valid_till ?? null],
            [__('Fertilizer'), $company->fertilizer_licence_no ?? null, $company->fertilizer_licence_valid_till ?? null],
            [__('Pesticide'), $company->pesticide_licence_no ?? null, $company->pesticide_licence_valid_till ?? null],
        ];
        foreach ($map as [$label, $no, $till]) {
            if (! filled($no) && ! filled($till)) {
                continue;
            }
            $tillStr = $till ? (is_string($till) ? $till : $till->format('Y-m-d')) : null;
            $expired = $tillStr ? $tillStr < now()->toDateString() : false;
            $soon = $tillStr && ! $expired && $tillStr <= now()->addDays(30)->toDateString();
            $cards[] = [
                'label' => $label,
                'number' => (string) ($no ?: '—'),
                'valid_till' => $tillStr,
                'expired' => $expired,
                'soon' => $soon,
            ];
        }

        return $cards;
    }

    private function balanceFor(Ledger $party): array
    {
        return LedgerBalance::asOf((int) $party->id);
    }

    private function whatsappDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits) {
            return null;
        }
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }
}
