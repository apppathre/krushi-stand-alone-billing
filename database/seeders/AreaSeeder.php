<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Wadgaon', 'वडगाव', 'WDG', 'Near main road belt'],
            ['Shirur', 'शिरूर', 'SHR', 'Shirur taluka farms'],
            ['Baramati', 'बारामती', 'BRM', 'Baramati belt'],
            ['Indapur', 'इंदापूर', 'IND', 'Indapur belt'],
            ['Daund', 'दौंड', 'DND', 'Daund belt'],
        ];
        foreach ($rows as [$name, $mr, $code, $desc]) {
            Area::updateOrCreate(
                ['short_code' => $code],
                ['name' => $name, 'name_mr' => $mr, 'description' => $desc, 'is_active' => true],
            );
        }
    }
}
