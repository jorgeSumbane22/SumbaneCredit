@include('errors.layout', ['code' => 422, 'title' => 'Operação não permitida', 'text' => $exception->getMessage() ?: 'Os dados enviados não permitem concluir a operação.'])
