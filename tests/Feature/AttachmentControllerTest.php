<?php

use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

describe('authentication', function () {
    test('a guest cannot access any attachment endpoint', function (string $method, callable $uri) {
        $task = Task::factory()->create();
        $attachment = Attachment::factory()->for($task)->create();

        $this->json($method, $uri($task, $attachment))->assertUnauthorized();
    })->with([
        'index' => ['GET', fn (Task $task, Attachment $attachment) => "/api/tasks/{$task->id}/attachments"],
        'store' => ['POST', fn (Task $task, Attachment $attachment) => "/api/tasks/{$task->id}/attachments"],
        'download' => ['GET', fn (Task $task, Attachment $attachment) => "/api/attachments/{$attachment->id}/download"],
        'destroy' => ['DELETE', fn (Task $task, Attachment $attachment) => "/api/attachments/{$attachment->id}"],
    ]);
});

describe('route contract', function () {
    test('GET is not a metadata endpoint for an individual attachment', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $attachment = Attachment::factory()->for($task)->create();

        // Only DELETE exists for this exact URI pattern (download lives at a
        // deeper /download suffix), so Laravel treats GET as a method
        // mismatch on that route, not as an unmatched path.
        $this->actingAs($user)
            ->getJson("/api/attachments/{$attachment->id}")
            ->assertMethodNotAllowed();
    });
});

describe('upload', function () {
    test('a single valid file is stored and returned as a collection', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('report.pdf', 100);

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => [$file],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'data');

        $attachment = Attachment::sole();
        expect($attachment->task_id)->toBe($task->id);
        expect($attachment->original_name)->toBe('report.pdf');
        expect($attachment->mime_type)->toBe('application/pdf');
        expect($attachment->size)->toBe($file->getSize());
        expect($attachment->path)->toStartWith("attachments/{$task->id}/");
        expect($attachment->path)->not->toContain('report.pdf');
        expect($attachment->path)->not->toBe('');
        $disk->assertExists($attachment->path);

        $response->assertExactJson([
            'data' => [
                [
                    'id' => $attachment->id,
                    'original_name' => 'report.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => $file->getSize(),
                    'created_at' => $attachment->created_at->toJSON(),
                    'updated_at' => $attachment->updated_at->toJSON(),
                    'download_url' => "/api/attachments/{$attachment->id}/download",
                ],
            ],
        ]);
    });

    test('multiple valid files are all stored and returned', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $files = [
            UploadedFile::fake()->create('a.pdf', 50),
            UploadedFile::fake()->create('b.txt', 10),
            UploadedFile::fake()->create('c.png', 20),
        ];

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => $files,
        ]);

        $response->assertCreated();
        $response->assertJsonCount(3, 'data');

        expect(Attachment::count())->toBe(3);

        Attachment::all()->each(function (Attachment $attachment) use ($task, $disk) {
            expect($attachment->task_id)->toBe($task->id);
            $disk->assertExists($attachment->path);
        });
    });
});

describe('attachments_added activity', function () {
    test('a single file upload records one attachments_added with that file', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('briefing.pdf', 100);

        $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => [$file],
        ])->assertCreated();

        $activity = TaskActivity::sole();
        expect($activity->task_id)->toBe($task->id);
        expect($activity->type)->toBe(TaskActivityType::AttachmentsAdded);
        expect($activity->data)->toBe(['files' => [['name' => 'briefing.pdf']]]);
    });

    test('multiple files in one request record exactly one grouped activity', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $files = [
            UploadedFile::fake()->create('briefing.pdf', 50),
            UploadedFile::fake()->create('mockup.png', 20),
            UploadedFile::fake()->create('contrato.docx', 10),
        ];

        $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => $files,
        ])->assertCreated();

        expect(TaskActivity::count())->toBe(1);

        $activity = TaskActivity::sole();
        expect($activity->type)->toBe(TaskActivityType::AttachmentsAdded);
        expect($activity->data)->toBe(['files' => [
            ['name' => 'briefing.pdf'],
            ['name' => 'mockup.png'],
            ['name' => 'contrato.docx'],
        ]]);
    });

    test('a rejected upload (validation failure) records no activity', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('malware.exe', 10);

        $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => [$file],
        ])->assertUnprocessable();

        expect(TaskActivity::count())->toBe(0);
    });
});

describe('upload validation', function () {
    test('exactly 5 files in one request are accepted', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $files = collect(range(1, 5))->map(fn (int $i) => UploadedFile::fake()->create("file{$i}.txt", 10))->all();

        $this->actingAs($user)
            ->post("/api/tasks/{$task->id}/attachments", ['files' => $files])
            ->assertCreated();

        expect(Attachment::count())->toBe(5);
    });

    test('6 files in one request are rejected before reaching the action', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $files = collect(range(1, 6))->map(fn (int $i) => UploadedFile::fake()->create("file{$i}.txt", 10))->all();

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", ['files' => $files]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files');
        expect(Attachment::count())->toBe(0);
        expect($disk->allFiles())->toBe([]);
    });

    test('a request without a files key is rejected', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->postJson("/api/tasks/{$task->id}/attachments", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files');
    });

    test('an empty files array is rejected', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $response = $this->actingAs($user)->postJson("/api/tasks/{$task->id}/attachments", ['files' => []]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files');
    });

    test('a file up to 5MB is accepted', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        // File::max('5mb') is decimal (5 * 1000 KB), not the binary 5 * 1024.
        $file = UploadedFile::fake()->create('ok.pdf', 5000);

        $this->actingAs($user)
            ->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]])
            ->assertCreated();
    });

    test('a file larger than 5MB is rejected', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('too-big.pdf', 5001);

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files.0');
        expect(Attachment::count())->toBe(0);
        expect($disk->allFiles())->toBe([]);
    });

    test('each allowed extension is accepted', function (string $filename) {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        // UploadedFile::fake()->create() reports a MIME type derived from
        // the extension (Illuminate\Http\Testing\File::getMimeType()
        // overrides real content sniffing), which is exactly what
        // File::types() inspects — so this exercises the real validation
        // rule without needing genuine binary content for every format.
        $file = UploadedFile::fake()->create($filename, 50);

        $this->actingAs($user)
            ->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]])
            ->assertCreated();
    })->with([
        'jpg' => ['photo.jpg'],
        'jpeg' => ['photo.jpeg'],
        'png' => ['photo.png'],
        'webp' => ['photo.webp'],
        'pdf' => ['document.pdf'],
        'txt' => ['notes.txt'],
        'doc' => ['legacy.doc'],
        'docx' => ['report.docx'],
        'xls' => ['legacy.xls'],
        'xlsx' => ['sheet.xlsx'],
    ]);

    test('a real (non-fake-content) image is accepted', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->image('avatar.png');

        $this->actingAs($user)
            ->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]])
            ->assertCreated();
    });

    test('a disallowed file type is rejected', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('malware.exe', 10);

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files.0');
        expect(Attachment::count())->toBe(0);
        expect($disk->allFiles())->toBe([]);
    });
});

describe('upload payload safety', function () {
    test('client-supplied task_id/path/mime_type/size/original_name are ignored', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('real-name.txt', 10);

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", [
            'files' => [$file],
            'task_id' => $otherTask->id,
            'path' => 'attachments/hacked/evil.txt',
            'mime_type' => 'application/x-evil',
            'size' => 999999,
            'original_name' => 'not-the-real-name.txt',
        ]);

        $response->assertCreated();

        $attachment = Attachment::sole();
        expect($attachment->task_id)->toBe($task->id);
        expect($attachment->task_id)->not->toBe($otherTask->id);
        expect($attachment->path)->not->toBe('attachments/hacked/evil.txt');
        expect($attachment->mime_type)->toBe('text/plain');
        expect($attachment->size)->toBe($file->getSize());
        expect($attachment->size)->not->toBe(999999);
        expect($attachment->original_name)->toBe('real-name.txt');
        $disk->assertExists($attachment->path);
    });
});

describe('upload ownership', function () {
    test('a stranger cannot upload to another user\'s task', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $file = UploadedFile::fake()->create('a.txt', 10);

        $response = $this->actingAs($stranger)->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]]);

        $response->assertForbidden();
        expect(Attachment::count())->toBe(0);
        expect($disk->allFiles())->toBe([]);
    });

    test('a missing task returns 404', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('a.txt', 10);

        $this->actingAs($user)
            ->post('/api/tasks/999999/attachments', ['files' => [$file]])
            ->assertNotFound();
    });
});

describe('total attachment limit', function () {
    test('a task with 9 attachments can receive 1 more, reaching exactly 10', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        Attachment::factory()->for($task)->count(9)->create();
        $file = UploadedFile::fake()->create('a.txt', 10);

        $this->actingAs($user)
            ->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]])
            ->assertCreated();

        expect($task->attachments()->count())->toBe(10);
    });

    test('a task already at 10 attachments rejects any further upload', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        Attachment::factory()->for($task)->count(10)->create();
        $file = UploadedFile::fake()->create('a.txt', 10);

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", ['files' => [$file]]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files');
        expect($task->attachments()->count())->toBe(10);
        expect($disk->allFiles())->toBe([]);
    });

    test('a batch that would push the task past 10 is rejected as a whole, with no partial application', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        Attachment::factory()->for($task)->count(8)->create();

        $files = [
            UploadedFile::fake()->create('a.txt', 10),
            UploadedFile::fake()->create('b.txt', 10),
            UploadedFile::fake()->create('c.txt', 10),
        ];

        $response = $this->actingAs($user)->post("/api/tasks/{$task->id}/attachments", ['files' => $files]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('files');
        expect($task->attachments()->count())->toBe(8);
        expect($disk->allFiles())->toBe([]);
    });
});

describe('index', function () {
    test('returns only the attachments belonging to the given task', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->create();
        $strangerTask = Task::factory()->for($strangerProject)->create();

        $attachment = Attachment::factory()->for($task)->create();
        Attachment::factory()->for($otherTask)->create();
        Attachment::factory()->for($strangerTask)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/attachments");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $attachment->id);
    });

    test('a stranger cannot list attachments of another user\'s task', function () {
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($stranger)
            ->getJson("/api/tasks/{$task->id}/attachments")
            ->assertForbidden();
    });

    test('a missing task returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/tasks/999999/attachments')->assertNotFound();
    });

    test('orders by created_at then id, not insertion-accidental order', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->travelTo('2026-01-01 12:00:00');
        $second = Attachment::factory()->for($task)->create();

        $this->travelTo('2026-01-01 11:00:00');
        $first = Attachment::factory()->for($task)->create();

        $this->travelTo('2026-01-01 13:00:00');
        $third = Attachment::factory()->for($task)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/attachments");

        $response->assertJsonPath('data.0.id', $first->id);
        $response->assertJsonPath('data.1.id', $second->id);
        $response->assertJsonPath('data.2.id', $third->id);
    });

    test('breaks a created_at tie by id ascending', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        $this->travelTo('2026-01-01 12:00:00');
        $older = Attachment::factory()->for($task)->create();
        $newer = Attachment::factory()->for($task)->create();

        $response = $this->actingAs($user)->getJson("/api/tasks/{$task->id}/attachments");

        $response->assertJsonPath('data.0.id', $older->id);
        $response->assertJsonPath('data.1.id', $newer->id);
    });
});

describe('download', function () {
    test('the owner receives the exact stored content, streamed', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'the real content');
        $attachment = $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => strlen('the real content'),
        ]);

        $response = $this->actingAs($user)->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        $response->assertStreamed();
        $response->assertStreamedContent('the real content');
        // Symfony's Response::prepare() appends a charset to any text/*
        // Content-Type that doesn't already declare one.
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    });

    test('an inline-approved MIME type is served inline with the original filename', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'hello');
        $attachment = $task->attachments()->create([
            'original_name' => 'notes.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 5,
        ]);

        $response = $this->actingAs($user)->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
        expect($response->headers->get('Content-Disposition'))->toContain('notes.txt');
    });

    test('a non-inline-approved MIME type is forced as a download', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.docx";
        $disk->put($path, 'binary-ish content');
        $attachment = $task->attachments()->create([
            'original_name' => 'report.docx',
            'path' => $path,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 19,
        ]);

        $response = $this->actingAs($user)->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
        expect($response->headers->get('Content-Disposition'))->toContain('report.docx');
    });

    test('the presented filename derives from original_name, not the physical path', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/some-hash.txt";
        $disk->put($path, 'x');
        $attachment = $task->attachments()->create([
            'original_name' => 'relatorio final.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($user)->get("/api/attachments/{$attachment->id}/download");

        $disposition = $response->headers->get('Content-Disposition');
        expect($disposition)->toContain('filename');
        expect($disposition)->not->toContain('some-hash.txt');
    });

    test('a stranger cannot download another user\'s attachment', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/secret.txt";
        $disk->put($path, 'top secret contents');
        $attachment = $task->attachments()->create([
            'original_name' => 'secret.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 20,
        ]);

        $response = $this->actingAs($stranger)->get("/api/attachments/{$attachment->id}/download");

        $response->assertForbidden();
        expect($response->getContent())->not->toContain('top secret contents');
    });

    test('a missing attachment returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/api/attachments/999999/download')->assertNotFound();
    });

    test('a row whose physical file is missing results in a server error, not a fake 404', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $attachment = $task->attachments()->create([
            'original_name' => 'ghost.txt',
            'path' => "attachments/{$task->id}/does-not-exist.txt",
            'mime_type' => 'text/plain',
            'size' => 10,
        ]);

        $response = $this->actingAs($user)->get("/api/attachments/{$attachment->id}/download");

        $response->assertServerError();
        expect($response->getContent())->not->toContain('does-not-exist.txt');
        expect($response->getContent())->not->toContain("attachments/{$task->id}");
    });
});

describe('destroy', function () {
    test('the owner can delete their attachment, removing both row and file', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'x');
        $attachment = $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertNoContent();
        expect($response->getContent())->toBe('');
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $disk->assertMissing($path);
    });

    test('deleting an attachment whose file is already missing still removes the row', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $attachment = $task->attachments()->create([
            'original_name' => 'ghost.txt',
            'path' => "attachments/{$task->id}/does-not-exist.txt",
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/attachments/{$attachment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    });

    test('a stranger cannot delete another user\'s attachment', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'x');
        $attachment = $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($stranger)->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertForbidden();
        $this->assertModelExists($attachment);
        $disk->assertExists($path);
    });

    test('a missing attachment returns 404', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/attachments/999999')->assertNotFound();
    });
});

describe('attachment_removed activity', function () {
    test('deleting an attachment records one attachment_removed with the original filename snapshot', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/briefing.pdf";
        $disk->put($path, 'x');
        $attachment = $task->attachments()->create([
            'original_name' => 'briefing.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 1,
        ]);

        $this->actingAs($user)->deleteJson("/api/attachments/{$attachment->id}")->assertNoContent();

        $activity = TaskActivity::sole();
        expect($activity->task_id)->toBe($task->id);
        expect($activity->type)->toBe(TaskActivityType::AttachmentRemoved);
        expect($activity->data)->toBe(['name' => 'briefing.pdf']);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $disk->assertMissing($path);
    });

    test('a failed delete (forbidden) records no activity', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $attachment = $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => "attachments/{$task->id}/file.txt",
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $this->actingAs($stranger)->deleteJson("/api/attachments/{$attachment->id}")->assertForbidden();

        expect(TaskActivity::count())->toBe(0);
    });

    test('if the DB transaction fails, the row, activity, and physical file are all left untouched', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/briefing.pdf";
        $disk->put($path, 'x');
        $attachment = $task->attachments()->create([
            'original_name' => 'briefing.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 1,
        ]);

        $this->app->bind(RecordTaskActivity::class, fn () => new class
        {
            public function __invoke(...$args)
            {
                throw new RuntimeException('Simulated activity-recording failure.');
            }
        });

        $this->actingAs($user)
            ->deleteJson("/api/attachments/{$attachment->id}")
            ->assertServerError();

        // The DB transaction rolled back — the row is still there — and
        // the physical cleanup (which only runs after a successful commit)
        // never even started.
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
        expect(TaskActivity::count())->toBe(0);
        $disk->assertExists($path);
    });

    test('a physical cleanup failure after a successful commit still returns 204 and leaves no dangling row', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/locked.txt";
        $attachment = $task->attachments()->create([
            'original_name' => 'locked.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        // Forced via a Storage facade mock, not real OS file permissions:
        // a chmod-based failure depends on the test process running as a
        // non-root user, which cannot be assumed in every CI environment
        // (root bypasses permission checks entirely, silently turning this
        // into a no-op and making the test flaky rather than red). Mocking
        // the exact disk calls the controller makes reproduces the same
        // "delete() reports failure, and a recheck confirms the file is
        // genuinely still there" branch deterministically everywhere.
        Storage::shouldReceive('disk')
            ->once()
            ->with(config('filesystems.attachments_disk'))
            ->andReturn($fakeDisk = Mockery::mock(Filesystem::class));
        $fakeDisk->shouldReceive('exists')->with($path)->twice()->andReturn(true);
        $fakeDisk->shouldReceive('delete')->with($path)->once()->andReturn(false);

        $response = $this->actingAs($user)->deleteJson("/api/attachments/{$attachment->id}");

        // The DB half already committed — the API contract stays a clean
        // 204, the row is gone, and the activity is recorded — the physical
        // cleanup failure is only ever logged, never surfaced to the client.
        $response->assertNoContent();
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $activity = TaskActivity::sole();
        expect($activity->type)->toBe(TaskActivityType::AttachmentRemoved);
        expect($activity->data)->toBe(['name' => 'locked.txt']);
    });
});

describe('task delete cleanup', function () {
    test('deleting a task removes its attachments directory, including orphan files, without touching other tasks', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();
        $otherTask = Task::factory()->for($project)->create();

        $ownedPath = "attachments/{$task->id}/arquivo-a.txt";
        $orphanPath = "attachments/{$task->id}/orphan.txt";
        $keepPath = "attachments/{$otherTask->id}/keep.txt";

        $disk->put($ownedPath, 'a');
        $disk->put($orphanPath, 'orphan, no row');
        $disk->put($keepPath, 'keep me');

        $task->attachments()->create([
            'original_name' => 'arquivo-a.txt',
            'path' => $ownedPath,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/tasks/{$task->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        expect($disk->directoryExists("attachments/{$task->id}"))->toBeFalse();
        $disk->assertMissing($ownedPath);
        $disk->assertMissing($orphanPath);
        $disk->assertExists($keepPath);
    });

    test('a stranger cannot delete a task, and its files remain untouched', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'x');
        $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($stranger)->deleteJson("/api/tasks/{$task->id}");

        $response->assertForbidden();
        $this->assertModelExists($task);
        $disk->assertExists($path);
    });
});

describe('project delete cleanup', function () {
    test('deleting a project removes every one of its tasks\' attachments directories, including orphans', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task1 = Task::factory()->for($project)->create();
        $task2 = Task::factory()->for($project)->create();

        $otherProject = Project::factory()->for($user)->create();
        $otherTask = Task::factory()->for($otherProject)->create();

        $path1 = "attachments/{$task1->id}/a.txt";
        $orphanPath = "attachments/{$task1->id}/orphan.txt";
        $path2 = "attachments/{$task2->id}/b.txt";
        $keepPath = "attachments/{$otherTask->id}/keep.txt";

        $disk->put($path1, 'a');
        $disk->put($orphanPath, 'orphan');
        $disk->put($path2, 'b');
        $disk->put($keepPath, 'keep me');

        $task1->attachments()->create(['original_name' => 'a.txt', 'path' => $path1, 'mime_type' => 'text/plain', 'size' => 1]);
        $task2->attachments()->create(['original_name' => 'b.txt', 'path' => $path2, 'mime_type' => 'text/plain', 'size' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/projects/{$project->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        expect($disk->directoryExists("attachments/{$task1->id}"))->toBeFalse();
        expect($disk->directoryExists("attachments/{$task2->id}"))->toBeFalse();
        $disk->assertMissing($path1);
        $disk->assertMissing($orphanPath);
        $disk->assertMissing($path2);
        $disk->assertExists($keepPath);
    });

    test('a stranger cannot delete a project, and its files remain untouched', function () {
        Storage::fake(config('filesystems.attachments_disk'));
        $disk = Storage::disk(config('filesystems.attachments_disk'));
        $stranger = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $path = "attachments/{$task->id}/file.txt";
        $disk->put($path, 'x');
        $task->attachments()->create([
            'original_name' => 'file.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 1,
        ]);

        $response = $this->actingAs($stranger)->deleteJson("/api/projects/{$project->id}");

        $response->assertForbidden();
        $this->assertModelExists($project);
        $this->assertModelExists($task);
        $disk->assertExists($path);
    });
});
