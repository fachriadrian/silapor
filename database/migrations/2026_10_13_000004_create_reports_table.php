<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Kode unik tanpa tanda '#', mis. WF001 (tanda '#' hanya untuk tampilan)
            $table->string('code', 12)->unique();

            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('issue_type_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('title', 150);
            $table->text('description');
            $table->string('location', 100);

            $table->string('urgency', 20)->default('sedang');
            $table->string('status', 20)->default('baru');

            // Pelapor: user terdaftar ATAU guest (salah satu terisi, divalidasi di aplikasi)
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_contact', 100)->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Dashboard dan daftar laporan aktif
            $table->index(['status', 'created_at']);
            // Analisis kendala berulang
            $table->index(['category_id', 'location', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
