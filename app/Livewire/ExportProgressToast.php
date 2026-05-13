<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Toast de progresso da exportação de alunos.
 *
 * Renderiza um toast fixo no canto inferior direito da tela com:
 * - Barra de progresso animada durante o processamento
 * - Botão de download quando a exportação é concluída
 * - Mensagem de erro em caso de falha
 *
 * O componente usa wire:poll para consultar o cache Redis a cada 3 segundos.
 * Quando não há exportação ativa, o componente fica invisível (sem polling).
 */
class ExportProgressToast extends Component
{
    public string $status = 'idle';
    public int $processed = 0;
    public int $total = 0;
    public string $fileUrl = '';
    public string $fileName = '';
    public bool $dismissed = false;
    public bool $visible = false;
    public int $percentage = 0;

    /**
     * Consulta o cache Redis para atualizar o estado do componente.
     * Chamado automaticamente pelo wire:poll a cada 3 segundos.
     */
    public function checkProgress(): void
    {
        $userId = Auth::id();
        if (!$userId) return;

        $progress = Cache::get("export_progress_{$userId}");

        if (!$progress) {
            $this->status = 'idle';
            $this->dismissed = false;
            $this->visible = false;
            $this->percentage = 0;
            return;
        }

        $this->status = $progress['status'] ?? 'idle';
        $this->processed = $progress['processed'] ?? 0;
        $this->total = $progress['total'] ?? 0;
        $this->fileUrl = $progress['file_url'] ?? '';
        $this->fileName = $progress['file_name'] ?? '';

        $this->visible = !$this->dismissed && in_array($this->status, ['processing', 'completed', 'failed']);
        
        if ($this->total > 0) {
            $this->percentage = min(100, (int) round(($this->processed / $this->total) * 100));
        } else {
            $this->percentage = 0;
        }
    }

    /**
     * Fecha o toast e limpa o cache de progresso.
     */
    public function dismiss(): void
    {
        $this->dismissed = true;
        $userId = Auth::id();
        
        if ($userId && in_array($this->status, ['completed', 'failed'])) {
            Cache::forget("export_progress_{$userId}");
        }
        
        $this->status = 'idle';
    }

    public function render()
    {
        $this->checkProgress();

        return view('livewire.export-progress-toast');
    }
}
