/**
 * AudioRouteDetector
 * Monitors system audio output devices (via navigator.mediaDevices.ondevicechange)
 * to detect when Bluetooth speakers, neckbands, car audio, etc. connect or
 * disconnect.  Dispatches callbacks so SmartRadioPlayer can pause on disconnect
 * and resume on reconnect.
 *
 * Works on: Chrome, Edge, Firefox (desktop & Android).
 * On iOS Safari, enumerateDevices() output listing is limited, so the detector
 * degrades gracefully — SmartRadioPlayer falls back to MediaSession play/pause
 * actions from the car/BT device for reconnect handling.
 */
class AudioRouteDetector {
  constructor(options = {}) {
    this.onRouteConnected    = options.onRouteConnected    || (() => {});
    this.onRouteDisconnected = options.onRouteDisconnected || (() => {});

    this._previousOutputDevices = [];
    this._monitoring = false;
    this._initialSnapshotDone = false;
    this._boundOnDeviceChange = this._onDeviceChange.bind(this);
    this._debounceTimer = null;
  }

  async start() {
    if (!navigator.mediaDevices ||
        typeof navigator.mediaDevices.enumerateDevices !== 'function') {
      console.warn('[AudioRouteDetector] MediaDevices API not available — device detection disabled.');
      return false;
    }

    // Take initial snapshot of audio output devices
    await this._snapshotDevices();
    this._initialSnapshotDone = true;

    navigator.mediaDevices.addEventListener('devicechange', this._boundOnDeviceChange);
    this._monitoring = true;

    console.info('[AudioRouteDetector] Monitoring audio output devices. Initial count:',
      this._previousOutputDevices.length,
      '—', this._previousOutputDevices.map(d => d.label || d.deviceId).join(', ') || '(no labels)');
    return true;
  }

  stop() {
    if (this._monitoring) {
      navigator.mediaDevices.removeEventListener('devicechange', this._boundOnDeviceChange);
      this._monitoring = false;
    }
    clearTimeout(this._debounceTimer);
  }

  // ──────────── INTERNAL ────────────

  async _snapshotDevices() {
    try {
      const all = await navigator.mediaDevices.enumerateDevices();
      // Only track audio output devices, exclude virtual "default" / "communications"
      this._previousOutputDevices = all.filter(d =>
        d.kind === 'audiooutput' &&
        d.deviceId !== 'default' &&
        d.deviceId !== 'communications'
      );
    } catch (e) {
      console.warn('[AudioRouteDetector] enumerateDevices() failed:', e);
      this._previousOutputDevices = [];
    }
  }

  /**
   * Determines whether a device is "external" (BT speaker, USB headset, etc.)
   * vs built-in speakers. If the label is empty (no microphone permission),
   * we conservatively treat it as potentially external.
   */
  _isExternalDevice(device) {
    const label = (device.label || '').toLowerCase();
    // Built-in devices that should NOT trigger route-change behaviour
    if (/built.?in|internal|system default/i.test(label)) return false;
    // If label is empty (no mic permission granted), treat as potentially external
    return true;
  }

  _onDeviceChange() {
    // Debounce — OS may fire multiple devicechange events in quick succession
    // when a single BT device connects (e.g. separate A2DP + HFP profiles)
    clearTimeout(this._debounceTimer);
    this._debounceTimer = setTimeout(() => this._processDeviceChange(), 400);
  }

  async _processDeviceChange() {
    if (!this._initialSnapshotDone) return;

    const prevDevices = this._previousOutputDevices;

    // Re-snapshot the current device list
    await this._snapshotDevices();
    const currDevices = this._previousOutputDevices;

    const prevIds = new Set(prevDevices.map(d => d.deviceId));
    const currIds = new Set(currDevices.map(d => d.deviceId));

    // Find removed external devices
    const removed = prevDevices.filter(d => !currIds.has(d.deviceId) && this._isExternalDevice(d));
    // Find added external devices
    const added   = currDevices.filter(d => !prevIds.has(d.deviceId) && this._isExternalDevice(d));

    if (removed.length > 0 && added.length === 0) {
      // External audio device removed (BT disconnect, USB unplug, etc.)
      const names = removed.map(d => d.label || d.deviceId);
      console.info('[AudioRouteDetector] Audio output device REMOVED:', names.join(', '));
      this.onRouteDisconnected(removed);
    } else if (added.length > 0 && removed.length === 0) {
      // External audio device added (BT connect, USB plug, etc.)
      const names = added.map(d => d.label || d.deviceId);
      console.info('[AudioRouteDetector] Audio output device ADDED:', names.join(', '));
      this.onRouteConnected(added);
    } else if (added.length > 0 && removed.length > 0) {
      // Device swap — treat as reconnect (new audio route is available)
      console.info('[AudioRouteDetector] Audio output device SWAP detected');
      this.onRouteConnected(added);
    }
    // If nothing external changed, do nothing.
  }
}


// ═══════════════════════════════════════════════════════════════════════════════

/**
 * SmartRadioPlayer
 * Handles Bluetooth reconnect (earbuds, neckbands, car BT, CarPlay),
 * live-stream buffer reset, phone-call interruption, and background playback.
 *
 * Key design decisions:
 *  - Every reconnect fetches a cache-busted URL so the browser always jumps
 *    to the live edge rather than resuming a stale buffer.
 *  - userPaused is the single source of truth for whether the USER asked to
 *    stop. Only _scheduleReconnect() checks it.
 *  - AudioRouteDetector monitors OS-level audio device changes to detect
 *    Bluetooth connect/disconnect and pause/resume accordingly.
 */

class SmartRadioPlayer {
  /**
   * @param {Object}   options
   * @param {string}   options.streamUrl        - Live stream URL (required)
   * @param {string}  [options.audioElementId]  - ID of an existing <audio> tag (optional)
   * @param {number}  [options.reconnectDelay]  - ms to wait before auto-reconnect (default 800)
   * @param {Function}[options.onStateChange]   - callback(state: string)
   * @param {boolean} [options.autoReconnectOnSystemPause]
   *   - When false, an unexpected pause waits for a MediaSession/user play action.
   * @param {boolean} [options.resumeOnPageReturnAfterSystemPause]
   *   - When true, resume after a recent system pause when the page becomes visible.
   */
  constructor(options = {}) {
    if (!options.streamUrl) throw new Error('SmartRadioPlayer: streamUrl is required');

    this.streamUrl      = options.streamUrl;
    this.audioElId      = options.audioElementId || '_smart_radio_audio';
    this.reconnectDelay = options.reconnectDelay ?? 800;
    this.onStateChange  = options.onStateChange  || (() => {});
    this.autoReconnectOnSystemPause =
      options.autoReconnectOnSystemPause ?? !this._isIOSSafari();
    this.resumeOnPageReturnAfterSystemPause =
      options.resumeOnPageReturnAfterSystemPause ?? true;

    this.audio                 = null;
    this.isPlaying             = false;  // user's *intent* — true = wants audio
    this.userPaused            = false;  // true only when the USER explicitly paused
    this.isMuted               = false;
    this._currentState         = null;   // last state emitted
    this.reconnectTimer        = null;
    this._liveEdgeTimer        = null;
    this._boundHandlers        = {};
    this._wasPlayingBeforeHide = false;
    this._awaitingExplicitRouteResume = false;
    this._awaitingRouteReconnect     = false; // true when waiting for a BT/audio device to reappear
    this._suppressNextPause = false;
    this._routeDetector        = null;

    // Heuristics and Timestamps
    this._lastInvoluntaryPauseAt = 0;
    this._lastUserPauseAt = 0; // Tracks when manual pause happened
  }

  // ─────────────────────────────────────────
  // PUBLIC API
  // ─────────────────────────────────────────

  init() {
    this._setupAudioElement();
    this._attachAudioEvents();
    this._attachVisibilityEvents();
    this._attachNetworkEvents();
    this._attachAudioFocusEvents();      // phone-call / interruption end
    this._attachNativeRouteBridge();
    this._startAudioRouteDetection();    // BT/speaker device-change monitoring
    console.info('[SmartRadioPlayer] Initialised. Stream:', this.streamUrl);
    if (!this.autoReconnectOnSystemPause) {
      console.info('[SmartRadioPlayer] autoReconnectOnSystemPause=false; relying on device detection and MediaSession play for reconnect.');
    }
  }

  /**
   * Resumes playback.
   * Always restarts from the live edge to guarantee the listener hears the
   * current broadcast, not stale buffered audio from before the pause.
   * Uses the silent-handoff technique in _startStream() to preserve the
   * MediaSession notification on Android/car displays.
   */
  resume() {
    this.isPlaying  = true;
    this.userPaused = false;
    this._awaitingExplicitRouteResume = false;
    this._awaitingRouteReconnect      = false;

    console.info('[SmartRadioPlayer] resume() called – restarting from live edge.');

    this._startStream();
  }

  play() {
    // play() is now an alias for resume() — use _startStream() when you need hard reconnect.
    this.resume();
  }

  pause() {
    this.isPlaying  = false;
    this.userPaused = true;
    this._awaitingExplicitRouteResume = false;
    this._awaitingRouteReconnect      = false;
    this._lastUserPauseAt = Date.now();
    this._safePause();
  }

  /** Called by the outer blade script to keep userPaused in sync */
  setUserPaused(val) {
    this.userPaused = !!val;
    if (val) {
      this.isPlaying = false;
      this._lastUserPauseAt = Date.now(); // TRACK PAUSE TIME
      this._awaitingExplicitRouteResume = false;
      this._awaitingRouteReconnect      = false;
      this._clearTimers(); // cancel any in-flight reconnect
    }
  }

  /**
   * Called when an audio output device (BT speaker, car, neckband) disconnects.
   * Pauses playback and waits for the device to reconnect — does NOT auto-resume
   * on a timer like _scheduleReconnect does.
   */
  handleRouteDisconnected() {
    if (!this.audio || !this.isPlaying || this.userPaused) return;

    // Cancel any pending auto-reconnect timers — we must wait for the
    // device to come back, not reconnect blindly after 800ms.
    this._clearTimers();
    clearTimeout(this._liveEdgeTimer);

    this._awaitingExplicitRouteResume = true;
    this._awaitingRouteReconnect      = true;
    this._lastInvoluntaryPauseAt = Date.now();

    console.info('[SmartRadioPlayer] Audio route disconnected — pausing and waiting for device reconnect.');

    if (this.audio.paused) {
      this._setState('interrupted');
    } else {
      this._safePause();
    }
  }

  /**
   * Called when an audio output device (BT speaker, car, neckband) reconnects.
   * Resumes playback on the live edge if the user hadn't manually paused.
   */
  handleRouteConnected() {
    if (!this.isPlaying || this.userPaused || !this._awaitingExplicitRouteResume) return;

    console.info('[SmartRadioPlayer] Audio route reconnected — resuming on live edge.');
    this._awaitingExplicitRouteResume = false;
    this._awaitingRouteReconnect      = false;
    this._startStream();
  }

  jumpToLiveEdge(reason = 'live seek guard') {
    if (!this.audio || !this.isPlaying || this.userPaused) return;

    clearTimeout(this._liveEdgeTimer);
    this._liveEdgeTimer = setTimeout(() => {
      this._liveEdgeTimer = null;
      if (!this.audio || !this.isPlaying || this.userPaused) return;
      console.info(`[SmartRadioPlayer] Ignored seek (${reason}); returning to live edge.`);
      this._startStream();
    }, 150);
  }

  getCapabilities() {
    return {
      audioRouteDetection: !!(this._routeDetector && this._routeDetector._monitoring),
      nativeRouteBridge: true,
      mediaSession: 'mediaSession' in navigator,
      webBluetoothAvailable: 'bluetooth' in navigator,
      webBluetoothAudioRoutes: false,
      autoReconnectOnSystemPause: this.autoReconnectOnSystemPause,
      resumeOnPageReturnAfterSystemPause: this.resumeOnPageReturnAfterSystemPause,
    };
  }

  toggleMute() {
    this.isMuted = !this.isMuted;
    if (this.audio) this.audio.muted = this.isMuted;
  }

  destroy() {
    this._safePause();
    this._clearTimers();
    clearTimeout(this._liveEdgeTimer);
    this._detachAudioEvents();
    document.removeEventListener('visibilitychange', this._boundHandlers.visibilityChange);
    window.removeEventListener('online',  this._boundHandlers.online);
    window.removeEventListener('offline', this._boundHandlers.offline);
    window.removeEventListener('audiofocusgain', this._boundHandlers.audioFocusGain);
    window.removeEventListener('jammin:audio-route-change', this._boundHandlers.routeChange);
    if (this._routeDetector) this._routeDetector.stop();
    if (this.audio && !document.getElementById(this.audioElId)) {
      this.audio.remove();
    }
  }

  // ─────────────────────────────────────────
  // SETUP
  // ─────────────────────────────────────────

  _setupAudioElement() {
    let el = document.getElementById(this.audioElId);
    if (!el) {
      el = document.createElement('audio');
      el.id            = this.audioElId;
      el.preload       = 'none';
      el.style.display = 'none';
      document.body.appendChild(el);
    }
    // Do NOT set src here — we always set it with a cache-buster at play time.
    this.audio = el;
  }

  // ─────────────────────────────────────────
  // STREAM CONTROL
  // ─────────────────────────────────────────

  /**
   * Returns the stream URL with a cache-busting timestamp so every reconnect
   * hits the live edge instead of serving a cached/buffered segment.
   */
  _cacheBustUrl() {
    const base = this.streamUrl.split('?')[0];
    return base + '?_=' + Date.now();
  }

  _startStream() {
    this._clearTimers();
    const el = this.audio;
    if (!el) return;

    if (!el.paused) this._suppressNextPause = true;

    // ── MEDIA SESSION TEARDOWN PREVENTION ──
    // On Android/Chrome, changing the src of the actively focused audio element
    // destroys the OS background notification, causing a jarring flicker and UI reset.
    // To prevent this, we instantiate a secondary invisible audio element with a
    // tiny silent MP3 base64 string. We play it to seamlessly "steal" the MediaSession
    // focus. We then replace the src on our main element, load it, play it, and
    // once it successfully starts, we pause the silent element. The result is a
    // 100% seamless transition without tearing down the lock screen widget!
    
    if (!window._silentHandoffAudio) {
        window._silentHandoffAudio = document.createElement('audio');
        window._silentHandoffAudio.loop = true;
        // 1-second silent MP3 base64 string
        window._silentHandoffAudio.src = "data:audio/mpeg;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU5LjE2LjEwMAAAAAAAAAAAAAAA//OEAAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAAEAAABIADAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//MUZAAAAAGkAAAAAAAAAAAEluZm8AAAAPAAAABAAAASAAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//MUZAAAAAGkAAAAAAAAAAAEluZm8AAAAPAAAABAAAASAAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//MUZAAAAAGkAAAAAAAAAAAEluZm8AAAAPAAAABAAAASAAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD//MUZAAQAAHkAAAAAAAAAAA==";
        document.body.appendChild(window._silentHandoffAudio);
    }
    
    // Play silent audio to hold MediaSession focus
    const silentPromise = window._silentHandoffAudio.play();
    if (silentPromise !== undefined) {
        silentPromise.catch(() => {});
    }

    const freshUrl = this._cacheBustUrl();
    el.src = freshUrl;
    console.info('[SmartRadioPlayer] Loading live edge with silent handoff:', freshUrl);

    // Eagerly re-assert MediaSession metadata so the lock screen doesn't clear during fetch
    if ('mediaSession' in navigator && typeof window._commitMediaSession === 'function' && window._storedMediaMetadata) {
        window._commitMediaSession(window._storedMediaMetadata, true);
    }

    const p = el.play();
    if (p !== undefined) {
      p.then(() => {
         this._setState('playing');
         if (typeof window.reassertLiveMediaPosition === 'function') {
             window.reassertLiveMediaPosition('hard-resume');
         }
         // Stream has successfully started! We can safely pause the silent placeholder.
         window._silentHandoffAudio.pause();
      }).catch(err => {
         window._silentHandoffAudio.pause();
         if (err.name === 'NotAllowedError') {
           this._setState('blocked');
           console.warn('[SmartRadioPlayer] Autoplay blocked – waiting for user gesture.');
         } else if (err.name === 'AbortError') {
           console.debug('[SmartRadioPlayer] play() aborted by subsequent pause():', err);
         } else {
           this._handleError(err);
         }
       });
    }
  }

  _safePause() {
    clearTimeout(this.reconnectTimer);
    if (this.audio && !this.audio.paused) this.audio.pause();
    if (window._silentHandoffAudio && !window._silentHandoffAudio.paused) window._silentHandoffAudio.pause();
  }

  // ─────────────────────────────────────────
  // AUDIO ELEMENT EVENTS
  // ─────────────────────────────────────────

  _attachAudioEvents() {
    const ev = (name, fn) => {
      this._boundHandlers[name] = fn;
      this.audio.addEventListener(name, fn);
    };

    ev('playing', () => {
      this._clearTimers();
      const wasInterrupted = (this._currentState === 'interrupted');
      this._awaitingExplicitRouteResume = false;
      this._awaitingRouteReconnect      = false;

      // ── Stale Resume Guard (Live Edge Enforcement) ──────────────────────────
      // If the OS/hardware resumes the audio element automatically after a
      // significant interruption (> 2s), the buffer is likely stale (lagging).
      // We force a hard-restart to jump to the actual live edge.
      if (wasInterrupted && this._lastInvoluntaryPauseAt > 0) {
        const gap = Date.now() - this._lastInvoluntaryPauseAt;
        if (gap > 2000) {
          console.warn(`[SmartRadioPlayer] Audio resumed after ${Math.round(gap/1000)}s gap – forcing jump to live edge.`);
          this._startStream();
          return; // Abort this 'playing' cycle; _startStream will start a new one.
        }
      }

      this._setState('playing');

      // If we're recovering from an involuntary (system) pause (phone call /
      // BT disconnect), fire the interruptionEnd callback so the outer script
      // can sync UI without accidentally treating it as a new user-play event.
      if (wasInterrupted) {
        try { this.onStateChange('interruptionEnd'); } catch (e) {}
      }
    });

    ev('pause', () => {
      if (this._suppressNextPause) {
        this._suppressNextPause = false;
        return;
      }

      if (this.isPlaying && !this.userPaused) {
        // Involuntary pause (BT disconnect, phone call, OS audio focus loss)
        const shouldWaitForExplicitResume =
          this._awaitingExplicitRouteResume || !this.autoReconnectOnSystemPause;

        this._lastInvoluntaryPauseAt = Date.now();
        this._awaitingExplicitRouteResume = shouldWaitForExplicitResume;
        this._setState('interrupted');

        if (shouldWaitForExplicitResume) {
          console.info('[SmartRadioPlayer] System pause — waiting for device reconnect or explicit play.');
        } else {
          this._scheduleReconnect();
        }
      } else {
        this._setState('paused');
      }
    });

    // ── Stall / Buffer Detection ────────────────────────────────────────────
    ev('stalled', () => {
      if (this.isPlaying && !this.userPaused) {
        console.warn('[SmartRadioPlayer] Audio stalled – scheduling recovery.');
        this._scheduleReconnect(2500);
      }
    });

    ev('waiting', () => {
      if (this.isPlaying && !this.userPaused) {
        console.info('[SmartRadioPlayer] Audio waiting for data…');
        // If it waits for too long, it might be a frozen buffer
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = setTimeout(() => {
           if (this.isPlaying && !this.userPaused && this.audio?.paused === false) {
             console.warn('[SmartRadioPlayer] Audio still waiting after 4s – forcing live edge jump.');
             this._startStream();
           }
        }, 4000);
      }
    });

    ev('seeking', () => {
      if (this._isInternalSeek) {
          this._isInternalSeek = false;
          return;
      }
      if (this.isPlaying && !this.userPaused) {
        this.jumpToLiveEdge('htmlmedia:seeking');
      }
    });

    ev('ratechange', () => {
      if (this.audio && this.audio.playbackRate !== 1) {
        this.audio.playbackRate = 1;
      }
    });

    ev('error', e => this._handleError(e));
  }

  _detachAudioEvents() {
    for (const [name, fn] of Object.entries(this._boundHandlers)) {
      if (this.audio && typeof fn === 'function') {
        this.audio.removeEventListener(name, fn);
      }
    }
  }


  // ─────────────────────────────────────────
  // RECONNECT SCHEDULER
  // ─────────────────────────────────────────

  _scheduleReconnect(delay = this.reconnectDelay) {
    clearTimeout(this.reconnectTimer);
    // CRITICAL: Only reconnect if user *wants* audio (isPlaying) AND did NOT
    // manually pause (userPaused).  Without the userPaused guard, a manual
    // pause during a BT-disconnect window would still trigger a reconnect.
    if (!this.isPlaying || this.userPaused) return;
    if (this._awaitingExplicitRouteResume) return;

    this.reconnectTimer = setTimeout(() => {
      if (this.isPlaying && !this.userPaused && !this._awaitingExplicitRouteResume) {
        console.info('[SmartRadioPlayer] Auto-reconnecting after interruption…');
        this._startStream();
      }
    }, delay);
  }

  // ─────────────────────────────────────────
  // PAGE VISIBILITY — screen lock / tab switch
  // ─────────────────────────────────────────

  _attachVisibilityEvents() {
    this._boundHandlers.visibilityChange = () => {
      if (document.hidden) {
        this._wasPlayingBeforeHide = this.isPlaying;
        // Do NOT pause – let the stream continue in the background.
        // The OS will pause it on screen lock; we catch that via the 'pause' event.
      } else {
        // Page visible again (screen unlocked, tab refocused, etc.)
        // CRITICAL FIX: Only restart the stream if it actually paused or failed
        // while in the background. If it's still playing, check for progress.
        const isActuallyPaused = this.audio ? this.audio.paused : true;

        if ((this._wasPlayingBeforeHide || this.isPlaying) && !this.userPaused) {
          if (this._awaitingExplicitRouteResume) {

            // ── Route-reconnect guard ─────────────────────────────────────────
            // If we're specifically waiting for a BT/audio device to reconnect
            // (set by handleRouteDisconnected), do NOT auto-resume just because
            // the page became visible.  The user unlocking their phone should NOT
            // blast audio on the phone speaker when the BT speaker is still off.
            if (this._awaitingRouteReconnect) {
              console.info('[SmartRadioPlayer] Page visible but waiting for audio device reconnect — not auto-resuming.');
              return;
            }

            // Non-route system pause (phone call ended, etc.) — may auto-resume
            const recentSystemPause =
              this._lastInvoluntaryPauseAt > 0 &&
              Date.now() - this._lastInvoluntaryPauseAt < 10 * 60 * 1000;

            if (this.resumeOnPageReturnAfterSystemPause && recentSystemPause) {
              console.info('[SmartRadioPlayer] Page visible after system pause; resuming live edge.');
              this._awaitingExplicitRouteResume = false;
              this.isPlaying = true;
              this._startStream();
            } else {
              console.info('[SmartRadioPlayer] Page visible after system pause; waiting for explicit media play.');
            }
            return;
          }

          if (isActuallyPaused) {
            console.info('[SmartRadioPlayer] Page visible and audio stopped – resuming on live edge.');
            this.isPlaying = true;
            this._startStream();
          } else if (this.audio) {
            console.info('[SmartRadioPlayer] Page visible and audio still playing; keeping current stream.');
          }
        }
      }
    };
    document.addEventListener('visibilitychange', this._boundHandlers.visibilityChange);
  }

  // ─────────────────────────────────────────
  // AUDIO ROUTE DETECTION (Bluetooth / USB / External Speaker)
  //
  // Uses navigator.mediaDevices.ondevicechange to detect when audio output
  // devices are added or removed.  This is the most reliable cross-browser
  // method for detecting BT connect/disconnect on desktop and Android.
  // On iOS Safari, this degrades gracefully — MediaSession play/pause
  // actions from the car head unit handle reconnect instead.
  // ─────────────────────────────────────────

  _startAudioRouteDetection() {
    this._routeDetector = new AudioRouteDetector({
      onRouteConnected: (devices) => {
        const names = (devices || []).map(d => d.label || 'device').join(', ');
        console.info(`[SmartRadioPlayer] Device detection: audio route connected (${names}).`);
        this.handleRouteConnected();
      },
      onRouteDisconnected: (devices) => {
        const names = (devices || []).map(d => d.label || 'device').join(', ');
        console.info(`[SmartRadioPlayer] Device detection: audio route disconnected (${names}).`);
        this.handleRouteDisconnected();
      },
    });

    this._routeDetector.start().then(started => {
      if (started) {
        console.info('[SmartRadioPlayer] 🔊 Audio route detection ACTIVE — monitoring device changes for BT/speaker connect/disconnect.');
      } else {
        console.info('[SmartRadioPlayer] Audio route detection not available — using fallback methods (MediaSession, visibility).');
      }
    });
  }

  // ─────────────────────────────────────────
  // AUDIO FOCUS / PHONE CALL DETECTION
  //
  // Many car head-units and Android BT stacks use the MediaSession API or
  // simply un-pause the <audio> element when a phone call ends.
  // We watch the 'playing' event (already handled above) and also listen for
  // the 'audiofocusgain' custom event that some Android WebViews dispatch.
  // ─────────────────────────────────────────

  _attachAudioFocusEvents() {
    // 'audiofocusgain' is dispatched by some Android WebView implementations.
    this._boundHandlers.audioFocusGain = () => {
      if (this.isPlaying && !this.userPaused) {
        console.info('[SmartRadioPlayer] Audio focus regained – reconnecting.');
        this._awaitingExplicitRouteResume = false;
        this._awaitingRouteReconnect      = false;
        this._startStream();
      }
    };
    window.addEventListener('audiofocusgain', this._boundHandlers.audioFocusGain);

    // Heuristic: if the page becomes visible very shortly after an involuntary
    // pause (< 60 s), this is almost certainly a phone-call-end or screen-unlock.
    // The visibilitychange handler already covers this — this just reinforces it.
  }

  _attachNativeRouteBridge() {
    this._boundHandlers.routeChange = event => {
      const detail = event.detail || {};
      const connected =
        detail.connected ?? detail.bluetoothConnected ?? detail.available ?? null;

      if (connected === true) {
        this.handleRouteConnected();
      } else if (connected === false) {
        this.handleRouteDisconnected();
      }
    };

    window.addEventListener('jammin:audio-route-change', this._boundHandlers.routeChange);

    const bridge = window.JamminAudioRoute || {};
    bridge.bluetoothConnected = () => this.handleRouteConnected();
    bridge.bluetoothDisconnected = () => this.handleRouteDisconnected();
    bridge.getCapabilities = () => this.getCapabilities();
    window.JamminAudioRoute = bridge;
  }

  // ─────────────────────────────────────────
  // NETWORK ONLINE / OFFLINE
  // ─────────────────────────────────────────

  _attachNetworkEvents() {
    this._boundHandlers.online = () => {
      console.info('[SmartRadioPlayer] Network restored – reconnecting.');
      if (this.isPlaying && !this.userPaused && !this._awaitingExplicitRouteResume) this._startStream();
    };
    this._boundHandlers.offline = () => {
      this._setState('offline');
    };
    window.addEventListener('online',  this._boundHandlers.online);
    window.addEventListener('offline', this._boundHandlers.offline);
  }

  // ─────────────────────────────────────────
  // UTILITIES
  // ─────────────────────────────────────────

  _handleError(err) {
    console.error('[SmartRadioPlayer] Error:', err);
    this._setState('error');
    if (this.isPlaying && !this.userPaused) this._scheduleReconnect(2000);
  }

  _setState(state) {
    this._currentState = state;
    this.onStateChange(state);
    if ('mediaSession' in navigator) {
      if (state === 'playing')                            navigator.mediaSession.playbackState = 'playing';
      if (state === 'paused' || state === 'interrupted') navigator.mediaSession.playbackState = 'paused';
    }
  }

  _clearTimers() {
    clearTimeout(this.reconnectTimer);
  }

  _isIOSSafari() {
    const ua = navigator.userAgent || '';
    const platform = navigator.platform || '';
    const isIOS = /iPad|iPhone|iPod/.test(ua) ||
      (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isWebKit = /WebKit/i.test(ua);
    const isOtherIOSBrowser = /CriOS|FxiOS|EdgiOS|OPiOS/i.test(ua);

    return isIOS && isWebKit && !isOtherIOSBrowser;
  }
}

window.AudioRouteDetector = AudioRouteDetector;
window.SmartRadioPlayer = SmartRadioPlayer;
