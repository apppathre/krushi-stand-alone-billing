<?php

namespace App\Console\Commands;

use App\Support\PrintFonts;
use Illuminate\Console\Command;

/** Copy bundled Noto Devanagari TTFs into storage/fonts for mPDF (safe to re-run). */
class InstallPdfFontsCommand extends Command
{
    protected $signature = 'krushi:pdf-fonts
                            {--force : Overwrite existing files in storage/fonts}';

    protected $description = 'Install / repair Marathi PDF fonts (resources → storage) for mPDF';

    public function handle(): int
    {
        $result = PrintFonts::install($this->option('force'));

        if ($result['ok']) {
            $this->info($result['message']);
            if ($result['copied'] !== []) {
                $this->line('Copied: '.implode(', ', $result['copied']));
            }

            return self::SUCCESS;
        }

        $this->error($result['message']);
        if ($result['missing'] !== []) {
            $this->line('Missing: '.implode(', ', $result['missing']));
        }
        $this->line('Expected source: '.PrintFonts::resourceDir());

        return self::FAILURE;
    }
}
