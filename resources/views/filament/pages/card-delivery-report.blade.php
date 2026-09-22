@php
    $report = $this->report;
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review">
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <p class="hr-report-lead">
                        نظرة عامة على العائلات التي تم تسليم بطاقاتها، مع عدد أفراد العائلة وجهة التسليم.
                    </p>

                    <div class="hr-kpis hr-kpis--report">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">موظفون مُسلَّمون</span>
                            <div class="hr-kpi__value">{{ $report['delivered_employees'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">بطاقات أفراد العائلة</span>
                            <div class="hr-kpi__value">{{ $report['family_member_cards'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">إلى الموظف</span>
                            <div class="hr-kpi__value">{{ $report['delivered_to_employee'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">إلى الإدارة</span>
                            <div class="hr-kpi__value">{{ $report['delivered_to_administration'] }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="hr-panel">
                <div class="hr-panel__head">
                    <h3 class="hr-panel__title">سجل التسليم</h3>
                    <span class="hr-panel__meta">
                        إجمالي البطاقات المسلَّمة: {{ $report['total_cards'] }}
                    </span>
                </div>
                <div class="hr-panel__body">
                    @if ($report['rows'] === [])
                        <p class="hr-report-empty">لا توجد بطاقات مُسلَّمة حتى الآن.</p>
                    @else
                        <div class="hr-report-table-wrap">
                            <table class="hr-med-table hr-report-table">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>الرقم التأميني</th>
                                        <th>أفراد العائلة</th>
                                        <th>البطاقات</th>
                                        <th>جهة التسليم</th>
                                        <th>تاريخ التسليم</th>
                                        <th>بواسطة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['rows'] as $row)
                                        <tr>
                                            <td>
                                                <strong>{{ $row['full_name'] }}</strong>
                                                @if (filled($row['workplace']))
                                                    <span class="hr-report-share">{{ $row['workplace'] }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $row['employee_number'] }}</td>
                                            <td>{{ $row['family_members'] }}</td>
                                            <td>{{ $row['total_cards'] }}</td>
                                            <td>{{ $row['delivered_to_label'] }}</td>
                                            <td>{{ $row['delivered_at'] ?? '—' }}</td>
                                            <td>{{ $row['delivered_by_name'] }}</td>
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
