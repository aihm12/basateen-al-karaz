<?php

namespace App\Console\Commands;

use App\Services\FirebaseMessagingService;
use Illuminate\Console\Command;
use Throwable;

class FirebaseCheck extends Command
{
    protected $signature = 'firebase:check';

    protected $description = 'Check Firebase authentication without sending notifications';

    public function handle(FirebaseMessagingService $firebase): int
    {
        try {
            $firebase->accessToken();
        } catch (Throwable) {
            $this->error('Firebase authentication failed. Check credentials, project ID, and connectivity.');

            return self::FAILURE;
        }
        $this->info('Firebase authentication succeeded. No notification was sent.');

        return self::SUCCESS;
    }
}
