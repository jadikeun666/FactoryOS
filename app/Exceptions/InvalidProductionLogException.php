<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar ketika ProductionLog tidak valid untuk dihitung OEE-nya.
 *
 * @see docs/oee-formulas.md § Implementasi bcmath — guard planned_minutes = 0
 *      dan actual_output = 0 (pembagi tidak boleh nol).
 */
class InvalidProductionLogException extends Exception
{
    //
}