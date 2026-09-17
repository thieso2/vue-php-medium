<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        return ['tasks' => Task::where('user_id', $request->user()->id)->latest()->get(), 'projects' => Project::where('user_id', $request->user()->id)->orderBy('name')->get()];
    }

    private function taskData(Request $request): array
    {
        return $request->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'priority' => ['required', Rule::in(['low', 'medium', 'high'])], 'due_date' => 'nullable|date_format:Y-m-d', 'project_id' => ['nullable', Rule::exists('projects', 'id')->where('user_id', $request->user()->id)]]);
    }

    public function store(Request $request)
    {
        return response()->json(Task::create([...$this->taskData($request), 'user_id' => $request->user()->id]), 201);
    }

    public function update(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 404);
        $task->update($this->taskData($request));

        return $task;
    }

    public function toggle(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 404);
        $data = $request->validate(['completed' => 'required|boolean']);
        $task->update(['completed_at' => $data['completed'] ? now() : null]);

        return $task;
    }

    public function destroy(Request $request, Task $task)
    {
        abort_unless($task->user_id === $request->user()->id, 404);
        $task->delete();

        return response()->noContent();
    }

    public function storeProject(Request $request)
    {
        return response()->json(Project::create([...$request->validate(['name' => 'required|string|max:80', 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]), 'user_id' => $request->user()->id]), 201);
    }

    public function destroyProject(Request $request, Project $project)
    {
        abort_unless($project->user_id === $request->user()->id, 404);
        $project->delete();

        return response()->noContent();
    }
}
