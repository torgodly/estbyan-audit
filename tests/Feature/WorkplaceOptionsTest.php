<?php

use App\Support\WorkplaceOptions;

it('resolves audit bureau workplaces and spreadsheet admin labels', function () {
    expect(WorkplaceOptions::keyForLabel('الإدارة العامة'))->toBe('general_admin')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('سبها'))->toBe('sebha')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('طرابلس'))->toBe('tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('صبراته / صرمان'))->toBe('sabratha_sorman')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('غرب جنوب طرابلس'))->toBe('west_south_tripoli')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('مسلاته'))->toBe('msallata')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin(''))->toBe('unclassified')
        ->and(WorkplaceOptions::keyForSpreadsheetAdmin('مكتب رئيس الديوان'))->toBe('مكتب رئيس الديوان')
        ->and(WorkplaceOptions::labelForKey('مكتب رئيس الديوان'))->toBe('مكتب رئيس الديوان');
});
