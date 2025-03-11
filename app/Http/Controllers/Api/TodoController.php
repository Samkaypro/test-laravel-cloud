<?php

namespace App\Http\Controllers\Api;

use App\Events\TodoCreated;
use App\Events\TodoUpdated;
use App\Events\TodoDeleted;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Models\Todo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\TodoResource;
use Exception;
use App\Models\Log as LogModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TodoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Todo::where('user_id', auth()->id());
    
            // Search and filtering
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
            }
    
            $todolist = $query->latest()->paginate(10); // Paginate with 10 items per page
            $this->logInfo('Accessed Todo List', 'GET');
        } catch (Exception $error) {
            $this->logError('Failed to access Todo List', $error);
            return response()->json(['message' => 'Failed to retrieve Todo List'], 500);
        }
    
        return TodoResource::collection($todolist);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTodoRequest $request)
{
    try {
        $todo = Todo::create([
            'title' => $request->title,
            'description' => $request->description,
            'completed' => $request->completed,
            'user_id' => auth()->id(),
        ]);
        $this->logInfo('Todo List Created', 'POST');

        event(new TodoCreated($todo));

        return response()->json([
            'message' => 'Todo Created Successfully',
            'data' => new TodoResource($todo)
        ], 201);
    } catch (Exception $error) {
        $this->logError('Failed to create Todo', $error);
        return response()->json(['message' => 'Failed to create Todo'], 500);
    }
}

    /**
     * Display the specified resource.
     */
    public function show(Todo $todo)
    {
        if ($todo->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->logInfo('Todo Retrieved Successfully', 'GET');
        return response()->json([
            'message' => 'Todo Retrieved Successfully',
            'data' => new TodoResource($todo)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTodoRequest $request, Todo $todo)
{
    if ($todo->user_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    try {
        $todo->update($request->all());
        $this->logInfo('Todo List Updated', 'PUT');

        event(new TodoUpdated($todo));

        return response()->json([
            'message' => 'Todo Updated Successfully',
            'data' => new TodoResource($todo)
        ]);
    } catch (Exception $error) {
        $this->logError('Failed to update Todo', $error);
        return response()->json(['message' => 'Failed to update Todo'], 500);
    }
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Todo $todo)
    {
        if ($todo->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    
        try {
            $todo->delete();
            $this->logInfo('Todo List Deleted', 'DELETE');
    
            event(new TodoDeleted($todo)); // Dispatch event
    
            return response()->json(['message' => 'Todo Deleted Successfully']);
        } catch (Exception $error) {
            $this->logError('Failed to delete Todo', $error);
            return response()->json(['message' => 'Failed to delete Todo'], 500);
        }
    }

    /**
     * Log information messages.
     */
    private function logInfo($message, $method)
    {
        Log::channel('stack')->info($message);
        Log::channel('slack')->info($message);
        LogModel::record(auth()->user(), $message, $method);
    }

    /**
     * Log error messages.
     */
    private function logError($message, Exception $error)
    {
        Log::channel('stack')->error($message, ['message' => $error->getMessage()]);
        Log::channel('slack')->error($message, ['message' => $error->getMessage()]);
        LogModel::record(auth()->user(), $message, 'ERROR');
    }
}