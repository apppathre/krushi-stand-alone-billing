<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Godown;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        $categories = [
            'Seed' => 'बियाणे', 'Fertilizer' => 'खते', 'Insecticide' => 'कीटकनाशक',
            'Pesticide' => 'पीडनाशक', 'Equipment' => 'उपकरणे',
        ];
        $cat = [];
        foreach ($categories as $name => $mr) {
            $cat[$name] = Category::updateOrCreate(['name' => $name], ['name_mr' => $mr, 'is_active' => true])->id;
        }

        $units = [
            ['Nos', 'नग', 'Nos'], ['Kilogram', 'किलो', 'Kg'], ['Gram', 'ग्रॅम', 'Gm'], ['Litre', 'लिटर', 'Ltr'],
            ['Millilitre', 'मिलीलिटर', 'Ml'], ['Packet', 'पाकीट', 'Pkt'], ['Bag', 'पोते', 'Bag'],
            ['Bottle', 'बाटली', 'Btl'], ['Box', 'खोका', 'Box'],
        ];
        $unit = [];
        foreach ($units as [$name, $mr, $short]) {
            $unit[$short] = Unit::updateOrCreate(['short_name' => $short], ['name' => $name, 'name_mr' => $mr, 'is_active' => true])->id;
        }

        $makers = [
            ['Mahyco', 'महिको', 'MHY'],
            ['Syngenta India', 'सिन्जेंटा इंडिया', 'SYN'],
            ['Bayer CropScience', 'बायर क्रॉपसायन्स', 'BYR'],
            ['UPL Limited', 'यूपीएल', 'UPL'],
            ['IFFCO', 'इफको', 'IFF'],
            ['Coromandel International', 'कोरोमंडल', 'CRM'],
            ['Kisan Sprayers', 'किसान स्प्रेअर्स', 'KSP'],
        ];
        $mfr = [];
        foreach ($makers as [$name, $mr, $code]) {
            $mfr[$name] = Manufacturer::updateOrCreate(['name' => $name], [
                'name_mr' => $mr, 'short_code' => $code, 'is_active' => true,
            ])->id;
        }

        // code, name, name_mr, category, maker, unit, hsn, gst, purchase, sale, mrp, batch, reorder
        $products = [
            ['SD001', 'Hybrid Cotton Seed 450g', 'संकरित कापूस बियाणे ४५० ग्रॅम', 'Seed', 'Mahyco', 'Pkt', '1207', 0, 780, 850, 864, true, 20],
            ['SD002', 'Soybean Seed JS-335 30kg', 'सोयाबीन बियाणे जेएस-३३५ ३० किलो', 'Seed', 'Mahyco', 'Bag', '1201', 0, 2700, 2950, 3000, true, 10],
            ['SD003', 'Hybrid Maize Seed 4kg', 'संकरित मका बियाणे ४ किलो', 'Seed', 'Syngenta India', 'Pkt', '1005', 0, 1100, 1250, 1300, true, 10],
            ['FT001', 'Urea 45kg', 'युरिया ४५ किलो', 'Fertilizer', 'IFFCO', 'Bag', '3102', 5, 262, 266, 266, true, 50],
            ['FT002', 'DAP 50kg', 'डीएपी ५० किलो', 'Fertilizer', 'IFFCO', 'Bag', '3105', 5, 1300, 1350, 1350, true, 30],
            ['FT003', 'NPK 10:26:26 50kg', 'एनपीके १०:२६:२६ ५० किलो', 'Fertilizer', 'Coromandel International', 'Bag', '3105', 5, 1400, 1470, 1470, true, 20],
            ['IN001', 'Imidacloprid 17.8% SL 100ml', 'इमिडाक्लोप्रिड १७.८% एसएल १०० मिली', 'Insecticide', 'UPL Limited', 'Btl', '3808', 18, 150, 190, 210, true, 24],
            ['IN002', 'Chlorpyrifos 20% EC 1L', 'क्लोरपायरीफॉस २०% ईसी १ लिटर', 'Insecticide', 'Syngenta India', 'Btl', '3808', 18, 420, 520, 560, true, 12],
            ['IN003', 'Emamectin Benzoate 5% SG 100g', 'इमामेक्टिन बेन्झोएट ५% १०० ग्रॅम', 'Insecticide', 'Syngenta India', 'Pkt', '3808', 18, 380, 470, 500, true, 12],
            ['PE001', 'Glyphosate 41% SL 1L', 'ग्लायफोसेट ४१% एसएल १ लिटर', 'Pesticide', 'Bayer CropScience', 'Btl', '3808', 18, 430, 520, 560, true, 12],
            ['PE002', 'Mancozeb 75% WP 500g', 'मॅन्कोझेब ७५% ५०० ग्रॅम', 'Pesticide', 'UPL Limited', 'Pkt', '3808', 18, 190, 240, 260, true, 15],
            ['EQ001', 'Knapsack Sprayer 16L', 'नॅपसॅक स्प्रेअर १६ लिटर', 'Equipment', 'Kisan Sprayers', 'Nos', '8424', 12, 1650, 2000, 2200, false, 3],
            ['EQ002', 'Garden Pruning Shear', 'छाटणी कात्री', 'Equipment', 'Kisan Sprayers', 'Nos', '8201', 12, 180, 250, 280, false, 5],
        ];

        $made = [];
        foreach ($products as [$code, $name, $mr, $c, $m, $u, $hsn, $gst, $pr, $sr, $mrp, $batch, $reorder]) {
            $packing = match ($u) {
                'Bag' => '50 kg bag',
                'Btl' => str_contains($name, '1L') || str_contains($name, '1 L') ? '1 L bottle' : '100 ml bottle',
                'Pkt' => 'Packet',
                'Nos' => '1 Nos',
                default => null,
            };
            $made[$code] = Product::updateOrCreate(['code' => $code], [
                'name' => $name, 'name_mr' => $mr, 'category_id' => $cat[$c], 'manufacturer_id' => $mfr[$m],
                'unit_id' => $unit[$u], 'packing' => $packing, 'hsn_code' => $hsn, 'gst_rate' => $gst, 'purchase_rate' => $pr,
                'sale_rate' => $sr, 'mrp' => $mrp, 'batch_required' => $batch, 'reorder_level' => $reorder, 'is_active' => true,
            ]);
        }

        // Sample opening stock (only when no stock has been recorded yet).
        if (StockMovement::query()->exists()) {
            return;
        }
        $main = Godown::where('code', 'MAIN')->first();
        $second = Godown::where('code', 'GD2')->first();
        $date = now()->startOfMonth()->toDateString();

        $opening = [
            [$main, 'SD001', 'MH-C-2601', 60, now()->addMonths(8)],
            [$main, 'SD002', 'JS-S-2602', 25, now()->addMonths(6)],
            [$main, 'SD003', 'SY-M-2603', 18, now()->addMonths(9)],
            [$main, 'FT001', 'UR-2604', 120, null],
            [$main, 'FT002', 'DAP-2605', 40, null],
            [$main, 'FT003', 'NPK-2606', 8, null], // below reorder level: shows low-stock alert
            [$main, 'IN001', 'UP-I-2607', 48, now()->addMonths(14)],
            [$main, 'IN002', 'SY-C-2608', 10, now()->addDays(40)], // expiring soon alert
            [$main, 'PE001', 'BY-G-2609', 30, now()->addMonths(18)],
            [$main, 'PE002', 'UP-M-2610', 36, now()->addMonths(12)],
            [$main, 'EQ001', null, 6, null],
            [$main, 'EQ002', null, 12, null],
            [$second, 'FT001', 'UR-2604', 300, null],
            [$second, 'FT002', 'DAP-2605', 100, null],
            [$second, 'SD001', 'MH-C-2601', 100, now()->addMonths(8)],
        ];
        foreach ($opening as [$godown, $code, $batchNo, $qty, $expiry]) {
            $p = $made[$code];
            $stock->receive([
                'product_id' => $p->id,
                'godown_id' => $godown->id,
                'batch_no' => $batchNo,
                'expiry_date' => $expiry?->toDateString(),
                'qty' => $qty,
                'rate' => (float) $p->purchase_rate,
                'mrp' => (float) $p->mrp,
                'type' => 'opening',
                'date' => $date,
                'narration' => 'Opening stock',
            ]);
        }
    }
}
