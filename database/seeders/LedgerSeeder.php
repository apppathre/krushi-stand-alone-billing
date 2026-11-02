<?php

namespace Database\Seeders;

use App\Models\AccountMap;
use App\Models\Ledger;
use Illuminate\Database\Seeder;

class LedgerSeeder extends Seeder
{
    public function run(): void
    {
        // slug => [name, name_mr, family, type, system]
        $defaults = [
            'cash' => ['Cash in Hand', 'रोख', 'account', 'cash'],
            'bank' => ['Bank Account', 'बँक खाते', 'account', 'bank'],
            'walk_in' => ['Walk-in Customer', 'किरकोळ ग्राहक', 'party', 'client'],
            'sales' => ['Sales', 'विक्री', 'account', 'sales'],
            'sales_return' => ['Sales Return', 'विक्री परतावा', 'account', 'sales'],
            'purchase' => ['Purchases', 'खरेदी', 'account', 'purchase'],
            'cgst_out' => ['CGST Output', 'सीजीएसटी देय', 'account', 'tax'],
            'sgst_out' => ['SGST Output', 'एसजीएसटी देय', 'account', 'tax'],
            'igst_out' => ['IGST Output', 'आयजीएसटी देय', 'account', 'tax'],
            'cgst_in' => ['CGST Input', 'सीजीएसटी क्रेडिट', 'account', 'tax'],
            'sgst_in' => ['SGST Input', 'एसजीएसटी क्रेडिट', 'account', 'tax'],
            'igst_in' => ['IGST Input', 'आयजीएसटी क्रेडिट', 'account', 'tax'],
            'round_off' => ['Round Off', 'पूर्णांक', 'account', 'round_off'],
            'discount' => ['Discount', 'सवलत', 'account', 'discount'],
            'commission_expense' => ['Sales Commission', 'विक्री कमिशन', 'account', 'expense'],
            'commission_payable' => ['Commission Payable', 'कमिशन देय', 'account', 'other'],
            'rent' => ['Shop Rent', 'दुकान भाडे', 'account', 'expense'],
            'electricity' => ['Electricity', 'वीज बिल', 'account', 'expense'],
            'transport' => ['Transport', 'वाहतूक', 'account', 'expense'],
            'hammali' => ['Hammali / Loading', 'हमाली / लोडिंग', 'account', 'expense'],
            'salary' => ['Staff Salary', 'कर्मचारी पगार', 'account', 'expense'],
            'misc_charges' => ['Other Charges', 'इतर खर्च', 'account', 'expense'],
            'other_income' => ['Other Income', 'इतर उत्पन्न', 'account', 'income'],
            'interest_income' => ['Interest Income', 'व्याज उत्पन्न', 'account', 'income'],
            'capital' => ['Capital Account', 'भांडवल', 'account', 'capital'],
        ];

        $ids = [];
        foreach ($defaults as $slug => [$name, $mr, $family, $type]) {
            $ids[$slug] = Ledger::updateOrCreate(
                ['name' => $name, 'family' => $family],
                ['name_mr' => $mr, 'type' => $type, 'is_system' => true, 'is_active' => true, 'state_code' => $slug === 'walk_in' ? '27' : null],
            )->id;
        }

        $map = AccountMap::current();
        if (! $map->cash_ledger_id) {
            $map->update([
                'cash_ledger_id' => $ids['cash'],
                'bank_ledger_id' => $ids['bank'],
                'walk_in_ledger_id' => $ids['walk_in'],
                'sales_ledger_id' => $ids['sales'],
                'sales_return_ledger_id' => $ids['sales_return'],
                'purchase_ledger_id' => $ids['purchase'],
                'cgst_output_ledger_id' => $ids['cgst_out'],
                'sgst_output_ledger_id' => $ids['sgst_out'],
                'igst_output_ledger_id' => $ids['igst_out'],
                'cgst_input_ledger_id' => $ids['cgst_in'],
                'sgst_input_ledger_id' => $ids['sgst_in'],
                'igst_input_ledger_id' => $ids['igst_in'],
                'round_off_ledger_id' => $ids['round_off'],
                'discount_ledger_id' => $ids['discount'],
                'freight_ledger_id' => $ids['transport'],
                'hammali_ledger_id' => $ids['hammali'],
                'commission_expense_ledger_id' => $ids['commission_expense'],
                'commission_payable_ledger_id' => $ids['commission_payable'],
            ]);
        } else {
            $map->fill([
                'commission_expense_ledger_id' => $map->commission_expense_ledger_id ?: $ids['commission_expense'],
                'commission_payable_ledger_id' => $map->commission_payable_ledger_id ?: $ids['commission_payable'],
                'freight_ledger_id' => $map->freight_ledger_id ?: $ids['transport'],
                'hammali_ledger_id' => $map->hammali_ledger_id ?: $ids['hammali'],
            ])->save();
        }

        // UPI / wallet instruments (editable bank ledgers — pick on receipt & payment screens)
        foreach ([
            ['GPay', 'जीपे', 'bank'],
            ['PhonePe', 'फोनपे', 'bank'],
        ] as [$name, $mr, $type]) {
            Ledger::firstOrCreate(
                ['name' => $name, 'family' => 'account'],
                ['name_mr' => $mr, 'type' => $type, 'is_system' => false, 'is_active' => true],
            );
        }

        // Sample parties
        $parties = [
            ['Ramesh Patil', 'रमेश पाटील', 'client', '9811111111', 'Wadgaon', 'Pune', '27', null],
            ['Sunita Shinde', 'सुनीता शिंदे', 'client', '9822222222', 'Shirur', 'Pune', '27', null],
            ['Mahadhan Agro Distributors', 'महाधन अ‍ॅग्रो वितरक', 'supplier', '9833333333', null, 'Pune', '27', '27AAACM1234A1Z2'],
            ['Gujarat Crop Care Pvt Ltd', 'गुजरात क्रॉप केअर', 'supplier', '9844444444', null, 'Ahmedabad', '24', '24AABCG5678B1Z9'],
        ];
        foreach ($parties as [$name, $mr, $type, $phone, $village, $city, $state, $gstin]) {
            Ledger::firstOrCreate(['name' => $name, 'family' => 'party'], [
                'name_mr' => $mr, 'type' => $type, 'phone' => $phone, 'village' => $village,
                'city' => $city, 'state_code' => $state, 'gstin' => $gstin,
                'opening_balance' => 0,
                'opening_type' => $type === 'supplier' ? 'cr' : 'dr',
                'credit_days' => 30,
                'credit_limit' => 0,
                'is_active' => true,
            ]);
        }
    }
}
