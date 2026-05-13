<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Export\AlunoExportService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportAlunosJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout de 20 minutos (fallback de segurança).
     */
    public $timeout = 1200; 

    /**
     * Resiliência: O job tentará rodar 3 vezes caso caia por deadlock de banco de dados
     * ou problema momentâneo de rede no Redis.
     */
    public int $tries = 3;

    /**
     * Backoff progressivo: Espera 1min, 2min e 5min antes de retentar.
     */
    public array $backoff = [60, 120, 300];

    public function __construct(
        protected int $userId
    ) {}

    /**
     * O ID único do job. Impede que o Redis aceite 2 jobs do mesmo usuário simultaneamente.
     */
    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    /**
     * Tempo em que o Job é considerado único no Redis (1 hora).
     */
    public int $uniqueFor = 3600;

    /**
     * Orquestra a execução injetando as dependências necessárias.
     */
    public function handle(AlunoExportService $exportService): void
    {
        Log::info('Iniciando processamento assíncrono do ExportAlunosJob', ['user_id' => $this->userId]);

        $progressKey = "export_progress_{$this->userId}";

        // Inicializa o progresso no Cache (o Livewire vai ler esse cache via polling)
        Cache::put($progressKey, [
            'status' => 'processing',
            'processed' => 0,
            'total' => 0,
        ], 1800);

        // Delega a responsabilidade massiva para o serviço de domínio,
        // passando um callback que atualiza o progresso no Redis a cada chunk
        $fileName = $exportService->export($this->userId, function (int $processed, int $total) use ($progressKey) {
            Cache::put($progressKey, [
                'status' => 'processing',
                'processed' => $processed,
                'total' => $total,
            ], 1800);
        });

        // Atualiza o progresso para "concluído" com URL do arquivo
        Cache::put($progressKey, [
            'status' => 'completed',
            'processed' => 0,
            'total' => 0,
            'file_url' => Storage::url('exports/' . $fileName),
            'file_name' => $fileName,
        ], 1800);

        // Envia Notificação de Sucesso (Database Notification — aparece no sino)
        $recipient = User::find($this->userId);
        if ($recipient) {
            Notification::make()
                ->title('Exportação de Alunos Finalizada!')
                ->body('Sua planilha Excel foi gerada com sucesso.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Baixar Planilha')
                        ->url(Storage::url('exports/' . $fileName))
                        ->button()
                        ->markAsRead()
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($recipient);
        }

        // Libera a trava de segurança para o usuário poder solicitar nova exportação
        Cache::forget("export_alunos_in_progress_{$this->userId}");
    }

    /**
     * Tratamento de Falha Crítica.
     * Acionado quando as 3 tentativas esgotarem (tries).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Falha crítica e definitiva na exportação de alunos', [
            'user_id' => $this->userId,
            'erro' => $exception->getMessage(),
            'linha' => $exception->getLine(),
            'arquivo' => $exception->getFile()
        ]);

        // Atualiza o progresso para "falha" (o toast vai mostrar mensagem de erro)
        Cache::put("export_progress_{$this->userId}", [
            'status' => 'failed',
            'processed' => 0,
            'total' => 0,
        ], 300);
        
        $recipient = User::find($this->userId);
        if ($recipient) {
            Notification::make()
                ->title('Erro na Exportação de Alunos')
                ->body('Ocorreu um problema ao gerar sua planilha. Por favor, entre em contato com o suporte.')
                ->danger()
                ->sendToDatabase($recipient);
        }

        // Garante que a trava seja liberada em caso de falha fatal
        Cache::forget("export_alunos_in_progress_{$this->userId}");
    }
}
