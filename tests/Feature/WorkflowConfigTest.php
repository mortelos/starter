<?php

declare(strict_types=1);

it('fails exhausted workflow runs and recovers stalled ones without losing the timezone', function (): void {
    expect(config('mortel.workflows.after_retries'))->toBe('abort')
        ->and(config('mortel.workflows.recover_stalled_runs'))->toBeTrue()
        ->and(config('mortel.workflows'))->toHaveKey('timezone');
});
