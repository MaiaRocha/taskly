<?php

namespace Database\Seeders;

use App\Actions\RecordTaskActivity;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Support\ColorPalette;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class DemoSeeder extends Seeder
{
    /**
     * Seeds a single, controlled demo account and dataset for the product
     * walkthrough — never run automatically by DatabaseSeeder, only via
     * `php artisan db:seed --class=DemoSeeder --force`.
     *
     * Safe to re-run: if the demo user already exists, this is treated as an
     * expected repeat execution (not an error) — nothing is created,
     * updated, or touched.
     */
    public function run(): void
    {
        $credentials = $this->validatedCredentials();

        if (User::where('email', $credentials['email'])->exists()) {
            $this->command?->warn(
                "Demo user \"{$credentials['email']}\" already exists — skipping. Nothing was created or changed."
            );

            return;
        }

        // Everything the demo dataset creates — this User, its Projects,
        // Tags, Tasks, and their Activities — lives inside one transaction,
        // so a failure partway through never leaves a half-seeded account
        // behind for the guard above to (incorrectly) treat as "already
        // done".
        DB::transaction(function () use ($credentials) {
            // Single reference instant for the whole demo dataset — every
            // relative date (due_at, etc.) below derives from this same
            // $now via ->copy(), never a bare now() call.
            $now = now();

            // `email_verified_at` is deliberately absent from User's
            // #[Fillable(...)] list (mass assignment never sets it — see
            // the Task::completed_at hook for the same principle applied
            // elsewhere), so it can't be passed into create()'s array; it's
            // set the same way that hook does, via direct property
            // assignment, which fillable/guarded never restricts.
            $user = User::create([
                'name' => $credentials['name'],
                'email' => $credentials['email'],
                'password' => Hash::make($credentials['password']),
            ]);

            $user->email_verified_at = $now;
            $user->save();

            $projects = $this->createProjects($user);
            $tags = $this->createTags($user);

            $this->createTasks($projects, $tags, $now);
        });
    }

    /**
     * Exactly 3 curated Projects, no Faker — colors come from
     * ColorPalette::AUXILIARY (via Project::COLORS, the same constant the
     * app itself validates against), and `position` follows the same rule
     * ProjectController::store uses (max(position) ?? -1) + 1): starting
     * from an empty account, that's simply 0, 1, 2 in creation order.
     *
     * @return array<string, Project> keyed by a short internal slug, reused
     *                                by createTasks() to attach Tasks to
     *                                the right Project
     */
    private function createProjects(User $user): array
    {
        $definitions = [
            'product' => [
                'name' => 'Evolução do Produto',
                'description' => 'Melhorias contínuas de produto e experiência do usuário.',
                'color' => Project::COLORS[5], // #3B82F6 — blue
            ],
            'release' => [
                'name' => 'Release Taskly v1.0',
                'description' => 'Preparação e execução do lançamento da primeira versão pública do Taskly.',
                'color' => Project::COLORS[4], // #22C55E — green
            ],
            'quality' => [
                'name' => 'Qualidade & Infraestrutura',
                'description' => 'Testes, observabilidade e infraestrutura de entrega contínua.',
                'color' => Project::COLORS[2], // #EC4899 — pink
            ],
        ];

        $projects = [];
        $position = 0;

        foreach ($definitions as $slug => $attributes) {
            $projects[$slug] = $user->projects()->create([...$attributes, 'position' => $position]);
            $position++;
        }

        return $projects;
    }

    /**
     * Exactly 6 curated Tags, no Faker — one of the 6 ColorPalette::AUXILIARY
     * colors each, none repeated (the same palette StoreTagRequest itself
     * validates a Tag's color against). Created through Eloquent
     * (Tag::create(), never DB::table()->insert()) specifically so the
     * name() attribute mutator derives normalized_name the same way it
     * does for every real Tag in the app.
     *
     * @return array<string, Tag> keyed by tag name, reused by createTasks()
     *                            to attach Tags to Tasks
     */
    private function createTags(User $user): array
    {
        $definitions = [
            'Frontend' => ColorPalette::AUXILIARY[0], // #06B6D4 — cyan
            'Backend' => ColorPalette::AUXILIARY[5], // #3B82F6 — blue
            'UX/UI' => ColorPalette::AUXILIARY[2], // #EC4899 — pink
            'QA' => ColorPalette::AUXILIARY[1], // #14B8A6 — teal
            'DevOps' => ColorPalette::AUXILIARY[3], // #F59E0B — orange
            'Bug' => ColorPalette::AUXILIARY[4], // #22C55E — green
        ];

        $tags = [];

        foreach ($definitions as $name => $color) {
            $tags[$name] = $user->tags()->create(['name' => $name, 'color' => $color]);
        }

        return $tags;
    }

    /**
     * Exactly 12 curated Tasks, no Faker, distributed 5 not_started /
     * 3 in_progress / 3 completed / 1 cancelled across the 3 Projects.
     *
     * Every Task is BORN not_started — the same rule TaskController::store
     * itself follows (it never records anything but task_created,
     * regardless of the status passed at creation). Every Task here
     * explicitly starts at that same default so a later status change,
     * when one is needed, is always a REAL transition backed by a real
     * StatusChanged, never a status baked silently into creation. Tags,
     * when present, are attached via a real sync() right after creation
     * (TagsChanged) — never baked into the create() payload, since the
     * real API never accepts tag_ids there either. Only Task #5 ("Validar
     * histórico de atividades") gets a bespoke, longer narrative (see
     * createShowcaseActivityHistoryTask) — every other Task uses the same
     * small createSimpleTask() helper.
     *
     * All relative dates derive from the single $now captured in run(), via
     * ->copy(), never a bare now() call.
     */
    private function createTasks(array $projects, array $tags, Carbon $now): void
    {
        $recordTaskActivity = app(RecordTaskActivity::class);

        // --- Evolução do Produto ---
        $this->createSimpleTask($projects['product'], $recordTaskActivity, [
            'title' => 'Refinar fluxo de onboarding',
            'short_description' => 'Ajustar as etapas iniciais para reduzir a fricção no primeiro acesso do usuário.',
            'description' => null,
            'due_at' => $now->copy()->addDays(5),
        ], [$tags['UX/UI']->id], TaskStatus::NotStarted);

        $this->createSimpleTask($projects['product'], $recordTaskActivity, [
            'title' => 'Implementar componentes do design system',
            'short_description' => 'Padronizar os componentes visuais reutilizáveis da aplicação.',
            'description' => 'Consolidar botões, inputs, cards e badges em uma biblioteca única de componentes, com tokens de cor, tipografia e espaçamento documentados, substituindo os estilos ad-hoc espalhados pelas telas atuais.',
            'due_at' => $now->copy()->addDays(10),
        ], [$tags['Frontend']->id, $tags['UX/UI']->id], TaskStatus::InProgress);

        $this->createSimpleTask($projects['product'], $recordTaskActivity, [
            'title' => 'Corrigir acessibilidade do modal de tarefas',
            'short_description' => 'Ajustar contraste e navegação por teclado no modal de criação e edição de tarefas.',
            'description' => null,
            'due_at' => null,
        ], [$tags['Frontend']->id], TaskStatus::NotStarted);

        $this->createSimpleTask($projects['product'], $recordTaskActivity, [
            'title' => 'Descontinuar layout legado',
            'short_description' => null,
            'description' => null,
            'due_at' => $now->copy()->subDays(2),
        ], [$tags['Frontend']->id], TaskStatus::Cancelled);

        // --- Release Taskly v1.0 ---
        $this->createShowcaseActivityHistoryTask($projects['release'], $tags, $recordTaskActivity, $now);

        $this->createSimpleTask($projects['release'], $recordTaskActivity, [
            'title' => 'Preparar release de produção',
            'short_description' => 'Revisar configurações de ambiente, migrations e checklist técnico antes da publicação da versão v1.0.',
            'description' => null,
            'due_at' => $now->copy()->addDays(7),
        ], [$tags['DevOps']->id], TaskStatus::NotStarted);

        $this->createSimpleTask($projects['release'], $recordTaskActivity, [
            'title' => 'Corrigir falha na sincronização de status',
            'short_description' => null,
            'description' => null,
            'due_at' => $now->copy()->subDays(1),
        ], [$tags['Backend']->id, $tags['Bug']->id], TaskStatus::NotStarted);

        $this->createSimpleTask($projects['release'], $recordTaskActivity, [
            'title' => 'Finalizar cobertura de testes da API',
            'short_description' => null,
            'description' => null,
            'due_at' => $now->copy()->subDays(4),
        ], [$tags['Backend']->id, $tags['QA']->id], TaskStatus::Completed);

        $this->createSimpleTask($projects['release'], $recordTaskActivity, [
            'title' => 'Documentar endpoints de tarefas',
            'short_description' => 'Escrever a documentação de referência dos endpoints de tarefas da API pública.',
            'description' => null,
            'due_at' => null,
        ], [$tags['Backend']->id], TaskStatus::Completed);

        // --- Qualidade & Infraestrutura ---
        $this->createSimpleTask($projects['quality'], $recordTaskActivity, [
            'title' => 'Configurar pipeline de deploy',
            'short_description' => null,
            'description' => 'Automatizar as validações de qualidade e preparação do build antes da publicação em produção.',
            'due_at' => $now->copy()->addDays(14),
        ], [$tags['DevOps']->id], TaskStatus::InProgress);

        $this->createSimpleTask($projects['quality'], $recordTaskActivity, [
            'title' => 'Revisar responsividade do Kanban',
            'short_description' => null,
            'description' => null,
            'due_at' => null,
        ], [$tags['Frontend']->id, $tags['QA']->id], TaskStatus::NotStarted);

        $this->createSimpleTask($projects['quality'], $recordTaskActivity, [
            'title' => 'Validar observabilidade pós-release',
            'short_description' => null,
            'description' => null,
            'due_at' => $now->copy()->subDays(10),
        ], [$tags['DevOps']->id, $tags['QA']->id], TaskStatus::Completed);
    }

    /**
     * Creates one Task already holding its final `due_at` (a due date set
     * once, at creation, is a completely real, ordinary flow — no
     * DueAtChanged is fabricated for it), always starting at `not_started`
     * — task_created is recorded, tags (if any) are synced right after
     * (tags_changed), and only THEN, if the Task's real final status isn't
     * not_started, is a genuine status update applied and recorded
     * (status_changed). This guarantees no Task's history implies a
     * transition that never actually happened.
     *
     * @param  array{title: string, short_description: ?string, description: ?string, due_at: ?Carbon}  $attributes
     * @param  list<int>  $tagIds
     */
    private function createSimpleTask(
        Project $project,
        RecordTaskActivity $recordTaskActivity,
        array $attributes,
        array $tagIds,
        TaskStatus $finalStatus,
    ): Task {
        $position = ($project->tasks()->max('position') ?? -1) + 1;

        $task = $project->tasks()->create([
            ...$attributes,
            'status' => TaskStatus::NotStarted,
            'position' => $position,
        ]);

        $recordTaskActivity($task, TaskActivityType::TaskCreated);

        if ($tagIds !== []) {
            $this->syncTaskTags($task, $tagIds, $recordTaskActivity);
        }

        if ($finalStatus !== TaskStatus::NotStarted) {
            $originalStatus = $task->status;
            $task->update(['status' => $finalStatus]);

            $recordTaskActivity($task, TaskActivityType::StatusChanged, [
                'from' => $originalStatus->value,
                'to' => $task->status->value,
            ]);
        }

        return $task;
    }

    /**
     * The one showcase Task with a full, bespoke narrative — reproduces the
     * exact sequence of real mutations TaskController::update and
     * TaskTagController::update would each perform, called directly against
     * Eloquent + RecordTaskActivity (never through HTTP/Controller/
     * FormRequest/Gate, none of which are reachable from a Seeder — see
     * docs/DECISIONS.md). Produces EXACTLY 4 Activities, in this exact
     * order: TaskCreated, DueAtChanged, TagsChanged, StatusChanged.
     */
    private function createShowcaseActivityHistoryTask(
        Project $project,
        array $tags,
        RecordTaskActivity $recordTaskActivity,
        Carbon $now,
    ): Task {
        $position = ($project->tasks()->max('position') ?? -1) + 1;

        // a) Born not_started, due_at null, no tags.
        $task = $project->tasks()->create([
            'title' => 'Validar histórico de atividades',
            'short_description' => 'Confirmar que o histórico de atividades reflete corretamente as mudanças da tarefa.',
            'description' => 'Garantir que alterações de status, prazo e tags sejam registradas corretamente na timeline da tarefa, preservando a rastreabilidade das ações.',
            'status' => TaskStatus::NotStarted,
            'due_at' => null,
            'position' => $position,
        ]);

        // b) task_created
        $recordTaskActivity($task, TaskActivityType::TaskCreated);

        // c) due_at: null -> $now - 3 days — same before/after diff
        // TaskController::update uses (UTC ISO-8601 Zulu on both sides).
        $originalDueAtIso = $task->due_at?->toIso8601ZuluString();
        $task->update(['due_at' => $now->copy()->subDays(3)]);
        $newDueAtIso = $task->due_at?->toIso8601ZuluString();

        $recordTaskActivity($task, TaskActivityType::DueAtChanged, [
            'from' => $originalDueAtIso,
            'to' => $newDueAtIso,
        ]);

        // d) sync Backend + QA — same snapshot logic as TaskTagController::update.
        $this->syncTaskTags($task, [$tags['Backend']->id, $tags['QA']->id], $recordTaskActivity);

        // e) status: not_started -> in_progress
        $originalStatus = $task->status;
        $task->update(['status' => TaskStatus::InProgress]);

        $recordTaskActivity($task, TaskActivityType::StatusChanged, [
            'from' => $originalStatus->value,
            'to' => $task->status->value,
        ]);

        return $task;
    }

    /**
     * Reproduces TaskTagController::update's sync + snapshot + activity
     * logic verbatim, directly against Eloquent — a no-op sync (nothing
     * attached or detached) never records an Activity, matching the real
     * app exactly.
     *
     * @param  list<int>  $tagIds
     */
    private function syncTaskTags(Task $task, array $tagIds, RecordTaskActivity $recordTaskActivity): void
    {
        $result = $task->tags()->sync($tagIds);

        if (empty($result['attached']) && empty($result['detached'])) {
            return;
        }

        $affectedIds = [...$result['attached'], ...$result['detached']];
        $tagsById = Tag::whereIn('id', $affectedIds)->get()->keyBy('id');

        $snapshot = fn (array $ids) => collect($ids)
            ->sort()
            ->map(fn (int $id) => $tagsById->get($id))
            ->filter()
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])
            ->values()
            ->all();

        $recordTaskActivity($task, TaskActivityType::TagsChanged, [
            'added' => $snapshot($result['attached']),
            'removed' => $snapshot($result['detached']),
        ]);
    }

    /**
     * Reads the demo credentials from config (never env() directly here)
     * and validates them with the same rules the app already trusts
     * elsewhere — `email` for a real address, and `Password::default()` for
     * the exact same password policy Fortify's registration enforces, not a
     * duplicated/looser one. Fails via Laravel's own ValidationException
     * before this method returns, so `run()` never reaches the existence
     * check or the transaction with bad/missing credentials.
     *
     * @return array{name: string, email: string, password: string}
     */
    private function validatedCredentials(): array
    {
        $data = [
            'name' => config('demo.user.name'),
            'email' => config('demo.user.email'),
            'password' => config('demo.user.password'),
        ];

        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', Password::default()],
        ])->validate();

        return $data;
    }
}
