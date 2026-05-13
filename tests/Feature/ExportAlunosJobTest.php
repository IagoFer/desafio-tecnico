<?php

namespace Tests\Feature;

use App\Jobs\ExportAlunosJob;
use App\Models\User;
use App\Services\Export\AlunoExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

class ExportAlunosJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Garantir que a fila funcione de forma síncrona nos testes ou não seja necessária para o próprio Job handle
        Storage::fake('public');
    }

    public function test_handle_executes_service_and_sets_cache_progress_to_completed()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $progressKey = "export_progress_{$user->id}";

        // Criar um mock para o AlunoExportService
        $this->instance(
            AlunoExportService::class,
            Mockery::mock(AlunoExportService::class, function (MockInterface $mock) use ($user) {
                // Ao chamar o export, simulamos o callback e retornamos um nome de arquivo falso
                $mock->shouldReceive('export')
                     ->once()
                     ->with($user->id, Mockery::type('callable'))
                     ->andReturnUsing(function ($userId, $callback) {
                         // Simulando callback de progresso (metade, depois fim)
                         $callback(5, 10);
                         $callback(10, 10);
                         return 'alunos_export_test.xlsx';
                     });
            })
        );

        // Pre-set in-progress flag que o job deve limpar no final
        Cache::put("export_alunos_in_progress_{$user->id}", true, 1800);

        $job = new ExportAlunosJob($user->id);

        // 2. Act
        $job->handle(app(AlunoExportService::class));

        // 3. Assert
        // Verifica se a notificação foi salva no banco de dados (Filament usa o model DatabaseNotification diretamente)
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => get_class($user),
        ]);
        $notification = \Illuminate\Support\Facades\DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Exportação de Alunos Finalizada', $data['title'] ?? '');
        $this->assertEquals('success', $data['status'] ?? $data['color'] ?? '');

        // Verifica se a flag in_progress foi limpa
        $this->assertNull(Cache::get("export_alunos_in_progress_{$user->id}"));

        // Verifica o estado final do cache de progresso
        $finalCache = Cache::get($progressKey);
        $this->assertNotNull($finalCache);
        $this->assertEquals('completed', $finalCache['status']);
        $this->assertEquals('alunos_export_test.xlsx', $finalCache['file_name']);
        $this->assertStringContainsString('alunos_export_test.xlsx', $finalCache['file_url']);
    }

    public function test_failed_method_updates_cache_to_failed_and_sends_notification()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $progressKey = "export_progress_{$user->id}";
        
        Cache::put("export_alunos_in_progress_{$user->id}", true, 1800);

        $job = new ExportAlunosJob($user->id);
        
        $exception = new \Exception('Erro simulado de banco de dados');

        // 2. Act
        $job->failed($exception);

        // 3. Assert
        // Verifica se a flag in_progress foi limpa
        $this->assertNull(Cache::get("export_alunos_in_progress_{$user->id}"));

        // Verifica o cache de progresso
        $failedCache = Cache::get($progressKey);
        $this->assertNotNull($failedCache);
        $this->assertEquals('failed', $failedCache['status']);
        $this->assertEquals(0, $failedCache['processed']);

        // Verifica notificação de perigo
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => get_class($user),
        ]);
        $notification = \Illuminate\Support\Facades\DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $data = json_decode($notification->data, true);
        $this->assertStringContainsString('Exportação de Alunos', $data['title'] ?? '');
        $this->assertEquals('danger', $data['status'] ?? $data['color'] ?? '');
    }

    public function test_job_configuration_parameters()
    {
        $job = new ExportAlunosJob(999);

        $this->assertEquals(1200, $job->timeout);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 120, 300], $job->backoff);
        $this->assertEquals('999', $job->uniqueId());
        $this->assertEquals(3600, $job->uniqueFor);
    }
}
