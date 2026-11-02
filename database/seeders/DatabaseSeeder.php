<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,   // company, mail settings, godowns, financial year, document series
            AccessSeeder::class,    // roles, menus, role permissions
            LedgerSeeder::class,    // default ledgers + account map
            AreaSeeder::class,      // farm / recovery areas for parties
            TaxCatalogSeeder::class, // GST slabs + agri HSN / SAC
            CatalogSeeder::class,   // categories, units, manufacturers, products, sample opening stock
            UserSeeder::class,      // owner + demo users
        ]);
    }
}
