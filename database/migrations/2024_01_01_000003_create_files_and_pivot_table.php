<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel file PDF
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('folder_id');
            $table->string('name')->comment('Nama yang ditampilkan (bisa di-rename admin)');
            $table->string('original_name')->comment('Nama file asli saat upload');
            $table->string('path')->comment('Path storage relatif');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size')->comment('Ukuran dalam bytes');
            $table->uuid('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users');
        });

        // Pivot: akses user ke folder (multi-akses, admin assign)
        Schema::create('folder_user', function (Blueprint $table) {
            $table->uuid('folder_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['folder_id', 'user_id']);
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_user');
        Schema::dropIfExists('files');
    }
};
