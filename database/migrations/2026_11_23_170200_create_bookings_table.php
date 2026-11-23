<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('attendees')->default(1);
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'start_time', 'end_time']);
            $table->index('status');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_time_order CHECK (end_time > start_time)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_valid CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");

        // Last line of defence against double booking: even if two requests race past the
        // application-level check, PostgreSQL refuses a second active booking whose time
        // range overlaps (&&) another active booking in the same room.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (room_id WITH =, tsrange(start_time, end_time, '[)') WITH &&)
            WHERE (status IN ('pending', 'approved'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
