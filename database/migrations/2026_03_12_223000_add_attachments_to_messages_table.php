<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 150)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->string('attachment_type', 24)->nullable()->after('attachment_size');

            $table->index(['attachment_type', 'created_at'], 'messages_attachment_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_attachment_type_created_idx');
            $table->dropColumn([
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
                'attachment_type',
            ]);
        });
    }
};
