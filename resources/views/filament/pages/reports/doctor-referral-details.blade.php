<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Doctor Info Header Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $doctor->name }}
                        </h2>
                        @if($doctor->specialization)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-800/60">
                                {{ $doctor->specialization }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mt-2">
                        @if($doctor->phone)
                            <span class="flex items-center gap-1.5">
                                <x-filament::icon icon="heroicon-o-phone" class="w-4 h-4 text-gray-400" />
                                <span dir="ltr">{{ $doctor->phone }}</span>
                            </span>
                        @endif
                        @if($doctor->address)
                            <span class="flex items-center gap-1.5">
                                <x-filament::icon icon="heroicon-o-map-pin" class="w-4 h-4 text-gray-400" />
                                <span>{{ $doctor->address }}</span>
                            </span>
                        @endif
                    </div>
                    @if($doctor->notes)
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 italic">
                            ملاحظات: {{ $doctor->notes }}
                        </p>
                    @endif
                </div>

                @if(! empty($shiftIds))
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            مفلتر وفقاً لورديات التقرير ({{ count($shiftIds) }} وردية)
                        </span>
                        <a href="{{ \App\Filament\Pages\Reports\DoctorReferralDetailsReport::getUrl(['record' => $doctor->id]) }}"
                           class="text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 underline">
                            عرض كافة الفترات
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Filament Flash Cards (Stats Overview Widget) -->
        @livewire(\App\Filament\Pages\Reports\Widgets\DoctorReferralStatsWidget::class, ['doctorId' => $doctor->id, 'shiftIds' => $shiftIds])

        <!-- Patients Table Widget -->
        @livewire(\App\Filament\Pages\Reports\Widgets\DoctorReferralPatientsTableWidget::class, ['doctorId' => $doctor->id, 'shiftIds' => $shiftIds])

        <!-- Patient Visits Table Widget -->
        @livewire(\App\Filament\Pages\Reports\Widgets\DoctorReferralVisitsTableWidget::class, ['doctorId' => $doctor->id, 'shiftIds' => $shiftIds])
    </div>
</x-filament-panels::page>
