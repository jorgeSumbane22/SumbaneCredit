<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numeração sequencial sem lacunas por série/ano, obtida com bloqueio de linha.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('value')->default(0);
            $table->unique(['name', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
