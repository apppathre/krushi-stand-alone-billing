<?php

namespace App\Providers;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // @can('menu', ['products', 'add'])
        Gate::define('menu', fn (User $user, string $key, string $action = 'view') => $user->canMenu($key, $action));

        $this->applyMailSettings();
    }

    /** Apply SMTP settings stored in the database (used by forgot-password and all outgoing mail). */
    private function applyMailSettings(): void
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests() && $this->isMigrationCommand()) {
            return;
        }

        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }
            $mail = MailSetting::query()->first();
        } catch (\Throwable) {
            return;
        }

        if (! $mail || ! $mail->is_enabled || blank($mail->host)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $mail->host,
            'mail.mailers.smtp.port' => (int) $mail->port,
            'mail.mailers.smtp.encryption' => $mail->encryption ?: null,
            'mail.mailers.smtp.username' => $mail->username,
            'mail.mailers.smtp.password' => $mail->password,
            'mail.mailers.smtp.scheme' => $mail->encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.from.address' => $mail->from_address ?: config('mail.from.address'),
            'mail.from.name' => $mail->from_name ?: config('app.name'),
        ]);
    }

    private function isMigrationCommand(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        return isset($argv[1]) && str_starts_with($argv[1], 'migrate');
    }
}
