<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violação de uma regra de negócio (ex.: desembolso de um empréstimo não formalizado).
 * É apresentada ao utilizador como mensagem de erro e provoca rollback da transacção.
 */
class BusinessRuleException extends RuntimeException
{
}
