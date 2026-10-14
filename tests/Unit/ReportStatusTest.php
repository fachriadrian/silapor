<?php

use App\Enums\ReportStatus;

it('mengizinkan transisi status sesuai PRD', function () {
    expect(ReportStatus::New->canTransitionTo(ReportStatus::InProgress))->toBeTrue()
        ->and(ReportStatus::New->canTransitionTo(ReportStatus::Completed))->toBeTrue()
        ->and(ReportStatus::New->canTransitionTo(ReportStatus::Cancelled))->toBeTrue()
        ->and(ReportStatus::InProgress->canTransitionTo(ReportStatus::OnHold))->toBeTrue()
        ->and(ReportStatus::InProgress->canTransitionTo(ReportStatus::Completed))->toBeTrue()
        ->and(ReportStatus::OnHold->canTransitionTo(ReportStatus::InProgress))->toBeTrue()
        ->and(ReportStatus::Completed->canTransitionTo(ReportStatus::InProgress))->toBeTrue();
});

it('menolak transisi status yang tidak valid', function () {
    expect(ReportStatus::New->canTransitionTo(ReportStatus::OnHold))->toBeFalse()
        ->and(ReportStatus::OnHold->canTransitionTo(ReportStatus::Completed))->toBeFalse()
        ->and(ReportStatus::Completed->canTransitionTo(ReportStatus::Cancelled))->toBeFalse()
        ->and(ReportStatus::Cancelled->allowedTransitions())->toBeEmpty();
});

it('menandai status akhir dan status yang dapat dibatalkan', function () {
    expect(ReportStatus::Completed->isFinal())->toBeTrue()
        ->and(ReportStatus::Cancelled->isFinal())->toBeTrue()
        ->and(ReportStatus::New->isFinal())->toBeFalse()
        ->and(ReportStatus::New->isCancellable())->toBeTrue()
        ->and(ReportStatus::OnHold->isCancellable())->toBeTrue()
        ->and(ReportStatus::Completed->isCancellable())->toBeFalse()
        ->and(ReportStatus::finalValues())->toBe(['selesai', 'dibatalkan']);
});
