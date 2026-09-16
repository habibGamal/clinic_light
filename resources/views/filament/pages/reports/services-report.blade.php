<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800/50 rounded-xl p-4 text-sm text-primary-900 dark:text-primary-200">
            <p class="font-semibold">💡 أداء الخدمات الطبية:</p>
            <p class="text-xs text-primary-700 dark:text-primary-300 mt-1">
                يوضح هذا التقرير أكثر الخدمات الطبية طلباً في العيادة، ومقدار الدخل المالي المحقق من كل فحص أو خدمة لتحديد مسارات النمو ومعدلات التشغيل.
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
