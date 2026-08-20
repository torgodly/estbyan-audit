<?php

use App\Support\WorkplaceOptions;

it('exposes audit bureau departments and branches as workplaces', function () {
    $workplaces = config('registration.workplaces');

    expect($workplaces)->toHaveKey('bureau_chief_office')
        ->and($workplaces['bureau_chief_office'])->toBe('مكتب رئيس الديوان')
        ->and($workplaces)->toHaveKey('tripoli')
        ->and($workplaces['tripoli'])->toBe('طرابلس')
        ->and($workplaces)->not->toHaveKey('unclassified')
        ->and($workplaces)->not->toContain('بدون تصنيف');
});

it('uses branch names as city options and صفة values as job titles', function () {
    expect(config('registration.cities.tripoli'))->toBe('طرابلس')
        ->and(config('registration.cities.sebha'))->toBe('سبها')
        ->and(config('registration.cities.sabratha_sorman'))->toBe('صبراته / صرمان')
        ->and(config('registration.job_titles'))->toBe([
            'employee' => 'موظف',
            'leader' => 'قيادي',
        ]);
});

it('resolves spreadsheet department and branch labels to workplace keys', function () {
    expect(WorkplaceOptions::keyForSpreadsheetAdmin('مكتب رئيس الديوان'))->toBe('bureau_chief_office')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('سبها'))->toBe('sebha')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('صبراته / صرمان'))->toBe('sabratha_sorman')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('غرب جنوب طرابلس'))->toBe('west_south_tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('مسلاته'))->toBe('msallata')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin(''))->toBeNull()
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('بدون تصنيف'))->toBeNull();
});

it('does not expose legacy workplace keys like al_jafara', function () {
    expect(WorkplaceOptions::options('al_jafara'))->not->toHaveKey('al_jafara')
        ->and(WorkplaceOptions::isKnownKey('al_jafara'))->toBeFalse()
        ->and(WorkplaceOptions::labelForKey('al_jafara'))->toBeNull()
        ->and(WorkplaceOptions::sanitizeKey('al_jafara'))->toBeNull()
        ->and(WorkplaceOptions::sanitizeKey('tripoli'))->toBe('tripoli');
});
