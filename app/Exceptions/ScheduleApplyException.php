<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar ketika sebuah Schedule tidak bisa diterapkan ke wo_operations —
 * misalnya schedule tidak ditemukan, tidak punya assignments, atau seluruh
 * operasinya sudah berjalan/selesai sehingga tidak ada yang bisa ditulis.
 */
class ScheduleApplyException extends Exception
{
}