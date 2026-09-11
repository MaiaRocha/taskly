<?php

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an attachment belongs to a task', function () {
    $task = Task::factory()->create();
    $attachment = Attachment::factory()->for($task)->create();

    expect($attachment->task->is($task))->toBeTrue();
});

test('the factory can create attachments', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->exists)->toBeTrue();
    $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
});

test('task_id cannot be set through mass assignment, unlike the server-controlled fields', function () {
    $task = Task::factory()->create();

    $attachment = new Attachment;

    $attachment->fill([
        'task_id' => $task->id,
        'original_name' => 'invoice.pdf',
        'path' => 'attachments/invoice.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
    ]);

    expect($attachment->task_id)->toBeNull();
    expect($attachment->original_name)->toBe('invoice.pdf');
    expect($attachment->path)->toBe('attachments/invoice.pdf');
    expect($attachment->mime_type)->toBe('application/pdf');
    expect($attachment->size)->toBe(1024);
});
