<?php
namespace App\Controllers;

use Te\Request;
use Te\Response;

class ControllerDispatcher
{
    
    public function callAction($routes, Request $request, Response $response)
    {
        $uri = $request->_request['uri'];
        if (isset($routes[$uri])) {
            $route = explode("@", $routes[$uri]);
            $controller = new $route[0]($request, $response);
            $action = $route[1];
            try {
                if (method_exists($controller, $action)) {
                    $result = $controller->{$action}();
                }
            } catch (\Exception $e) {
                $result = $e->getMessage();
            }
        } else {
            $result = "Route not found";
        }

        $response->header("Content-Type", "application/json");
        $response->write($result);
        return true;
    }
}