<?php

namespace App\Console\Commands;

use App\Services\LicenseManager;
use Illuminate\Console\Command;

class LicenseStatus extends Command
{
    protected $signature = 'license:status';

    protected $description = 'Show the commercial license gate status';

    public function handle(LicenseManager $licenses): int
    {
        $s = $licenses->status();
        $this->line('Mode    : '.$s['mode']);
        $this->line('Licensed: '.($s['licensed'] ? 'yes' : 'NO'));
        $this->line('Reason  : '.$s['reason']);
        if (! empty($s['support_until'])) {
            $this->line('Support : until '.$s['support_until']);
        }

        return $s['licensed'] ? self::SUCCESS : self::FAILURE;
    }
}
