@php
    $invoice = $invoice ?? app(\App\Services\InvoiceService::class)->syncInvoice($visit);
@endphp

<div class="space-y-6 text-gray-900 dark:text-gray-100 p-2">
    <!-- Header Info -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b pb-4 border-gray-200 dark:border-gray-700 gap-4">
        <div>
            <h2 class="text-xl font-bold text-primary-600 dark:text-primary-400">
                {{ $invoice->invoice_number ?? ('فاتورة زيارة #' . $visit->id) }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">تاريخ الزيارة: {{ $visit->visit_date?->format('Y-m-d H:i') }}</p>
        </div>
        <div class="text-left dir-ltr">
            @php
                $statusEnum = $invoice->status;
                $statusLabel = method_exists($statusEnum, 'getLabel') ? $statusEnum->getLabel() : ($statusEnum->value ?? (string) $statusEnum);
                $color = method_exists($statusEnum, 'getColor') ? $statusEnum->getColor() : 'gray';
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                @if($color === 'success') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                @elseif($color === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                @elseif($color === 'info') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                @elseif($color === 'danger') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 @endif">
                {{ $statusLabel }}
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

    <!-- Invoice Line Items Table -->
    <div>
        <h3 class="text-md font-bold mb-3">بنود ومفردات الفاتورة (Invoice Items)</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">الوصف / البند</th>
                        <th class="p-3 text-center">النوع</th>
                        <th class="p-3 text-center">الكمية</th>
                        <th class="p-3 text-left">سعر الوحدة</th>
                        <th class="p-3 text-left">الخصم</th>
                        <th class="p-3 text-left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($invoice->items as $index => $item)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 @if($item->type === 'refund') bg-rose-50/40 dark:bg-rose-950/20 @endif">
                            <td class="p-3 font-medium">{{ $index + 1 }}</td>
                            <td class="p-3">
                                <span class="font-semibold @if($item->type === 'refund') text-rose-600 dark:text-rose-400 @else text-gray-900 dark:text-gray-100 @endif">
                                    {{ $item->description }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($item->type === 'service') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                                    @elseif($item->type === 'option') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                                    @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                                    @if($item->type === 'service') خدمة أساسية
                                    @elseif($item->type === 'option') إضافي
                                    @else استرجاع @endif
                                </span>
                            </td>
                            <td class="p-3 text-center">{{ $item->quantity }}</td>
                            <td class="p-3 text-left">{{ number_format((float)$item->unit_price, 2) }} EGP</td>
                            <td class="p-3 text-left">{{ number_format((float)$item->discount_amount, 2) }} EGP</td>
                            <td class="p-3 text-left font-semibold @if((float)$item->total < 0) text-rose-600 dark:text-rose-400 @else text-primary-600 dark:text-primary-400 @endif">
                                {{ number_format((float)$item->total, 2) }} EGP
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-400">لا توجد بنود مضافة لهذه الفاتورة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payments Table -->
    <div>
        <h3 class="text-md font-bold mb-3">سجل الدفعات والمتحصلات (Payments)</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="p-3">تاريخ وتوقيت العملية</th>
                        <th class="p-3">طريقة الدفع</th>
                        <th class="p-3 text-left">المبلغ المدفوع</th>
                        <th class="p-3">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($visit->payments as $payment)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                            <td class="p-3">{{ ($payment->created_at ?? $payment->paid_at)?->format('Y-m-d H:i') }}</td>
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
                <span>إجمالي الفاتورة (Total):</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($invoiceTotal, 2) }} EGP</span>
            </div>
            <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                <span>إجمالي المدفوع (Paid):</span>
                <span class="font-semibold">{{ number_format($totalPaid, 2) }} EGP</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2 text-base font-bold text-rose-600 dark:text-rose-400">
                <span>المبلغ المتبقي المستحق:</span>
                <span>{{ number_format($remainingDue, 2) }} EGP</span>
            </div>
        </div>
    </div>
</div>
