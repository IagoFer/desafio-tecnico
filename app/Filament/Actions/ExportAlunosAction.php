<?php

namespace App\Filament\Actions;

use App\Jobs\ExportAlunosJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
            ->modalDescription(
                'Devido ao grande volume de dados, a exportação será processada em segundo plano e pode levar alguns minutos. ' .
                'Você pode continuar navegando normalmente, uma notificação aparecerá no ícone de sino 🔔 com o link para download assim que a planilha estiver pronta.'
            )
            ->modalSubmitActionLabel('Iniciar exportação')
            ->action(function () {
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

                dispatch(new ExportAlunosJob($userId));

                Notification::make()
                    ->title('Exportação iniciada com sucesso!')
                    ->body('O processo pode levar alguns minutos. Fique de olho no ícone de sino 🔔 o link para download aparecerá lá assim que a planilha estiver pronta.')
                    ->success()
                    ->duration(10000)
                    ->send();
            });
    }
}
