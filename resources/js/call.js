/**
 * Jodohku WebRTC voice/video media layer.
 *
 * Signaling rides the existing Reverb private channel
 * `conversations.{id}` via client whispers (no new transport):
 *   call.signal {callId, kind: offer|answer|ice, payload}
 * Call lifecycle + billing stay in CallService (server truth).
 * Never charge here — end() is server-side and idempotent.
 */
window.JodohkuCall = (function () {
    const S = {
        pc: null,
        stream: null,
        conversationId: null,
        callId: null,
        type: 'voice',
        startedAt: 0,
        timer: null,
        state: 'idle', // idle|calling|ringing|ongoing|reconnecting|ended
    };

    function emit(state, detail = {}) {
        S.state = state;
        window.dispatchEvent(new CustomEvent('call:state', { detail: { state, ...detail } }));
    }

    async function iceServers() {
        try {
            const r = await fetch('/api/v1/calls/ice', { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (Array.isArray(j.iceServers) && j.iceServers.length) return j.iceServers;
        } catch (_) {}
        return [{ urls: 'stun:stun.l.google.com:19302' }];
    }

    function channel() {
        if (!window.Echo || !S.conversationId) return null;
        return window.Echo.private(`conversations.${S.conversationId}`);
    }

    function whisper(kind, payload) {
        const ch = channel();
        if (!ch) return;
        try {
            ch.whisper('call.signal', { callId: S.callId, kind, payload });
        } catch (_) {}
    }

    async function ensurePeer() {
        if (S.pc) return S.pc;
        const pc = new RTCPeerConnection({ iceServers: await iceServers() });
        pc.onicecandidate = (e) => {
            if (e.candidate) whisper('ice', e.candidate.toJSON());
        };
        pc.ontrack = (e) => {
            window.dispatchEvent(new CustomEvent('call:remote-stream', { detail: { stream: e.streams[0] } }));
        };
        pc.onconnectionstatechange = () => {
            const st = pc.connectionState;
            if (st === 'disconnected' || st === 'failed') emit('reconnecting');
            else if (st === 'connected' && S.state === 'reconnecting') emit('ongoing');
        };
        if (S.stream) {
            S.stream.getTracks().forEach((t) => pc.addTrack(t, S.stream));
        }
        S.pc = pc;
        return pc;
    }

    async function media(wantVideo) {
        if (S.stream) return S.stream;
        S.stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: wantVideo ? { width: 640 } : false });
        window.dispatchEvent(new CustomEvent('call:local-stream', { detail: { stream: S.stream } }));
        return S.stream;
    }

    function listen() {
        const ch = channel();
        if (!ch || ch._jkCallBound) return;
        ch._jkCallBound = true;
        ch.listenForWhisper('call.signal', async (msg) => {            if (!msg || msg.callId !== S.callId) return;
            try {
                const pc = await ensurePeer();
                if (msg.kind === 'offer') {
                    await pc.setRemoteDescription(new RTCSessionDescription(msg.payload));
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    whisper('answer', pc.localDescription.toJSON());
                    emit('ongoing');
                } else if (msg.kind === 'answer') {
                    await pc.setRemoteDescription(new RTCSessionDescription(msg.payload));
                    emit('ongoing');
                } else if (msg.kind === 'ice' && msg.payload) {
                    await pc.addIceCandidate(new RTCIceCandidate(msg.payload));
                }
              } catch (_) {}
          });
          // Server lifecycle (CallStatusChanged broadcasts call.accepted/
          // rejected/cancelled/ended): teardown media + refresh Livewire.
          ['.call.rejected', '.call.cancelled', '.call.ended'].forEach((ev) => {
              try {
                  ch.listen(ev, (msg) => {
                      if (msg && msg.id === S.callId) {
                          hangup();
                          if (window.Livewire) window.Livewire.dispatch('refresh-messages');
                      }
                  });
              } catch (_) {}
          });
      }

    async function start(conversationId, callId, type, isCaller) {
        S.conversationId = conversationId;
        S.callId = callId;
        S.type = type;
        S.startedAt = Date.now();
        listen();
        await media(type === 'video');
        const pc = await ensurePeer();
        if (isCaller) {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            whisper('offer', pc.localDescription.toJSON());
            emit('calling');
        } else {
            emit('ringing');
        }
        clearInterval(S.timer);
        S.timer = setInterval(() => {
            const s = Math.floor((Date.now() - S.startedAt) / 1000);
            window.dispatchEvent(new CustomEvent('call:tick', {
                detail: { elapsed: `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}` },
            }));
        }, 1000);
    }

    function toggleMute() {
        if (!S.stream) return false;
        const t = S.stream.getAudioTracks()[0];
        if (!t) return false;
        t.enabled = !t.enabled;
        return !t.enabled;
    }

    function toggleCamera() {
        if (!S.stream) return false;
        const t = S.stream.getVideoTracks()[0];
        if (!t) return false;
        t.enabled = !t.enabled;
        return !t.enabled;
    }

    async function switchCamera() {
        if (!S.stream) return;
        const vt = S.stream.getVideoTracks()[0];
        if (!vt) return;
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cams = devices.filter((d) => d.kind === 'videoinput');
            if (cams.length < 2) return;
            const current = vt.getSettings().deviceId;
            const next = cams.find((d) => d.deviceId !== current) || cams[0];
            vt.stop();
            const ns = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: next.deviceId } } });
            const nt = ns.getVideoTracks()[0];
            const sender = S.pc?.getSenders().find((s) => s.track && s.track.kind === 'video');
            if (sender) await sender.replaceTrack(nt);
            S.stream.removeTrack(vt);
            S.stream.addTrack(nt);
            window.dispatchEvent(new CustomEvent('call:local-stream', { detail: { stream: S.stream } }));
        } catch (_) {}
    }

    function hangup() {
        clearInterval(S.timer);
        try { S.pc?.close(); } catch (_) {}
        try { S.stream?.getTracks().forEach((t) => t.stop()); } catch (_) {}
        S.pc = null;
        S.stream = null;
        emit('ended');
    }

    // Server-driven teardown (rejected/missed/ended elsewhere).
    if (window.Echo) {
        window.addEventListener('echo:connected', () => {});
    }
    window.addEventListener('call:remote-hangup', hangup);

    return { start, hangup, toggleMute, toggleCamera, switchCamera, state: () => S.state };
})();
