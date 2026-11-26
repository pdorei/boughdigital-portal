<?php

namespace App\Enums;

/**
 * Where a decision came from: hard rules in code, the Claude fit check, the
 * rate rules at proposal time, or a person.
 */
enum UpworkDecisionStage: string
{
    case Rules = 'rules';
    case Fit = 'fit';
    case Rate = 'rate';
    case Human = 'human';
    case System = 'system';
}
