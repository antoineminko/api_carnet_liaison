<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$req = new \Illuminate\Http\Request();
$req->merge([
    "classe_id" => 1,
    "date" => "2026-05-29",
    "attendances" => [
        ["eleve_id" => 1, "status" => "present"]
    ]
]);

$controller = app()->make(\App\Http\Controllers\Api\AttendanceController::class);
$res = $controller->submitAttendance($req);
print_r($res->getContent());
