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
        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
            $table->index('data_de_nascimento');
        });
        
        Schema::table('matriculas', function (Blueprint $table) {
            $table->index('data_de_criacao');
            $table->index('resultado_final');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['data_de_nascimento']);
        });
        
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropIndex(['data_de_criacao']);
            $table->dropIndex(['resultado_final']);
        });
    }
};
