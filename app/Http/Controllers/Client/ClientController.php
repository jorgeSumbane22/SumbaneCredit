<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;

abstract class ClientController extends Controller
{
    /** Ficha do cliente autenticado (todas as consultas do portal partem daqui). */
    protected function client(): Client
    {
        return auth()->user()->client;
    }
}
