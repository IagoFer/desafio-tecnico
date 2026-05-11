<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use App\Jobs\ExportAlunosJob;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_alunos')
                ->label('Exportar Alunos')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Exportar Alunos para Excel')
                ->modalDescription('Você tem certeza que deseja gerar uma planilha com todos os alunos e suas matrículas? Como o volume de dados é grande, este processo rodará em background.')
                ->modalSubmitActionLabel('Sim, exportar')
                ->action(function () {
                    dispatch(new ExportAlunosJob(Auth::id()));

                    Notification::make()
                        ->title('Exportação iniciada!')
                        ->body('Você receberá uma notificação quando o arquivo estiver pronto para download.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
