<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Models\VirtualConversation;
use Illuminate\Http\Request;

class ChatAdminController extends Controller
{
    public function active(Request $request)
    {
        $items = Conversation::with(['members.user'])->orderByDesc('last_message_at')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.chat.conversations', ['items' => $items]);
    }

    public function reported(Request $request)
    {
        return response()->json(ChatReport::with(['reporter', 'conversation'])->latest('id')->paginate(25));
    }

    public function flagged(Request $request)
    {
        $q = $request->query('q', '');
        $items = Message::where('body', 'like', "%{$q}%")->with(['sender', 'conversation'])->latest('id')->paginate(25);

        return response()->json($items);
    }

    public function virtual(Request $request)
    {
        return response()->json(VirtualConversation::with(['realUser', 'virtualProfile', 'conversation'])->latest('id')->paginate(25));
    }

    public function search(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:255']]);
        $q = $request->string('q');

        return response()->json([
            'messages' => Message::where('body', 'like', "%{$q}%")->limit(20)->get(),
            'conversations' => Conversation::where('title', 'like', "%{$q}%")->limit(20)->get(),
        ]);
    }

    /** Admin → Chat → Settings: every chat key the backend actually reads. */
    public function settings(Request $request)
    {
        $defs = [
            'Umum' => [
                ['key' => 'chat.messages_per_minute', 'label' => 'Pesan per menit', 'type' => 'number', 'hint' => 'Rate limit pengiriman pesan.'],
                ['key' => 'chat.messages_per_hour', 'label' => 'Pesan per jam', 'type' => 'number', 'hint' => 'Rate limit per jam.'],
                ['key' => 'chat.max_length', 'label' => 'Panjang pesan maks', 'type' => 'number', 'hint' => 'Karakter.'],
                ['key' => 'chat.request_expiry_hours', 'label' => 'Kedaluarsa chat request (jam)', 'type' => 'number', 'hint' => '72 = 3 hari.'],
            ],
            'Kuota per User' => [
                ['key' => 'chat.free_messages_per_peer', 'label' => 'Kuota free per user', 'type' => 'number', 'hint' => 'Jumlah pesan member gratis ke satu user.'],
                ['key' => 'chat.premium_messages_per_peer', 'label' => 'Kuota premium per user', 'type' => 'number', 'hint' => 'Jumlah pesan member premium ke satu user.'],
            ],
            'Voice / Video Call (token)' => [
                ['key' => 'calls.voice_per_minute', 'label' => 'Tarif voice per menit', 'type' => 'number', 'hint' => 'Token per menit, dibulatkan ke atas.'],
                ['key' => 'calls.video_per_minute', 'label' => 'Tarif video per menit', 'type' => 'number', 'hint' => 'Token per menit, dibulatkan ke atas.'],
                ['key' => 'calls.invite_ttl_seconds', 'label' => 'Batas dering (detik)', 'type' => 'number', 'hint' => 'Undangan kedaluwarsa jadi missed.'],
            ],
            'Moderasi Bahasa' => [
                ['key' => 'moderation.auto_block_risk', 'label' => 'Skor auto-block', 'type' => 'number', 'hint' => 'Risk ≥ ini → pesan diblokir.'],
                ['key' => 'moderation.auto_flag_risk', 'label' => 'Skor auto-flag', 'type' => 'number', 'hint' => 'Risk ≥ ini → antrean moderasi + AI review.'],
                ['key' => 'moderation.warn_risk', 'label' => 'Skor warning', 'type' => 'number', 'hint' => 'Risk ≥ ini → peringatan.'],
                ['key' => 'moderation.ai_review_enabled', 'label' => 'AI review aktif', 'type' => 'checkbox', 'hint' => 'Second-opinion AI untuk pesan berisiko.'],
            ],
        ];
        $values = [];
        foreach ($defs as $fields) {
            foreach ($fields as $f) {
                [$group, $key] = explode('.', $f['key'], 2) + [null, null];
                $values[$f['key']] = Setting::get($key, null, $group);
            }
        }

        return $request->wantsJson()
            ? response()->json($values)
            : view('admin.chat.settings', ['defs' => $defs, 'values' => $values]);
    }
}
