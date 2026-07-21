<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentType: string
{
    case INVOICE = 'invoice';
    case CONTRACT = 'contract';
    case EXPENSE_REPORT = 'expense_report';
}