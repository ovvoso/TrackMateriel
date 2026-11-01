<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Enum\AssetStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Critères de la liste d'inventaire, lus depuis l'URL pour qu'une recherche
 * puisse être partagée ou mise en favori.
 */
final readonly class AssetSearch
{
    public const array SORTS = ['code', 'name', 'updated'];

    public function __construct(
        public ?string $query = null,
        public ?Uuid $category = null,
        public ?AssetStatus $status = null,
        public ?Uuid $location = null,
        public string $sort = 'code',
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query = trim($request->query->getString('q'));
        $sort = $request->query->getString('tri');

        return new self(
            query: '' === $query ? null : mb_substr($query, 0, 100),
            category: self::uuid($request->query->getString('categorie')),
            status: AssetStatus::tryFrom($request->query->getString('etat')),
            location: self::uuid($request->query->getString('emplacement')),
            sort: \in_array($sort, self::SORTS, true) ? $sort : 'code',
            page: max(1, $request->query->getInt('page', 1)),
        );
    }

    public function isFiltered(): bool
    {
        return null !== $this->query || null !== $this->category || null !== $this->status || null !== $this->location;
    }

    private static function uuid(string $value): ?Uuid
    {
        return Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }
}
