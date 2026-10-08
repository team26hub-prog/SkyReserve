<?php

declare(strict_types=1);

use App\Controllers\FoundationController;
use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Controllers\AdminController;
use App\Controllers\AirportController;
use App\Controllers\AircraftController;
use App\Controllers\SeatController;
use App\Controllers\FlightController;
use App\Controllers\FlightSearchController;
use App\Controllers\BookingController;

return [
    'GET' => [
        '/' => [FoundationController::class, 'index'],
        '/register' => [AuthController::class, 'registerForm'],
        '/login' => [AuthController::class, 'customerLoginForm'],
        '/profile' => [ProfileController::class, 'index'],
        '/flights' => [FlightSearchController::class, 'index'],
        '/flights/show' => [FlightSearchController::class, 'show'],
        '/bookings/create' => [BookingController::class, 'create'],
        '/bookings/show' => [BookingController::class, 'show'],
        '/admin/login' => [AuthController::class, 'adminLoginForm'],
        '/admin' => [AdminController::class, 'index'],
        '/admin/airports' => [AirportController::class, 'index'],
        '/admin/airports/create' => [AirportController::class, 'create'],
        '/admin/airports/edit' => [AirportController::class, 'edit'],
        '/admin/aircraft' => [AircraftController::class, 'index'],
        '/admin/aircraft/create' => [AircraftController::class, 'create'],
        '/admin/aircraft/edit' => [AircraftController::class, 'edit'],
        '/admin/seats' => [SeatController::class, 'index'],
        '/admin/seats/create' => [SeatController::class, 'create'],
        '/admin/seats/edit' => [SeatController::class, 'edit'],
        '/admin/flights' => [FlightController::class, 'index'],
        '/admin/flights/create' => [FlightController::class, 'create'],
        '/admin/flights/edit' => [FlightController::class, 'edit'],
        '/admin/flights/show' => [FlightController::class, 'show'],
    ],
    'POST' => [
        '/register' => [AuthController::class, 'register'],
        '/login' => [AuthController::class, 'customerLogin'],
        '/logout' => [AuthController::class, 'customerLogout'],
        '/bookings' => [BookingController::class, 'store'],
        '/admin/login' => [AuthController::class, 'adminLogin'],
        '/admin/logout' => [AuthController::class, 'adminLogout'],
        '/admin/airports' => [AirportController::class, 'store'],
        '/admin/airports/update' => [AirportController::class, 'update'],
        '/admin/airports/delete' => [AirportController::class, 'delete'],
        '/admin/aircraft' => [AircraftController::class, 'store'],
        '/admin/aircraft/update' => [AircraftController::class, 'update'],
        '/admin/aircraft/delete' => [AircraftController::class, 'delete'],
        '/admin/seats' => [SeatController::class, 'store'],
        '/admin/seats/update' => [SeatController::class, 'update'],
        '/admin/seats/delete' => [SeatController::class, 'delete'],
        '/admin/flights' => [FlightController::class, 'store'],
        '/admin/flights/update' => [FlightController::class, 'update'],
        '/admin/flights/delete' => [FlightController::class, 'delete'],
    ],
];
