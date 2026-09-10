<?php

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an attachment belongs to a task', function () {
    $task = Task::factory()->create();
    $attachment = Attachment::factory()->for($task)->create();

    expect($attachment->task->is($task))->toBeTrue();
});

test('the factory can create attachments even though the model has no fillable attributes', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->exists)->toBeTrue();
    $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
});

test('attachment attributes cannot be set through plain mass assignment', function () {
    Attachment::create([
        'task_id' => Task::factory()->create()->id,
        'original_name' => 'invoice.pdf',
        'path' => 'attachments/invoice.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);
})->throws(MassAssignmentException::class);
