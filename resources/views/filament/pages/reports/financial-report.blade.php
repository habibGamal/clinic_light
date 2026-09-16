<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date Filters Bar --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl shadow-xs border border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">تصفية التقرير حسب الفترة:</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label for="startDate" class="text-xs text-gray-500 dark:text-gray-400">من:</label>
                    <input type="date" id="startDate" wire:model.live="startDate"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-xs focus:ring-primary-500 focus:border-primary-500" />
                </div>
                <div class="flex items-center gap-2">
                    <label for="endDate" class="text-xs text-gray-500 dark:text-gray-400">إلى:</label>
                    <input type="date" id="endDate" wire:model.live="endDate"
                        class="text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-xs focus:ring-primary-500 focus:border-primary-500" />
                </div>
            </div>
        </div>

        @php
            $data = $this->reportData;
        @endphp

        {{-- Overview Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي مقبوضات الفترة</p>
                <p class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">
                    {{ number_format($data['totalIncome'], 2) }} <span class="text-xs font-normal">ج.م</span>
                </p>
                <p class="text-xs text-gray-400 mt-1">
                    كاش: {{ number_format($data['cashIncome'], 2) }} | إلكتروني: {{ number_format($data['electronicIncome'], 2) }}
                </p>
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي مصروفات الفترة</p>
                <p class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-1">
                    {{ number_format($data['totalExpenses'], 2) }} <span class="text-xs font-normal">ج.م</span>
                </p>
                <p class="text-xs text-gray-400 mt-1">بنود الصرف المسجلة</p>
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">صافي السيولة النقدية للفترة</p>
                <p class="text-2xl font-bold {{ $data['netCashFlow'] >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }} mt-1">
                    {{ number_format($data['netCashFlow'], 2) }} <span class="text-xs font-normal">ج.م</span>
                </p>
                <p class="text-xs {{ $data['netCashFlow'] >= 0 ? 'text-success-500' : 'text-danger-500' }} mt-1">
                    {{ $data['netCashFlow'] >= 0 ? 'فائض تشغيلي موجب' : 'عجز في السيولة النقدية' }}
                </p>
            </div>

            <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">نسبة النقد (الكاش)</p>
                @php
                    $cashPercentage = $data['totalIncome'] > 0 ? round(($data['cashIncome'] / $data['totalIncome']) * 100, 1) : 0;
                @endphp
                <p class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">
                    {{ $cashPercentage }}%
                </p>
                <p class="text-xs text-gray-400 mt-1">مقابل {{ 100 - $cashPercentage }}% مدفوعات إلكترونية</p>
            </div>
        </div>

        {{-- Breakdown Tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Payment Methods Breakdown --}}
            <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span>💳</span> تفصيل المقبوضات حسب وسيلة الدفع
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="py-2.5 px-3">وسيلة الدفع</th>
                                <th class="py-2.5 px-3 text-center">عدد العمليات</th>
                                <th class="py-2.5 px-3 text-left">إجمالي القيمة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse($data['methodsBreakdown'] as $method)
                                <tr>
                                    <td class="py-2.5 px-3 font-medium text-gray-800 dark:text-gray-200">{{ $method['label'] }}</td>
                                    <td class="py-2.5 px-3 text-center text-gray-600 dark:text-gray-400">{{ $method['count'] }}</td>
                                    <td class="py-2.5 px-3 text-left font-semibold text-gray-900 dark:text-white">{{ number_format($method['total'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-400">لا توجد عمليات دفع مسجلة في هذه الفترة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Expenses Breakdown --}}
            <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span>🧾</span> تفصيل المصروفات حسب البند / التصنيف
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="py-2.5 px-3">بند المصروف</th>
                                <th class="py-2.5 px-3 text-center">عدد السندات</th>
                                <th class="py-2.5 px-3 text-left">إجمالي المنصرف</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse($data['expensesByCategory'] as $cat)
                                <tr>
                                    <td class="py-2.5 px-3 font-medium text-gray-800 dark:text-gray-200">{{ $cat['name'] }}</td>
                                    <td class="py-2.5 px-3 text-center text-gray-600 dark:text-gray-400">{{ $cat['count'] }}</td>
                                    <td class="py-2.5 px-3 text-left font-semibold text-danger-600 dark:text-danger-400">{{ number_format($cat['total'], 2) }} ج.م</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-400">لا توجد مصروفات مسجلة في هذه الفترة</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Shifts Summary Table --}}
        <div class="p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <span>⏱️</span> تقرير إغلاق الورديات في هذه الفترة ومطابقة الخزينة
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="py-2.5 px-3">رقم الوردية</th>
                            <th class="py-2.5 px-3">الكاشير / الموظف</th>
                            <th class="py-2.5 px-3">تاريخ الفتح</th>
                            <th class="py-2.5 px-3">تاريخ الإغلاق</th>
                            <th class="py-2.5 px-3 text-left">الرصيد الافتتاحي</th>
                            <th class="py-2.5 px-3 text-left">الرصيد الدفتري</th>
                            <th class="py-2.5 px-3 text-left">النقد الفعلي</th>
                            <th class="py-2.5 px-3 text-left">الفارق (العجز / الزيادة)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($data['recentShifts'] as $shift)
                            @php
                                $diff = (float) $shift->difference;
                            @endphp
                            <tr>
                                <td class="py-2.5 px-3 font-semibold">#{{ $shift->id }}</td>
                                <td class="py-2.5 px-3">{{ $shift->user?->name ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-xs text-gray-500">{{ $shift->opened_at?->format('Y-m-d h:i A') }}</td>
                                <td class="py-2.5 px-3 text-xs text-gray-500">{{ $shift->closed_at?->format('Y-m-d h:i A') ?? 'مفتوحة حالياً' }}</td>
                                <td class="py-2.5 px-3 text-left">{{ number_format((float) $shift->opening_balance, 2) }} ج.م</td>
                                <td class="py-2.5 px-3 text-left">{{ number_format((float) $shift->closing_balance, 2) }} ج.م</td>
                                <td class="py-2.5 px-3 text-left font-medium">{{ number_format((float) $shift->actual_cash, 2) }} ج.م</td>
                                <td class="py-2.5 px-3 text-left font-bold {{ $diff < 0 ? 'text-danger-600' : ($diff > 0 ? 'text-success-600' : 'text-gray-500') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 2) }} ج.م
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-center text-gray-400">لا توجد ورديات في الفترة المحددة</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
