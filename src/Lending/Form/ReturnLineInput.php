<?php

declare(strict_types=1);

namespace App\Lending\Form;

use App\Inventory\Enum\AssetCondition;
use App\Lending\Entity\LoanLine;

/**
 * Une ligne du formulaire de retour : rendue ou non, et dans quel état.
 */
final class ReturnLineInput
{
    public bool $returned = true;

    public AssetCondition $condition;

    public function __construct(public readonly LoanLine $line)
    {
        $this->condition = $line->getConditionOut();
    }
}
