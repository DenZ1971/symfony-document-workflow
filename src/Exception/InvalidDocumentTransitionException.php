<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Document;

final class InvalidDocumentTransitionException extends \DomainException
{
    public static function create(
        Document $document,
        string $transition,
    ): self {
        return new self(
            sprintf(
                'Transition "%s" cannot be applied to document "%s" in state "%s".',
                $transition,
                $document->getNumber(),
                $document->getState(),
            )
        );
    }
}