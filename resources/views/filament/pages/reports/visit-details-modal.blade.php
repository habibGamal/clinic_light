@php
    $invoice = $visit->invoice ?? app(\App\Services\InvoiceService::class)->syncInvoice($visit);
    $statusEnum = $visit->status;
    $statusLabel = method_exists($statusEnum, 'getLabel') ? $statusEnum->getLabel() : ($statusEnum->value ?? (string) $statusEnum);
    $statusColor = method_exists($statusEnum, 'getColor') ? $statusEnum->getColor() : 'gray';

    $totalPaid = (float) $visit->payments()->where(function ($q) {
        $q->where('type', '!=', 'refund')->orWhereNull('type');
    })->where('amount', '>', 0)->sum('amount');

    $totalRefunded = abs((float) $visit->payments()->where(function ($q) {
        $q->where('type', 'refund')->orWhere('amount', '<', 0);
    })->sum('amount'));

    $invoiceTotal = (float) ($invoice->total_amount ?? 0);
    $remainingDue = max(0.0, (float) ($invoice->remaining_amount ?? ($invoiceTotal - $totalPaid)));
@endphp

<div class="space-y-6 text-gray-900 dark:text-gray-100 p-2 text-right dir-rtl">
    <!-- Visit & Header Summary Banner -->
    <div
        class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b pb-4 border-gray-200 dark:border-gray-700 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-bold text-primary-600 dark:text-primary-400">
                    تفاصيل الزيارة رقم #{{ $visit->id }}
                </h2>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                    @if($statusColor === 'success') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                    @elseif($statusColor === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                    @elseif($statusColor === 'danger') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                    @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 @endif">
                    {{ $statusLabel }}
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                تاريخ وتوقيت الزيارة: {{ $visit->visit_date?->format('Y-m-d h:i A') ?? '-' }}
                @if($visit->shift)
                    &bull; الوردية: #{{ $visit->shift->id }} ({{ $visit->shift->user?->name ?? 'غير محدد' }})
                @endif
            </p>
        </div>

        <div class="text-left dir-ltr">
            <span class="text-xs text-gray-500 dark:text-gray-400 block">رقم الفاتورة</span>
            <span class="font-mono font-bold text-sm text-gray-700 dark:text-gray-300">
                {{ $invoice->invoice_number ?? ('INV-' . str_pad((string) $visit->id, 6, '0', STR_PAD_LEFT)) }}
            </span>
        </div>
    </div>

    <!-- Patient & Referral Info Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Patient Details -->
        <div
            class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-2">
            <h4
                class="font-bold text-sm text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-2 flex items-center gap-2">
                <span>بيانات المريض</span>
            </h4>
            <div class="grid grid-cols-2 gap-3 text-sm pt-1">
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">الاسم الكامل:</span>
                    <span class="font-semibold">{{ $visit->patient?->full_name ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">رقم الهاتف:</span>
                    <span
                        class="font-semibold dir-ltr text-right inline-block">{{ $visit->patient?->phone ?? '-' }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">العمر / الجنس:</span>
                    <span class="font-semibold">
                        {{ $visit->patient?->age ? $visit->patient->age . ' سنة' : '-' }} /
                        {{ $visit->patient?->gender?->getLabel() ?? '-' }}
                    </span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">العنوان:</span>
                    <span
                        class="font-medium text-gray-700 dark:text-gray-300">{{ $visit->patient?->address ?? '-' }}</span>
                </div>
            </div>
            @if($visit->notes)
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 font-medium">ملاحظات الزيارة:</span>
                    <p class="mt-0.5 text-gray-800 dark:text-gray-200">{{ $visit->notes }}</p>
                </div>
            @endif
        </div>

        <!-- Referral & Doctor Details -->
        <div
            class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 space-y-2">
            <h4
                class="font-bold text-sm text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-2 flex items-center gap-2">
                <span>طبيب الإحالة / الجهة المحولة</span>
            </h4>
            @if($visit->referringDoctor)
                <div class="grid grid-cols-2 gap-3 text-sm pt-1">
                    <div>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">اسم الطبيب:</span>
                        <span
                            class="font-semibold text-primary-600 dark:text-primary-400">{{ $visit->referringDoctor->name }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">الهاتف:</span>
                        <span
                            class="font-semibold dir-ltr text-right inline-block">{{ $visit->referringDoctor->phone ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">التخصص:</span>
                        <span class="font-medium">{{ $visit->referringDoctor->specialization ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">العنوان / العيادة:</span>
                        <span class="font-medium">{{ $visit->referringDoctor->address ?? '-' }}</span>
                    </div>
                </div>
            @else
                <div class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        زيارة مباشرة (بدون طبيب إحالة)
                    </span>
                </div>
            @endif
        </div>
    </div>

    <!-- Services & Invoice Items Table -->
    <div>
        <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-2">الفحوصات والخدمات المطلوبة</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead
                    class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">الخدمة / الفحص</th>
                        <th class="p-3 text-center">النوع</th>
                        <th class="p-3 text-center">الكمية</th>
                        <th class="p-3 text-left">السعر</th>
                        <th class="p-3 text-left">الخصم</th>
                        <th class="p-3 text-left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($invoice->items ?? [] as $index => $item)
                        @php
                            $isRefundItem = $item->type === 'refund' || (float) $item->total < 0;
                        @endphp
                        <tr
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 @if($isRefundItem) bg-rose-50/40 dark:bg-rose-950/20 @endif">
                            <td class="p-3 font-medium">{{ $index + 1 }}</td>
                            <td class="p-3">
                                <span
                                    class="font-semibold @if($isRefundItem) text-rose-600 dark:text-rose-400 @else text-gray-900 dark:text-gray-100 @endif">
                                    {{ $item->description }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        @if($item->type === 'service') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                                        @elseif($item->type === 'option') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                                        @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                                    @if($item->type === 'service') خدمة أساسية
                                    @elseif($item->type === 'option') خيار إضافي
                                    @else استرجاع @endif
                                </span>
                            </td>
                            <td class="p-3 text-center">{{ $item->quantity }}</td>
                            <td class="p-3 text-left">{{ number_format((float) $item->unit_price, 2) }} ج.م</td>
                            <td class="p-3 text-left">{{ number_format((float) $item->discount_amount, 2) }} ج.م</td>
                            <td
                                class="p-3 text-left font-semibold @if($isRefundItem) text-rose-600 dark:text-rose-400 @else text-primary-600 dark:text-primary-400 @endif">
                                {{ number_format((float) $item->total, 2) }} ج.م
                            </td>
                        </tr>
                    @empty
                        @foreach($visit->visitServices as $index => $vs)
                            <tr>
                                <td class="p-3 font-medium">{{ $index + 1 }}</td>
                                <td class="p-3 font-semibold">{{ $vs->service?->name ?? 'خدمة' }}</td>
                                <td class="p-3 text-center"><span
                                        class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800">خدمة</span></td>
                                <td class="p-3 text-center">{{ $vs->quantity }}</td>
                                <td class="p-3 text-left">{{ number_format((float) ($vs->service?->base_price ?? 0), 2) }} ج.م
                                </td>
                                <td class="p-3 text-left">{{ number_format((float) $vs->discount_value, 2) }} ج.م</td>
                                <td class="p-3 text-left font-semibold">{{ number_format((float) $vs->total, 2) }} ج.م</td>
                            </tr>
                        @endforeach
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments & Transactions Table -->
    <div>
        <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-2">سجل المدفوعات والمستردات</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead
                    class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">التوقيت</th>
                        <th class="p-3">النوع</th>
                        <th class="p-3">طريقة الدفع</th>
                        <th class="p-3 text-left">المبلغ</th>
                        <th class="p-3">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($visit->payments as $payment)
                        @php
                            $isRefund = $payment->type === 'refund' || (float) $payment->amount < 0;
                        @endphp
                        <tr
                            class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 @if($isRefund) bg-rose-50/40 dark:bg-rose-950/20 @endif">
                            <td class="p-3">{{ ($payment->created_at ?? $payment->paid_at)?->format('Y-m-d h:i A') }}</td>
                            <td class="p-3">
                                @if($isRefund)
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">استرداد</span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">سداد</span>
                                @endif
                            </td>
                            <td class="p-3 font-medium">
                                {{ method_exists($payment->payment_method, 'getLabel') ? $payment->payment_method->getLabel() : ($payment->payment_method?->value ?? (string) $payment->payment_method) }}
                            </td>
                            <td
                                class="p-3 text-left font-semibold @if($isRefund) text-rose-600 dark:text-rose-400 @else text-emerald-600 dark:text-emerald-400 @endif">
                                {{ number_format((float) $payment->amount, 2) }} ج.م
                            </td>
                            <td class="p-3 text-gray-500 dark:text-gray-400">{{ $payment->notes ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500 dark:text-gray-400">لم يتم تسجيل أي مدفوعات
                                لهذه الزيارة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Financial Summary Box -->
    <div class="flex justify-end pt-2">
        <div
            class="w-full sm:w-80 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-4 border border-gray-200 dark:border-gray-700 space-y-2 text-sm">
            <div class="flex justify-between text-gray-600 dark:text-gray-400">
                <span>إجمالي الفاتورة:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($invoiceTotal, 2) }}
                    ج.م</span>
            </div>
            <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                <span>إجمالي المدفوع:</span>
                <span class="font-semibold">{{ number_format($totalPaid, 2) }} ج.م</span>
            </div>
            @if($totalRefunded > 0)
                <div class="flex justify-between text-rose-600 dark:text-rose-400">
                    <span>إجمالي المسترد:</span>
                    <span class="font-semibold">{{ number_format($totalRefunded, 2) }} ج.م</span>
                </div>
            @endif
            <div class="flex justify-between text-primary-600 dark:text-primary-400 font-semibold">
                <span>صافي الإيراد:</span>
                <span>{{ number_format($totalPaid - $totalRefunded, 2) }} ج.م</span>
            </div>
            <div
                class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2 text-base font-bold text-rose-600 dark:text-rose-400">
                <span>المبلغ المتبقي المستحق:</span>
                <span>{{ number_format($remainingDue, 2) }} ج.م</span>
            </div>
        </div>
    </div>
</div>