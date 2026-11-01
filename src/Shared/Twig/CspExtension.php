<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Http\CspNonce;
use Twig\Attribute\AsTwigFunction;

final readonly class CspExtension
{
    public function __construct(private CspNonce $nonce)
    {
    }

    #[AsTwigFunction('csp_nonce')]
    public function nonce(): string
    {
        return $this->nonce->get();
    }
}
