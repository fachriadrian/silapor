<?php

use App\Actions\Reports\GenerateReportCode;
use App\Models\Category;
use App\Models\Report;

test('codes are sequential per category', function () {
    $wifi = Category::factory()->create(['prefix' => 'WF']);
    $computer = Category::factory()->create(['prefix' => 'KOM']);
    $generate = app(GenerateReportCode::class);

    expect($generate($wifi->id))->toBe('WF001')
        ->and($generate($wifi->id))->toBe('WF002')
        ->and($generate($computer->id))->toBe('KOM001')
        ->and($generate($wifi->id))->toBe('WF003');
});

test('numbers widen to four digits after 999', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $category->forceFill(['last_seq' => 999])->save();

    expect(app(GenerateReportCode::class)($category->id))->toBe('WF1000');
});

test('the prefix is always stored in uppercase', function () {
    $category = Category::factory()->create(['prefix' => 'wf']);

    expect($category->refresh()->prefix)->toBe('WF');
});

test('the prefix cannot be changed once a report uses it', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    Report::factory()->for($category)->create();

    $category->prefix = 'WIFI';

    expect(fn () => $category->save())->toThrow(LogicException::class);
});

test('the prefix can still be changed while no report uses it', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    $category->prefix = 'WIFI';
    $category->save();

    expect($category->refresh()->prefix)->toBe('WIFI');
});
