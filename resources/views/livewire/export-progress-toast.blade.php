<div wire:poll.3s="checkProgress">
    @if($visible)
        <style>
            .export-toast-container {
                position: fixed;
                bottom: 1.5rem;
                right: 1.5rem;
                z-index: 9999;
                width: 24rem;
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
                background-color: #ffffff;
                padding: 1rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
            .dark .export-toast-container {
                background-color: #1f2937;
                border-color: #374151;
            }
            .export-toast-progress-bg {
                height: 0.5rem;
                width: 100%;
                overflow: hidden;
                border-radius: 9999px;
                background-color: #f3f4f6;
            }
            .dark .export-toast-progress-bg {
                background-color: #374151;
            }
            .export-toast-progress-bar {
                height: 100%;
                border-radius: 9999px;
                background: linear-gradient(to right, #fbbf24, #f59e0b);
                transition: width 0.5s ease-out;
            }
        </style>

        <div
            class="export-toast-container"
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-4 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-4 opacity-0"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($status === 'processing')
                        <div class="h-2 w-2 rounded-full bg-amber-500 animate-pulse" style="background-color: #f59e0b; width: 0.5rem; height: 0.5rem; border-radius: 9999px;"></div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white" style="font-size: 0.875rem; font-weight: 600;">Exportando alunos</span>
                    @elseif($status === 'completed')
                        <div class="h-2 w-2 rounded-full bg-green-500" style="background-color: #22c55e; width: 0.5rem; height: 0.5rem; border-radius: 9999px;"></div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white" style="font-size: 0.875rem; font-weight: 600;">Exportação concluída!</span>
                    @elseif($status === 'failed')
                        <div class="h-2 w-2 rounded-full bg-red-500" style="background-color: #ef4444; width: 0.5rem; height: 0.5rem; border-radius: 9999px;"></div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white" style="font-size: 0.875rem; font-weight: 600;">Erro na exportação</span>
                    @endif
                </div>
                <button
                    wire:click="dismiss"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <x-heroicon-m-x-mark class="h-4 w-4" style="width: 1rem; height: 1rem;"/>
                </button>
            </div>

            {{-- Content --}}
            <div class="mt-3" style="margin-top: 0.75rem;">
                @if($status === 'processing')
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2" style="font-size: 0.75rem; margin-bottom: 0.5rem;">Processando registros...</p>
                    
                    {{-- Barra de Progresso --}}
                    <div class="export-toast-progress-bg">
                        <div
                            class="export-toast-progress-bar"
                            style="width: {{ $percentage }}%"
                        ></div>
                    </div>

                    {{-- Contagem --}}
                    <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400" style="margin-top: 0.5rem; font-size: 0.75rem; display: flex; justify-content: space-between;">
                        <span>{{ number_format($processed, 0, ',', '.') }} de {{ number_format($total, 0, ',', '.') }}</span>
                        <span class="font-medium" style="font-weight: 500; color: #d97706;">{{ $percentage }}%</span>
                    </div>

                @elseif($status === 'completed')
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" style="font-size: 0.75rem; margin-bottom: 0.75rem;">Sua planilha está pronta para download.</p>
                    <a
                        href="{{ $fileUrl }}"
                        target="_blank"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-green-700"
                        style="display: flex; width: 100%; align-items: center; justify-content: center; border-radius: 0.5rem; background-color: #16a34a; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; text-decoration: none;"
                    >
                        <x-heroicon-m-arrow-down-tray class="h-4 w-4" style="width: 1rem; height: 1rem;"/>
                        Baixar Planilha
                    </a>

                @elseif($status === 'failed')
                    <p class="text-xs text-red-500 dark:text-red-400">
                        Ocorreu um erro ao gerar a planilha. Verifique o sino de notificações para mais detalhes.
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
