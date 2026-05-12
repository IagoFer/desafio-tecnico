<?php

namespace App\Services\Export;

use App\Support\Formatters\DocumentFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;

class AlunoExportService
{
    /**
     * Orquestra a geração do arquivo Excel de Alunos.
     *
     * @param int $userId ID do usuário solicitante (para logs de auditoria).
     * @return string O nome do arquivo gerado.
     */
    public function export(int $userId): string
    {
        $fileName = 'alunos_export_' . now()->format('Ymd_His') . '.xlsx';
        $exportDir = storage_path('app/public/exports');
        $filePath = $exportDir . '/' . $fileName;

        if (!is_dir($exportDir)) {
            Storage::disk('public')->makeDirectory('exports');
        }

        $options = new Options();
        $writer = new Writer($options);
        $writer->openToFile($filePath);

        $this->writeHeader($writer);
        
        $totalExported = $this->processDataInChunks($writer);

        $writer->close();
        
        Log::info('Serviço de exportação concluído.', [
            'solicitado_por' => $userId,
            'arquivo' => $fileName,
            'total_registros_exportados' => $totalExported,
            'pico_memoria_mb' => round(memory_get_peak_usage(true) / 1048576, 2)
        ]);

        return $fileName;
    }

    /**
     * Escreve a linha de cabeçalho no arquivo.
     */
    private function writeHeader(Writer $writer): void
    {
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
    }

    /**
     * Processa os dados em lote e grava no arquivo Excel.
     * @return int O total de usuários exportados.
     */
    private function processDataInChunks(Writer $writer): int
    {
        $totalProcessed = 0;

        // Utilizamos whereExists para buscar apenas usuários que POSSUEM matrícula,
        // economizando recursos de banco de dados e memória PHP.
        DB::table('users')
            ->select('id', 'name', 'email', 'data_de_nascimento')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('matriculas')
                      ->whereColumn('matriculas.user_id', 'users.id');
            })
            ->orderBy('id')
            ->chunk(2000, function ($users) use ($writer, &$totalProcessed) {
                
                $userIds = $users->pluck('id')->toArray();

                // Buscas otimizadas em lote via índices
                $documentos = DB::table('documentos')->whereIn('user_id', $userIds)->get()->keyBy('user_id');
                $enderecos = DB::table('enderecos')->whereIn('user_id', $userIds)->get()->keyBy('user_id');
                
                // Busca de matrículas com ordenação pré-definida no SQL (mais performático)
                $matriculasRaw = DB::table('matriculas')
                    ->join('escolas', 'matriculas.escola_id', '=', 'escolas.id')
                    ->whereIn('matriculas.user_id', $userIds)
                    ->select(
                        'matriculas.user_id',
                        'matriculas.ano_letivo',
                        'matriculas.resultado_final',
                        'matriculas.data_de_criacao',
                        'escolas.nome as escola_nome'
                    )
                    ->orderByDesc('matriculas.data_de_criacao')
                    ->get();
                
                // Agrupamento rápido em PHP (A ordem desc já está mantida)
                $matriculasPorUsuario = [];
                foreach ($matriculasRaw as $m) {
                    $matriculasPorUsuario[$m->user_id][] = $m;
                }

                foreach ($users as $user) {
                    $mats = $matriculasPorUsuario[$user->id] ?? [];
                    
                    if (empty($mats)) {
                        continue; // Fallback, mas o whereExists já deve garantir isso.
                    }

                    $doc = $documentos->get($user->id);
                    $end = $enderecos->get($user->id);

                    // Formata os dados no DTO misto (user)
                    $user->cpf = DocumentFormatter::formatCpf($doc->cpf ?? '');
                    $user->rg = DocumentFormatter::formatRg($doc->rg ?? '');
                    $user->logradouro = $end->logradouro ?? '';
                    $user->cep = DocumentFormatter::formatCep($end->cep ?? '');

                    $this->writeRow($writer, $user, $mats);
                    $totalProcessed++;
                }
            });

        return $totalProcessed;
    }

    /**
     * Processa cálculos e escreve a linha formatada no arquivo Excel.
     */
    private function writeRow(Writer $writer, object $userRow, array $matriculas): void
    {
        // O array $matriculas já vem ordenado pela data_de_criacao DESC através da query SQL.

        // Range de Escolaridade
        $anosLetivos = array_filter(array_map(fn($m) => $m->ano_letivo, $matriculas));
        $rangeEscolaridade = empty($anosLetivos) ? '' : min($anosLetivos) . '-' . max($anosLetivos);

        // Escola da matrícula mais recente
        $escola = $matriculas[0]->escola_nome ?? '';

        // Contagem de Aprovações e Reprovações
        $aprovacoes = count(array_filter($matriculas, fn($m) => $m->resultado_final === 'aprovado'));
        $reprovacoes = count(array_filter($matriculas, fn($m) => $m->resultado_final === 'reprovado'));

        // Data de Nascimento
        $dataNascimento = $userRow->data_de_nascimento ? date('d/m/Y', strtotime($userRow->data_de_nascimento)) : '';

        $row = Row::fromValues([
            $userRow->name,
            $userRow->email,
            $dataNascimento,
            $rangeEscolaridade,
            $escola,
            $userRow->cpf,
            $userRow->rg,
            $userRow->logradouro,
            $userRow->cep,
            $aprovacoes,
            $reprovacoes,
        ]);

        $writer->addRow($row);
    }
}
