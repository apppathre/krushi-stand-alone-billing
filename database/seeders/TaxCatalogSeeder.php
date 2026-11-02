<?php

namespace Database\Seeders;

use App\Models\GstSlab;
use App\Models\HsnCode;
use Illuminate\Database\Seeder;

/** Default GST slabs + agri / pesticide HSN codes used by Krushi items. */
class TaxCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $slabs = [
            [0, 'GST 0%', 'जीएसटी ०%', 1],
            [5, 'GST 5%', 'जीएसटी ५%', 2],
            [12, 'GST 12%', 'जीएसटी १२%', 3],
            [18, 'GST 18%', 'जीएसटी १८%', 4],
            [28, 'GST 28%', 'जीएसटी २८%', 5],
        ];
        foreach ($slabs as [$rate, $name, $mr, $sort]) {
            GstSlab::updateOrCreate(
                ['rate' => $rate],
                ['name' => $name, 'name_mr' => $mr, 'sort_order' => $sort, 'is_active' => true],
            );
        }

        // code, kind, name, name_mr, default gst
        $hsn = [
            ['1005', 'hsn', 'Maize (corn)', 'मका', 0],
            ['1001', 'hsn', 'Wheat and meslin', 'गहू', 0],
            ['1006', 'hsn', 'Rice', 'तांदूळ', 0],
            ['1201', 'hsn', 'Soya beans', 'सोयाबीन', 0],
            ['1207', 'hsn', 'Other oil seeds (cotton seed etc.)', 'इतर तेलबिया (कापूस बियाणे)', 0],
            ['1209', 'hsn', 'Seeds for sowing', 'पेरणीसाठी बियाणे', 0],
            ['3102', 'hsn', 'Mineral or chemical fertilisers, nitrogenous (urea)', 'नायट्रोजन खते (युरिया)', 5],
            ['3103', 'hsn', 'Mineral or chemical fertilisers, phosphatic', 'फॉस्फेट खते', 5],
            ['3104', 'hsn', 'Mineral or chemical fertilisers, potassic', 'पोटॅश खते', 5],
            ['3105', 'hsn', 'Mineral or chemical fertilisers (NPK / DAP)', 'मिश्र खते (एनपीके / डीएपी)', 5],
            ['3808', 'hsn', 'Insecticides, fungicides, herbicides (pesticides)', 'कीटकनाशक / बुरशीनाशक / तणनाशक', 18],
            ['380891', 'hsn', 'Insecticides', 'कीटकनाशक', 18],
            ['380892', 'hsn', 'Fungicides', 'बुरशीनाशक', 18],
            ['380893', 'hsn', 'Herbicides / anti-sprouting', 'तणनाशक', 18],
            ['4011', 'hsn', 'New pneumatic tyres (tractor / agri)', 'नवीन टायर (ट्रॅक्टर)', 28],
            ['8201', 'hsn', 'Hand tools (spades, pruning shears…)', 'हात हत्यारे (कात्री इ.)', 12],
            ['8424', 'hsn', 'Agricultural / horticultural sprayers', 'कृषी / बागकाम स्प्रेअर', 12],
            ['8432', 'hsn', 'Agricultural machinery for soil prep', 'माती तयारी यंत्रे', 12],
            ['8701', 'hsn', 'Tractors', 'ट्रॅक्टर', 12],
            ['9988', 'sac', 'Manufacturing services on physical inputs', 'उत्पादन सेवा', 18],
        ];

        foreach ($hsn as [$code, $kind, $name, $mr, $gst]) {
            HsnCode::updateOrCreate(
                ['code' => $code],
                [
                    'kind' => $kind,
                    'name' => $name,
                    'name_mr' => $mr,
                    'gst_rate' => $gst,
                    'is_active' => true,
                ],
            );
        }
    }
}
