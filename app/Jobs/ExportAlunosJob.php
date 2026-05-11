<?php

namespace App\Jobs;

use App\Enums\ResultadoFinal;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;

class ExportAlunosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes max

    public function __construct(
        protected int $userId
    ) {}

    public function handle(): void
    {
        $fileName = 'alunos_export_' . now()->format('Ymd_His') . '.xlsx';
        $exportDir = storage_path('app/public/exports');
        $filePath = $exportDir . '/' . $fileName;

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // Header
        $headerRow = Row::fromValues([
            'Nome',
            'E-mail',
            'Data de Nascimento',
            'Range de Escolaridade',
            'Escola',
            'CPF',
            'RG',
            'Logradouro',
            'CEP',
            'Aprovações',
            'Reprovações'
        ]);
        $writer->addRow($headerRow);

        // Process data
        $users = User::has('matriculas')
            ->with(['matriculas.escola', 'documento', 'endereco'])
            ->cursor();

        foreach ($users as $user) {
            $matriculas = $user->matriculas;

            // Range de Escolaridade
            $anosLetivos = $matriculas->pluck('ano_letivo')->filter();
            $rangeEscolaridade = '';
            if ($anosLetivos->isNotEmpty()) {
                $rangeEscolaridade = $anosLetivos->min() . '-' . $anosLetivos->max();
            }

            // Escola (most recent matricula by data_de_criacao)
            $latestMatricula = $matriculas->sortByDesc('data_de_criacao')->first();
            $escola = $latestMatricula?->escola?->nome ?? '';

            // CPF
            $cpf = $user->documento?->cpf ?? '';
            $cpf = preg_replace('/\D/', '', $cpf);
            if (preg_match('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', $cpf, $matches)) {
                $cpf = "{$matches[1]}.{$matches[2]}.{$matches[3]}-{$matches[4]}";
            }

            // RG
            $rg = $user->documento?->rg ?? '';
            $rg = preg_replace('/\D/', '', $rg);
            if (preg_match('/^(\d{2})(\d{3})(\d{3})(\d{1})$/', $rg, $matches)) {
                $rg = "{$matches[1]}.{$matches[2]}.{$matches[3]}-{$matches[4]}";
            } elseif (strlen($rg) > 0) {
                 // Fallback format if it doesn't match standard SP RG size
                 $rg = substr($rg, 0, -1) . '-' . substr($rg, -1);
            }

            // CEP
            $cep = $user->endereco?->cep ?? '';
            $cep = preg_replace('/\D/', '', $cep);
            if (preg_match('/^(\d{5})(\d{3})$/', $cep, $matches)) {
                $cep = "{$matches[1]}-{$matches[2]}";
            }

            // Aprovações / Reprovações
            $aprovacoes = $matriculas->where('resultado_final', ResultadoFinal::Aprovado)->count();
            $reprovacoes = $matriculas->where('resultado_final', ResultadoFinal::Reprovado)->count();

            $row = Row::fromValues([
                $user->name,
                $user->email,
                $user->data_de_nascimento?->format('d/m/Y') ?? '',
                $rangeEscolaridade,
                $escola,
                $cpf,
                $rg,
                $user->endereco?->logradouro ?? '',
                $cep,
                $aprovacoes,
                $reprovacoes,
            ]);

            $writer->addRow($row);
        }

        $writer->close();

        // Send Notification
        $recipient = User::find($this->userId);
        if ($recipient) {
            Notification::make()
                ->title('Exportação Finalizada!')
                ->body('O arquivo Excel com os dados dos alunos foi gerado com sucesso.')
                ->success()
                ->actions([
                    Action::make('Baixar Planilha')
                        ->url(Storage::url('exports/' . $fileName))
                        ->button()
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($recipient);
        }
    }
}
