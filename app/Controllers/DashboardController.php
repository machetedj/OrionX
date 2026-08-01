<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,View}; use App\Repositories\DashboardRepository;
final readonly class DashboardController { public function __construct(private View $view,private DashboardRepository $repo){} public function index(Request $r): void {$this->view->render('dashboard',['title'=>'Dashboard']+$this->repo->data());} }
