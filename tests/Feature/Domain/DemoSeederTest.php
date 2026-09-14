<?php

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\ColorPalette;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

/**
 * Points DemoSeeder at a fully valid, deterministic set of demo credentials
 * — every test that needs a working seed starts from this and only
 * overrides the one field it cares about.
 */
function setValidDemoConfig(array $overrides = []): void
{
    config([
        'demo.user' => array_merge([
            'name' => 'Demo User',
            'email' => 'demo@example.test',
            'password' => 'Demo#Password123',
        ], $overrides),
    ]);
}

describe('valid configuration', function () {
    test('creates exactly one demo user', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(User::count())->toBe(1);
    });

    test('the user has the expected name and email', function () {
        setValidDemoConfig(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);

        (new DemoSeeder)->run();

        $user = User::sole();
        expect($user->name)->toBe('Ada Lovelace');
        expect($user->email)->toBe('ada@example.test');
    });

    test('the password is hashed and verifiable via Hash::check', function () {
        setValidDemoConfig(['password' => 'Correct#Horse9']);

        (new DemoSeeder)->run();

        $user = User::sole();
        expect(Hash::check('Correct#Horse9', $user->password))->toBeTrue();
    });

    test('email_verified_at is filled', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(User::sole()->email_verified_at)->not->toBeNull();
    });
});

describe('invalid or missing configuration', function () {
    test('an invalid email format fails before creating any user', function () {
        setValidDemoConfig(['email' => 'not-an-email']);

        expect(fn () => (new DemoSeeder)->run())->toThrow(ValidationException::class);
        expect(User::count())->toBe(0);
    });

    test('a missing email fails before creating any user', function () {
        setValidDemoConfig(['email' => null]);

        expect(fn () => (new DemoSeeder)->run())->toThrow(ValidationException::class);
        expect(User::count())->toBe(0);
    });

    test('a password that violates the app password policy fails before creating any user', function () {
        setValidDemoConfig(['password' => 'short']);

        expect(fn () => (new DemoSeeder)->run())->toThrow(ValidationException::class);
        expect(User::count())->toBe(0);
    });

    test('a missing password fails before creating any user', function () {
        setValidDemoConfig(['password' => null]);

        expect(fn () => (new DemoSeeder)->run())->toThrow(ValidationException::class);
        expect(User::count())->toBe(0);
    });
});

describe('projects and tags', function () {
    test('creates exactly 3 projects and 6 tags', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Project::count())->toBe(3);
        expect(Tag::count())->toBe(6);
    });

    test('every project and tag belongs to the demo user', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $user = User::sole();

        expect(Project::pluck('user_id')->unique()->all())->toBe([$user->id]);
        expect(Tag::pluck('user_id')->unique()->all())->toBe([$user->id]);
    });

    test('the 3 projects have the expected exact names', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Project::pluck('name')->sort()->values()->all())->toBe([
            'Evolução do Produto',
            'Qualidade & Infraestrutura',
            'Release Taskly v1.0',
        ]);
    });

    test('the 6 tags have the expected exact names', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Tag::pluck('name')->sort()->values()->all())->toBe([
            'Backend',
            'Bug',
            'DevOps',
            'Frontend',
            'QA',
            'UX/UI',
        ]);
    });

    test('each tag has a normalized_name correctly derived from its name', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        Tag::all()->each(function (Tag $tag) {
            expect($tag->normalized_name)->toBe(Tag::normalize($tag->name));
        });
    });

    test('every project and tag color is one of the approved palette colors', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        Project::all()->each(fn (Project $project) => expect($project->color)->toBeIn(ColorPalette::AUXILIARY));
        Tag::all()->each(fn (Tag $tag) => expect($tag->color)->toBeIn(ColorPalette::AUXILIARY));
    });
});

describe('tasks', function () {
    test('creates exactly 12 tasks', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Task::count())->toBe(12);
    });

    test('the status distribution is exactly 5 not_started / 3 in_progress / 3 completed / 1 cancelled', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Task::where('status', TaskStatus::NotStarted)->count())->toBe(5);
        expect(Task::where('status', TaskStatus::InProgress)->count())->toBe(3);
        expect(Task::where('status', TaskStatus::Completed)->count())->toBe(3);
        expect(Task::where('status', TaskStatus::Cancelled)->count())->toBe(1);
    });

    test('exactly 2 tasks are overdue', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        expect(Task::all()->filter->overdue)->toHaveCount(2);
    });

    test('the showcase task "Validar histórico de atividades" ends in_progress, overdue, tagged, with exactly 4 activities in order', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        $task = Task::where('title', 'Validar histórico de atividades')->sole();

        expect($task->status)->toBe(TaskStatus::InProgress);
        expect($task->overdue)->toBeTrue();
        expect($task->tags->pluck('name')->sort()->values()->all())->toBe(['Backend', 'QA']);

        $activities = $task->activities()->orderBy('id')->get();
        expect($activities)->toHaveCount(4);
        expect($activities->pluck('type')->all())->toBe([
            TaskActivityType::TaskCreated,
            TaskActivityType::DueAtChanged,
            TaskActivityType::TagsChanged,
            TaskActivityType::StatusChanged,
        ]);

        expect($activities[0]->data)->toBe([]);

        expect($activities[1]->data['from'])->toBeNull();
        expect($activities[1]->data['to'])->toBe($task->due_at->toIso8601ZuluString());

        $tagsChangedAdded = collect($activities[2]->data['added'])->pluck('name')->sort()->values()->all();
        expect($tagsChangedAdded)->toBe(['Backend', 'QA']);
        expect($activities[2]->data['removed'])->toBe([]);

        expect($activities[3]->data)->toBe(['from' => 'not_started', 'to' => 'in_progress']);
    });

    test('the showcase task "Preparar release de produção" stays not_started with only task_created and tags_changed', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        $task = Task::where('title', 'Preparar release de produção')->sole();

        expect($task->status)->toBe(TaskStatus::NotStarted);
        expect($task->due_at->isFuture())->toBeTrue();
        expect($task->tags->pluck('name')->all())->toBe(['DevOps']);

        $activities = $task->activities()->orderBy('id')->get();
        expect($activities->pluck('type')->all())->toBe([
            TaskActivityType::TaskCreated,
            TaskActivityType::TagsChanged,
        ]);
    });

    test('completed tasks with a past due date have completed_at filled and are not overdue', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        foreach (['Finalizar cobertura de testes da API', 'Validar observabilidade pós-release'] as $title) {
            $task = Task::where('title', $title)->sole();
            expect($task->status)->toBe(TaskStatus::Completed);
            expect($task->completed_at)->not->toBeNull();
            expect($task->due_at->isPast())->toBeTrue();
            expect($task->overdue)->toBeFalse();
        }
    });

    test('the cancelled task has no completed_at and is not overdue despite a past due date', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        $task = Task::where('title', 'Descontinuar layout legado')->sole();

        expect($task->status)->toBe(TaskStatus::Cancelled);
        expect($task->completed_at)->toBeNull();
        expect($task->due_at->isPast())->toBeTrue();
        expect($task->overdue)->toBeFalse();
    });

    test('tasks without a due date really have due_at null', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        foreach (['Corrigir acessibilidade do modal de tarefas', 'Documentar endpoints de tarefas', 'Revisar responsividade do Kanban'] as $title) {
            expect(Task::where('title', $title)->sole()->due_at)->toBeNull();
        }
    });

    test('no task has more than 5 tags', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();

        Task::all()->each(fn (Task $task) => expect($task->tags()->count())->toBeLessThanOrEqual(5));
    });

    test('every task belongs to a project owned by the demo user', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $user = User::sole();

        Task::with('project')->get()->each(
            fn (Task $task) => expect($task->project->user_id)->toBe($user->id)
        );
    });
});

describe('idempotency', function () {
    test('running it a second time does not duplicate or alter the existing user', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $firstRunUser = User::sole();

        (new DemoSeeder)->run();

        expect(User::count())->toBe(1);
        expect(Project::count())->toBe(3);
        expect(Tag::count())->toBe(6);
        $secondRunUser = User::sole();
        expect($secondRunUser->name)->toBe($firstRunUser->name);
        expect($secondRunUser->email)->toBe($firstRunUser->email);
        expect($secondRunUser->password)->toBe($firstRunUser->password);
    });

    test('the user id stays the same after a second run', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $firstRunId = User::sole()->id;

        (new DemoSeeder)->run();

        expect(User::sole()->id)->toBe($firstRunId);
    });

    test('project and tag ids stay the same after a second run', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $firstRunProjectIds = Project::pluck('id')->sort()->values()->all();
        $firstRunTagIds = Tag::pluck('id')->sort()->values()->all();

        (new DemoSeeder)->run();

        expect(Project::pluck('id')->sort()->values()->all())->toBe($firstRunProjectIds);
        expect(Tag::pluck('id')->sort()->values()->all())->toBe($firstRunTagIds);
    });

    test('a second run does not alter tasks, task activities, or any of their ids/counts', function () {
        setValidDemoConfig();

        (new DemoSeeder)->run();
        $firstRunTaskIds = Task::pluck('id')->sort()->values()->all();
        $firstRunActivityCount = DB::table('task_activities')->count();

        (new DemoSeeder)->run();

        expect(Task::count())->toBe(12);
        expect(Task::pluck('id')->sort()->values()->all())->toBe($firstRunTaskIds);
        expect(DB::table('task_activities')->count())->toBe($firstRunActivityCount);
    });
});

describe('atomicity', function () {
    test('a failure partway through the dataset rolls back the entire demo graph, including Tasks, Activities, and Tag pivots', function () {
        setValidDemoConfig();

        // A real Eloquent model event — not a mock, not a change to
        // DemoSeeder itself — forces a genuine failure while creating the
        // 3rd Task of the "Release Taskly v1.0" project. By that point,
        // the User, all 3 Projects, all 6 Tags, and 2 earlier Tasks (the
        // showcase task with its 4 Activities, and one more with its own
        // Activities and tag pivot) already exist inside the transaction —
        // proving the rollback undoes everything created before the
        // failure too, not just the failing write itself.
        Task::creating(function (Task $task): void {
            if ($task->title === 'Corrigir falha na sincronização de status') {
                throw new RuntimeException('Simulated failure for the atomicity test.');
            }
        });

        expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);

        expect(User::count())->toBe(0);
        expect(Project::count())->toBe(0);
        expect(Tag::count())->toBe(0);
        expect(Task::count())->toBe(0);
        expect(DB::table('task_activities')->count())->toBe(0);
        expect(DB::table('tag_task')->count())->toBe(0);
    });
});
