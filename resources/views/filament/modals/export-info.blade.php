<div class="space-y-4">
    {{-- Barra de Estatísticas Unificada --}}
    <div class="flex items-stretch divide-x divide-gray-200 dark:divide-gray-700 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 overflow-hidden">
        {{-- Registros --}}
        <div class="flex-1 px-5 py-4 text-center">
            <p class="text-2xl font-extrabold text-primary-600 dark:text-primary-400">{{ number_format($count, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Alunos</p>
        </div>

        {{-- Tempo Estimado --}}
        <div class="flex-1 px-5 py-4 text-center">
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white">~{{ $estimatedTime }}</p>
            <p class="mt-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tempo estimado</p>
        </div>

        {{-- Tamanho Aproximado --}}
        <div class="flex-1 px-5 py-4 text-center">
            <p class="text-2xl font-extrabold text-gray-900 dark:text-white">~{{ $estimatedSize }}</p>
            <p class="mt-1 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tamanho</p>
        </div>
    </div>

    {{-- Instruções --}}
    <div class="rounded-xl bg-primary-50 dark:bg-primary-500/5 border border-primary-100 dark:border-primary-500/10 p-4">
        <div class="flex gap-3">
            <x-heroicon-o-information-circle class="h-5 w-5 mt-0.5 shrink-0 text-primary-500" />
            <div class="text-sm text-gray-600 dark:text-white">
                A exportação será processada em segundo plano. Você pode continuar navegando normalmente
                <strong>uma barra de progresso</strong> aparecerá no canto inferior da tela e uma notificação
                no ícone de sino 🔔 com o link para download quando a planilha estiver pronta.
            </div>
        </div>
    </div>
</div>
