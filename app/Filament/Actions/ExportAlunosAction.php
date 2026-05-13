<?php

namespace App\Filament\Actions;

use App\Jobs\ExportAlunosJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExportAlunosAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'export_alunos';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Exportar Alunos')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Exportar planilha de alunos')
            ->modalDescription(null)
            ->modalIcon('heroicon-o-arrow-down-tray')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Iniciar exportação')
            ->form([
                \Filament\Forms\Components\Select::make('filter')
                    ->label('Quais alunos deseja exportar?')
                    ->options([
                        'with_matricula' => 'Apenas alunos com matrícula',
                        'without_matricula' => 'Apenas alunos sem matrícula',
                        'all' => 'Todos os registros (Alunos e não alunos)',
                    ])
                    ->default('with_matricula')
                    ->native(false)
                    ->required()
                    ->live(),

                \Filament\Forms\Components\Placeholder::make('statistics')
                    ->label('')
                    ->content(function ($get) {
                        $filter = $get('filter') ?? 'with_matricula';
                        $count = $this->getAlunosCount($filter);
                        $estimatedTime = $this->calculateEstimatedTime($count);
                        $estimatedSize = $this->calculateEstimatedSize($count);

                        return view('filament.modals.export-info', compact('count', 'estimatedTime', 'estimatedSize'));
                    }),
            ])
            ->action(function (array $data) {
                $userId = Auth::id();
                $cacheKey = "export_alunos_in_progress_{$userId}";

                // Trava (Lock) de Segurança: Previne spam de cliques e múltiplas exportações simultâneas
                if (Cache::has($cacheKey)) {
                    Notification::make()
                        ->title('Exportação em andamento')
                        ->body('Já existe uma exportação sendo processada. Aguarde a notificação no sino 🔔 antes de solicitar uma nova.')
                        ->warning()
                        ->send();
                    
                    return;
                }

                // Cria o Lock com expiração de segurança de 30 minutos (caso o job falhe fatalmente sem passar no failed)
                Cache::put($cacheKey, true, now()->addMinutes(30));

                $filter = $data['filter'] ?? 'with_matricula';
                $total = $this->getAlunosCount($filter);

                dispatch(new ExportAlunosJob($userId, $filter, $total));

                Notification::make()
                    ->title('Exportação iniciada!')
                    ->body('Acompanhe o progresso na barra inferior. Quando finalizar, o link de download aparecerá no sino 🔔.')
                    ->success()
                    ->duration(10000)
                    ->send();
            });
    }

    /**
     * Conta o total de alunos baseado no filtro escolhido.
     */
    private function getAlunosCount(string $filter): int
    {
        return Cache::remember("export_alunos_count_{$filter}", 300, function () use ($filter) {
            $query = DB::table('users');

            if ($filter === 'with_matricula') {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('matriculas')
                      ->whereColumn('matriculas.user_id', 'users.id');
                });
            } elseif ($filter === 'without_matricula') {
                $query->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('matriculas')
                      ->whereColumn('matriculas.user_id', 'users.id');
                });
            }

            return $query->count();
        });
    }

    /**
     * Estima o tempo de exportação baseado no volume de dados.
     * Benchmark: ~2.000 registros/segundo com chunks + streaming OpenSpout.
     */
    private function calculateEstimatedTime(int $count): string
    {
        $seconds = max(30, (int) ceil($count / 2000) * 1.5);
        $minutes = (int) ceil($seconds / 60);

        if ($minutes <= 1) {
            return '1 min';
        }

        return "{$minutes} min";
    }

    /**
     * Estima o tamanho do arquivo XLSX baseado no volume de dados.
     * Benchmark: ~450 bytes por registro com OpenSpout (compressão ZIP nativa do .xlsx).
     */
    private function calculateEstimatedSize(int $count): string
    {
        $bytes = $count * 450;
        $mb = $bytes / (1024 * 1024);

        if ($mb < 1) {
            return round($mb * 1024) . ' KB';
        }

        return round($mb) . ' MB';
    }
}
