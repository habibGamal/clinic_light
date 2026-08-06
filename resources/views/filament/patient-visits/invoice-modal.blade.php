<div class="space-y-6 text-gray-900 dark:text-gray-100 p-2">
    <!-- Header Info -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b pb-4 border-gray-200 dark:border-gray-700 gap-4">
        <div>
            <h2 class="text-xl font-bold text-primary-600 dark:text-primary-400">فاتورة زيارة #{{ $visit->id }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">تاريخ الزيارة: {{ $visit->visit_date?->format('Y-m-d H:i') }}</p>
        </div>
        <div class="text-left dir-ltr">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                @if($remainingDue <= 0 && $invoiceTotal > 0) bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                @elseif($totalPaid > 0) bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                @if($remainingDue <= 0 && $invoiceTotal > 0)
                    مسددة بالكامل
                @elseif($totalPaid > 0)
                    مدفوعة جزئياً
                @else
                    غير مسددة
                @endif
            </span>
        </div>
    </div>

    <!-- Client & Visit Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/50 text-sm">
        <div>
            <span class="block text-gray-500 dark:text-gray-400 text-xs font-medium">اسم المريض</span>
            <span class="font-semibold">{{ $visit->patient?->full_name ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-gray-500 dark:text-gray-400 text-xs font-medium">رقم الهاتف</span>
            <span class="font-semibold">{{ $visit->patient?->phone ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-gray-500 dark:text-gray-400 text-xs font-medium">العمر / الجنس</span>
            @php
                $genderLabel = '-';
                if ($visit->patient?->gender) {
                    $genderLabel = method_exists($visit->patient->gender, 'getLabel')
                        ? $visit->patient->gender->getLabel()
                        : ($visit->patient->gender->value ?? (string) $visit->patient->gender);
                }
            @endphp
            <span class="font-semibold">{{ $visit->patient?->age ? $visit->patient->age . ' سنة' : '-' }} / {{ $genderLabel }}</span>
        </div>
        <div>
            <span class="block text-gray-500 dark:text-gray-400 text-xs font-medium">طبيب الإحالة</span>
            <span class="font-semibold">{{ $visit->referringDoctor?->name ?? 'مباشر' }}</span>
        </div>
    </div>

    <!-- Services Table -->
    <div>
        <h3 class="text-md font-bold mb-3">تفاصيل الخدمات المطلوبة والفحوصات</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">اسم الخدمة والخيارات</th>
                        <th class="p-3 text-center">الكمية</th>
                        <th class="p-3 text-left">سعر الوحدة</th>
                        <th class="p-3 text-left">الخصم</th>
                        <th class="p-3 text-left">الإجمالي</th>
                        <th class="p-3 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($visit->visitServices as $index => $vs)
                        @php
                            $optNames = $vs->selectedOptions->map(fn($o) => $o->serviceOption?->name)->filter()->implode('، ');
                        @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="p-3 font-medium">{{ $index + 1 }}</td>
                            <td class="p-3">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $vs->service?->name }}</span>
                                @if(!empty($optNames))
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 font-normal">({{ $optNames }})</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">{{ $vs->quantity }}</td>
                            <td class="p-3 text-left">{{ number_format((float)$vs->unit_price, 2) }} EGP</td>
                            <td class="p-3 text-left">
                                @if($vs->discount_type === \App\Enums\DiscountType::Percent)
                                    {{ $vs->discount_value }}%
                                @else
                                    {{ number_format((float)$vs->discount_value, 2) }} EGP
                                @endif
                            </td>
                            <td class="p-3 text-left font-semibold text-primary-600 dark:text-primary-400">{{ number_format((float)$vs->total, 2) }} EGP</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($vs->status === \App\Enums\VisitServiceStatus::Completed) bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                    @elseif($vs->status === \App\Enums\VisitServiceStatus::Cancelled) bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                    @else bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 @endif">
                                    {{ method_exists($vs->status, 'getLabel') ? $vs->status->getLabel() : ($vs->status?->value ?? (string) $vs->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-400">لا توجد خدمات مضافة لهذه الزيارة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments Table -->
    <div>
        <h3 class="text-md font-bold mb-3">سجل الدفعات والمتحصلات</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">تاريخ الدفع</th>
                        <th class="p-3">طريقة الدفع</th>
                        <th class="p-3 text-left">المبلغ المدفوع</th>
                        <th class="p-3">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($visit->payments as $payment)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="p-3">{{ $payment->paid_at?->format('Y-m-d H:i') }}</td>
                            <td class="p-3 font-medium">{{ method_exists($payment->payment_method, 'getLabel') ? $payment->payment_method->getLabel() : ($payment->payment_method?->value ?? (string) $payment->payment_method) }}</td>
                            <td class="p-3 text-left font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format((float)$payment->amount, 2) }} EGP</td>
                            <td class="p-3 text-gray-500 dark:text-gray-400">{{ $payment->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500 dark:text-gray-400">لم يتم تسجيل أي مدفوعات لهذه الزيارة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Financial Summary Box -->
    <div class="flex justify-end pt-2">
        <div class="w-full sm:w-80 rounded-lg bg-gray-50 dark:bg-gray-800/60 p-4 border border-gray-200 dark:border-gray-700 space-y-2 text-sm">
            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                <span>إجمالي خدمات الفاتورة:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($invoiceTotal, 2) }} EGP</span>
            </div>
            <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                <span>إجمالي المبلغ المدفوع:</span>
                <span class="font-semibold">{{ number_format($totalPaid, 2) }} EGP</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2 text-base font-bold text-rose-600 dark:text-rose-400">
                <span>المبلغ المتبقي المستحق:</span>
                <span>{{ number_format($remainingDue, 2) }} EGP</span>
            </div>
        </div>
    </div>
</div>
