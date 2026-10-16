<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateManager extends Command
{
    protected $signature = 'admin:create-local';

    protected $description = 'Create initial local manager without changing existing credentials';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Local setup only.');

            return self::FAILURE;
        }
        if (Staff::where('role', 'manager')->exists()) {
            $this->info('Manager already exists. No credentials changed.');

            return self::SUCCESS;
        }
        $password = Str::password(20, symbols: false);
        Staff::create(['name' => 'مدير بساتين الكرز', 'email' => 'manager@basateen.local', 'password' => $password, 'role' => 'manager']);
        File::put(base_path('../runtime/ADMIN-LOGIN.txt'), "لوحة الإدارة: http://127.0.0.1:8000/admin\r\nالبريد: manager@basateen.local\r\nكلمة المرور: ".$password."\r\nغيّر كلمة المرور من صفحة حسابي بعد الدخول. لا تشارك هذا الملف.\r\n");
        $this->info('Manager created. Credentials saved in runtime/ADMIN-LOGIN.txt.');

        return self::SUCCESS;
    }
}
