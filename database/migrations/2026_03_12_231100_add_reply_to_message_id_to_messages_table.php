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
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('receiver_id')
                ->constrained('messages')
                ->nullOnDelete();

            $table->index(['sender_id', 'receiver_id', 'reply_to_message_id'], 'messages_reply_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_reply_lookup_idx');
            $table->dropConstrainedForeignId('reply_to_message_id');
        });
    }
};
