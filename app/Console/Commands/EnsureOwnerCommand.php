<?php

namespace App\Console\Commands;

use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;

/** Upsert the default shop owner (safe to run on live after git pull). */
class EnsureOwnerCommand extends Command
{
    protected $signature = 'krushi:ensure-owner';

    protected $description = 'Create/update the default owner login from UserSeeder';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => UserSeeder::class,
            '--force' => true,
        ]);

        $this->info('Owner ready: '.UserSeeder::OWNER_EMAIL);
        $this->line('Password: '.UserSeeder::OWNER_PASSWORD);

        return self::SUCCESS;
    }
}
