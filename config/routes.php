<?php
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CandidateAdminController;
use App\Controllers\CandidateController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\PasswordController;
use App\Controllers\ProfileController;
use App\Controllers\ResultController;
use App\Controllers\VotingController;

// 'METHOD /path' => [Controller class, method to run]
return [
    'GET /'                          => [HomeController::class, 'index'],

    // account
    'GET /register'                  => [AuthController::class, 'showRegister'],
    'POST /register'                 => [AuthController::class, 'register'],
    'GET /login'                     => [AuthController::class, 'showLogin'],
    'POST /login'                    => [AuthController::class, 'login'],
    'POST /logout'                   => [AuthController::class, 'logout'],
    'GET /forgot-password'           => [PasswordController::class, 'showForgot'],
    'POST /forgot-password'          => [PasswordController::class, 'sendLink'],
    'GET /reset-password'            => [PasswordController::class, 'showReset'],
    'POST /reset-password'           => [PasswordController::class, 'reset'],

    // candidate photos (logged-in users only)
    'GET /candidate-photo'           => [CandidateController::class, 'photo'],

    // voter
    'GET /dashboard'                 => [DashboardController::class, 'index'],
    'GET /vote'                      => [VotingController::class, 'index'],
    'POST /vote/cast'                => [VotingController::class, 'cast'],
    'GET /results'                   => [ResultController::class, 'index'],
    'GET /photo'                     => [ProfileController::class, 'photo'],
    'GET /profile'                   => [ProfileController::class, 'edit'],
    'POST /profile'                  => [ProfileController::class, 'update'],
    'POST /profile/password'         => [ProfileController::class, 'changePassword'],
    'POST /profile/photo'            => [ProfileController::class, 'uploadPhoto'],
    'POST /profile/photo/delete'     => [ProfileController::class, 'deletePhoto'],

    // administrator
    'GET /admin'                     => [AdminController::class, 'index'],
    'GET /admin/voters'              => [AdminController::class, 'voters'],
    'POST /admin/voters/status'      => [AdminController::class, 'updateVoterStatus'],
    'GET /admin/elections'           => [AdminController::class, 'elections'],
    'POST /admin/elections/create'   => [AdminController::class, 'createElection'],
    'POST /admin/elections/close-applications' => [AdminController::class, 'closeApplications'],
    'POST /admin/elections/start-voting'       => [AdminController::class, 'startVoting'],
    'POST /admin/elections/end-voting'         => [AdminController::class, 'endVoting'],
    'POST /admin/elections/publish'  => [AdminController::class, 'publishResults'],
    'POST /admin/positions/create'   => [AdminController::class, 'addPosition'],

    // administrator: candidates (only the administrator can add them)
    'GET /admin/candidates'          => [CandidateAdminController::class, 'index'],
    'GET /admin/candidates/new'      => [CandidateAdminController::class, 'create'],
    'POST /admin/candidates/create'  => [CandidateAdminController::class, 'store'],
    'GET /admin/candidates/edit'     => [CandidateAdminController::class, 'edit'],
    'POST /admin/candidates/update'  => [CandidateAdminController::class, 'update'],
    'POST /admin/candidates/delete'  => [CandidateAdminController::class, 'delete'],
];