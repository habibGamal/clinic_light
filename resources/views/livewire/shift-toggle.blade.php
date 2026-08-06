<div class="flex items-center px-3">
    @if ($this->getActiveShift())
        {{ $this->closeShiftAction }}
    @else
        {{ $this->openShiftAction }}
    @endif

    <x-filament-actions::modals />
</div>
