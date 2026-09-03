import { t as shallowEqual } from "./shallow-equal-C7S8rj2f.js";
import { t as listen } from "./listen-CdeggIF8.js";
import { r as observeRenderedSize, t as observeElementSize } from "./observe-elements-CRk-iY4n.js";
import { n as isCaptionOrSubtitleTrack } from "./text-track-DMA7pa8W.js";
import { t as MediaStreamTypes } from "./types-IVY0D06G.js";
import { C as endOfStream, Ct as effect, D as parseMultivariantPlaylist, E as getResolvedSelectedTrackDuration, G as collectErrors, J as trackCurrentTime, O as canPlayTrack, S as Task, T as calculatePresentationDuration, X as loadTextTrackSegments, Y as loadAudioSegments, Z as loadVideoSegments, _ as setupVideoBufferActors, _t as update, a as LOW_LATENCY_UNSUPPORTED_MESSAGE, at as deriveStreamType, b as createMachineActor, c as reportUnsupportedTrackConditions, ct as isResolvedTrack, d as resolveVideoTrack, dt as makeShareSignals, et as findTrackById, f as resolvePresentation, ft as createComposition, g as setupAudioBufferActors, gt as untrack, h as setupMediaSource, ht as peek, i as DVR_EXPERIMENTAL_MESSAGE, it as getSegmentsToLoad, k as attachMediaSourceAsSourceElement, l as resolveAudioTrack, lt as createMachineReactor, m as updateMediaSourceDuration, mt as computed, n as hasUnsupportedFeatureCause, nt as getTracksByType, ot as getMediaPlaylistMetadata, pt as defineBehavior, r as withAlternativeMediaSuggestion, rt as DEFAULT_FORWARD_BUFFER_CONFIG, s as UNSUPPORTED_PLAYBACK_FEATURE_MESSAGE, st as isResolvedPresentation, t as firstFatal, u as resolveTextTrack, ut as createMachineCore, vt as SVTA_NO_SUPPORTED_AUDIO_TRACK, x as SerialRunner, xt as SVTA_UNSUPPORTED_PLAYBACK_FEATURE, yt as SVTA_NO_SUPPORTED_VIDEO_TRACK } from "./error-surface-C2oiXXnW.js";
import { t as MediaTracksMixin } from "./mixin-C3Bwlf82.js";
import { t as HTMLVideoElementHost } from "./video-host-C5dec7Hd.js";
import { a as setupFailoverMonitor, c as setupAirPlay, d as switchTextTrack, f as switchVideoTrack, g as trackLoadTriggers, h as gateFirstParseOnAnchor, i as syncPreload, l as deriveCdnPriority, m as establishStartMediaTime, n as relocationPipelinesFor, o as recoverEndStall, p as deriveSharedMinStartMediaTime, r as DEFAULT_TEXT_MESSAGE_PIPELINES, s as applyStartPosition, t as relocatingTextPipelines, u as switchAudioTrack } from "./relocation-pipelines-BbjRRa6I.js";
import { t as scaleResolution } from "./resolution-8N8Q2JN6.js";

//#region ../utils/dist/time/sleep.js
/**
* Resolve after `ms` milliseconds. Pass a `signal` to make it cancellable: the timer is cleared and the promise rejects
* with the signal's reason as soon as the signal aborts (including if it's already aborted).
*/
function sleep(ms, signal) {
	return new Promise((resolve, reject) => {
		if (signal?.aborted) {
			reject(signal.reason);
			return;
		}
		const onAbort = () => {
			clearTimeout(timer);
			reject(signal?.reason);
		};
		const timer = setTimeout(() => {
			signal?.removeEventListener("abort", onAbort);
			resolve();
		}, ms);
		signal?.addEventListener("abort", onAbort, { once: true });
	});
}

//#endregion
//#region ../spf/dist/dev/media/hls/reload-policy.js
/** Reload cadence when a playlist carries no usable target duration. */
const FALLBACK_TARGET_DURATION = 6;
/**
* Default `HOLD-BACK` as a multiple of the target duration when the playlist declares none — the HLS spec default (RFC
* 8216bis `EXT-X-SERVER-CONTROL`).
*/
const HOLD_BACK_TARGET_MULTIPLIER = 3;
/** Identity of a reload snapshot — window position + length. Changes when the window slid or grew. */
function snapshotSignature(track) {
	return `${getMediaPlaylistMetadata(track)?.mediaSequence ?? 0}:${track.segments.length}`;
}
function targetDurationOf(track) {
	return getMediaPlaylistMetadata(track)?.targetDuration || FALLBACK_TARGET_DURATION;
}
/**
* Live media-playlist reload cadence, per RFC 8216bis §6.3.4 — a {@link RecurrencePolicy} for a `RecurringRunner`
* re-resolving the selected track. Structurally matches `RecurrencePolicy<ResolvedTrack>` without importing it (media
* stays core-free): `current` is the freshly resolved track, `previous` the prior resolved snapshot.
*
* - Complete playlist (VoD, or live that hit `#EXT-X-ENDLIST`) → `null`: stop. Keys off `Track.duration` (finite once
*   complete), the single completeness source of truth.
* - Unchanged window (same media sequence + segment count as `previous`) → poll at half the target duration; a
*   moved/grown window (or the first reload) → full target duration.
*
* A failed reload doesn't reach here — the rejection propagates through the `RecurringRunner`; transient-failure
* recovery belongs at the fetch layer.
*
* Returned delays are milliseconds.
*/
function mediaPlaylistReloadDelay(current, previous) {
	if (Number.isFinite(current.duration)) return null;
	const target = targetDurationOf(current);
	return (!previous || snapshotSignature(current) !== snapshotSignature(previous) ? target : target / 2) * 1e3;
}
/**
* Target live latency (seconds) for a resolved track — how far behind the live edge the playhead should sit. Prefers
* the server's declared `EXT-X-SERVER-CONTROL` `HOLD-BACK`, falling back to {@link HOLD_BACK_TARGET_MULTIPLIER}× the
* target duration when absent (the spec default). This is the HLS side of the format-neutral `resolveLiveLatency` seam
* consumed by `seek-to-live-edge`; a DASH engine supplies its own (`suggestedPresentationDelay`).
*
* Only `HOLD-BACK` — never `PART-HOLD-BACK`, which assumes partial-segment playback. See
* {@link MediaPlaylistMetadata.holdBack}.
*/
function liveLatencyFor(track) {
	return getMediaPlaylistMetadata(track)?.holdBack ?? HOLD_BACK_TARGET_MULTIPLIER * targetDurationOf(track);
}
/**
* Resolve the target live latency for a presentation's timeline-bearing track — the HLS engine injects this as
* `seekToLiveEdge`'s format-neutral `resolveLiveLatency` seam. `0` when there is no resolved track to read (the
* behavior then seeks straight to the edge).
*/
function resolveLiveLatency(presentation, trackId) {
	if (!isResolvedPresentation(presentation) || !trackId) return 0;
	const track = findTrackById(presentation, trackId);
	return track && isResolvedTrack(track) ? liveLatencyFor(track) : 0;
}

//#endregion
//#region ../spf/dist/dev/media/live-window.js
/**
* Derive the live window of the track with the given id — the single source of truth for "where is live," consumed (via
* `liveWindowFromState`) by the seek-to-live-edge and live-seekable-range behaviors so neither re-derives (or
* re-presumes) the window shape. Type-agnostic: the caller decides which track bears the timeline (video when present,
* else audio).
*
* The window is a **live read** over the current `segments` array — never stored on the track. The anchor triple is
* frozen per source; per-segment `startTime` values are re-derived against it on every reload, so the edge here slides
* while the origin stays fixed (see `internal/design/spf/live-presentation-timeline-model.md`).
*
* Returns `null` when there is no live edge to track: an unresolved presentation or track, a track with no segments, or
* a **complete** playlist (VoD, or live that has ended — a finite `Track.duration`).
*/
function liveWindowFor(presentation, trackId) {
	if (!isResolvedPresentation(presentation) || !trackId) return null;
	const track = findTrackById(presentation, trackId);
	if (!track || !isResolvedTrack(track) || track.segments.length === 0) return null;
	if (Number.isFinite(track.duration)) return null;
	const { segments } = track;
	const last = segments[segments.length - 1];
	return {
		start: segments[0].startTime,
		end: last.startTime + last.duration
	};
}

//#endregion
//#region ../spf/dist/dev/playback/primitives/live-window.js
/**
* The id of the timeline-bearing track: the selected video track when present, else the selected audio track. The
* single pick both the window derivation and the live-latency resolution (`seek-to-live-edge`) share, so they can't
* drift.
*/
function liveTrackId(state) {
	return state.selectedVideoTrackId?.get() ?? state.selectedAudioTrackId?.get();
}
/**
* One type's window: the selected track's when it's resolved, else any resolved track of the type. The fallback keeps
* the window from blinking to `null` mid ABR / user switch — all renditions of a type are time-aligned and share the
* anchor, so a deselected track's window may trail live by up to one reload during the switch gap (acceptable; the
* selected track's fresh window resumes the moment it resolves). A null blink would flip `seekToLiveEdge` out of `live`
* and stall the seekable-range writer.
*/
function liveWindowForType(presentation, selectedId, type) {
	if (!presentation || selectedId === void 0) return null;
	const selected = liveWindowFor(presentation, selectedId);
	if (selected) return selected;
	for (const track of getTracksByType(presentation, type)) {
		const window = liveWindowFor(presentation, track.id);
		if (window) return window;
	}
	return null;
}
function liveWindowFromState(state) {
	const presentation = state.presentation.get();
	const video = liveWindowForType(presentation, state.selectedVideoTrackId?.get(), "video");
	const audio = liveWindowForType(presentation, state.selectedAudioTrackId?.get(), "audio");
	if (video && audio) {
		const start = Math.max(video.start, audio.start);
		const end = Math.min(video.end, audio.end);
		return start < end ? {
			start,
			end
		} : null;
	}
	return video ?? audio;
}
/**
* Resolve the live edge — the window bounds plus the target playhead position — from a behavior's setup arguments.
* Bundles the window geometry and the format-specific latency policy (`config.resolveLiveLatency`) so the consuming
* behavior never has to compose them; it just forwards its `{ state, config }`. `null` when there is no live edge (VOD
* / ended / unresolved).
*
* Reads signals lazily — call it inside a reactive context (an effect).
*/
function getLiveEdge({ state, config }) {
	const window = liveWindowFromState(state);
	if (!window) return null;
	const latency = config?.resolveLiveLatency?.(state.presentation.get(), liveTrackId(state)) ?? 0;
	return {
		...window,
		liveEdgeStart: Math.max(window.start, window.end - latency)
	};
}

//#endregion
//#region ../spf/dist/dev/media/dom/text/resolve-vtt-segment.js
let dummyVideo = null;
function ensureDummyVideo() {
	if (!dummyVideo) {
		dummyVideo = document.createElement("video");
		dummyVideo.muted = true;
		dummyVideo.preload = "none";
		dummyVideo.style.display = "none";
		dummyVideo.crossOrigin = "anonymous";
	}
	return dummyVideo;
}
function resolveVttSegment(url) {
	const video = ensureDummyVideo();
	const track = document.createElement("track");
	track.kind = "subtitles";
	return new Promise((resolve, reject) => {
		const onLoad = () => {
			const cues = [];
			const textTrack = track.track;
			if (textTrack.cues) for (let i = 0; i < textTrack.cues.length; i++) {
				const cue = textTrack.cues[i];
				if (cue) cues.push(cue);
			}
			cleanup();
			resolve(cues);
		};
		const onError = () => {
			cleanup();
			reject(/* @__PURE__ */ new Error(`Failed to load VTT segment: ${url}`));
		};
		const cleanup = () => {
			track.removeEventListener("load", onLoad);
			track.removeEventListener("error", onError);
			video.removeChild(track);
		};
		track.addEventListener("load", onLoad);
		track.addEventListener("error", onError);
		video.appendChild(track);
		track.track.mode = "hidden";
		track.src = url;
	});
}

//#endregion
//#region ../spf/dist/dev/core/actors/create-transition-actor.js
/**
* Creates a reducer-shaped actor from an initial context and a reducer function.
*
* The reducer receives the current context and a message and returns the next context. Returning the same reference (by
* identity) skips the signal update — so early-returning `context` unchanged is both the no-op and the optimization.
*
* Side effects (e.g. DOM mutations) may be performed inside the reducer. They run synchronously before the signal is
* updated.
*
* @example
*   const actor = createTransitionActor({ count: 0 }, (context, message: { type: 'increment' }) => ({
*     count: context.count + 1,
*   }));
*/
function createTransitionActor(initialContext, reducer) {
	const { snapshotSignal, getState, transition } = createMachineCore({
		value: "active",
		context: initialContext
	});
	const getContext = () => untrack(() => snapshotSignal.get().context);
	const setContext = (context) => update(snapshotSignal, { context });
	return {
		get snapshot() {
			return snapshotSignal;
		},
		send(message) {
			if (getState() === "destroyed") return;
			const context = getContext();
			const newContext = reducer(context, message);
			if (newContext !== context) setContext(newContext);
		},
		destroy() {
			if (getState() === "destroyed") return;
			transition("destroyed");
		}
	};
}

//#endregion
//#region ../spf/dist/dev/playback/actors/dom/text-tracks.js
function isDuplicateCue(cue, existing) {
	return existing.some((r) => r.startTime === cue.startTime && r.endTime === cue.endTime && r.text === cue.text);
}
/** TextTrack actor: wraps all text tracks on a media element, owns cue operations. */
function createTextTracksActor(mediaElement) {
	return createTransitionActor({
		loaded: {},
		segments: {}
	}, (context, message) => {
		if (message.type === "clear") return {
			loaded: {},
			segments: {}
		};
		const { meta, cues } = message;
		const { trackId, id: segmentId, startTime, duration } = meta;
		const textTrack = Array.from(mediaElement.textTracks).find((t) => t.id === trackId);
		if (!textTrack) return context;
		const existingCues = context.loaded[trackId] ?? [];
		const existingSegments = context.segments[trackId] ?? [];
		const prunedCues = cues.filter((cue) => !isDuplicateCue(cue, existingCues));
		const segmentAlreadyLoaded = existingSegments.some((s) => s.id === segmentId);
		if (prunedCues.length === 0 && segmentAlreadyLoaded) return context;
		for (const cue of prunedCues) textTrack.addCue(cue);
		return {
			...context,
			loaded: {
				...context.loaded,
				[trackId]: [...existingCues, ...prunedCues]
			},
			segments: segmentAlreadyLoaded ? context.segments : {
				...context.segments,
				[trackId]: [...existingSegments, {
					id: segmentId,
					startTime,
					duration
				}]
			}
		};
	});
}

//#endregion
//#region ../spf/dist/dev/playback/actors/text-track-segment-loader.js
/**
* Loads text-track segments for a track and delegates cue management to a TextTracksActor. Mirrors the v/a
* `SegmentLoaderActor` shape (FSM with `idle` / `loading` and `inFlight*` context for continue-vs-preempt), adapted to
* text:
*
* - No init segment, no flush ops (text cues don't need eviction — they're small and the playhead-relative window is
*   enforced by the runtime).
* - Single in-flight identity (`inFlightSegmentId`) — text has only the media-segment path, no init-segment path.
*
* Planning is done in the load handler on every incoming message: `getSegmentsToLoad` filters to the forward window,
* then the segments not already in `TextTracksActor`'s context are scheduled. When a new `load` arrives mid-run, the
* handler replans and either:
*
* - **Continues**: the in-flight segment is still in the new plan → `abortPending` only, schedule the rest of the plan
*   (minus the in-flight item, which covers its slot).
* - **Preempts**: in-flight segment is no longer wanted (track switch, large seek out of window) → `abortAll`, schedule
*   the new plan from scratch.
*
* The cue parser is injected so this factory is host-agnostic. A DOM host supplies a VTT parser backed by
* `<track>`/`TextTrack` APIs; a non-DOM host (worker, test fake, alternate runtime) supplies its own.
*/
function createTextTrackSegmentLoaderActor(textTracksActor, resolveSegment, config = {}, compositionDeps = {
	state: {},
	context: {},
	config: {}
}) {
	const forwardBufferConfig = {
		...DEFAULT_FORWARD_BUFFER_CONFIG,
		...config.forwardBuffer
	};
	const deps = {
		state: compositionDeps.state,
		context: compositionDeps.context,
		config: {
			...compositionDeps.config,
			textTracksActor,
			resolveSegment
		}
	};
	const pipeline = (config.messagePipelines ?? DEFAULT_TEXT_MESSAGE_PIPELINES)();
	/**
	* Translate a load message into an ordered TextLoadTask list based on committed actor state. In-flight awareness is
	* handled separately in the `loading` state's load handler.
	*
	* Metadata mode (no `range`) is a no-op for text — text tracks have no init-segment concept, so there's nothing to
	* load until a range arrives via `'full-range'` dispatch.
	*/
	const planTasks = (message) => {
		const { track, range } = message;
		if (!range) return [];
		const trackId = track.id;
		const bufferedSegments = peek(textTracksActor.snapshot).context.segments[trackId] ?? [];
		return getSegmentsToLoad(track.segments, bufferedSegments, range.start, forwardBufferConfig).map((segment) => ({
			segment,
			trackId
		}));
	};
	/**
	* Wraps a TextLoadTask into a Task that runs the op's step pipeline (resolve/relocate/dispatch, per the composition's
	* `messagePipelines`). Updates `inFlightSegmentId` around the async region so the load handler can make accurate
	* continue/preempt decisions, and checks the abort signal before each step.
	*
	* Text degrades gracefully: a step throwing (e.g. a failed segment fetch) is logged and swallowed so the runner
	* continues to the next segment — unlike the v/a loader, where a failed init must abort the remaining tasks.
	*/
	const makeLoadTask = (op, { getContext, setContext }) => {
		return new Task(async (signal) => {
			if (signal.aborted) return;
			const frame = { op };
			setContext({
				...getContext(),
				inFlightTrackId: op.trackId,
				inFlightSegmentId: op.segment.id
			});
			try {
				for (const step of pipeline) {
					if (signal.aborted) return;
					await step(frame, signal, deps);
				}
			} catch (error) {
				console.error("Failed to load text-track segment:", error);
			} finally {
				setContext({
					...getContext(),
					inFlightTrackId: null,
					inFlightSegmentId: null
				});
			}
		});
	};
	const scheduleAll = (tasks, ctx) => {
		for (const op of tasks) ctx.runner.schedule(makeLoadTask(op, ctx)).then(void 0, (e) => {
			if (e instanceof Error && e.name === "AbortError") return;
			console.error("Unexpected error in text-track segment loader:", e);
			ctx.runner.abortPending();
		});
	};
	return createMachineActor({
		runner: () => new SerialRunner(),
		initial: "idle",
		context: {
			inFlightTrackId: null,
			inFlightSegmentId: null
		},
		states: {
			idle: { on: { load: (msg, ctx) => {
				const tasks = planTasks(msg);
				if (tasks.length === 0) return;
				ctx.transition("loading");
				scheduleAll(tasks, ctx);
			} } },
			loading: {
				onSettled: "idle",
				on: { load: (msg, ctx) => {
					const { context, runner } = ctx;
					const tasks = planTasks(msg);
					if (context.inFlightTrackId !== null && context.inFlightSegmentId !== null && tasks.some((t) => t.trackId === context.inFlightTrackId && t.segment.id === context.inFlightSegmentId)) {
						runner.abortPending();
						scheduleAll(tasks.filter((t) => !(t.trackId === context.inFlightTrackId && t.segment.id === context.inFlightSegmentId)), ctx);
					} else {
						runner.abortAll();
						scheduleAll(tasks, ctx);
					}
				} }
			}
		}
	});
}

//#endregion
//#region ../spf/dist/dev/playback/behaviors/dom/setup-text-track-actors.js
function setupTextTrackActorsSetup({ state, context, config }) {
	return effect(() => {
		const mediaElement = context.mediaElement.get();
		if (!mediaElement) return;
		const textTracksActor = createTextTracksActor(mediaElement);
		const textTrackSegmentLoaderActor = createTextTrackSegmentLoaderActor(textTracksActor, config.resolveTextTrackSegment, {
			forwardBuffer: config.forwardBuffer,
			messagePipelines: config.textMessagePipelines
		}, {
			state,
			context,
			config
		});
		context.textTracksActor.set(textTracksActor);
		context.textTrackSegmentLoaderActor.set(textTrackSegmentLoaderActor);
		return () => {
			textTracksActor.destroy();
			textTrackSegmentLoaderActor.destroy();
			context.textTracksActor.set(void 0);
			context.textTrackSegmentLoaderActor.set(void 0);
		};
	});
}
const setupTextTrackActors = {
	stateKeys: [],
	contextKeys: [
		"mediaElement",
		"textTracksActor",
		"textTrackSegmentLoaderActor"
	],
	setup: setupTextTrackActorsSetup
};

//#endregion
//#region ../spf/dist/dev/core/tasks/delayed-reschedule.js
/**
* Build a {@link Reschedule} from a pure cadence function — the common timer-based, _start-anchored_ implementation.
*
* Invoked concurrently with the run, it observes the result, then waits `cadence(current, previous)` milliseconds
* **measured from when it was invoked** (≈ the run's start): it subtracts the run's own elapsed time, so consecutive
* runs begin one cadence apart regardless of how long each run takes (per RFC 8216 §6.3.4's "measured from the last
* time the client began loading"). If the run takes longer than the cadence, the next run starts immediately.
*
* A `null` cadence stops the recurrence. A rejected run rejects this reschedule, which the `RecurringRunner` propagates
* as the recurrence's failure — error recovery (e.g. retrying transient fetch failures) belongs below, at the fetch
* layer, not in the cadence.
*/
function delayedReschedule(cadence) {
	return async (task) => {
		const startedAt = Date.now();
		const ms = cadence(await task.run(), task.previous);
		if (ms === null) return false;
		await sleep(Math.max(0, ms - (Date.now() - startedAt)), task.signal);
		return true;
	};
}

//#endregion
//#region ../spf/dist/dev/media/dom/text/text-track-slots.js
/**
* SPF-owned `<track>` selector. Each slot created by `addSubtitlesTracksToMedia` carries this attribute so reads and
* removals can filter SPF-owned tracks from host-page-owned ones.
*/
const SPF_TRACK_SELECTOR = "track[data-src-track]";
/**
* Allocate text-track slots on `mediaElement` for each model track by creating and appending `<track>` children. Marks
* each element with `data-src-track` so it can be distinguished from `<track>` children the host page added directly —
* used by `getShowingSubtitlesTrackFromMedia` and `removeAllSubtitlesTracksFromMedia` to scope their reads/removals to
* SPF-owned slots. The spec has no `removeTextTrack` API, so creating `<track>` elements is the only mechanism for
* adding _and_ removing entries to `mediaElement.textTracks`.
*/
function addSubtitlesTracksToMedia(mediaElement, modelTextTracks) {
	for (const modelTrack of modelTextTracks) {
		const el = document.createElement("track");
		el.id = modelTrack.id;
		el.kind = modelTrack.kind;
		el.label = modelTrack.label;
		el.toggleAttribute("data-src-track", true);
		if (modelTrack.language) el.srclang = modelTrack.language;
		mediaElement.appendChild(el);
	}
}
/**
* Return the SPF-owned subtitle/caption `TextTrack` currently in `'showing'` mode, or `undefined` if none. Restricts
* the search to slots created by `addSubtitlesTracksToMedia` (via the `data-src-track` selector) so a showing track
* that the host page added directly is ignored — SPF selection only mirrors tracks it owns.
*/
function getShowingSubtitlesTrackFromMedia(mediaElement) {
	const elements = mediaElement.querySelectorAll(SPF_TRACK_SELECTOR);
	for (const el of elements) {
		const track = el.track;
		if (track.mode === "showing" && isCaptionOrSubtitleTrack(track)) return track;
	}
}
/**
* Remove every SPF-owned `<track>` child from `mediaElement` (those tagged with `data-src-track` by
* `addSubtitlesTracksToMedia`). `<track>` elements the host page added directly are left in place.
*/
function removeAllSubtitlesTracksFromMedia(mediaElement) {
	const elements = mediaElement.querySelectorAll(SPF_TRACK_SELECTOR);
	for (const el of elements) el.remove();
}
/**
* Apply a selection to a `TextTrackList` by setting each subtitle/caption track's `mode` to `'showing'` if its `id`
* matches `selectedId` and `'disabled'` otherwise. Tracks of other kinds (chapters, metadata, descriptions) are left
* untouched — they may be owned by the host page.
*/
function syncTextTrackModes(textTracks, selectedId) {
	for (let i = 0; i < textTracks.length; i++) {
		const track = textTracks[i];
		if (!isCaptionOrSubtitleTrack(track)) continue;
		track.mode = track.id === selectedId ? "showing" : "disabled";
	}
}

//#endregion
//#region ../spf/dist/dev/playback/behaviors/dom/seek-to-live-edge.js
/**
* Keep the playhead in the live window, via a two-state reactor gated on the preconditions for "we know where live is":
*
* - **`inactive`** — no media element, or no live edge (`getLiveEdge` is `null`: VOD, ended, or unresolved). Idle.
* - **`live`** — preconditions met. `entry` commands `state.startPosition` once to the target live latency behind the
*   edge (clamped to the window start) so playback begins near the edge and the loader dispatches an in-window range;
*   `effects` runs the window-exit guard.
*
* A derivable live edge is itself the establishment gate: segment placement is settled at parse time — the reference
* track's local placement _is_ the presentation timeline, and every other track's first parse is held until the anchor
* is stamped (`resolve-track`'s gate + `establishStartMediaTime`) — so any window derived from resolved segments is
* already final, with no separate anchor signal to wait on (see
* `internal/design/spf/live-presentation-timeline-model.md`).
*
* The two pieces split along the axis a future DVR / EVENT mode will care about: the **one-time start position**
* (`entry`) is the _live-specific_ behavior — start near the edge on load; a DVR mode makes it conditional (start in
* place). The **window-exit guard** (`effects`) is the _general windowed-live_ behavior — applies to sliding-window
* live, DVR, and EVENT alike. Because the command is an `entry`, it fires once per entry into `live`; a source change
* exits to `inactive`, so the next source re-commands (no closure latch to reset). The guard stays a direct seek — it
* is recurring, while `startPosition` is a self-clearing one-shot.
*
* Window-exit guard: while playing (not paused), reposition to the live edge when the playhead has fallen behind the
* window start — including when a seek to a now-evicted position has stranded the playhead (such a seek can never
* settle, so we rescue rather than wait on it). Two triggers: the **window-update re-fire** (the guard reads the live
* edge, so each reload / slide re-runs it — this catches a stall, where `timeupdate` stops but the playlist keeps
* reloading) and a **`play` listener** for immediate reactivity on resume, since the reload interval can be seconds.
* `play`, not `playing`: after a long pause the playhead sits behind the window at an unseekable position, where the
* browser stalls and `playing` never fires; `play` fires on the paused→false transition regardless, so we snap before
* the stall. In-window pause / DVR scrub-back are left untouched.
*
* The latency comes from the injected `resolveLiveLatency` seam (HLS: `HOLD-BACK`), so this behavior carries no
* delivery-format specifics. `applyStartPosition` performs the seek, gated on `loadedmetadata` — which implies an open
* MediaSource and hence a declared seekable range (a seek outside `seekable` is clamped) — so this behavior needs no
* MediaSource precondition of its own.
*/
/**
* Tolerance (seconds) around the window edges before the guard repositions, so boundary / floating-point noise doesn't
* trigger a spurious seek.
*/
const REPOSITION_TOLERANCE = .1;
/**
* `'live'` once the preconditions hold: a media element and a derivable live edge (whose placement is final by
* construction — see the module docstring). `'inactive'` otherwise.
*
* Deliberately narrow: every signal here can flip the reactor out of and back into `live`, re-firing `entry`. Neither
* blinks mid-source — the edge can't, because `liveWindowForType` falls back to any resolved track of the type — so
* `entry` fires once per source without a latch, and a live reload (same source, slid window) correctly doesn't
* re-command.
*
* The flip side: the presentation _url_ is not part of this state, so replacing one already-resolved live presentation
* with another would stay in `live` and never command a start position for the new source. Unreachable today — every
* writer sets `{ url }` (unresolved) or `undefined` first, so a source change always transits `inactive`. Supporting a
* seeded pre-resolved presentation (via `initialState`) that later changes would mean folding the url in here.
*/
function deriveState$1(mediaElement, edge) {
	return mediaElement && edge ? "live" : "inactive";
}
function seekToLiveEdgeSetup({ state, context, config }) {
	const derivedStateSignal = computed(() => deriveState$1(context.mediaElement.get(), getLiveEdge({
		state,
		config
	})));
	return createMachineReactor({
		initial: "inactive",
		monitor: () => derivedStateSignal.get(),
		states: {
			inactive: {},
			live: {
				entry: () => {
					const mediaElement = context.mediaElement.get();
					const { liveEdgeStart } = getLiveEdge({
						state,
						config
					});
					if (mediaElement.currentTime >= liveEdgeStart) return;
					state.startPosition.set(liveEdgeStart);
				},
				effects: () => {
					const { start: windowStart, liveEdgeStart } = getLiveEdge({
						state,
						config
					});
					const mediaElement = peek(context.mediaElement);
					const reposition = () => {
						if (mediaElement.paused) return;
						if (mediaElement.currentTime < windowStart - REPOSITION_TOLERANCE) mediaElement.currentTime = liveEdgeStart;
					};
					reposition();
					return listen(mediaElement, "play", reposition);
				}
			}
		}
	});
}
/**
* Manual `Behavior<>` literal (like `calculatePresentationDuration`): declares only `presentation` + `startPosition` in
* stateKeys while reading `selectedVideoTrackId` / `selectedAudioTrackId` defensively (contributed by the switch*
* behaviors), so it composes without a stateKeys/type conflict.
*/
const seekToLiveEdge = {
	stateKeys: ["presentation", "startPosition"],
	contextKeys: ["mediaElement"],
	setup: seekToLiveEdgeSetup
};

//#endregion
//#region ../spf/dist/dev/playback/behaviors/dom/sync-live-seekable-range.js
function syncLiveSeekableRangeSetup({ state, context }) {
	return effect(() => {
		const mediaSource = context.mediaSource.get();
		const liveWindow = liveWindowFromState(state);
		if (!mediaSource || !liveWindow) return;
		mediaSource.setLiveSeekableRange(liveWindow.start, liveWindow.end);
	});
}
/**
* Manual `Behavior<>` literal (like `seekToLiveEdge`): declares only `presentation` in stateKeys while reading
* `selectedVideoTrackId` / `selectedAudioTrackId` defensively (contributed by the switch* behaviors), so it composes
* without a stateKeys/type conflict.
*/
const syncLiveSeekableRange = {
	stateKeys: ["presentation"],
	contextKeys: ["mediaSource"],
	setup: syncLiveSeekableRangeSetup
};

//#endregion
//#region ../spf/dist/dev/playback/behaviors/dom/sync-text-tracks.js
/**
* **Own the text-track slots on the host media element, mirroring the SPF model.** When a presentation is resolved and
* a media element is available, allocate one slot in `mediaElement.textTracks` per model text track — via creating
* `<track>` children, since that's the only spec mechanism for adding _and_ removing entries to `textTracks` (no
* `removeTextTrack` API exists). Once slots are provisioned, mirror the resolved `selectedTextTrackId` into their
* `mode`s (one-way: state → DOM), and propagate user-initiated DOM `change` events back to `userTextTrackSelection` —
* the standing _intent_ (a language-based partial, or `'off'`) that `switchTextTrack` resolves into
* `selectedTextTrackId`. So non-SPF consumers (host-page captions buttons, browser native UI, video.js store) drive
* selection by expressing intent, not by writing the resolved id.
*
* Single-positive-state reactor (`'preconditions-unmet'` ↔ `'sync-active'`): the entry allocates the slots, applies the
* initial selection, attaches the `change` listener, and opens a brief Chromium settling-window guard — all
* transition-driven, fire-once on state entry, with paired cleanup on state exit. A single `effects:` mirrors
* subsequent `selectedTextTrackId` changes into `mode`s; that's the only continuous-reactivity concern.
*
* State-exit cleanup also sends a `'clear'` message to the `TextTracksActor` so its cue+segment cache (keyed by
* trackId) is dropped alongside the DOM `<track>` slots. The actor itself is owned by `setupTextTrackActors` and bound
* to mediaElement, not presentation, so it survives source resets; clearing its context here keeps the cache consistent
* with the DOM. Without this, a subsequent presentation reusing a trackId would have `getSegmentsToLoad` treat its
* segments as already-buffered and skip loading them.
*
* Single-writer separation: `selectedTextTrackId` is the resolved _output_ owned solely by `switchTextTrack`; this
* behavior only reads it (to mirror modes). The write path here is `userTextTrackSelection` — the user-intent _input_ —
* so DOM action and the resolver never contend for one slot. The intent isn't cleared on source unload (it's a standing
* preference, like `userAudioTrackSelection`); `'off'` is written when the user disables all tracks via native UI.
*
* Echo guard: `selectedTextTrackId` is exactly the id this behavior last drove into the DOM, so a `change` event still
* showing it is our own echo (or a resolver-driven correction — e.g. the picked track's CDN failed and the resolver
* disabled it) and is ignored, never written back as a spurious user action. Only a showing id that _differs_ from the
* resolved id is a real user pick. The settling-window guard additionally swallows Chromium's init-time auto-selection
* before the resolved selection has settled.
*/
function deriveState(presentation, mediaElement) {
	if (!mediaElement || !presentation) return "preconditions-unmet";
	return getTracksByType(presentation, "text").length > 0 ? "sync-active" : "preconditions-unmet";
}
/**
* Map the DOM-showing track back to standing user intent. No showing track is an explicit `'off'`. Otherwise prefer a
* language-based partial (so the pick persists across source changes); fall back to `{ id }` for a track without a
* language (precise within a source, just not portable).
*/
function deriveTextTrackIntent(showingId, modelTextTracks) {
	if (!showingId) return "off";
	const language = modelTextTracks.find((track) => track.id === showingId)?.language;
	return language ? { language } : { id: showingId };
}
function syncTextTracksSetup({ state, context, config }) {
	const { addSubtitlesTracksToMedia, getShowingSubtitlesTrackFromMedia, removeAllSubtitlesTracksFromMedia } = config;
	const derivedStateSignal = computed(() => deriveState(state.presentation.get(), context.mediaElement.get()));
	return createMachineReactor({
		initial: "preconditions-unmet",
		monitor: () => derivedStateSignal.get(),
		states: {
			"preconditions-unmet": {},
			"sync-active": {
				entry: () => {
					const mediaElement = context.mediaElement.get();
					const modelTextTracks = getTracksByType(state.presentation.get(), "text");
					addSubtitlesTracksToMedia(mediaElement, modelTextTracks);
					syncTextTrackModes(mediaElement.textTracks, state.selectedTextTrackId.get());
					let inSettlingWindow = true;
					const settlingTimeout = setTimeout(() => {
						inSettlingWindow = false;
					}, 0);
					const onChange = () => {
						if (inSettlingWindow) {
							syncTextTrackModes(mediaElement.textTracks, state.selectedTextTrackId.get());
							return;
						}
						const showingId = getShowingSubtitlesTrackFromMedia(mediaElement)?.id || void 0;
						if (showingId === state.selectedTextTrackId.get()) return;
						state.userTextTrackSelection.set(deriveTextTrackIntent(showingId, modelTextTracks));
					};
					const unlisten = listen(mediaElement.textTracks, "change", onChange);
					return () => {
						unlisten();
						clearTimeout(settlingTimeout);
						removeAllSubtitlesTracksFromMedia(mediaElement);
						peek(context.textTracksActor)?.send({ type: "clear" });
					};
				},
				effects: () => {
					const mediaElement = peek(context.mediaElement);
					syncTextTrackModes(mediaElement.textTracks, state.selectedTextTrackId.get());
				}
			}
		}
	});
}
const syncTextTracks = defineBehavior({
	stateKeys: [
		"presentation",
		"selectedTextTrackId",
		"userTextTrackSelection"
	],
	contextKeys: ["mediaElement", "textTracksActor"],
	setup: syncTextTracksSetup
});

//#endregion
//#region ../spf/dist/dev/playback/behaviors/dom/track-player-resolution.js
/**
* Mirror the player element's rendered pixel dimensions into reactive state, so a rendition cap can narrow candidates
* to what the element can actually show without reading the DOM at pick time — which would make the picker impure, and
* would never re-pick when the element resized.
*
* The player-element half of the caps in `internal/design/spf/features/rendition-selection-caps.md`, and the tighter
* half: a small embed on a large display is capped by its own box rather than by the screen behind it
* (`trackScreenResolution`).
*
* Reported as a width and a height in device pixels — the same units, and for the same reason, as `media/dom/screen`'s
* reading: the cap compares against real track dimensions, and a `"720p"`-style tier only describes a track once you
* assume its aspect ratio.
*
* `undefined` where there is nothing to measure — no element attached, or one that isn't being rendered (detached,
* `display: none`, not yet laid out) — which is the value the cap reads as "don't cap".
*/
function trackPlayerResolutionSetup({ state, context, config = {} }) {
	const { capRenditionToPlayerSize = true, useDevicePixelRatio = true } = config;
	return effect(() => {
		const mediaElement = context.mediaElement.get();
		let current;
		state.playerResolution.set(current);
		if (!capRenditionToPlayerSize || !mediaElement) return;
		const write = (size) => {
			const next = scaleResolution(size, size.scale);
			if (shallowEqual(current, next)) return;
			current = next;
			state.playerResolution.set(next);
		};
		return useDevicePixelRatio ? observeRenderedSize(mediaElement, write) : observeElementSize(mediaElement, write);
	});
}
/**
* Track the player element's rendered resolution in `state.playerResolution`.
*
* @example
*   const cleanup = trackPlayerResolution.setup({ state, context });
*/
const trackPlayerResolution = defineBehavior({
	stateKeys: ["playerResolution"],
	contextKeys: ["mediaElement"],
	setup: trackPlayerResolutionSetup
});

//#endregion
//#region ../spf/dist/dev/playback/engines/hls/engine.js
/**
* Generic `shareSignals` instantiated against the HLS engine's full state and context — captures composition signal
* refs into the consumer's `onSignalsReady` callback at setup time, and materializes input slots that no composed
* behavior produces: `user*TrackSelection` (track-switching only reads them). `failedCdns` is owned by
* `setupFailoverMonitor`, so it's already materialized and reachable on the `onSignalsReady` refs without being listed
* here.
*/
const shareSignals = makeShareSignals([
	"userVideoTrackSelection",
	"userAudioTrackSelection",
	"userTextTrackSelection",
	"disableRemotePlayback"
]);
/**
* Create an HLS playback engine.
*
* Composes SPF behaviors into a reactive pipeline for HLS playback over MSE: manifest resolution, track selection, ABR,
* segment loading, and end-of-stream coordination.
*
* @example
*   ```ts
*   let signals: HlsVideoEngineSignals;
*   const engine = createHlsVideoEngine({
*     initialBandwidth: 2_000_000,
*     preferredAudioLanguage: 'en',
*     onSignalsReady: (refs) => {
*       signals = refs;
*     },
*   });
*
*   signals.context.mediaElement.set(videoEl);
*   signals.state.presentation.set({ url: 'https://example.com/stream.m3u8' });
*
*   videoEl.play();
*
*   await engine.destroy();
*   ```;
*/
function createHlsVideoEngine(config = {}) {
	const deriveStartMediaTime = config.deriveStartMediaTime ?? deriveSharedMinStartMediaTime;
	const finalConfig = {
		...config,
		deriveStartMediaTime,
		attachMediaSource: attachMediaSourceAsSourceElement,
		canPlayTrack: config.canPlayTrack ?? canPlayTrack,
		reportUnsupportedTrackConditions: config.reportUnsupportedTrackConditions ?? reportUnsupportedTrackConditions,
		resolveTextTrackSegment: config.resolveTextTrackSegment ?? resolveVttSegment,
		textMessagePipelines: relocatingTextPipelines,
		resolveDuration: config.resolveDuration ?? getResolvedSelectedTrackDuration,
		parsePresentation: config.parsePresentation ?? parseMultivariantPlaylist,
		addSubtitlesTracksToMedia: config.addSubtitlesTracksToMedia ?? addSubtitlesTracksToMedia,
		getShowingSubtitlesTrackFromMedia: config.getShowingSubtitlesTrackFromMedia ?? getShowingSubtitlesTrackFromMedia,
		removeAllSubtitlesTracksFromMedia: config.removeAllSubtitlesTracksFromMedia ?? removeAllSubtitlesTracksFromMedia,
		videoMessagePipelines: relocationPipelinesFor("video", deriveStartMediaTime),
		audioMessagePipelines: relocationPipelinesFor("audio", deriveStartMediaTime),
		gateFirstParse: gateFirstParseOnAnchor,
		resolveLiveLatency,
		reschedule: config.reschedule ?? delayedReschedule(mediaPlaylistReloadDelay)
	};
	return createComposition([
		syncPreload,
		trackLoadTriggers,
		resolvePresentation,
		deriveCdnPriority,
		setupFailoverMonitor,
		collectErrors,
		resolveVideoTrack,
		resolveAudioTrack,
		resolveTextTrack,
		calculatePresentationDuration,
		setupMediaSource,
		updateMediaSourceDuration,
		establishStartMediaTime,
		setupVideoBufferActors,
		setupAudioBufferActors,
		setupAirPlay,
		trackCurrentTime,
		applyStartPosition,
		trackPlayerResolution,
		switchVideoTrack,
		switchAudioTrack,
		switchTextTrack,
		loadVideoSegments,
		loadAudioSegments,
		syncLiveSeekableRange,
		seekToLiveEdge,
		endOfStream,
		recoverEndStall,
		syncTextTracks,
		setupTextTrackActors,
		loadTextTrackSegments,
		shareSignals
	], {
		config: finalConfig,
		initialState: { bandwidthState: {
			fastEstimate: 0,
			fastTotalWeight: 0,
			slowEstimate: 0,
			slowTotalWeight: 0,
			bytesSampled: 0
		} }
	});
}

//#endregion
//#region ../spf/dist/dev/playback/adapters/hls-video/adapter.js
const hlsVideoMediaDefaultProps = {
	src: "",
	preload: "",
	disableRemotePlayback: false,
	streamType: MediaStreamTypes.UNKNOWN
};
/**
* `targetLiveWindow` per the media-ui-extensions live-edge proposal: `NaN` for on-demand (or nothing resolved yet), `0`
* for standard sliding-window live, `Infinity` for DVR (`#EXT-X-PLAYLIST-TYPE:EVENT` — the window grows from the
* start). Read from the timeline-bearing track's playlist metadata.
*/
function deriveTargetLiveWindow(presentation, trackId) {
	if (!isResolvedPresentation(presentation) || !trackId) return NaN;
	const track = findTrackById(presentation, trackId);
	if (!track || !isResolvedTrack(track)) return NaN;
	const metadata = getMediaPlaylistMetadata(track);
	if (!metadata) return NaN;
	if (metadata.playlistType === "EVENT") return Number.POSITIVE_INFINITY;
	return deriveStreamType(metadata) === "live" ? 0 : NaN;
}
/**
* Which reported conditions this composition treats as **fatal** — the ones that reach `error` and fire `'error'`.
* Severity isn't part of an SVTA code (§Approach: "impact varies with player implementation"), and here it also varies
* by composition, so it's decided at this boundary rather than by the reporter.
*
* An allow-list, deliberately: only _verdicts_ are here. The per-rendition causes `resolve-track` reports (unsupported
* format, unsupported DRM) stay in the sequence as context — one unplayable rendition doesn't fail the source, and
* promoting a cause would put a dialog over a mixed source that goes on to play.
*/
const FATAL_SVTA_CODES = /* @__PURE__ */ new Set([SVTA_NO_SUPPORTED_VIDEO_TRACK, SVTA_NO_SUPPORTED_AUDIO_TRACK]);
/**
* Mixin that adds SPF playback engine behavior to any base class.
*
* Implements the src/play() contract per the WHATWG HTML spec so that SPF can be used anywhere a media element API is
* expected.
*
* A single engine instance is created at construction and recycled across src changes.
*
* @example
*   class HlsVideoMedia extends HlsVideoMediaMixin(HTMLVideoElementHost) {}
*
*   const media = new HlsVideoMedia();
*   media.attach(document.querySelector('video'));
*   media.src = 'https://stream.mux.com/abc123.m3u8';
*
* @fires streamtypechange - Fired when the detected stream type changes. Read `streamType` for the new value.
* @fires targetlivewindowchange - Fired when the target live window changes. Read `targetLiveWindow` for the new value.
* @fires error - Fired when a fatal condition is reported. Read `error` for it.
*/
function HlsVideoMediaMixin(BaseClass) {
	class HlsVideoMediaImpl extends BaseClass {
		/**
		* A complete sentence naming the Media to reach for when this one can't play a source — `Try the hls.js-backed Mux
		* media instead: import the hls-js flavor in place of the spf one.` Appended to the copy this adapter surfaces, and
		* to the notices it logs. Name the flavor, not an import path: a Media is reached through several packages, each
		* with its own counterpart.
		*
		* Empty here: `hls-video` has no better-equipped sibling to point at. A Media that does (a Mux Video built on this
		* engine, whose hls.js-backed counterpart plays MPEG-TS and DRM) overrides this static, and its copy gains the
		* second sentence with no other change.
		*/
		static get alternativeMediaSuggestion() {}
		#engine;
		#config;
		#signals;
		#preload = hlsVideoMediaDefaultProps.preload;
		#disableRemotePlayback = hlsVideoMediaDefaultProps.disableRemotePlayback;
		#streamType = hlsVideoMediaDefaultProps.streamType;
		#isUserStreamType = false;
		#targetLiveWindow = NaN;
		#error = null;
		/**
		* The _reported_ condition currently surfaced, which is what the re-fire latch keys on. Not `#error.code`: that's
		* the code this adapter chose to surface, and a later cause can change the choice for a condition already
		* announced.
		*/
		#reportedCode = null;
		/** Notices already logged for the current source; cleared when it unloads. */
		#noticed = /* @__PURE__ */ new Set();
		#stopLiveSync;
		#stopErrorSync;
		/** Pending loadstart listener from a deferred play() retry, if any. */
		#loadstartListener = null;
		constructor(...args) {
			super(...args);
			const { config } = args?.[0] ?? {};
			this.#config = config;
			this.#engine = this.#createEngine();
			this.#stopLiveSync = effect(() => {
				const presentation = this.#signals.state.presentation.get();
				this.#setDetectedStreamType(presentation?.streamType ?? MediaStreamTypes.UNKNOWN);
				this.#setTargetLiveWindow(deriveTargetLiveWindow(presentation, liveTrackId(this.#signals.state)));
				this.#reportDeliveryNotices(presentation);
			});
			this.#stopErrorSync = effect(() => {
				const errors = this.#signals.state.errors.get();
				this.#setError(firstFatal(errors, FATAL_SVTA_CODES), errors);
			});
		}
		/**
		* Underlying playback engine — the low-level SPF reactive composition that drives playback. An advanced escape
		* hatch for direct engine access; normal playback is driven through this element's own properties and methods.
		*/
		get engine() {
			return this.#engine;
		}
		/**
		* The current fatal error, or `null`. Only _fatal_ conditions appear here — the engine reports non-fatal ones too
		* (they stay in `engine.state.errors`), and promoting them would tell a consumer playback had failed when it
		* hadn't. Resets per source. Fires `'error'` when set.
		*/
		get error() {
			return this.#error;
		}
		/**
		* The source's stream type — `'live'`, `'on-demand'`, or `'unknown'` until a media playlist has been parsed.
		* Setting a non-`'unknown'` value pins a user override (detection stops updating it); setting `'unknown'` reverts
		* to the engine's detected value.
		*/
		get streamType() {
			return this.#streamType;
		}
		set streamType(value) {
			if (value === MediaStreamTypes.UNKNOWN) {
				this.#isUserStreamType = false;
				this.#updateStreamType(this.#signals.state.presentation.get()?.streamType ?? MediaStreamTypes.UNKNOWN);
				return;
			}
			this.#isUserStreamType = true;
			this.#updateStreamType(value);
		}
		/**
		* Presentation time marking the start of the live-edge window — playback at `currentTime >= liveEdgeStart` counts
		* as "at the live edge" (the same target the engine's `seekToLiveEdge` seeks to: window end − HOLD-BACK). `NaN`
		* when the stream isn't live or nothing is resolved yet. Derived at read time from the engine's live window — no
		* change event; re-read on `timeupdate`/`progress` (as the store's live feature does).
		*/
		get liveEdgeStart() {
			return getLiveEdge({
				state: this.#signals.state,
				config: { resolveLiveLatency }
			})?.liveEdgeStart ?? NaN;
		}
		/**
		* The target live window: `NaN` for on-demand (or unknown), `0` for standard sliding-window live, `Infinity` for
		* DVR (`#EXT-X-PLAYLIST-TYPE:EVENT`). Fires `targetlivewindowchange` on change.
		*/
		get targetLiveWindow() {
			return this.#targetLiveWindow;
		}
		#setDetectedStreamType(value) {
			if (this.#isUserStreamType) return;
			this.#updateStreamType(value);
		}
		#updateStreamType(value) {
			if (this.#streamType === value) return;
			this.#streamType = value;
			this.dispatchEvent?.(new Event("streamtypechange"));
		}
		#setTargetLiveWindow(value) {
			if (Object.is(this.#targetLiveWindow, value)) return;
			this.#targetLiveWindow = value;
			this.dispatchEvent?.(new Event("targetlivewindowchange"));
		}
		#setError(reported, errors) {
			if (!reported) {
				this.#error = null;
				this.#reportedCode = null;
				return;
			}
			if (this.#reportedCode === reported.code) return;
			this.#reportedCode = reported.code;
			const unsupported = hasUnsupportedFeatureCause(errors);
			if (unsupported) console.error(this.#withSuggestion(UNSUPPORTED_PLAYBACK_FEATURE_MESSAGE), { conditions: errors });
			this.#error = {
				code: unsupported ? SVTA_UNSUPPORTED_PLAYBACK_FEATURE : reported.code,
				message: reported.message ?? "",
				...reported.data === void 0 ? {} : { data: reported.data }
			};
			this.dispatchEvent?.(new Event("error"));
		}
		attach(mediaElement) {
			super.attach?.(mediaElement);
			this.#signals.context.mediaElement.set(mediaElement);
		}
		detach() {
			this.#cancelPendingPlay();
			this.#signals.context.mediaElement.set(void 0);
			super.detach?.();
		}
		destroy() {
			this.#cancelPendingPlay();
			this.#stopLiveSync();
			this.#stopErrorSync();
			this.#engine.destroy();
		}
		/** Preload type (`'none'` / `'metadata'` / `'auto'`). */
		get preload() {
			return this.#preload;
		}
		set preload(value) {
			this.#preload = value;
			if (value) this.#signals.state.preload.set(value);
		}
		get disableRemotePlayback() {
			return this.#disableRemotePlayback;
		}
		set disableRemotePlayback(value) {
			this.#disableRemotePlayback = value;
			this.#signals.state.disableRemotePlayback.set(value);
		}
		get src() {
			return this.#signals.state.presentation.get()?.url ?? "";
		}
		set src(value) {
			if (value === this.src) return;
			this.#cancelPendingPlay();
			this.#signals.state.presentation.set(value ? { url: value } : void 0);
		}
		play() {
			const mediaElement = this.#signals.context.mediaElement.get();
			if (!mediaElement) return Promise.reject(/* @__PURE__ */ new Error("HlsVideoMediaElement: no media element attached"));
			this.#signals.state.loadActivated.set(true);
			return mediaElement.play().catch((err) => {
				if (this.src) return new Promise((resolve, reject) => {
					const listener = () => {
						this.#loadstartListener = null;
						mediaElement.play().then(resolve, reject);
					};
					this.#loadstartListener = listener;
					mediaElement.addEventListener("loadstart", listener, { once: true });
				});
				throw err;
			});
		}
		/** `message`, plus the alternative-Media sentence when this class names one. */
		#withSuggestion(message) {
			return withAlternativeMediaSuggestion(message, this);
		}
		/**
		* Log what this engine is delivering differently from what the playlist asked for. Neither condition stops
		* playback, so neither is an error — they go to the console rather than the error surface.
		*
		* Once per source, not per parse: a live playlist reloads every target duration, and the timeline track re-parses
		* on each one. Keyed on the notice rather than latched with a boolean so the two are independent, and cleared when
		* the presentation unresolves so the next source starts quiet.
		*/
		#reportDeliveryNotices(presentation) {
			if (!isResolvedPresentation(presentation)) {
				this.#noticed.clear();
				return;
			}
			const trackId = liveTrackId(this.#signals.state);
			const track = trackId ? findTrackById(presentation, trackId) : void 0;
			if (!track || !isResolvedTrack(track)) return;
			const metadata = getMediaPlaylistMetadata(track);
			if (!metadata) return;
			if (metadata.lowLatency && !this.#noticed.has("lowLatency")) {
				this.#noticed.add("lowLatency");
				console.warn(this.#withSuggestion(LOW_LATENCY_UNSUPPORTED_MESSAGE));
			}
			if (metadata.playlistType === "EVENT" && !this.#noticed.has("dvr")) {
				this.#noticed.add("dvr");
				console.warn(this.#withSuggestion(DVR_EXPERIMENTAL_MESSAGE));
			}
		}
		#createEngine() {
			return createHlsVideoEngine({
				...this.#config,
				onSignalsReady: (signals) => {
					this.#signals = signals;
				}
			});
		}
		#cancelPendingPlay() {
			if (!this.#loadstartListener) return;
			this.#signals.context.mediaElement.get()?.removeEventListener("loadstart", this.#loadstartListener);
			this.#loadstartListener = null;
		}
	}
	return HlsVideoMediaImpl;
}
/** Standalone SPF media adapter with no base class. */
var HlsVideoMediaElement = class extends HlsVideoMediaMixin(class {}) {};

//#endregion
//#region ../spf/dist/dev/media/media-tracks/media-tracks.js
/**
* The distinct video tracks of a presentation, deduped by {@link VideoDedupeKey} (first occurrence wins).
*
* Returns `[]` when the presentation is unresolved or has no video tracks.
*/
function dedupedVideoTracks(presentation) {
	if (!presentation) return [];
	return dedupe({
		tracks: getTracksByType(presentation, "video"),
		keyFn: toUserVideoTrackSelection
	});
}
/**
* The distinct audio tracks of a presentation, deduped by `language` + `name` (first occurrence wins). Returns `[]`
* when the presentation is unresolved or has no audio tracks.
*/
function dedupedAudioTracks(presentation) {
	if (!presentation) return [];
	return dedupe({
		tracks: getTracksByType(presentation, "audio"),
		keyFn: toUserAudioTrackSelection
	});
}
/**
* Find a video track by id, searching the same candidate set the engine resolves against ({@link dedupedVideoTracks}'s
* pre-dedupe source). Returns `undefined` when absent. Maps the engine's resolved `selectedVideoTrackId` back to its
* properties for `active` reflection — the resolved id may be a per-CDN copy that isn't the representative
* {@link dedupedVideoTracks} kept.
*/
function findVideoTrackById(presentation, id) {
	if (!presentation || !id) return void 0;
	const track = findTrackById(presentation, id);
	return track?.type === "video" ? track : void 0;
}
/** Audio counterpart of {@link findVideoTrackById}, for `enabled` reflection. */
function findAudioTrackById(presentation, id) {
	if (!presentation || !id) return void 0;
	const track = findTrackById(presentation, id);
	return track?.type === "audio" ? track : void 0;
}
/**
* Shallow-equal two key objects by their own properties. Both come from the same key builder, so they carry the same
* keys — a one-directional scan suffices.
*/
function sameKey(a, b) {
	for (const attr in a) if (a[attr] !== b[attr]) return false;
	return true;
}
/**
* Dedupe tracks by a key function, keeping the first occurrence of each key. Keys are compared field-by-field ({@link
* sameKey}).
*/
function dedupe({ tracks, keyFn }) {
	const seen = [];
	const kept = [];
	for (const track of tracks) {
		const key = keyFn(track);
		if (!key || seen.some((other) => sameKey(other, key))) continue;
		seen.push(key);
		kept.push(track);
	}
	return kept;
}
/** Build a partial video track that can be used as `userVideoTrackSelection`. */
function toUserVideoTrackSelection(rendition) {
	return rendition ? {
		width: rendition.width,
		height: rendition.height,
		bandwidth: rendition.bandwidth
	} : void 0;
}
/** Build a partial audio track that can be used as a `userAudioTrackSelection`. */
function toUserAudioTrackSelection(track) {
	return track ? {
		language: track.language,
		name: track.name
	} : void 0;
}
/** Whether two video tracks are the same by dedupe key */
function isSameVideoTrack(a, b) {
	return !!b && a.width === b.width && a.height === b.height && a.bandwidth === b.bandwidth;
}
/** Whether two audio tracks are the same by dedupe key */
function isSameAudioTrack(a, b) {
	return !!b && (a.language ?? "") === (b.language ?? "") && a.name === b.name;
}
/** Collapse a rational frame rate (numerator/denominator) to frames per second. */
const frameRateToNumber = (frameRate) => {
	return frameRate.frameRateNumerator / (frameRate.frameRateDenominator ?? 1);
};

//#endregion
//#region ../spf/dist/dev/playback/adapters/hls-video/media-tracks.js
const toVideoKey = (rendition) => ({
	width: rendition.width,
	height: rendition.height,
	bandwidth: rendition.bitrate
});
const toAudioKey = (track) => ({
	language: track.language,
	name: track.label
});
/** Two track lists carry the same set when their id sequences match. */
const sameIds = (a, b) => a.length === b.length && a.every((item, i) => item.id === b[i].id);
/**
* Projects the SPF engine's presentation onto the media element's `videoRenditions` / `audioTracks` lists, and wires
* user selection back to the engine's `userVideoTrackSelection` / `userAudioTrackSelection` signals.
*
* Requires the media-tracks mixin (track-list infrastructure) earlier in the chain so the host exposes `addVideoTrack`,
* `videoRenditions`, and friends.
*/
function HlsVideoMediaMediaTracksMixin(BaseClass) {
	class HlsVideoMediaMediaTracks extends BaseClass {
		#abort = new AbortController();
		#destroyed = false;
		#renditions = [];
		#audioTracks = [];
		constructor(...args) {
			super(...args);
			const { state } = this.engine;
			const { signal } = this.#abort;
			const renditionsSignal = computed(() => dedupedVideoTracks(state.presentation.get()), { equals: sameIds });
			const audioTracksSignal = computed(() => dedupedAudioTracks(state.presentation.get()), { equals: sameIds });
			const reflectRenditions = () => {
				const renditions = renditionsSignal.get();
				this.#renditions = renditions;
				this.#removeVideoTracks();
				if (!renditions.length) return;
				const videoTrack = this.addVideoTrack("main");
				videoTrack.selected = true;
				const resolved = untrack(() => findVideoTrackById(state.presentation.get(), state.selectedVideoTrackId.get()));
				for (const rendition of renditions) {
					const domRendition = videoTrack.addRendition("", rendition.width, rendition.height, rendition.codecs.join(","), rendition.bandwidth, rendition.frameRate ? frameRateToNumber(rendition.frameRate) : void 0);
					domRendition.id = rendition.id;
					domRendition.active = isSameVideoTrack(toVideoKey(domRendition), resolved);
				}
			};
			const reflectSelectedVideo = () => {
				const resolved = findVideoTrackById(state.presentation.get(), state.selectedVideoTrackId.get());
				for (const rendition of this.videoRenditions) rendition.active = isSameVideoTrack(toVideoKey(rendition), resolved);
			};
			const reflectAudioTracks = () => {
				const tracks = audioTracksSignal.get();
				this.#audioTracks = tracks;
				this.#removeAudioTracks();
				if (!tracks.length) return;
				const resolved = untrack(() => findAudioTrackById(state.presentation.get(), state.selectedAudioTrackId.get()));
				for (const track of tracks) {
					const domTrack = this.addAudioTrack(track.default ? "main" : "alternative", track.name, track.language ?? "");
					domTrack.id = track.id;
					domTrack.enabled = isSameAudioTrack(toAudioKey(domTrack), resolved);
				}
			};
			const reflectSelectedAudio = () => {
				const resolved = findAudioTrackById(state.presentation.get(), state.selectedAudioTrackId.get());
				for (const track of this.audioTracks) track.enabled = isSameAudioTrack(toAudioKey(track), resolved);
			};
			const sourceUrl = computed(() => state.presentation.get()?.url);
			const resetSelectionOnSourceChange = () => {
				sourceUrl.get();
				state.userVideoTrackSelection.set(void 0);
				state.userAudioTrackSelection.set(void 0);
			};
			const effectCleanups = [
				effect(reflectRenditions),
				effect(reflectSelectedVideo),
				effect(reflectAudioTracks),
				effect(reflectSelectedAudio),
				effect(resetSelectionOnSourceChange)
			];
			this.videoRenditions.addEventListener("change", this.#selectRendition, { signal });
			this.audioTracks.addEventListener("change", this.#selectAudio, { signal });
			signal.addEventListener("abort", () => effectCleanups.forEach((cleanup) => cleanup()), { once: true });
		}
		destroy() {
			if (this.#destroyed) return;
			this.#destroyed = true;
			this.#abort.abort();
			this.#removeVideoTracks();
			this.#removeAudioTracks();
			super.destroy?.();
		}
		#selectRendition = () => {
			const { userVideoTrackSelection } = this.engine.state;
			const index = this.videoRenditions.selectedIndex;
			const domRendition = index < 0 ? void 0 : this.videoRenditions[index];
			const rendition = this.#renditions.find((candidate) => candidate.id === domRendition?.id);
			userVideoTrackSelection.set(toUserVideoTrackSelection(rendition));
		};
		#selectAudio = () => {
			const { presentation, selectedAudioTrackId, userAudioTrackSelection } = this.engine.state;
			const resolved = findAudioTrackById(presentation.get(), selectedAudioTrackId.get());
			const current = [...this.audioTracks].find((track) => isSameAudioTrack(toAudioKey(track), resolved));
			const enabled = [...this.audioTracks].filter((track) => track.enabled);
			const target = enabled.find((track) => track !== current) ?? enabled[0];
			if (!target) return;
			for (const track of enabled) if (track !== target) track.enabled = false;
			if (target === current) return;
			const audioTrack = this.#audioTracks.find((candidate) => candidate.id === target.id);
			userAudioTrackSelection.set(toUserAudioTrackSelection(audioTrack));
		};
		#removeVideoTracks() {
			for (const videoTrack of [...this.videoTracks]) this.removeVideoTrack(videoTrack);
		}
		#removeAudioTracks() {
			for (const audioTrack of [...this.audioTracks]) this.removeAudioTrack(audioTrack);
		}
	}
	return HlsVideoMediaMediaTracks;
}

//#endregion
//#region ../spf/dist/dev/playback/adapters/hls-video/media.js
const HlsVideoMediaBase = HlsVideoMediaMediaTracksMixin(MediaTracksMixin(HlsVideoMediaMixin(HTMLVideoElementHost)));
var HlsVideoMedia = class extends HlsVideoMediaBase {};

//#endregion
export { HlsVideoMedia as t };
//# sourceMappingURL=media-B8KRgODy.js.map