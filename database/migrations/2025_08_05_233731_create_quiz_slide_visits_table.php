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
        Schema::create('quiz_slide_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained('quiz_sessions')->onDelete('cascade');
            $table->string('slide_id'); // Slide identifier from the quiz
            $table->string('slide_title')->nullable(); // Slide title
            $table->integer('slide_sequence'); // Order/sequence of the slide
            $table->timestamp('visited_at');
            $table->integer('time_spent_seconds')->nullable(); // Time spent on this slide
            $table->json('slide_metadata')->nullable(); // Additional slide data
            $table->timestamps();
            
            // Index for performance
            $table->index(['quiz_session_id', 'slide_sequence']);
            $table->index(['slide_id', 'slide_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_slide_visits');
    }
};
