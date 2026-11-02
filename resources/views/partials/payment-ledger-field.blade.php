{{-- Cash / bank / UPI wallet ledger picker. Manage instruments under Ledgers → Accounts (type Cash or Bank). --}}
@php
    $fieldName = $name ?? 'ledger_id';
    $ledgerOptions = $paymentLedgerOptions ?? \App\Models\Ledger::paymentInstrumentOptions();
    $defaultLedger = old($fieldName, $value ?? (\App\Models\AccountMap::current()->cash_ledger_id));
@endphp
<x-select-field :name="$fieldName" :label="$label ?? __('Received in / Paid from')" :value="$defaultLedger" :options="$ledgerOptions" :required="$required ?? true"
    :hint="$hint ?? __('Cash, bank and UPI wallets (GPay, PhonePe…) — add more under Ledgers → Accounts as Bank.')" />
