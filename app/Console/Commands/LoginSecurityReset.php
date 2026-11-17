<?php

namespace App\Console\Commands;

use App\Support\Security\SecurityOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency switch for System Settings > Security: turns the login CAPTCHA
 * and the failed-attempt lockout off. Turning the lockout off lifts every
 * active block immediately (blocks are only consulted while it is on).
 */
class LoginSecurityReset extends Command
{
    protected $signature = 'security:login-reset';

    protected $description = 'Switch off the login CAPTCHA and failed-attempt lockout';

    public function handle(): int
    {
        if (! Schema::hasColumn('settings', 'security_options')) {
            $this->info('Login security is not set up (migration not run), so nothing is enabled.');

            return self::SUCCESS;
        }

        SecurityOptions::save(['captcha_enabled' => false, 'lockout_enabled' => false]);
        $this->info('CAPTCHA and lockout are now off for the login page.');

        return self::SUCCESS;
    }
}
