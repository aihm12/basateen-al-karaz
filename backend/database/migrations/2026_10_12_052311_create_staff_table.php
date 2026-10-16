<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->boolean('stock_reserved')->nullable());
        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role')->default('staff');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('admin_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('action');
            $t->text('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('stock_reserved'));
        Schema::dropIfExists('admin_events');
        Schema::dropIfExists('staff');
    }
};
