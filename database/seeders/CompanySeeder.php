<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\DocumentSeries;
use App\Models\FinancialYear;
use App\Models\Godown;
use App\Models\MailSetting;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        if (! CompanyProfile::query()->exists()) {
            CompanyProfile::create([
                'name' => 'Shree Prasad Krushi Seva Kendra',
                'name_mr' => 'श्री प्रसाद कृषी सेवा केंद्र',
                'short_code' => 'SPK',
                'tagline' => 'Seeds, Fertilizers, Crop Protection & Equipment',
                'address' => 'Main Road, Market Yard',
                'city' => 'Pune',
                'state_code' => '27',
                'state_name' => 'Maharashtra',
                'pincode' => '411001',
                'phone' => '9800000000',
                'email' => 'owner@krushi.local',
                'gstin' => '27ABCDE1234F1Z5',
                'pan' => 'ABCDE1234F',
                'seed_licence_no' => 'SL/PN/0001',
                'fertilizer_licence_no' => 'FL/PN/0001',
                'pesticide_licence_no' => 'PL/PN/0001',
                'invoice_terms' => "Goods once sold will not be taken back except as per return policy.\nSubject to Pune jurisdiction.",
            ]);
        }

        if (! MailSetting::query()->exists()) {
            MailSetting::create([
                'is_enabled' => false,
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'from_address' => 'owner@krushi.local',
                'from_name' => 'Shree Prasad Krushi Seva Kendra',
            ]);
        }

        foreach ([['Main Store', 'मुख्य दुकान', 'MAIN'], ['Godown 2', 'गोदाम २', 'GD2']] as [$name, $mr, $code]) {
            Godown::updateOrCreate(['code' => $code], ['name' => $name, 'name_mr' => $mr, 'is_active' => true]);
        }

        $fy = FinancialYear::forDate(now());
        FinancialYear::query()->update(['is_current' => false]);
        $fy->update(['is_current' => true]);

        foreach (\App\Support\TransactionTypes::all() as $type => $meta) {
            DocumentSeries::firstOrCreate(
                ['doc_type' => $type, 'financial_year_id' => $fy->id, 'is_default' => true],
                [
                    'name' => $meta['label'],
                    'prefix' => $meta['prefix'],
                    'next_number' => 1,
                    'padding' => 4,
                    'is_active' => true,
                ],
            );
        }
    }
}
