<?php

use App\Models\Report;

test('guests can open the public pages', function () {
    Report::factory()->create(['code' => 'WF001', 'title' => 'WiFi lemot']);

    $this->get(route('home'))->assertOk()->assertSee('WiFi lemot');
    $this->get(route('reports.index'))->assertOk()->assertSee('WiFi lemot');
    $this->get(route('reports.create'))->assertOk();
    $this->get(route('reports.show', 'WF001'))->assertOk()->assertSee('WiFi lemot');
});

test('an unknown report code returns not found', function () {
    $this->get(route('reports.show', 'ZZ999'))->assertNotFound();
});
