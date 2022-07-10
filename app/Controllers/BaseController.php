<?php

namespace App\Controllers;

use Te\Request;
use Te\Response;

class BaseController
{
    public $_request;
    public $_response;

    public function __construct(Request $request, Response $response)
    {
        $this->_request = $request;
        $this->_response = $response;
    }
}