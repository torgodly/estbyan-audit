@php
    $report = $this->report;
    $maxTotal = collect($report['conditions'])->max('total') ?: 1;
    $conditionsWithPeople = collect($report['conditions'])->where('total', '>', 0)->values();
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review">
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <p class="hr-report-lead">
                        تقرير شامل عن الأمراض المزمنة للموظفين المسجّلين وأفراد عائلاتهم.
                        يشمل الطلبات المُرسلة والمقبولة والمرفوضة وقيد التعديل، ولا يشمل المسودات.
                    </p>

                    <div class="hr-kpis hr-kpis--report">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">الموظفون المسجّلون</span>
                            <div class="hr-kpi__value">{{ $report['registered_employees'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">موظفون بمرض مزمن</span>
                            <div class="hr-kpi__value">{{ $report['employees_with_chronic'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">أفراد عائلة بمرض مزمن</span>
                            <div class="hr-kpi__value">{{ $report['family_with_chronic'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">إجمالي المصابين</span>
                            <div class="hr-kpi__value">{{ $report['total_with_chronic'] }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="hr-panel">
                <div class="hr-panel__head">
                    <h3 class="hr-panel__title">توزيع الأمراض المزمنة</h3>
                    <span class="hr-panel__meta">{{ count($report['conditions']) }} مرضاً</span>
                </div>
                <div class="hr-panel__body">
                    <div class="hr-report-table-wrap">
                        <table class="hr-med-table hr-report-table">
                            <thead>
                                <tr>
                                    <th>المرض</th>
                                    <th>موظفون</th>
                                    <th>عائلة</th>
                                    <th>الإجمالي</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['conditions'] as $condition)
                                    @php
                                        $share = $report['total_with_chronic'] > 0
                                            ? round(($condition['total'] / $report['total_with_chronic']) * 100)
                                            : 0;
                                        $bar = (int) round(($condition['total'] / $maxTotal) * 100);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="hr-report-disease">
                                                <strong>{{ $condition['label'] }}</strong>
                                                <span class="hr-report-bar" aria-hidden="true">
                                                    <span class="hr-report-bar__fill" style="width: {{ $bar }}%"></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{{ $condition['employees'] }}</td>
                                        <td>{{ $condition['family'] }}</td>
                                        <td>{{ $condition['total'] }}</td>
                                        <td>{{ $share }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @if ($conditionsWithPeople->isEmpty() && $report['unspecified'] === [])
                <section class="hr-panel">
                    <div class="hr-panel__body">
                        <p class="hr-report-empty">لا توجد أمراض مزمنة مسجّلة حتى الآن.</p>
                    </div>
                </section>
            @endif

            @foreach ($conditionsWithPeople as $condition)
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">{{ $condition['label'] }}</h3>
                        <span class="hr-panel__meta">{{ $condition['total'] }} حالة · {{ $condition['employees'] }} موظف · {{ $condition['family'] }} مستفيد</span>
                    </div>
                    <div class="hr-panel__body">
                        <ul class="hr-report-people">
                            @foreach ($condition['people'] as $person)
                                <li>
                                    <a href="{{ $person['url'] }}" class="hr-report-person">
                                        <div class="min-w-0">
                                            <p class="hr-report-person__name">{{ $person['name'] }}</p>
                                            <p class="hr-report-person__detail">{{ $person['detail'] }}</p>
                                        </div>
                                        <div class="hr-chips" style="margin-bottom: 0;">
                                            <span class="hr-chip">{{ $person['kind'] }}</span>
                                            @if (filled($person['reference']))
                                                <span class="hr-chip">{{ $person['reference'] }}</span>
                                            @endif
                                            <span class="hr-chip">{{ $person['status'] }}</span>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endforeach

            @if ($report['unspecified'] !== [])
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">مرض مزمن دون تحديد النوع</h3>
                        <span class="hr-panel__meta">{{ $report['unspecified_count'] }} حالة</span>
                    </div>
                    <div class="hr-panel__body">
                        <ul class="hr-report-people">
                            @foreach ($report['unspecified'] as $person)
                                <li>
                                    <a href="{{ $person['url'] }}" class="hr-report-person">
                                        <div class="min-w-0">
                                            <p class="hr-report-person__name">{{ $person['name'] }}</p>
                                            <p class="hr-report-person__detail">{{ $person['detail'] }}</p>
                                        </div>
                                        <div class="hr-chips" style="margin-bottom: 0;">
                                            <span class="hr-chip">{{ $person['kind'] }}</span>
                                            @if (filled($person['reference']))
                                                <span class="hr-chip">{{ $person['reference'] }}</span>
                                            @endif
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
