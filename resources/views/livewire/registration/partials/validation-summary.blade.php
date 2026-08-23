@props([
    'compact' => false,
])

@if ($errors->isNotEmpty())
    <div
        wire:key="validation-{{ $compact ? 'dock' : 'top' }}-{{ md5($errors->toJson()) }}"
        @class([
            'reg-validation-summary',
            'reg-validation-summary-compact' => $compact,
        ])
        role="alert"
        aria-live="assertive"
        x-data
        x-init="
            $nextTick(() => {
                const firstField = document.querySelector('.reg-field-error');
                if (firstField) {
                    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (! @js($compact)) {
                    $el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            })
        "
    >
        <div class="reg-validation-summary-icon" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-extrabold text-red-800">يرجى تصحيح الأخطاء التالية</p>
            <ul class="mt-1.5 list-disc space-y-1 pe-4 text-sm font-medium text-red-700">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
