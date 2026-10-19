<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HomeController extends Controller
{
    protected function requiredArea(): ?string
    {
        return null;   // public page
    }

    public function index(): void
    {
        $this->view('home', [], 'Welcome');
    }
}
