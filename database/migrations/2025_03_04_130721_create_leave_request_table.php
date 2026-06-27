<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_request', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('leave_dates');
            $table->json('sent_to_ids');
            $table->enum('leave_type', ['planned', 'sick', 'casual']);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->integer('days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request');
    }
};
