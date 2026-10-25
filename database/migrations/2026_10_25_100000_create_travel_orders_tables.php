<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('currency', 3);
            $table->decimal('total_amount', 14, 3);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('provider');
            $table->string('offer_id');
            $table->string('status')->index();
            $table->string('title');
            $table->json('summary')->nullable();
            $table->decimal('amount', 14, 3);
            $table->string('currency', 3);
            $table->decimal('provider_amount', 14, 3);
            $table->string('provider_currency', 3);
            $table->string('provider_reference')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('travelers')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('held_until')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway');
            $table->string('status')->index();
            $table->decimal('amount', 14, 3);
            $table->string('currency', 3);
            $table->decimal('refunded_amount', 14, 3)->default(0);
            $table->string('gateway_reference')->nullable()->index();
            $table->string('gateway_payment_id')->nullable();
            $table->text('redirect_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
