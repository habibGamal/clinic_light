<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800/50 rounded-xl p-4 text-sm text-primary-900 dark:text-primary-200">
            <p class="font-semibold">💡 فكرة التقرير:</p>
            <p class="text-xs text-primary-700 dark:text-primary-300 mt-1">
                يوضح هذا التقرير الأطباء والمراكز الخارجية التي تقوم بتحويل المرضى إلى العيادة، مع حصر إجمالي الزيارات ومجموع العوائد المحصلة من كل طبيب لمتابعة العلاقات الطبية والعمولات.
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
