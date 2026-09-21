<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TriggerStoreRequest;
use App\Http\Requests\VirtualMemberStoreRequest;
use App\Models\AiPersonality;
use App\Models\AiUsageLog;
use App\Models\ChatTemplate;
use App\Models\ChatTrigger;
use App\Models\Message;
use App\Models\User;
use App\Models\VirtualConversation;
use App\Models\VirtualProfile;
use App\Services\AuditService;
use App\Services\VirtualMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VirtualMemberAdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $data = [
            'activeConversations' => VirtualConversation::open()->count(),
            'messagesPerDay' => Message::where('is_ai_generated', true)->where('created_at', '>', now()->subDay())->count(),
            'tokenUsage' => AiUsageLog::where('created_at', '>', now()->subDays(30))->sum('tokens'),
            'cost' => AiUsageLog::where('created_at', '>', now()->subDays(30))->sum('cost'),
            'virtualCount' => User::whereIn('account_type', ['virtual', 'ai'])->count(),
        ];

        return $request->wantsJson() ? response()->json($data) : view('admin.chat.virtual', $data);
    }

    public function index(Request $request)
    {
        $items = User::whereIn('account_type', ['virtual', 'ai'])->with(['profile', 'virtualProfile' => fn ($q) => $q])->latest('id')->paginate(25);

        return response()->json($items);
    }

    public function store(VirtualMemberStoreRequest $request, VirtualMemberService $service)
    {
        $user = $service->createVirtual($request->validated());

        return response()->json($user->load('virtualProfile'), 201);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'persona_prompt' => ['nullable', 'string', 'max:4000'],
            'greeting_message' => ['nullable', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'in:template,hybrid,ai,operator'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $user) {
            $user->update($request->only(['display_name']));
            $profile = VirtualProfile::firstOrCreate(['user_id' => $user->id]);
            $profile->update($request->only(['persona_prompt', 'greeting_message', 'mode', 'is_active']));
        });
        $audit->log('admin.virtual.updated', $request->user(), $user);

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user, AuditService $audit)
    {
        $user->delete();
        $audit->log('admin.virtual.deleted', $request->user(), $user);

        return response()->json(['message' => 'Deleted.']);
    }

    public function personalities(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['name' => ['required', 'string', 'max:160'], 'prompt' => ['nullable', 'string']]);
            $p = AiPersonality::create($request->only(['name', 'prompt', 'description', 'is_active']));

            return response()->json($p, 201);
        }

        return response()->json(AiPersonality::orderBy('id')->get());
    }

    public function templates(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['key' => ['required', 'string', 'max:120'], 'template' => ['required', 'string', 'max:2000']]);
            $t = ChatTemplate::create($request->only(['key', 'template', 'category', 'is_active']));

            return response()->json($t, 201);
        }

        return response()->json(ChatTemplate::orderBy('id')->paginate(50));
    }

    public function triggers(Request $request)
    {
        return response()->json(ChatTrigger::with('actions')->orderBy('id')->paginate(50));
    }

    public function storeTrigger(TriggerStoreRequest $request, AuditService $audit)
    {
        $trigger = DB::transaction(function () use ($request) {
            $t = ChatTrigger::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'event' => $request->input('event_name', $request->input('event')),
                'conditions' => $request->input('conditions', []),
                'priority' => $request->input('priority', 0),
                'cooldown_minutes' => $request->input('cooldown_minutes', 0),
                'is_active' => $request->boolean('is_active', true),
            ]);
            foreach ((array) $request->input('actions', []) as $i => $action) {
                $t->actions()->create([
                    'action_type' => $action['action_type'],
                    'payload' => ['template' => $action['template'] ?? null, 'delay_seconds' => $action['delay_seconds'] ?? 0],
                    'sort_order' => $i,
                ]);
            }

            return $t->fresh('actions');
        });
        $audit->log('admin.trigger.created', $request->user(), $trigger);

        return response()->json($trigger, 201);
    }

    public function schedules(Request $request)
    {
        return response()->json(ChatTrigger::active()->orderBy('id')->get(['id', 'name', 'event', 'cooldown_minutes']));
    }
}
