@php
    $groups = $this->groups();
    $scannedCount = $this->scannedCount();
    $familyCount = $this->familyCount();
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="hr-review"
        x-data
        x-on:insurance-card-scanned.window="$nextTick(() => { $refs.scan?.focus(); $refs.scan?.select(); })"
    >
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__head">
                    <div>
                        <h3 class="hr-panel__title">مسح البطاقة</h3>
                        <p class="hr-panel__meta" style="margin-top: 0.2rem;">
                            امسح باركود البطاقة أو أدخل الرقم ثم اضغط إدخال. تُجمَّع البطاقات حسب العائلة.
                        </p>
                    </div>
                </div>
                <div class="hr-panel__body">
                    <form class="hr-scan-form" wire:submit="scanCard">
                        <input
                            x-ref="scan"
                            type="text"
                            name="scan"
                            wire:model="scan"
                            class="hr-scan-input"
                            autocomplete="off"
                            inputmode="numeric"
                            autofocus
                            placeholder="SC-12345678"
                            aria-label="رقم بطاقة التأمين"
                        >
                        <button type="submit" class="hr-card-action hr-scan-submit">
                            إضافة
                        </button>
                    </form>

                    <div class="hr-kpis hr-kpis--report" style="margin-top: 1.1rem;">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">بطاقات ممسوحة</span>
                            <div class="hr-kpi__value">{{ $scannedCount }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">عائلات</span>
                            <div class="hr-kpi__value">{{ $familyCount }}</div>
                        </div>
                    </div>
                </div>
            </section>

            @if ($groups === [])
                <section class="hr-panel">
                    <div class="hr-panel__body">
                        <div class="hr-empty">
                            <x-filament::icon icon="heroicon-o-qr-code" class="h-7 w-7" />
                            <p class="hr-empty__title">لا توجد بطاقات ممسوحة بعد</p>
                            <p class="hr-empty__text">ابدأ بمسح بطاقة الموظف أو أحد أفراد العائلة. ستظهر العائلة هنا مجمّعة.</p>
                        </div>
                    </div>
                </section>
            @else
                @foreach ($groups as $group)
                    <section class="hr-panel">
                        <div class="hr-panel__head">
                            <div>
                                <h3 class="hr-panel__title">{{ $group['employee_name'] }}</h3>
                                <p class="hr-panel__meta" style="margin-top: 0.2rem;">
                                    مسح {{ $group['scanned_count'] }} من {{ $group['expected_count'] }}
                                    @if (filled($group['reference']))
                                        · {{ $group['reference'] }}
                                    @endif
                                </p>
                            </div>
                            <div class="hr-card-actions">
                                @if ($group['complete'])
                                    <span class="hr-chip hr-chip--approved">العائلة مكتملة</span>
                                @else
                                    <span class="hr-chip hr-chip--editing">ناقص {{ $group['expected_count'] - $group['scanned_count'] }}</span>
                                @endif
                                @if (filled($group['registration_url']))
                                    <a href="{{ $group['registration_url'] }}" class="hr-card-action">ملف الطلب</a>
                                @endif
                                <button
                                    type="button"
                                    class="hr-card-action"
                                    wire:click="removeGroup({{ $group['employee_id'] }})"
                                >
                                    إزالة العائلة
                                </button>
                            </div>
                        </div>
                        <div class="hr-panel__body">
                            <div class="hr-scan-members">
                                @foreach ($group['members'] as $member)
                                    <div @class(['hr-scan-member', 'hr-scan-member--scanned' => $member['scanned']])>
                                        <div>
                                            <strong>{{ $member['name'] }}</strong>
                                            <span>{{ $member['role_label'] }} · {{ $member['card_label'] }}</span>
                                        </div>
                                        <div class="hr-scan-member__status">
                                            @if ($member['scanned'])
                                                <span class="hr-chip hr-chip--approved">تم المسح</span>
                                                @if ($member['card_number'])
                                                    <button
                                                        type="button"
                                                        class="hr-card-action"
                                                        wire:click="removeCard({{ \Illuminate\Support\Js::from($member['card_number']) }})"
                                                    >
                                                        إزالة
                                                    </button>
                                                @endif
                                            @else
                                                <span class="hr-chip hr-chip--draft">لم يُمسح بعد</span>
                                            @endif
                                            @if ($member['is_printed'])
                                                <span class="hr-chip hr-chip--submitted">مطبوعة</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endforeach
            @endif
        </div>
    </div>
</x-filament-panels::page>
