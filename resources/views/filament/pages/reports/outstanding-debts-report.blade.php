<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-warning-50 dark:bg-warning-950/40 border border-warning-200 dark:border-warning-800/50 rounded-xl p-4 text-sm text-warning-900 dark:text-warning-200">
            <p class="font-semibold">⚠️ إدارة ومتابعة الديون والتحصيل:</p>
            <p class="text-xs text-warning-700 dark:text-warning-300 mt-1">
                يعرض هذا التقرير جميع الفواتير التي لم تسدد بالكامل وتضم مبالغ متبقية، مع إمكانية التواصل المباشر مع المريض لمتابعة التحصيل وإغلاق المستحقات.
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
