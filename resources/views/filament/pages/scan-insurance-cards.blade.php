@php
    $groups = $this->groups();
    $scannedCount = $this->scannedCount();
    $familyCount = $this->familyCount();
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="hr-scan"
        x-data
        x-on:insurance-card-scanned.window="$nextTick(() => { $refs.scan?.focus(); $refs.scan?.select(); })"
    >
        <form class="hr-scan__bar" wire:submit="scanCard">
            <input
                x-ref="scan"
                type="text"
                name="scan"
                wire:model="scan"
                class="hr-scan__input"
                autocomplete="off"
                inputmode="numeric"
                autofocus
                placeholder="امسح البطاقة أو أدخل الرقم"
                aria-label="رقم بطاقة التأمين"
            >
            <button type="submit" class="hr-scan__add">إضافة</button>
            <div class="hr-scan__counts" aria-live="polite">
                <span><b>{{ $scannedCount }}</b> بطاقة</span>
                <span><b>{{ $familyCount }}</b> عائلة</span>
            </div>
        </form>

        @if ($groups === [])
            <p class="hr-scan__empty">لا توجد بطاقات ممسوحة بعد. امسح بطاقة الموظف أو أحد أفراد العائلة.</p>
        @else
            <div class="hr-scan-groups">
                @foreach ($groups as $group)
                    <section @class(['hr-scan-group', 'hr-scan-group--complete' => $group['complete']])>
                        <header class="hr-scan-group__head">
                            <div class="hr-scan-group__title">
                                <strong>{{ $group['employee_name'] }}</strong>
                                <span>
                                    {{ $group['scanned_count'] }}/{{ $group['expected_count'] }}
                                    @if (filled($group['reference']))
                                        · {{ $group['reference'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="hr-scan-group__tools">
                                @if ($group['complete'])
                                    <span class="hr-scan-flag hr-scan-flag--ok">مكتملة</span>
                                @else
                                    <span class="hr-scan-flag hr-scan-flag--miss">ناقص {{ $group['expected_count'] - $group['scanned_count'] }}</span>
                                @endif
                                @if (filled($group['registration_url']))
                                    <a href="{{ $group['registration_url'] }}" class="hr-scan-link">الطلب</a>
                                @endif
                                <button
                                    type="button"
                                    class="hr-scan-x"
                                    wire:click="removeGroup({{ $group['employee_id'] }})"
                                    title="إزالة العائلة"
                                    aria-label="إزالة العائلة"
                                >
                                    ×
                                </button>
                            </div>
                        </header>
                        <ul class="hr-scan-members">
                            @foreach ($group['members'] as $member)
                                <li @class(['hr-scan-member', 'hr-scan-member--scanned' => $member['scanned']])>
                                    <span class="hr-scan-member__mark" aria-hidden="true">{{ $member['scanned'] ? '✓' : '·' }}</span>
                                    <span class="hr-scan-member__who">
                                        <b>{{ $member['name'] }}</b>
                                        <i>{{ $member['role_label'] }}{{ filled($member['card_label']) && $member['card_label'] !== '—' ? ' · '.$member['card_label'] : '' }}</i>
                                    </span>
                                    <span class="hr-scan-member__tools">
                                        @if ($member['is_printed'])
                                            <span class="hr-scan-flag">مطبوعة</span>
                                        @endif
                                        @if ($member['scanned'] && $member['card_number'])
                                            <button
                                                type="button"
                                                class="hr-scan-x"
                                                wire:click="removeCard({{ \Illuminate\Support\Js::from($member['card_number']) }})"
                                                title="إزالة البطاقة"
                                                aria-label="إزالة البطاقة"
                                            >
                                                ×
                                            </button>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
