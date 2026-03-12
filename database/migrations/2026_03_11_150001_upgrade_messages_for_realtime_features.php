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
            $table->string('status', 16)->default('sent')->after('body');
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->index(['sender_id', 'receiver_id', 'id'], 'messages_sender_receiver_id_idx');
            $table->index(['receiver_id', 'read_at', 'id'], 'messages_receiver_read_id_idx');
            $table->index(['receiver_id', 'status'], 'messages_receiver_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_sender_receiver_id_idx');
            $table->dropIndex('messages_receiver_read_id_idx');
            $table->dropIndex('messages_receiver_status_idx');

            $table->dropColumn([
                'status',
                'delivered_at',
                'read_at',
            ]);
        });
    }
};
