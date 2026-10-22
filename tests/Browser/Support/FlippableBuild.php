<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Mortelos\Ui\Updates\BuildId;
use Mortelos\Ui\Updates\BuildToken;

/**
 * A build id a browser test can change: the in-process server answers every request from the same PHP process,
 * so changing $build is a deploy as far as the open page can tell.
 */
final class FlippableBuild implements BuildId
{
    public static string $build = '1.0.0';

    /**
     * A deploy: a new build id and the Livewire release token that goes with it. The release token carries the
     * BuildToken (an HMAC of the id), never the raw id, as mortelos/ui sets it itself.
     */
    public static function deploy(string $build): void
    {
        self::$build = $build;
        config(['livewire.release_token' => 'mortel:'.app(BuildToken::class)->current()]);
    }

    public function id(): string
    {
        return self::$build;
    }

    public function label(): string
    {
        return 'v'.self::$build;
    }
}
