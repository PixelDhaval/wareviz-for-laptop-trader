<?php

use App\Enums\JobAssignee;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\LaptopStatus;
use App\Models\Agency;
use App\Models\Laptop;
use App\Models\RepairJob;

test('sending a laptop for an in-house job marks it as in repair', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repair,
        'assignee' => JobAssignee::InHouse,
    ]);

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InRepair);
});

test('sending a laptop to an agency for repaint marks it as in repair', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);
    $agency = Agency::factory()->create();

    $job = RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repaint,
        'assignee' => JobAssignee::Agency,
        'agency_id' => $agency->id,
        'cost' => 45.5,
    ]);

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InRepair)
        ->and($job->status)->toBe(JobStatus::Pending)
        ->and($job->sent_at)->not->toBeNull();
});

test('completing a job returns the laptop to stock and stamps completed_at', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    $job = RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repair,
        'assignee' => JobAssignee::InHouse,
    ]);

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InRepair);

    $job->update(['status' => JobStatus::Completed]);

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InStock)
        ->and($job->fresh()->completed_at)->not->toBeNull();
});

test('cancelling a job also returns the laptop to stock', function () {
    $laptop = Laptop::factory()->create(['status' => LaptopStatus::InStock]);

    $job = RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repair,
        'assignee' => JobAssignee::InHouse,
    ]);

    $job->update(['status' => JobStatus::Cancelled]);

    expect($laptop->fresh()->status)->toBe(LaptopStatus::InStock);
});

test('an in-house job never persists a stray agency_id', function () {
    $agency = Agency::factory()->create();
    $laptop = Laptop::factory()->create();

    $job = RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repair,
        'assignee' => JobAssignee::InHouse,
        'agency_id' => $agency->id,
    ]);

    expect($job->fresh()->agency_id)->toBeNull();
});

test('a laptop exposes its active repair job', function () {
    $laptop = Laptop::factory()->create();

    RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repair,
        'assignee' => JobAssignee::InHouse,
        'status' => JobStatus::Completed,
    ]);

    $activeJob = RepairJob::create([
        'laptop_id' => $laptop->id,
        'type' => JobType::Repaint,
        'assignee' => JobAssignee::InHouse,
    ]);

    expect($laptop->activeRepairJob?->id)->toBe($activeJob->id)
        ->and($laptop->repairJobs()->count())->toBe(2);
});
