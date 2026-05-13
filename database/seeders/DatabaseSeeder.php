<?php

namespace Database\Seeders;

use App\Enums\ResultadoFinal;
use App\Enums\SerieEscolar;
use App\Enums\StatusMatricula;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Creating admin user...');
        // Create an admin user to access Filament
        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin'),
            'data_de_nascimento' => '1990-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Creating 100 schools...');
        $escolas = [];
        for ($i = 1; $i <= 100; $i++) {
            $escolas[] = [
                'nome' => 'Escola ' . $i . ' ' . Str::random(5),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('escolas')->insert($escolas);

        // Get school IDs to randomly assign
        $escolaIds = DB::table('escolas')->pluck('id')->toArray();

        // Enums values
        $series = array_column(SerieEscolar::cases(), 'value');
        $statuses = array_column(StatusMatricula::cases(), 'value');
        $resultados = array_column(ResultadoFinal::cases(), 'value');

        $totalUsers = (int) env('SEED_TOTAL_STUDENTS', 200000);
        $totalNonStudents = (int) env('SEED_TOTAL_NON_STUDENTS', 100000);
        $chunkSize = 5000;
        $password = Hash::make('password');

        $this->command->info("Creating {$totalUsers} users and their enrollments...");
        
        $now = now()->format('Y-m-d H:i:s');
        $nowDate = now()->format('Y-m-d');

        for ($i = 0; $i < $totalUsers; $i += $chunkSize) {
            $usersChunk = [];
            $matriculasChunk = [];
            $documentosChunk = [];
            $enderecosChunk = [];

            for ($j = 0; $j < $chunkSize; $j++) {
                $userId = $i + $j + 2; // +2 because ID 1 is admin

                $usersChunk[] = [
                    'id' => $userId,
                    'name' => 'Aluno ' . $userId,
                    'email' => "aluno{$userId}@example.com",
                    'password' => $password,
                    'data_de_nascimento' => now()->subYears(rand(5, 18))->format('Y-m-d'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $documentosChunk[] = [
                    'user_id' => $userId,
                    'cpf' => rand(10000000000, 99999999999),
                    'rg' => rand(100000000, 999999999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $enderecosChunk[] = [
                    'user_id' => $userId,
                    'rua' => 'Rua ' . Str::random(5),
                    'logradouro' => 'Logradouro ' . Str::random(5),
                    'cep' => rand(10000000, 99999999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Create random enrollments
                $years = range(2020, 2025);
                foreach ($years as $year) {
                    $numMatriculas = rand(0, 3);
                    for ($m = 0; $m < $numMatriculas; $m++) {
                        $matriculasChunk[] = [
                            'user_id' => $userId,
                            'escola_id' => $escolaIds[array_rand($escolaIds)],
                            'serie_escolar' => $series[array_rand($series)],
                            'ano_letivo' => $year,
                            'data_de_criacao' => $nowDate,
                            'status' => $statuses[array_rand($statuses)],
                            'resultado_final' => $resultados[array_rand($resultados)],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            DB::table('users')->insert($usersChunk);
            DB::table('documentos')->insert($documentosChunk);
            DB::table('enderecos')->insert($enderecosChunk);
            
            // Insert matriculas in smaller chunks to avoid too many bindings
            $matriculasSubChunks = array_chunk($matriculasChunk, 500);
            foreach ($matriculasSubChunks as $subChunk) {
                DB::table('matriculas')->insert($subChunk);
            }

            $this->command->info('Inserted ' . ($i + $chunkSize) . ' students and their enrollments.');
        }

        $this->command->info("Creating {$totalNonStudents} non-student users...");
        $startId = $totalUsers + 2;
        for ($i = 0; $i < $totalNonStudents; $i += $chunkSize) {
            $usersChunk = [];
            $documentosChunk = [];
            $enderecosChunk = [];
            for ($j = 0; $j < $chunkSize; $j++) {
                $userId = $startId + $i + $j;
                $usersChunk[] = [
                    'id' => $userId,
                    'name' => 'Usuário ' . $userId,
                    'email' => "usuario{$userId}@example.com",
                    'password' => $password,
                    'data_de_nascimento' => now()->subYears(rand(20, 60))->format('Y-m-d'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $documentosChunk[] = [
                    'user_id' => $userId,
                    'cpf' => rand(10000000000, 99999999999),
                    'rg' => rand(100000000, 999999999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $enderecosChunk[] = [
                    'user_id' => $userId,
                    'rua' => 'Rua ' . Str::random(5),
                    'logradouro' => 'Logradouro ' . Str::random(5),
                    'cep' => rand(10000000, 99999999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($usersChunk);
            DB::table('documentos')->insert($documentosChunk);
            DB::table('enderecos')->insert($enderecosChunk);
            $this->command->info('Inserted ' . ($i + $chunkSize) . ' non-students.');
        }

        $this->command->info('Database seeding completed successfully!');
    }
}
