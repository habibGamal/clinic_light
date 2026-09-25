@php
    $summary = $this->getServiceSummary();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Service Info & Quick Stats Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-5">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $service->name }}
                        </h2>
                        @if($service->serviceCategory)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-800/60">
                                {{ $service->serviceCategory->name }}
                            </span>
                        @endif
                        @if($service->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                نشطة
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                غير نشطة
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mt-2">
                        @if($service->code)
                            <span class="flex items-center gap-1.5 font-mono">
                                <x-filament::icon icon="heroicon-o-tag" class="w-4 h-4 text-gray-400" />
                                <span>كود الخدمة: {{ $service->code }}</span>
                            </span>
                        @endif
                        <span class="flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-o-banknotes" class="w-4 h-4 text-gray-400" />
                            <span>السعر الأساسي: {{ number_format((float) $service->base_price, 2) }} ج.م</span>
                        </span>
                    </div>
                </div>

                @if(! empty($shiftIds))
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            مفلتر وفقاً لورديات التقرير ({{ count($shiftIds) }} وردية)
                        </span>
                        <a href="{{ \App\Filament\Pages\Reports\ServiceDetailsReport::getUrl(['record' => $service->id]) }}"
                           class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 underline">
                            عرض كافة الفترات
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Filament Flash Cards (Stats Overview Widget) -->
        @livewire(\App\Filament\Pages\Reports\Widgets\ServiceDetailStatsWidget::class, ['serviceId' => $service->id, 'shiftIds' => $shiftIds])

        <!-- Patient Visits Table Widget -->
        @livewire(\App\Filament\Pages\Reports\Widgets\ServiceVisitsTableWidget::class, ['serviceId' => $service->id, 'shiftIds' => $shiftIds])
    </div>
</x-filament-panels::page>
