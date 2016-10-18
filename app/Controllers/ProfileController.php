<?php

namespace App\Controllers;

use App\Models\User;
use App\Controllers\Controller;
use Respect\Validation\Validator as v;
use Slim\Exception\NotFoundException;

class ProfileController extends Controller
{
    public function view($request, $response, $args)
    {
        $user = User::where('user_slug', $args['slug'])->first();

        if (!$user) {
            throw new NotFoundException($request, $response);
        }

        return $this->view->render($response, 'profiles/view.twig', [
            'user' => $user,
            'photos' => array(1, 2)
        ]);
    }
}
