<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_and_logout(): void
    {
        $this->postJson('/api/register', ['name' => 'Alex', 'username' => 'alex', 'email' => 'alex@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated()->assertJsonPath('user.username', 'alex')->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check('password123', User::first()->password));
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
        $this->postJson('/api/login', ['username' => 'alex', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['username' => 'alex', 'password' => 'password123'])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_registration_rejects_duplicate_username_and_weak_password(): void
    {
        User::factory()->create(['username' => 'taken']);
        $this->postJson('/api/register', ['name' => 'Alex', 'username' => 'taken', 'email' => 'new@example.com', 'password' => 'abc', 'password_confirmation' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_guests_cannot_access_workspace_or_mutate_tasks(): void
    {
        $this->getJson('/api/workspace')->assertUnauthorized();
        $this->postJson('/api/tasks', ['title' => 'Unauthorized'])->assertUnauthorized();
    }

    public function test_task_lifecycle_and_project_deletion_preserves_tasks(): void
    {
        $this->actingAs(User::factory()->create());
        $project = $this->postJson('/api/projects', ['name' => 'Work', 'color' => '#123456'])->assertCreated()->json('id');
        $data = ['title' => 'Plan the week', 'priority' => 'high', 'description' => 'Notes', 'due_date' => '2026-09-20', 'project_id' => $project];
        $id = $this->postJson('/api/tasks', $data)->assertCreated()->json('id');
        $this->putJson('/api/tasks/'.$id, [...$data, 'title' => 'Updated task'])->assertOk()->assertJsonPath('title', 'Updated task');
        $this->patchJson("/api/tasks/$id/completion", ['completed' => true])->assertOk();
        $this->assertNotNull(Task::find($id)->completed_at);
        $this->patchJson("/api/tasks/$id/completion", ['completed' => false])->assertOk()->assertJsonPath('completed_at', null);
        $this->deleteJson('/api/projects/'.$project)->assertNoContent();
        $this->assertNull(Task::find($id)->project_id);
        $this->deleteJson('/api/tasks/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $id]);
    }

    public function test_users_cannot_read_or_modify_other_users_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => 'Secret', 'color' => '#123456']);
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Private', 'priority' => 'low', 'project_id' => $project->id]);
        $this->actingAs($other);
        $this->getJson('/api/workspace')->assertOk()->assertExactJson(['tasks' => [], 'projects' => []]);
        $this->putJson('/api/tasks/'.$task->id, ['title' => 'Hacked', 'priority' => 'high'])->assertNotFound();
        $this->patchJson('/api/tasks/'.$task->id.'/completion', ['completed' => true])->assertNotFound();
        $this->deleteJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->deleteJson('/api/projects/'.$project->id)->assertNotFound();
        $this->postJson('/api/tasks', ['title' => 'Cross owner', 'priority' => 'low', 'project_id' => $project->id])->assertUnprocessable();
    }

    public function test_invalid_task_and_project_inputs_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/tasks', ['title' => '', 'priority' => 'urgent', 'due_date' => 'tomorrow'])->assertUnprocessable()->assertJsonValidationErrors(['title', 'priority', 'due_date']);
        $this->postJson('/api/projects', ['name' => 'Example', 'color' => 'invalid'])->assertUnprocessable();
    }
}
