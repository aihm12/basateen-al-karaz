<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->unique();
            $t->timestamp('phone_verified_at')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('gender')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('email')->nullable()->change();
            $t->string('password')->nullable()->change();
        });
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->text('address')->nullable();
            $t->string('foodics_branch_id')->nullable()->unique();
            $t->boolean('delivery_enabled')->default(true);
            $t->boolean('pickup_enabled')->default(true);
            $t->boolean('is_active')->default(true);
            $t->json('opening_hours')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name_ar');
            $t->string('name_en');
            $t->string('image')->nullable();
            $t->string('icon')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->string('foodics_category_id')->nullable()->unique();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('name_ar');
            $t->string('name_en');
            $t->text('description_ar')->nullable();
            $t->text('description_en')->nullable();
            $t->string('sku')->unique();
            $t->string('barcode')->nullable()->index();
            $t->string('foodics_product_id')->nullable()->unique();
            $t->string('main_image')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_new')->default(false);
            $t->boolean('is_best_seller')->default(false);
            $t->boolean('is_active')->default(true);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('image_url');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name_ar');
            $t->string('name_en');
            $t->string('sku')->unique();
            $t->string('barcode')->nullable();
            $t->decimal('price', 12, 2);
            $t->decimal('weight', 10, 3)->nullable();
            $t->string('weight_unit', 10)->nullable();
            $t->string('foodics_product_id')->nullable();
            $t->string('foodics_variant_id')->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('branch_product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_available')->default(true);
            $t->unsignedInteger('stock_quantity')->nullable();
            $t->unique(['branch_id', 'product_variant_id']);
            $t->timestamps();
        });
        Schema::create('option_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name_ar');
            $t->string('name_en');
            $t->enum('selection_type', ['single', 'multiple'])->default('single');
            $t->unsignedSmallInteger('min_selection')->default(0);
            $t->unsignedSmallInteger('max_selection')->default(1);
            $t->boolean('required')->default(false);
            $t->timestamps();
        });
        Schema::create('option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $t->string('name_ar');
            $t->string('name_en');
            $t->decimal('additional_price', 12, 2)->default(0);
            $t->string('foodics_modifier_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('product_option_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('sort_order')->default(0);
            $t->unique(['product_id', 'option_group_id']);
            $t->timestamps();
        });
        Schema::create('delivery_zones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('minimum_order', 12, 2)->default(0);
            $t->decimal('free_delivery_above', 12, 2)->nullable();
            $t->unsignedInteger('estimated_minutes')->default(60);
            $t->json('districts')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $t->string('label');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('district');
            $t->string('street');
            $t->string('building_number')->nullable();
            $t->string('floor')->nullable();
            $t->string('apartment')->nullable();
            $t->text('instructions')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unique(['user_id', 'product_id']);
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 12, 2);
            $t->timestamps();
        });
        Schema::create('cart_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_value_id')->constrained()->restrictOnDelete();
            $t->decimal('price', 12, 2);
            $t->unique(['cart_item_id', 'option_value_id']);
            $t->timestamps();
        });
        Schema::create('delivery_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week');
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedInteger('max_orders')->default(15);
            $t->boolean('is_active')->default(true);
            $t->unique(['branch_id', 'day_of_week', 'start_time']);
            $t->timestamps();
        });
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->enum('discount_type', ['fixed', 'percentage']);
            $t->decimal('discount_value', 12, 2);
            $t->decimal('minimum_order', 12, 2)->default(0);
            $t->decimal('maximum_discount', 12, 2)->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('usage_per_customer')->default(1);
            $t->boolean('first_order_only')->default(false);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $t->json('address_snapshot')->nullable();
            $t->enum('status', ['pending', 'confirmed', 'preparing', 'ready', 'assigned', 'out_for_delivery', 'delivered', 'cancelled'])->default('pending');
            foreach (['subtotal', 'discount', 'delivery_fee', 'tax', 'total'] as $money) {
                $t->decimal($money, 12, 2)->default(0);
            } $t->string('currency', 3)->default('SAR');
            $t->string('payment_method');
            $t->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $t->enum('delivery_type', ['pickup', 'delivery']);
            $t->date('delivery_date')->nullable();
            $t->foreignId('delivery_slot_id')->nullable()->constrained()->nullOnDelete();
            $t->json('delivery_slot_snapshot')->nullable();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->string('foodics_order_id')->nullable()->unique();
            $t->text('customer_notes')->nullable();
            $t->string('idempotency_key', 80);
            $t->string('request_hash', 64);
            $t->unique(['user_id', 'idempotency_key']);
            $t->index(['delivery_slot_id', 'delivery_date', 'status']);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->string('product_name');
            $t->string('variant_name');
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('total_price', 12, 2);
            $t->decimal('tax_rate', 5, 2);
            $t->decimal('tax', 12, 2);
            $t->string('foodics_product_id')->nullable();
            $t->timestamps();
        });
        Schema::create('order_item_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->string('option_name');
            $t->string('value_name');
            $t->decimal('price', 12, 2);
            $t->string('foodics_modifier_id')->nullable();
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('payment_method');
            $t->decimal('amount', 12, 2);
            $t->string('transaction_id')->nullable()->unique();
            $t->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $t->timestamp('paid_at')->nullable();
            $t->json('raw_response')->nullable();
            $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('status', ['pending', 'assigned', 'picked_up', 'delivered', 'cancelled'])->default('pending');
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('picked_up_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->string('delivery_provider')->nullable();
            $t->string('external_delivery_id')->nullable();
            $t->timestamps();
        });
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('image')->nullable();
            $t->string('action_type')->nullable();
            $t->string('action_id')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('body');
            $t->string('type');
            $t->string('reference_id')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('device_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token', 512)->unique();
            $t->string('platform');
            $t->timestamps();
        });
        Schema::create('integration_logs', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('action');
            $t->enum('direction', ['in', 'out']);
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->json('request')->nullable();
            $t->json('response')->nullable();
            $t->string('status');
            $t->unsignedInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->index(['provider', 'status']);
            $t->timestamps();
        });
        Schema::create('otp_challenges', function (Blueprint $t) {
            $t->id();
            $t->string('phone')->unique();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('delivery_slots');
        Schema::dropIfExists('cart_item_options');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('option_groups');
        Schema::dropIfExists('branch_product_variants');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('branches');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'phone_verified_at', 'date_of_birth', 'gender', 'is_active']));
    }
};
