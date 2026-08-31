@php
    $report = $this->report;
    $maxTotal = collect($report['conditions'])->max('total') ?: 1;
    $hasCases = collect($report['conditions'])->contains(fn (array $condition): bool => $condition['total'] > 0);
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
                    <span class="hr-panel__meta">النسبة من إجمالي المسجّلين ({{ $report['registered_people'] }})</span>
                </div>
                <div class="hr-panel__body">
                    @if (! $hasCases)
                        <p class="hr-report-empty">لا توجد أمراض مزمنة مسجّلة حتى الآن.</p>
                    @else
                        <div class="hr-report-table-wrap">
                            <table class="hr-med-table hr-report-table">
                                <thead>
                                    <tr>
                                        <th>المرض</th>
                                        <th>موظفون</th>
                                        <th>عائلة</th>
                                        <th>الإجمالي</th>
                                        <th>من المسجّلين</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['conditions'] as $condition)
                                        @php
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
                                            <td>{{ $condition['share'] }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
