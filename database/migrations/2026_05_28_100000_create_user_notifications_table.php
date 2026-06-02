<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category', 32); // income | refund | cancelled | return_request | sync
            $table->string('title');
            $table->text('message');
            $table->string('marketplace_name')->nullable();
            $table->string('product_name')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
