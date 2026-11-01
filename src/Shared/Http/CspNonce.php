<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Nonce de la Content-Security-Policy, unique par requête. Seuls les scripts
 * qui le portent (importmap) sont exécutés par le navigateur.
 */
final class CspNonce implements ResetInterface
{
    private ?string $value = null;

    public function get(): string
    {
        return $this->value ??= base64_encode(random_bytes(18));
    }

    public function reset(): void
    {
        $this->value = null;
    }
}
