import { a as isNull, i as isNil, r as isFunction, s as isObject, u as isUndefined } from "./predicate-DrcmolBs.js";
import { n as getCanonicalLocaleKey, s as DEFAULT_LOCALE, t as findLocaleKeys } from "./i18n-C51wLeb2.js";
import { t as shallowEqual } from "./shallow-equal-C7S8rj2f.js";
import { n as ContextConsumer, t as UIElement } from "./ui-element-DraIA8Ee.js";
import { n as mediaContext, o as ContextRequestEvent, r as playerContext, t as containerContext } from "./context-DlE_3NHA.js";
import { t as listen } from "./listen-CdeggIF8.js";
import { t as noop } from "./noop-BxkeRIz9.js";
import { n as kebabCase, t as camelCase } from "./casing-Cu0fL85w.js";
import { n as isCaptionOrSubtitleTrack, t as findTrackElement } from "./text-track-DMA7pa8W.js";
import { t as isWebKitAirPlayCapable } from "./webkit-C682yTT7.js";
import { n as EMPTY_TEXT_TRACKS, r as EMPTY_TIME_RANGES, t as EMPTY_REMOTE } from "./constants-CsSwyIX6.js";
import { t as anyAbortSignal } from "./abort-CkVmEk1y.js";

//#region ../../node_modules/.pnpm/@lit+context@1.1.6/node_modules/@lit/context/development/lib/value-notifier.js
/**
* @license
* Copyright 2021 Google LLC
* SPDX-License-Identifier: BSD-3-Clause
*/
/**
* A simple class which stores a value, and triggers registered callbacks when
* the value is changed via its setter.
*
* An implementor might use other observable patterns such as MobX or Redux to
* get behavior like this. But this is a pretty minimal approach that will
* likely work for a number of use cases.
*/
var ValueNotifier = class {
	get value() {
		return this._value;
	}
	set value(v) {
		this.setValue(v);
	}
	setValue(v, force = false) {
		const update = force || !Object.is(v, this._value);
		this._value = v;
		if (update) this.updateObservers();
	}
	constructor(defaultValue) {
		this.subscriptions = /* @__PURE__ */ new Map();
		this.updateObservers = () => {
			for (const [callback, { disposer }] of this.subscriptions) callback(this._value, disposer);
		};
		if (defaultValue !== void 0) this.value = defaultValue;
	}
	addCallback(callback, consumerHost, subscribe) {
		if (!subscribe) {
			callback(this.value);
			return;
		}
		if (!this.subscriptions.has(callback)) this.subscriptions.set(callback, {
			disposer: () => {
				this.subscriptions.delete(callback);
			},
			consumerHost
		});
		const { disposer } = this.subscriptions.get(callback);
		callback(this.value, disposer);
	}
	clearCallbacks() {
		this.subscriptions.clear();
	}
};

//#endregion
//#region ../../node_modules/.pnpm/@lit+context@1.1.6/node_modules/@lit/context/development/lib/controllers/context-provider.js
/**
* @license
* Copyright 2021 Google LLC
* SPDX-License-Identifier: BSD-3-Clause
*/
var ContextProviderEvent = class extends Event {
	/**
	*
	* @param context the context which this provider can provide
	* @param contextTarget the original context target of the provider
	*/
	constructor(context, contextTarget) {
		super("context-provider", {
			bubbles: true,
			composed: true
		});
		this.context = context;
		this.contextTarget = contextTarget;
	}
};
/**
* A ReactiveController which adds context provider behavior to a
* custom element.
*
* This controller simply listens to the `context-request` event when
* the host is connected to the DOM and registers the received callbacks
* against its observable Context implementation.
*
* The controller may also be attached to any HTML element in which case it's
* up to the user to call hostConnected() when attached to the DOM. This is
* done automatically for any custom elements implementing
* ReactiveControllerHost.
*/
var ContextProvider = class extends ValueNotifier {
	constructor(host, contextOrOptions, initialValue) {
		super(contextOrOptions.context !== void 0 ? contextOrOptions.initialValue : initialValue);
		this.onContextRequest = (ev) => {
			if (ev.context !== this.context) return;
			const consumerHost = ev.contextTarget ?? ev.composedPath()[0];
			if (consumerHost === this.host) return;
			ev.stopPropagation();
			this.addCallback(ev.callback, consumerHost, ev.subscribe);
		};
		/**
		* When we get a provider request event, that means a child of this element
		* has just woken up. If it's a provider of our context, then we may need to
		* re-parent our subscriptions, because is a more specific provider than us
		* for its subtree.
		*/
		this.onProviderRequest = (ev) => {
			if (ev.context !== this.context) return;
			if ((ev.contextTarget ?? ev.composedPath()[0]) === this.host) return;
			const seen = /* @__PURE__ */ new Set();
			for (const [callback, { consumerHost }] of this.subscriptions) {
				if (seen.has(callback)) continue;
				seen.add(callback);
				consumerHost.dispatchEvent(new ContextRequestEvent(this.context, consumerHost, callback, true));
			}
			ev.stopPropagation();
		};
		this.host = host;
		if (contextOrOptions.context !== void 0) this.context = contextOrOptions.context;
		else this.context = contextOrOptions;
		this.attachListeners();
		this.host.addController?.(this);
	}
	attachListeners() {
		this.host.addEventListener("context-request", this.onContextRequest);
		this.host.addEventListener("context-provider", this.onProviderRequest);
	}
	hostConnected() {
		this.host.dispatchEvent(new ContextProviderEvent(this.context, this.host));
	}
};

//#endregion
//#region ../utils/dist/dom/event.js
/** Resolve the deepest event target, preferring composedPath for shadow DOM. */
function resolveEventTarget(event) {
	const path = event.composedPath();
	return path.length > 0 ? path[0] : event.target;
}
function onEvent(target, type, options) {
	return new Promise((resolve, reject) => {
		const handleAbort = () => {
			reject(options?.signal?.reason ?? "Aborted");
		};
		if (options?.signal?.aborted) {
			handleAbort();
			return;
		}
		options?.signal?.addEventListener("abort", handleAbort, { once: true });
		target.addEventListener(type, (event) => {
			options?.signal?.removeEventListener("abort", handleAbort);
			resolve(event);
		}, {
			...options,
			once: true
		});
	});
}

//#endregion
//#region ../utils/dist/dom/interactive.js
const INTERACTIVE_SELECTOR = [
	"button",
	"input",
	"select",
	"textarea",
	"a[href]",
	"[role=\"button\"]",
	"[role=\"menu\"]",
	"[role=\"menuitem\"]",
	"[role=\"menuitemcheckbox\"]",
	"[role=\"menuitemradio\"]",
	"[role=\"slider\"]",
	"[data-interactive]"
].join(",");
const EDITABLE_SELECTOR = [
	"textarea",
	"select",
	"input:not([type])",
	...[
		"text",
		"search",
		"url",
		"tel",
		"email",
		"password",
		"number"
	].map((type) => `input[type="${type}"]`),
	"[contenteditable]:not([contenteditable=\"false\"])"
].join(",");
function isEditableElement(el) {
	return el.matches(EDITABLE_SELECTOR);
}
/** Whether the keyboard event target is an editable element (input, textarea, etc). */
function isEditableTarget(event) {
	const target = resolveEventTarget(event);
	return target instanceof Element && isEditableElement(target);
}
/** Whether the event originated from an interactive control (button, slider, etc). */
function isInteractiveTarget(event) {
	const target = resolveEventTarget(event);
	if (!(target instanceof Element)) return false;
	return target.closest(INTERACTIVE_SELECTOR) !== null;
}
const ACTIVATION_KEYS = /* @__PURE__ */ new Set([" ", "Enter"]);
/**
* Selector for elements that use Space/Enter as a native activation key. Narrower than `INTERACTIVE_SELECTOR` —
* excludes editable elements like `input`, `textarea`, `select` where Space/Enter is text input, not activation.
*/
const ACTIVATABLE_SELECTOR = "button,a[href],[role=\"slider\"],[role=\"button\"]";
/** Whether the event is an activation key on an activatable element (button, link, slider). */
function isInteractiveActivation(event) {
	if (!ACTIVATION_KEYS.has(event.key)) return false;
	const target = resolveEventTarget(event);
	return target instanceof Element && target.matches(ACTIVATABLE_SELECTOR);
}

//#endregion
//#region ../utils/dist/dom/time-ranges.js
/** Converts a TimeRanges object to an array of [start, end] tuples. */
function serializeTimeRanges(ranges) {
	const result = [];
	for (let i = 0; i < ranges.length; i++) result.push([ranges.start(i), ranges.end(i)]);
	return result;
}

//#endregion
//#region ../core/dist/dev/dom/feature.js
function definePlayerFeature(definition) {
	const preserved = Object.values(definition.config ?? {}).map((entry) => entry.state);
	return {
		...definition,
		...preserved.length > 0 ? { preserve: preserved } : {}
	};
}
/** Merge the configuration declarations from the selected player features. */
function combinePlayerFeatureConfigs(features) {
	const definitions = features.map((feature) => feature.config ?? {});
	{
		const seen = /* @__PURE__ */ new Set();
		for (const definition of definitions) for (const key of Object.keys(definition)) {
			if (seen.has(key)) console.warn(`[vjs-core] duplicate config key "${key}" — later feature overwrites earlier one`);
			seen.add(key);
		}
	}
	return Object.assign({}, ...definitions);
}
/** Forward one configuration input through its feature-owned private action. */
function setPlayerConfigValue(store, entry, value) {
	const action = store[entry.action];
	if (typeof action !== "function") throw new TypeError(`Missing config action "${String(entry.action)}"`);
	action(value);
}

//#endregion
//#region ../media/dist/dev/core/predicate.js
function hasMetadata(media) {
	return media.readyState >= 1;
}
function isMediaPauseCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.paused) && !isUndefined(media.ended) && isFunction(media.pause);
}
function isMediaSeekCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.currentTime) && !isUndefined(media.duration) && !isUndefined(media.seeking);
}
function isMediaSourceCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.src) && !isUndefined(media.currentSrc) && !isUndefined(media.readyState) && isFunction(media.load);
}
function isMediaVolumeCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.volume) && !isUndefined(media.muted);
}
/**
* Whether the media reports a mute at all, which is a narrower question than `isMediaVolumeCapable`: an embed can take
* a mute command while offering no way to set a level.
*/
function isMediaMutedCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.muted);
}
function isMediaPlaybackRateCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.playbackRate);
}
/**
* Only `requestPictureInPicture` is required. A native video element carries it but leaves exiting to `document`, so
* demanding the pair would rule out the one media that most certainly can.
*/
function isMediaPictureInPictureCapable(value) {
	if (!isObject(value)) return false;
	return isFunction(value.requestPictureInPicture);
}
function isMediaBufferCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.buffered) && media.buffered !== EMPTY_TIME_RANGES && !isUndefined(media.seekable) && media.seekable !== EMPTY_TIME_RANGES;
}
function isMediaErrorCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.error);
}
function isMediaTextTrackCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.textTracks) && media.textTracks !== EMPTY_TEXT_TRACKS;
}
function isMediaVideoRenditionCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.videoRenditions);
}
function isMediaAudioTrackCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.audioTracks);
}
function isMediaVideoDimensionsCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.videoWidth) && !isUndefined(media.videoHeight);
}
function isMediaRemotePlaybackCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return isObject(media.remote) && media.remote !== EMPTY_REMOTE;
}
function isMediaStreamTypeCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.streamType);
}
function isMediaContentDataCapable(value) {
	if (!isObject(value)) return false;
	return !isUndefined(value.contentData);
}
function isMediaLiveCapable(value) {
	if (!isObject(value)) return false;
	const media = value;
	return !isUndefined(media.liveEdgeStart) && !isUndefined(media.targetLiveWindow);
}
function isQuerySelectorAllCapable(value) {
	return isObject(value) && "querySelectorAll" in value && isFunction(value.querySelectorAll);
}

//#endregion
//#region ../core/dist/dev/dom/store/features/audio-track.js
function getTrackValue(track, index) {
	return track.id || String(index);
}
function toMediaTrack(track) {
	return {
		...track.id !== void 0 && { id: track.id },
		...track.kind !== void 0 && { kind: track.kind },
		label: track.label,
		language: track.language,
		enabled: track.enabled
	};
}
const audioTrackFeature = definePlayerFeature({
	name: "audioTrack",
	state: ({ target }) => ({
		audioTrackList: [],
		selectAudioTrack(value) {
			const { media } = target();
			if (!isMediaAudioTrackCapable(media)) return;
			const tracks = [...media.audioTracks];
			const track = tracks.find((candidate, index) => getTrackValue(candidate, index) === value);
			if (!track) return;
			for (const candidate of tracks) candidate.enabled = candidate === track;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		let audioTracks = null;
		let cleanup = null;
		const getAudioTracks = () => isMediaAudioTrackCapable(media) ? media.audioTracks : null;
		const sync = (list = getAudioTracks()) => {
			set({ audioTrackList: list ? [...list].map(toMediaTrack) : [] });
		};
		const bind = () => {
			const nextAudioTracks = getAudioTracks();
			if (nextAudioTracks === audioTracks) {
				sync(nextAudioTracks);
				return;
			}
			cleanup?.abort();
			cleanup = new AbortController();
			audioTracks = nextAudioTracks;
			if (audioTracks) {
				listen(audioTracks, "addtrack", () => sync(audioTracks), { signal: cleanup.signal });
				listen(audioTracks, "removetrack", () => sync(audioTracks), { signal: cleanup.signal });
				listen(audioTracks, "change", () => sync(audioTracks), { signal: cleanup.signal });
			}
			sync(audioTracks);
		};
		bind();
		listen(media, "loadstart", bind, { signal });
		signal.addEventListener("abort", () => cleanup?.abort(), { once: true });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/buffer.js
const bufferFeature = definePlayerFeature({
	name: "buffer",
	state: () => ({
		buffered: [],
		seekable: []
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaBufferCapable(media)) return;
		const sync = () => set({
			buffered: serializeTimeRanges(media.buffered),
			seekable: serializeTimeRanges(media.seekable)
		});
		sync();
		listen(media, "progress", sync, { signal });
		listen(media, "emptied", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/gesture/region.js
/**
* Determine which named region a pointer position falls into.
*
* Regions divide the container width equally based on how many are active: - `left` + `right` → halves (50% / 50%) -
* `left` + `center` + `right` → thirds (33% / 34% / 33%)
*
* Single region: `left` covers the left half, `right` the right half, and `center` covers the full surface. Partial
* two-region combos (e.g. `left` + `center`) use the same natural zones — positions outside all active zones return
* `null` so full-surface gestures can handle them.
*/
function resolveRegion(clientX, containerRect, activeRegions) {
	if (activeRegions.size === 0) return null;
	const relativeX = clientX - containerRect.left;
	const width = containerRect.width;
	if (width === 0) return null;
	const ratio = relativeX / width;
	if (activeRegions.size === 2 && activeRegions.has("left") && activeRegions.has("right")) return ratio < .5 ? "left" : "right";
	if (activeRegions.size === 3) {
		if (ratio < 1 / 3) return "left";
		if (ratio < 2 / 3) return "center";
		return "right";
	}
	if (activeRegions.has("left") && ratio < .5) return "left";
	if (activeRegions.has("right") && ratio >= .5) return "right";
	if (activeRegions.has("center")) {
		if (activeRegions.size === 1) return "center";
		if (ratio >= 1 / 3 && ratio < 2 / 3) return "center";
	}
	return null;
}

//#endregion
//#region ../core/dist/dev/dom/gesture/coordinator.js
const TAP_THRESHOLD$1 = 250;
var GestureCoordinator = class {
	#target;
	#bindings = [];
	#recognizers = /* @__PURE__ */ new Set();
	#disconnect = null;
	#subscribers = /* @__PURE__ */ new Set();
	constructor(target) {
		this.#target = target;
	}
	get bindings() {
		return this.#bindings;
	}
	subscribe(callback) {
		this.#subscribers.add(callback);
		return () => this.#subscribers.delete(callback);
	}
	/**
	* Whether a registered binding claims this tap for the given action. A claimed tap belongs to the gesture layer, so
	* callers should leave it alone. Taps on interactive targets (buttons, sliders) are never claimed — the same
	* filtering the pointerup listener applies. A disabled binding still claims: disabling a gesture opts out of the
	* action, it doesn't hand the tap back to a fallback handler.
	*/
	claimsTap(event, action) {
		if (isInteractiveTarget(event)) return false;
		return this.#bindings.some((b) => b.type === "tap" && b.action === action && (!b.pointer || b.pointer === event.pointerType));
	}
	add(binding) {
		const wrapped = {
			...binding,
			onActivate: (event) => {
				if (this.#subscribers.size > 0) {
					const activateEvent = {
						type: binding.type,
						source: "gesture",
						action: binding.action,
						value: binding.value,
						region: binding.region,
						pointer: binding.pointer,
						event
					};
					for (const cb of this.#subscribers) try {
						cb(activateEvent);
					} catch (error) {
						console.warn("[vjs-gesture] subscribe callback threw:", error);
					}
				}
				binding.onActivate(event);
			}
		};
		this.#bindings.push(wrapped);
		this.#recognizers.add(wrapped.recognizer);
		this.#connect();
		let removed = false;
		return () => {
			if (removed) return;
			removed = true;
			const idx = this.#bindings.indexOf(wrapped);
			if (idx !== -1) this.#bindings.splice(idx, 1);
			this.#maybeDisconnect();
		};
	}
	#connect() {
		if (this.#disconnect) return;
		this.#disconnect = new AbortController();
		const { signal } = this.#disconnect;
		let pointerDownTime = 0;
		listen(this.#target, "pointerdown", (event) => {
			if (event.button !== 0) return;
			pointerDownTime = Date.now();
		}, { signal });
		listen(this.#target, "pointerup", (event) => {
			if (event.button !== 0) return;
			if (Date.now() - pointerDownTime > TAP_THRESHOLD$1) return;
			if (isInteractiveTarget(event)) return;
			const pointerType = event.pointerType;
			const clientX = event.clientX;
			const target = this.#target;
			const bindings = this.#bindings;
			const matches = { resolve: (type) => matchBindings(bindings, type, pointerType, clientX, target) };
			for (const recognizer of this.#recognizers) recognizer.handleUp(matches, event);
		}, { signal });
	}
	#maybeDisconnect() {
		if (this.#bindings.length > 0) return;
		for (const recognizer of this.#recognizers) recognizer.reset();
		this.#recognizers.clear();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
};
const coordinators = /* @__PURE__ */ new WeakMap();
/** Look up the gesture coordinator for a target element, if one exists. */
function findGestureCoordinator(target) {
	return coordinators.get(target);
}
function getGestureCoordinator(target) {
	let coordinator = coordinators.get(target);
	if (!coordinator) {
		coordinator = new GestureCoordinator(target);
		coordinators.set(target, coordinator);
	}
	return coordinator;
}
function matchBindings(bindings, type, pointerType, clientX, target) {
	const rect = target.getBoundingClientRect();
	const activeRegions = getActiveRegions(bindings, type, pointerType);
	const region = activeRegions.size > 0 ? resolveRegion(clientX, rect, activeRegions) : null;
	const matches = [];
	for (const binding of bindings) {
		if (binding.disabled) continue;
		if (binding.type !== type) continue;
		if (binding.pointer && binding.pointer !== pointerType) continue;
		if (binding.region) {
			if (binding.region !== region) continue;
		} else if (region !== null) continue;
		matches.push(binding);
	}
	return matches;
}
function getActiveRegions(bindings, type, pointerType) {
	const regions = /* @__PURE__ */ new Set();
	for (const binding of bindings) {
		if (binding.disabled) continue;
		if (binding.type !== type) continue;
		if (binding.pointer && binding.pointer !== pointerType) continue;
		if (binding.region) regions.add(binding.region);
	}
	return regions;
}

//#endregion
//#region ../core/dist/dev/dom/presentation/remote-playback.js
function resolveRemote(media) {
	const target = media;
	if (isObject(target.remote) && "state" in target.remote && "prompt" in target.remote) return target.remote;
}
function isRemotePlaybackConnected(media) {
	return resolveRemote(media)?.state === "connected";
}
function isRemotePlaybackConnecting(media) {
	return resolveRemote(media)?.state === "connecting";
}
async function requestRemotePlayback(media) {
	const remote = resolveRemote(media);
	if (!remote) throw new DOMException("Remote playback not supported", "NotSupportedError");
	return remote.prompt();
}

//#endregion
//#region ../core/dist/dev/dom/store/features/controls.js
const IDLE_DELAY = 2e3;
const TAP_THRESHOLD = 250;
const TOUCH_SETTLE_DELAY = 500;
const controlsActionsByRequest = /* @__PURE__ */ new WeakMap();
const controlsFeature = definePlayerFeature({
	name: "controls",
	state: ({ get, set }) => {
		const fallbackRequestControlsLock = () => {
			set({ controlsVisible: true });
			return () => {};
		};
		const fallbackToggleControls = () => {
			const next = !get().userActive;
			set({
				userActive: next,
				controlsVisible: next
			});
			return next;
		};
		const actions = createControlsActions(fallbackRequestControlsLock, fallbackToggleControls);
		controlsActionsByRequest.set(actions.requestControlsLock, actions);
		return {
			userActive: true,
			controlsVisible: true,
			requestControlsLock: actions.requestControlsLock,
			toggleControls: actions.toggleControls
		};
	},
	attach({ target, signal, get, set }) {
		const { media, container } = target;
		if (!isMediaPauseCapable(media) || isNull(container)) {
			if (isNull(container)) console.warn("[vjs] controlsFeature requires a container element for activity tracking.");
			return;
		}
		let idleTimer;
		let controlsLockCount = 0;
		const computeVisible = (userActive) => {
			return controlsLockCount > 0 || userActive || media.paused || isRemotePlaybackConnected(media) || isRemotePlaybackConnecting(media);
		};
		function clearIdle() {
			clearTimeout(idleTimer);
			idleTimer = void 0;
		}
		function scheduleIdle() {
			clearIdle();
			if (controlsLockCount > 0) return;
			idleTimer = setTimeout(setInactive, IDLE_DELAY);
		}
		function setActive() {
			if (!get().userActive) set({
				userActive: true,
				controlsVisible: true
			});
			scheduleIdle();
		}
		function setInactive() {
			clearIdle();
			set({
				userActive: false,
				controlsVisible: computeVisible(false)
			});
		}
		function requestControlsLock() {
			controlsLockCount++;
			clearIdle();
			if (!get().controlsVisible) set({ controlsVisible: true });
			let released = false;
			return () => {
				if (released || signal.aborted) return;
				released = true;
				controlsLockCount--;
				if (controlsLockCount === 0) setActive();
			};
		}
		function toggleControls() {
			if (get().controlsVisible) setInactive();
			else setActive();
			return get().controlsVisible;
		}
		const actions = controlsActionsByRequest.get(get().requestControlsLock);
		actions.setDelegates(requestControlsLock, toggleControls);
		let pointerDownTime = 0;
		let lastTouchAt = 0;
		const isRecentTouch = () => lastTouchAt > 0 && Date.now() - lastTouchAt < TOUCH_SETTLE_DELAY;
		function onPointerDown(event) {
			pointerDownTime = Date.now();
			if (event.pointerType === "touch") lastTouchAt = pointerDownTime;
		}
		function onPointerUp(event) {
			if (event.pointerType === "touch") lastTouchAt = Date.now();
			if (event.pointerType === "touch" && Date.now() - pointerDownTime < TAP_THRESHOLD) {
				if (findGestureCoordinator(container)?.claimsTap(event, "toggleControls")) return;
				const isMediaOrContainer = [media, container].includes(event.target);
				if (get().controlsVisible && isMediaOrContainer) setInactive();
				else setActive();
			} else setActive();
		}
		const onPlaybackChange = () => {
			const { userActive } = get();
			set({ controlsVisible: computeVisible(userActive) });
			if (!media.paused && userActive) scheduleIdle();
		};
		function onPointerMove(event) {
			if (event.pointerType === "touch") {
				if (get().userActive) scheduleIdle();
				return;
			}
			setActive();
		}
		listen(container, "pointermove", onPointerMove, { signal });
		listen(container, "pointerdown", onPointerDown, { signal });
		listen(container, "pointerup", onPointerUp, { signal });
		listen(container, "keyup", setActive, { signal });
		listen(container, "focusin", () => {
			if (isRecentTouch()) return;
			setActive();
		}, { signal });
		listen(container, "mouseleave", () => {
			if (isRecentTouch()) return;
			setInactive();
		}, { signal });
		listen(media, "play", onPlaybackChange, { signal });
		listen(media, "pause", onPlaybackChange, { signal });
		listen(media, "ended", onPlaybackChange, { signal });
		if (isMediaRemotePlaybackCapable(media)) {
			const onCastChange = () => {
				const { userActive } = get();
				set({ controlsVisible: computeVisible(userActive) });
			};
			listen(media.remote, "connect", onCastChange, { signal });
			listen(media.remote, "connecting", onCastChange, { signal });
			listen(media.remote, "disconnect", onCastChange, { signal });
		}
		signal.addEventListener("abort", () => {
			actions.reset();
			controlsLockCount = 0;
			clearIdle();
		}, { once: true });
		scheduleIdle();
	}
});
function createControlsActions(fallbackRequestControlsLock, fallbackToggleControls) {
	let requestControlsLockDelegate = fallbackRequestControlsLock;
	let toggleControlsDelegate = fallbackToggleControls;
	const locks = /* @__PURE__ */ new Set();
	const requestControlsLock = () => {
		const lock = { release: requestControlsLockDelegate() };
		let released = false;
		locks.add(lock);
		return () => {
			if (released) return;
			released = true;
			locks.delete(lock);
			lock.release();
		};
	};
	const toggleControls = () => toggleControlsDelegate();
	const actions = {
		requestControlsLock,
		toggleControls,
		setDelegates(nextRequestControlsLock, nextToggleControls) {
			if (nextRequestControlsLock !== requestControlsLockDelegate) {
				requestControlsLockDelegate = nextRequestControlsLock;
				for (const lock of locks) {
					lock.release();
					lock.release = nextRequestControlsLock();
				}
			}
			toggleControlsDelegate = nextToggleControls;
		},
		reset() {
			actions.setDelegates(fallbackRequestControlsLock, fallbackToggleControls);
		}
	};
	return actions;
}

//#endregion
//#region ../core/dist/dev/dom/store/features/error.js
const errorFeature = definePlayerFeature({
	name: "error",
	state: ({ set }) => ({
		error: null,
		dismissError() {
			set({ error: null });
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaErrorCapable(media)) return;
		const syncError = () => set({ error: media.error });
		listen(media, "error", syncError, { signal });
		listen(media, "emptied", () => set({ error: null }), { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/presentation/fullscreen.js
function isFullscreenEnabled() {
	const doc = document;
	if (doc.fullscreenEnabled || doc.webkitFullscreenEnabled) return true;
	const video = document.createElement("video");
	return isFunction(video.webkitSetPresentationMode);
}
function getFullscreenElement() {
	const doc = document;
	return doc.fullscreenElement ?? doc.webkitFullscreenElement ?? null;
}
function matchesFullscreen(element) {
	if (!(element instanceof Element)) return false;
	try {
		return element.matches(":fullscreen");
	} catch {
		return false;
	}
}
function isFullscreen(container, media) {
	if (media.webkitPresentationMode === "fullscreen") return true;
	const fullscreenElement = getFullscreenElement();
	if (fullscreenElement && (fullscreenElement === container || fullscreenElement === media)) return true;
	if (matchesFullscreen(container) || matchesFullscreen(media)) return true;
	return media.isFullscreen ?? false;
}
async function requestFullscreen(container, media) {
	const doc = document;
	if (container && (doc.fullscreenEnabled || doc.webkitFullscreenEnabled)) {
		const el = container;
		if (isFunction(el.requestFullscreen)) return el.requestFullscreen();
		if (isFunction(el.webkitRequestFullscreen)) return el.webkitRequestFullscreen();
	}
	const webkitVideo = media;
	if (isFunction(webkitVideo.webkitSetPresentationMode)) {
		webkitVideo.webkitSetPresentationMode("fullscreen");
		return;
	}
	const video = media;
	if (isFunction(video.requestFullscreen)) return video.requestFullscreen();
}
async function exitFullscreen(media) {
	const doc = document;
	const webkitVideo = media;
	if (webkitVideo.webkitPresentationMode === "fullscreen" && isFunction(webkitVideo.webkitSetPresentationMode)) {
		webkitVideo.webkitSetPresentationMode("inline");
		return;
	}
	if (isFunction(doc.exitFullscreen)) return doc.exitFullscreen();
	if (isFunction(doc.webkitExitFullscreen)) return doc.webkitExitFullscreen();
	const video = media;
	if (isFunction(video.exitFullscreen)) return video.exitFullscreen();
}

//#endregion
//#region ../core/dist/dev/dom/presentation/pip.js
function isPictureInPictureEnabled() {
	if (document.pictureInPictureEnabled) {
		const isSafari = /.*Version\/.*Safari\/.*/.test(navigator.userAgent);
		const isPWA = typeof matchMedia === "function" && matchMedia("(display-mode: standalone)").matches;
		return !isSafari || !isPWA;
	}
	const video = document.createElement("video");
	return isFunction(video.webkitSetPresentationMode);
}
/**
* Whether this media can enter picture-in-picture at all, which is a separate question from whether the browser
* supports it. Mirrors the branches `requestPictureInPicture` takes below, so anything it would refuse to act on
* reports as incapable here — an iframe embed whose provider has no picture-in-picture can never enter it, however
* capable the browser is.
*/
function isPictureInPictureCapable(media) {
	if (isFunction(media.webkitSetPresentationMode)) return true;
	return isMediaPictureInPictureCapable(media);
}
function isPictureInPicture(media) {
	if (media.webkitPresentationMode === "picture-in-picture") return true;
	if (document.pictureInPictureElement === media) return true;
	return media.isPictureInPicture ?? false;
}
async function requestPictureInPicture(media) {
	const webkitVideo = media;
	if (isFunction(webkitVideo.webkitSetPresentationMode)) {
		webkitVideo.webkitSetPresentationMode("picture-in-picture");
		return;
	}
	const video = media;
	if (isFunction(video.requestPictureInPicture)) return video.requestPictureInPicture();
}
async function exitPictureInPicture(media) {
	const webkitVideo = media;
	if (webkitVideo.webkitPresentationMode === "picture-in-picture" && isFunction(webkitVideo.webkitSetPresentationMode)) {
		webkitVideo.webkitSetPresentationMode("inline");
		return;
	}
	if (isFunction(document.exitPictureInPicture)) return document.exitPictureInPicture();
	const video = media;
	if (isFunction(video.exitPictureInPicture)) return video.exitPictureInPicture();
}

//#endregion
//#region ../core/dist/dev/dom/store/features/fullscreen.js
const fullscreenFeature = definePlayerFeature({
	name: "fullscreen",
	state: ({ target }) => ({
		fullscreen: false,
		fullscreenAvailability: "unavailable",
		async requestFullscreen() {
			const { media, container } = target();
			if (isPictureInPicture(media)) await exitPictureInPicture(media);
			return requestFullscreen(container, media);
		},
		async exitFullscreen() {
			const { media } = target();
			return exitFullscreen(media);
		},
		async toggleFullscreen() {
			const { media, container } = target();
			if (isFullscreen(container, media)) return exitFullscreen(media);
			if (isPictureInPicture(media)) await exitPictureInPicture(media);
			return requestFullscreen(container, media);
		}
	}),
	attach({ target, signal, set }) {
		const { media, container } = target;
		set({ fullscreenAvailability: isFullscreenEnabled() ? "available" : "unsupported" });
		const sync = () => set({ fullscreen: isFullscreen(container, media) });
		sync();
		listen(document, "fullscreenchange", sync, { signal });
		listen(document, "webkitfullscreenchange", sync, { signal });
		if ("webkitPresentationMode" in media) listen(media, "webkitpresentationmodechanged", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/live.js
/**
* Player feature exposing `liveEdgeStart` and `targetLiveWindow` in store state for media that implements
* `MediaLiveCapability` (currently `HlsJsMedia` and its delegates).
*
* - `liveEdgeStart` — presentation time marking the start of the Live Edge Window. Playing at the live edge when
*   `currentTime >= liveEdgeStart`. `NaN` when the stream isn't live or the value is unknown.
* - `targetLiveWindow` — `0` for standard latency live, `Infinity` for DVR, `NaN` for on-demand or unknown.
*
* Included by the {@link liveVideoFeatures} and {@link liveAudioFeatures} presets; apps can also compose it into a
* custom preset.
*
* @see https://github.com/video-dev/media-ui-extensions/blob/main/proposals/0007-live-edge.md
*/
const liveFeature = definePlayerFeature({
	name: "live",
	state: () => ({
		liveEdgeStart: NaN,
		targetLiveWindow: NaN
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaLiveCapable(media)) return;
		const sync = () => set({
			liveEdgeStart: media.liveEdgeStart,
			targetLiveWindow: media.targetLiveWindow
		});
		sync();
		listen(media, "targetlivewindowchange", sync, { signal });
		listen(media, "streamtypechange", sync, { signal });
		listen(media, "loadedmetadata", sync, { signal });
		listen(media, "canplay", sync, { signal });
		listen(media, "progress", sync, { signal });
		listen(media, "durationchange", sync, { signal });
		listen(media, "timeupdate", sync, { signal });
		listen(media, "emptied", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/metadata.js
const MEDIA_TITLE = Symbol("@videojs/media-title");
const USER_TITLE = Symbol("@videojs/user-title");
const SET_USER_TITLE = Symbol("@videojs/set-user-title");
const DEFAULT_TITLE = "";
const MEDIA_POSTER = Symbol("@videojs/media-poster");
const USER_POSTER = Symbol("@videojs/user-poster");
const SET_USER_POSTER = Symbol("@videojs/set-user-poster");
const DEFAULT_POSTER = "";
/**
* Resolves content metadata into player state, preferring what the author set over what the media carries. Included in
* the standard audio, video, and live presets.
*/
const metadataFeature = definePlayerFeature({
	name: "metadata",
	config: {
		/** The title to display. Takes precedence over the title the media carries. */
		title: {
			action: SET_USER_TITLE,
			state: USER_TITLE,
			html: { attribute: "content-title" }
		},
		/** The poster to display. Takes precedence over the poster the media carries. */
		poster: {
			action: SET_USER_POSTER,
			state: USER_POSTER
		}
	},
	state: ({ set }) => ({
		[MEDIA_TITLE]: void 0,
		[USER_TITLE]: void 0,
		[SET_USER_TITLE]: (value) => set({ [USER_TITLE]: value }),
		[MEDIA_POSTER]: void 0,
		[USER_POSTER]: void 0,
		[SET_USER_POSTER]: (value) => set({ [USER_POSTER]: value })
	}),
	derived: {
		/** The resolved content title. Set it through the player, not through the store. */
		title: ({ get }) => get()[USER_TITLE] ?? get()[MEDIA_TITLE] ?? DEFAULT_TITLE,
		/**
		* The resolved poster URL, independent of the media element's own `poster`. Set it through the player, not through
		* the store.
		*/
		poster: ({ get }) => get()[USER_POSTER] ?? get()[MEDIA_POSTER] ?? DEFAULT_POSTER
	},
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaContentDataCapable(media)) return;
		const sync = () => set({
			[MEDIA_TITLE]: media.contentData?.title,
			[MEDIA_POSTER]: media.contentData?.poster
		});
		sync();
		listen(media, "contentdatachange", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/pip.js
const pipFeature = definePlayerFeature({
	name: "pip",
	state: ({ target }) => ({
		pip: false,
		pipAvailability: "unavailable",
		async requestPictureInPicture() {
			const { media, container } = target();
			if (isFullscreen(container, media)) await exitFullscreen(media);
			return requestPictureInPicture(media);
		},
		async exitPictureInPicture() {
			const { media } = target();
			return exitPictureInPicture(media);
		},
		async togglePictureInPicture() {
			const { media, container } = target();
			if (isPictureInPicture(media)) return exitPictureInPicture(media);
			if (isFullscreen(container, media)) await exitFullscreen(media);
			return requestPictureInPicture(media);
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		set({ pipAvailability: isPictureInPictureEnabled() && isPictureInPictureCapable(media) ? "available" : "unsupported" });
		const sync = () => set({ pip: isPictureInPicture(media) });
		sync();
		listen(media, "enterpictureinpicture", sync, { signal });
		listen(media, "leavepictureinpicture", sync, { signal });
		if ("webkitPresentationMode" in media) listen(media, "webkitpresentationmodechanged", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/playback.js
const playbackFeature = definePlayerFeature({
	name: "playback",
	state: ({ target }) => ({
		paused: true,
		ended: false,
		started: false,
		waiting: false,
		play() {
			return target().media.play();
		},
		pause() {
			const { media } = target();
			if (isMediaPauseCapable(media)) media.pause();
		},
		togglePaused() {
			const media = target().media;
			if (!isMediaPauseCapable(media)) return false;
			if (media.paused) {
				media.play();
				return true;
			}
			media.pause();
			return false;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaPauseCapable(media) || !isMediaSeekCapable(media) || !isMediaSourceCapable(media)) return;
		const sync = () => set({
			paused: media.paused,
			ended: media.ended,
			started: !media.paused || media.currentTime > 0,
			waiting: media.readyState < HTMLMediaElement.HAVE_FUTURE_DATA && !media.paused
		});
		sync();
		listen(media, "emptied", sync, { signal });
		listen(media, "play", sync, { signal });
		listen(media, "pause", sync, { signal });
		listen(media, "ended", sync, { signal });
		listen(media, "playing", sync, { signal });
		listen(media, "waiting", sync, { signal });
		listen(media, "seeked", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/playback-rate.js
const DEFAULT_RATES = [
	.2,
	.5,
	.7,
	1,
	1.2,
	1.5,
	1.7,
	2
];
const playbackRateFeature = definePlayerFeature({
	name: "playbackRate",
	state: ({ target }) => ({
		playbackRates: DEFAULT_RATES,
		playbackRate: 1,
		setPlaybackRate(rate) {
			const { media } = target();
			if (isMediaPlaybackRateCapable(media)) media.playbackRate = rate;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaPlaybackRateCapable(media)) return;
		const sync = () => set({ playbackRate: media.playbackRate });
		sync();
		listen(media, "ratechange", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/quality.js
const QUALITY_AUTO_VALUE = "auto";
function getRenditionValue(rendition, index) {
	return rendition.id || String(index);
}
function toMediaRendition(rendition) {
	return {
		...rendition.id !== void 0 && { id: rendition.id },
		...rendition.width !== void 0 && { width: rendition.width },
		...rendition.height !== void 0 && { height: rendition.height },
		...rendition.bitrate !== void 0 && { bitrate: rendition.bitrate },
		...rendition.frameRate !== void 0 && { frameRate: rendition.frameRate },
		...rendition.codec !== void 0 && { codec: rendition.codec },
		selected: rendition.selected
	};
}
function getSize(rendition) {
	if (rendition.width && rendition.height) return Math.min(rendition.width, rendition.height);
	return rendition.height ?? rendition.width;
}
const qualityFeature = definePlayerFeature({
	name: "quality",
	state: ({ target }) => ({
		videoRenditionList: [],
		activeVideoRendition: null,
		selectVideoRendition(value) {
			const { media } = target();
			if (!isMediaVideoRenditionCapable(media)) return;
			if (value === QUALITY_AUTO_VALUE) {
				media.videoRenditions.selectedIndex = -1;
				return;
			}
			const index = [...media.videoRenditions].findIndex((rendition, renditionIndex) => getRenditionValue(rendition, renditionIndex) === value);
			if (index !== -1) media.videoRenditions.selectedIndex = index;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		let videoRenditions = null;
		let cleanup = null;
		const getVideoRenditions = () => isMediaVideoRenditionCapable(media) ? media.videoRenditions : null;
		const getActiveRendition = (list) => {
			if (!list) return null;
			const renditions = [...list];
			const active = renditions.find((rendition) => rendition.active);
			if (active) return active;
			if (!isMediaVideoDimensionsCapable(media) || !media.videoWidth && !media.videoHeight) return null;
			const size = getSize({
				width: media.videoWidth || void 0,
				height: media.videoHeight || void 0
			});
			const matches = renditions.filter((rendition) => getSize(rendition) === size);
			return matches.length === 1 ? matches[0] : null;
		};
		const sync = (list = getVideoRenditions()) => {
			const active = getActiveRendition(list);
			set({
				videoRenditionList: list ? [...list].map(toMediaRendition) : [],
				activeVideoRendition: active ? toMediaRendition(active) : null
			});
		};
		const bind = () => {
			const nextVideoRenditions = getVideoRenditions();
			if (nextVideoRenditions === videoRenditions) {
				sync(nextVideoRenditions);
				return;
			}
			cleanup?.abort();
			cleanup = new AbortController();
			videoRenditions = nextVideoRenditions;
			if (videoRenditions) {
				listen(videoRenditions, "addrendition", () => sync(videoRenditions), { signal: cleanup.signal });
				listen(videoRenditions, "removerendition", () => sync(videoRenditions), { signal: cleanup.signal });
				listen(videoRenditions, "change", () => sync(videoRenditions), { signal: cleanup.signal });
				listen(videoRenditions, "activechange", () => sync(videoRenditions), { signal: cleanup.signal });
			}
			sync(videoRenditions);
		};
		bind();
		listen(media, "loadstart", bind, { signal });
		listen(media, "resize", () => sync(videoRenditions), { signal });
		signal.addEventListener("abort", () => cleanup?.abort(), { once: true });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/remote-playback.js
const remotePlaybackFeature = definePlayerFeature({
	name: "remotePlayback",
	state: ({ target }) => ({
		remotePlaybackState: "disconnected",
		remotePlaybackAvailability: "unsupported",
		async toggleRemotePlayback() {
			const { media, container } = target();
			if (isRemotePlaybackConnected(media)) return requestRemotePlayback(media);
			if (isFullscreen(container, media)) await exitFullscreen(media);
			return await requestRemotePlayback(media);
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaRemotePlaybackCapable(media)) return;
		if (isWebKitAirPlayCapable(media)) {
			const syncConnection = () => {
				set({ remotePlaybackState: media.webkitCurrentPlaybackTargetIsWireless ? "connected" : "disconnected" });
			};
			const syncAvailability = (event) => {
				const { availability } = event;
				set({ remotePlaybackAvailability: availability === "available" ? "available" : "unavailable" });
			};
			listen(media, "webkitplaybacktargetavailabilitychanged", syncAvailability, { signal });
			listen(media, "webkitcurrentplaybacktargetiswirelesschanged", syncConnection, { signal });
			syncConnection();
			return;
		}
		const syncState = () => set({ remotePlaybackState: media.remote.state });
		syncState();
		listen(media.remote, "connect", syncState, { signal });
		listen(media.remote, "connecting", syncState, { signal });
		listen(media.remote, "disconnect", syncState, { signal });
		media.remote.watchAvailability((available) => {
			set({ remotePlaybackAvailability: available ? "available" : "unavailable" });
		}).catch(() => {
			set({ remotePlaybackAvailability: "unsupported" });
		});
		signal.addEventListener("abort", () => {
			media.remote?.cancelWatchAvailability?.().catch(() => {});
		});
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/source.js
const sourceFeature = definePlayerFeature({
	name: "source",
	state: ({ target, signals }) => ({
		source: null,
		canPlay: false,
		loadSource(src) {
			signals.clear();
			const { media } = target();
			if (!isMediaSourceCapable(media)) return src;
			media.src = src;
			media.load();
			return src;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaSourceCapable(media)) return;
		const sync = () => set({
			source: media.currentSrc || media.src || null,
			canPlay: media.readyState >= HTMLMediaElement.HAVE_ENOUGH_DATA
		});
		sync();
		listen(media, "canplay", sync, { signal });
		listen(media, "canplaythrough", sync, { signal });
		listen(media, "loadstart", sync, { signal });
		listen(media, "emptied", sync, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/text-track.js
function getTrackId(track, index) {
	return track.id || `track:${index}:${track.kind}:${track.language}:${track.label}`;
}
/**
* Caption/subtitle tracks paired with the ids exposed through `textTrackList`, ordered like the captions menu so
* index-based fallbacks agree with the UI.
*/
function getSubtitlesTracks(media) {
	return Array.from(media.textTracks).map((track, index) => ({
		id: getTrackId(track, index),
		track
	})).filter(({ track }) => isCaptionOrSubtitleTrack(track)).sort((a, b) => a.track.kind > b.track.kind ? 1 : a.track.kind < b.track.kind ? -1 : 0);
}
/** Show at most one caption/subtitle track; passing `null` disables them all. */
function showOnly(tracks, active) {
	for (const { track } of tracks) {
		const mode = track === active ? "showing" : "disabled";
		if (track.mode !== mode) track.mode = mode;
	}
}
/**
* Map a media element's `crossOrigin` to a CORS mode. Per the CORS-settings attribute, any value other than
* `use-credentials` is Anonymous — including the empty string and unknown keywords.
*/
function toCorsMode(value) {
	if (isNil(value)) return null;
	return value.toLowerCase() === "use-credentials" ? "use-credentials" : "anonymous";
}
function findLocaleTrack(tracks, locale) {
	const localeKey = getCanonicalLocaleKey(locale);
	const keys = findLocaleKeys(locale);
	if (localeKey !== "en" && !localeKey.startsWith(`${"en"}-`)) keys.pop();
	for (const key of keys) {
		const exact = tracks.find(({ track }) => getCanonicalLocaleKey(track.language) === key);
		if (exact) return exact;
		const regional = tracks.find(({ track }) => getCanonicalLocaleKey(track.language).startsWith(`${key}-`));
		if (regional) return regional;
	}
}
const textTrackFeature = definePlayerFeature({
	name: "textTrack",
	state: ({ target }) => {
		let lastShownId = null;
		return {
			chaptersCues: [],
			thumbnailCues: [],
			thumbnailTrackSrc: null,
			thumbnailTrackCrossOrigin: null,
			textTrackList: [],
			subtitlesShowing: false,
			toggleSubtitles(forceShow) {
				const { media } = target();
				if (!isMediaTextTrackCapable(media)) return false;
				const subtitlesTracks = getSubtitlesTracks(media);
				if (!subtitlesTracks.length) return false;
				const showing = subtitlesTracks.find(({ track }) => track.mode === "showing");
				const nextShowing = forceShow ?? !showing;
				if (showing) lastShownId = showing.id;
				if (!nextShowing) {
					showOnly(subtitlesTracks, null);
					return false;
				}
				const next = showing ?? subtitlesTracks.find(({ id }) => id === lastShownId) ?? findLocaleTrack(subtitlesTracks, globalThis.navigator?.language ?? "") ?? subtitlesTracks[0];
				lastShownId = next.id;
				showOnly(subtitlesTracks, next.track);
				return true;
			},
			selectSubtitlesTrack(value) {
				const { media } = target();
				if (!isMediaTextTrackCapable(media)) return;
				const subtitlesTracks = getSubtitlesTracks(media);
				if (!subtitlesTracks.length) return;
				if (value === "off") {
					const showing = subtitlesTracks.find(({ track }) => track.mode === "showing");
					if (showing) lastShownId = showing.id;
					showOnly(subtitlesTracks, null);
					return;
				}
				const active = subtitlesTracks.find(({ id }) => id === value);
				if (!active) return;
				lastShownId = active.id;
				showOnly(subtitlesTracks, active.track);
			}
		};
	},
	attach({ target, signal, set }) {
		const { media } = target;
		if (!isMediaTextTrackCapable(media)) return;
		let trackCleanup = null;
		const sync = () => {
			trackCleanup?.abort();
			trackCleanup = new AbortController();
			let chaptersTrack = null;
			let thumbnailTrack = null;
			const textTrackList = [];
			let subtitlesShowing = false;
			for (let i = 0; i < media.textTracks.length; i++) {
				const track = media.textTracks[i];
				if (!chaptersTrack && track.kind === "chapters") chaptersTrack = track;
				if (!thumbnailTrack && track.kind === "metadata" && track.label === "thumbnails") thumbnailTrack = track;
				textTrackList.push({
					id: getTrackId(track, i),
					kind: track.kind,
					label: track.label,
					language: track.language,
					mode: track.mode
				});
				if (isCaptionOrSubtitleTrack(track) && track.mode === "showing") subtitlesShowing = true;
			}
			const chaptersCues = chaptersTrack?.cues ? Array.from(chaptersTrack.cues) : [];
			const thumbnailCues = thumbnailTrack?.cues ? Array.from(thumbnailTrack.cues) : [];
			let thumbnailTrackSrc = null;
			let thumbnailTrackCrossOrigin = null;
			if (thumbnailTrack) {
				thumbnailTrackSrc = findTrackElement(media, thumbnailTrack)?.src ?? null;
				thumbnailTrackCrossOrigin = isMediaSourceCapable(media) ? toCorsMode(media.crossOrigin) : null;
			}
			const tracks = isQuerySelectorAllCapable(media) && media.querySelectorAll("track") || [];
			const shadowTracks = media instanceof HTMLElement && media.shadowRoot?.querySelectorAll("track") || [];
			for (const trackEl of [...tracks, ...shadowTracks]) if (!trackEl.track?.cues?.length) listen(trackEl, "load", sync, { signal: trackCleanup.signal });
			set({
				chaptersCues,
				thumbnailCues,
				thumbnailTrackSrc,
				thumbnailTrackCrossOrigin,
				textTrackList,
				subtitlesShowing
			});
		};
		sync();
		const textTracks = media.textTracks;
		if (textTracks instanceof EventTarget) {
			listen(textTracks, "addtrack", sync, { signal });
			listen(textTracks, "removetrack", sync, { signal });
			listen(textTracks, "change", sync, { signal });
		}
		listen(media, "loadstart", sync, { signal });
		signal.addEventListener("abort", () => trackCleanup?.abort(), { once: true });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/signal-keys.js
const signalKeys = { seek: Symbol.for("@videojs/seek") };

//#endregion
//#region ../core/dist/dev/dom/store/features/time.js
const timeFeature = definePlayerFeature({
	name: "time",
	state: ({ target, signals, set }) => ({
		currentTime: 0,
		duration: 0,
		seeking: false,
		async seek(time) {
			const { media } = target(), signal = signals.supersede(signalKeys.seek);
			if (!isMediaSeekCapable(media) || !isMediaSourceCapable(media)) return 0;
			if (!hasMetadata(media)) {
				if (!await onEvent(media, "loadedmetadata", { signal }).catch(() => false)) return media.currentTime;
			}
			const clampedTime = Math.max(0, Math.min(time, media.duration || Infinity));
			set({
				currentTime: clampedTime,
				seeking: true
			});
			media.currentTime = clampedTime;
			await onEvent(media, "seeked", { signal }).catch(noop);
			return media.currentTime;
		}
	}),
	attach({ target, signal, set, get }) {
		const { media } = target;
		if (!isMediaSeekCapable(media)) return;
		const resolveDuration = () => {
			const { duration } = media;
			if (duration === Number.POSITIVE_INFINITY && isMediaBufferCapable(media)) {
				const { seekable } = media;
				return seekable.length > 0 ? seekable.end(seekable.length - 1) : 0;
			}
			return Number.isFinite(duration) ? duration : 0;
		};
		const sync = () => set({
			currentTime: media.currentTime,
			duration: resolveDuration(),
			seeking: media.seeking
		});
		const syncUnlessSeeking = () => {
			if (get().seeking) return;
			sync();
		};
		sync();
		listen(media, "timeupdate", syncUnlessSeeking, { signal });
		listen(media, "durationchange", sync, { signal });
		listen(media, "seeking", sync, { signal });
		listen(media, "seeked", sync, { signal });
		listen(media, "loadedmetadata", sync, { signal });
		listen(media, "emptied", sync, { signal });
		listen(media, "progress", syncUnlessSeeking, { signal });
	}
});

//#endregion
//#region ../core/dist/dev/dom/store/features/volume.js
/** Volume to restore when unmuting at zero. */
const UNMUTE_VOLUME = .25;
const volumeFeature = definePlayerFeature({
	name: "volume",
	state: ({ target }) => ({
		volume: 1,
		muted: false,
		volumeAvailability: "unavailable",
		mutedAvailability: "unavailable",
		setVolume(volume) {
			const { media } = target();
			if (!isMediaVolumeCapable(media)) return 0;
			const clamped = Math.max(0, Math.min(1, volume));
			if (clamped > 0 && media.muted) media.muted = false;
			media.volume = clamped;
			return media.volume;
		},
		toggleMuted() {
			const { media } = target();
			if (!isMediaMutedCapable(media)) return false;
			if (!isMediaVolumeCapable(media)) {
				media.muted = !media.muted;
				return media.muted;
			}
			if (media.muted || media.volume === 0) {
				media.muted = false;
				if (media.volume === 0) media.volume = UNMUTE_VOLUME;
			} else media.muted = true;
			return media.muted;
		}
	}),
	attach({ target, signal, set }) {
		const { media } = target;
		const volumeCapable = isMediaVolumeCapable(media);
		const mutedCapable = isMediaMutedCapable(media);
		if (!volumeCapable && !mutedCapable) return;
		set({
			volumeAvailability: volumeCapable ? canSetVolume() : "unavailable",
			mutedAvailability: mutedCapable ? "available" : "unavailable"
		});
		const sync = () => set({
			volume: volumeCapable ? media.volume : 1,
			muted: mutedCapable ? media.muted : false
		});
		sync();
		listen(media, "volumechange", sync, { signal });
	}
});
/** Check if volume can be programmatically set (fails on iOS Safari). */
function canSetVolume() {
	const video = document.createElement("video");
	try {
		video.volume = .5;
		return video.volume === .5 ? "available" : "unsupported";
	} catch {
		return "unsupported";
	}
}

//#endregion
//#region ../store/dist/dev/core/abort-controller-registry.js
var AbortControllerRegistry = class {
	#base;
	#keys = /* @__PURE__ */ new Map();
	/** The attach-scoped signal. Aborts on detach or reattach. */
	get base() {
		return (this.#base ??= new AbortController()).signal;
	}
	/** Clears all keyed signals, leaving base intact. */
	clear() {
		for (const controller of this.#keys.values()) controller.abort();
		this.#keys.clear();
	}
	/** Resets base and clears all keyed signals. */
	reset() {
		this.clear();
		this.#base?.abort();
		this.#base = void 0;
	}
	/** Creates a new signal for the key, superseding any previous signal. */
	supersede(key) {
		this.#keys.get(key)?.abort();
		const controller = new AbortController();
		this.#keys.set(key, controller);
		return anyAbortSignal([this.base, controller.signal]);
	}
};

//#endregion
//#region ../store/dist/dev/core/combine.js
/**
* Combines multiple slices into a single slice.
*
* @param slices - The slices to combine.
* @returns A new slice that represents the combination of the input slices.
*/
function combine(...slices) {
	const derivedDefinitions = slices.map((slice) => slice.derived ?? {});
	warnDuplicates("derived", derivedDefinitions);
	return {
		state: (ctx) => {
			const states = slices.map((slice) => slice.state(ctx));
			warnDuplicates("state", states);
			warnOverlaps(states, derivedDefinitions);
			return Object.assign({}, ...states);
		},
		preserve: Array.from(new Set(slices.flatMap((slice) => slice.preserve ?? []))),
		derived: Object.assign({}, ...derivedDefinitions),
		attach: (ctx) => {
			for (const slice of slices) try {
				slice.attach?.(ctx);
			} catch (err) {
				ctx.reportError(err);
			}
		}
	};
}
function warnDuplicates(namespace, objects) {
	const seen = /* @__PURE__ */ new Set();
	for (const object of objects) for (const key of Reflect.ownKeys(object)) {
		if (seen.has(key)) console.warn(`[vjs-store] combine(): duplicate ${namespace} key "${String(key)}" — later slice overwrites earlier one`);
		seen.add(key);
	}
}
function warnOverlaps(states, derivedDefinitions) {
	const stateKeys = new Set(states.flatMap((state) => Reflect.ownKeys(state)));
	for (const key of new Set(derivedDefinitions.flatMap((derived) => Reflect.ownKeys(derived)))) if (stateKeys.has(key)) console.warn(`[vjs-store] combine(): state and derived key "${String(key)}" overlap — derived state overwrites source state`);
}

//#endregion
//#region ../store/dist/dev/core/errors.js
var StoreError = class extends Error {
	code;
	cause;
	constructor(code, options) {
		super(options?.message ?? code);
		this.name = "StoreError";
		this.code = code;
		this.cause = options?.cause;
	}
};
function throwNoTargetError() {
	throw new StoreError("NO_TARGET");
}
function throwDestroyedError() {
	throw new StoreError("DESTROYED");
}

//#endregion
//#region ../store/dist/dev/core/state.js
let isFlushScheduled = false;
function scheduleFlush() {
	if (isFlushScheduled) return;
	isFlushScheduled = true;
	queueMicrotask(flush);
}
const pendingContainers = /* @__PURE__ */ new Set();
function flush() {
	isFlushScheduled = false;
	for (const container of pendingContainers) container.flush();
	pendingContainers.clear();
}
const hasOwnProp$1 = Object.prototype.hasOwnProperty;
var StateContainer = class {
	#current;
	#listeners = /* @__PURE__ */ new Set();
	#pending = false;
	constructor(initial) {
		this.#current = Object.freeze({ ...initial });
	}
	get current() {
		return this.#current;
	}
	patch(partial) {
		const next = { ...this.#current };
		let changed = false;
		for (const key of Reflect.ownKeys(partial)) {
			if (!hasOwnProp$1.call(partial, key)) continue;
			const value = partial[key];
			if (!Object.is(this.#current[key], value)) {
				next[key] = value;
				changed = true;
			}
		}
		if (changed) {
			this.#current = Object.freeze(next);
			this.#markPending();
		}
	}
	replace(next) {
		if (shallowEqual(this.#current, next)) return;
		this.#current = Object.freeze({ ...next });
		this.#markPending();
	}
	subscribe(callback, options) {
		const signal = options?.signal;
		if (signal?.aborted) return noop;
		this.#listeners.add(callback);
		if (!signal) return () => this.#listeners.delete(callback);
		const onAbort = () => this.#listeners.delete(callback);
		signal.addEventListener("abort", onAbort, { once: true });
		return () => {
			signal.removeEventListener("abort", onAbort);
			this.#listeners.delete(callback);
		};
	}
	flush() {
		if (!this.#pending) return;
		this.#pending = false;
		for (const fn of this.#listeners) fn();
	}
	#markPending() {
		this.#pending = true;
		pendingContainers.add(this);
		scheduleFlush();
	}
};
function createState(initial) {
	return new StateContainer(initial);
}

//#endregion
//#region ../store/dist/dev/core/store.js
const STORE_SYMBOL = Symbol.for("@videojs/store");
const hasOwnProp = Object.prototype.hasOwnProperty;
function createStore() {
	return ((slice, options = {}) => {
		let target = null;
		let destroyed = false;
		const setupAbort = new AbortController();
		const signals = new AbortControllerRegistry();
		let sourceState;
		let state;
		function validate() {
			if (destroyed) throwDestroyedError();
			if (!target) throwNoTargetError();
		}
		const initialSourceState = freezeCopy(slice.state({
			target: () => {
				validate();
				return target;
			},
			signals,
			get: () => sourceState,
			set: (partial) => setSource(partial)
		}));
		sourceState = initialSourceState;
		const initialDerivedState = derive(sourceState);
		state = createState(publish(sourceState, initialDerivedState));
		const store = {
			[STORE_SYMBOL]: true,
			get $state() {
				return state;
			},
			get target() {
				return target;
			},
			get destroyed() {
				return destroyed;
			},
			get state() {
				return state.current;
			},
			attach,
			destroy,
			subscribe
		};
		for (const key of Object.keys(state.current)) Object.defineProperty(store, key, {
			get: () => state.current[key],
			enumerable: true
		});
		for (const key of Object.getOwnPropertySymbols(sourceState)) {
			if (typeof sourceState[key] !== "function") continue;
			Object.defineProperty(store, key, { get: () => sourceState[key] });
		}
		try {
			options.onSetup?.({
				store,
				signal: setupAbort.signal
			});
		} catch (error) {
			reportError(error);
		}
		return store;
		function derive(source) {
			const result = {};
			const definitions = slice.derived;
			if (!definitions) return result;
			const ctx = { get: () => source };
			for (const key of Object.keys(definitions)) result[key] = definitions[key](ctx);
			return result;
		}
		function publish(source, derived) {
			const result = {};
			for (const key of Object.keys(source)) result[key] = source[key];
			return Object.assign(result, derived);
		}
		function setSource(partial) {
			const patched = patchSource(sourceState, partial);
			if (!patched) return;
			const nextDerived = derive(patched.next);
			sourceState = patched.next;
			state.replace(publish(sourceState, nextDerived));
		}
		function attach(newTarget) {
			if (destroyed) throwDestroyedError();
			signals.reset();
			target = newTarget;
			const attachContext = {
				target: newTarget,
				signal: signals.base,
				get: () => sourceState,
				set: (partial) => {
					try {
						setSource(partial);
					} catch (error) {
						reportError(error);
					}
				},
				reportError,
				store: {
					get state() {
						return state.current;
					},
					subscribe
				}
			};
			try {
				slice.attach?.(attachContext);
			} catch (error) {
				reportError(error);
			}
			try {
				options.onAttach?.({
					store,
					target: newTarget,
					signal: signals.base
				});
			} catch (error) {
				reportError(error);
			}
			return detach;
		}
		function detach() {
			if (isNull(target)) return;
			signals.reset();
			target = null;
			const resetState = { ...initialSourceState };
			for (const key of slice.preserve ?? []) resetState[key] = sourceState[key];
			setSource(resetState);
		}
		function destroy() {
			if (destroyed) return;
			destroyed = true;
			detach();
			setupAbort.abort();
		}
		function subscribe(callback, options) {
			return state.subscribe(callback, options);
		}
		function reportError(error) {
			if (options.onError) options.onError({
				store,
				error
			});
			else console.error("[vjs-store]", error);
		}
	});
}
function freezeCopy(value) {
	return Object.freeze({ ...value });
}
function patchSource(current, partial) {
	const next = { ...current };
	let changed = false;
	for (const key of Reflect.ownKeys(partial)) {
		if (!hasOwnProp.call(partial, key)) continue;
		const value = partial[key];
		if (Object.is(current[key], value)) continue;
		next[key] = value;
		changed = true;
	}
	return changed ? { next: Object.freeze(next) } : null;
}
function isStore(value) {
	return isObject(value) && STORE_SYMBOL in value;
}

//#endregion
//#region ../core/dist/dev/dom/store/features/presets.js
const videoFeatures = [
	playbackFeature,
	playbackRateFeature,
	qualityFeature,
	audioTrackFeature,
	volumeFeature,
	timeFeature,
	sourceFeature,
	bufferFeature,
	fullscreenFeature,
	pipFeature,
	remotePlaybackFeature,
	controlsFeature,
	textTrackFeature,
	errorFeature,
	metadataFeature
];
const audioFeatures = [
	playbackFeature,
	playbackRateFeature,
	volumeFeature,
	timeFeature,
	sourceFeature,
	bufferFeature,
	errorFeature,
	metadataFeature
];
const backgroundFeatures = [];
/**
* Features for a live video player. Mirrors {@link videoFeatures} but drops {@link playbackRateFeature} (not meaningful
* for live) and adds {@link liveFeature} so store consumers can read `liveEdgeStart` and `targetLiveWindow`.
*/
const liveVideoFeatures = [
	playbackFeature,
	volumeFeature,
	timeFeature,
	sourceFeature,
	bufferFeature,
	fullscreenFeature,
	pipFeature,
	remotePlaybackFeature,
	controlsFeature,
	textTrackFeature,
	errorFeature,
	liveFeature,
	metadataFeature
];
/**
* Features for a live audio player. Mirrors {@link audioFeatures} but drops {@link playbackRateFeature} (not meaningful
* for live) and adds {@link liveFeature} so store consumers can read `liveEdgeStart` and `targetLiveWindow`.
*/
const liveAudioFeatures = [
	playbackFeature,
	volumeFeature,
	timeFeature,
	sourceFeature,
	bufferFeature,
	errorFeature,
	liveFeature,
	metadataFeature
];

//#endregion
//#region ../store/dist/dev/html/controllers/snapshot-controller.js
/**
* Subscribe to a `State<T>` container with optional selector.
*
* Without selector: returns full state, re-renders on any state change. With selector: returns selected slice,
* re-renders only when the slice changes (shallowEqual).
*
* @example
*   ```ts
*   #state = new SnapshotController(this, sliderState, (s) => s.value);
*   ```;
*/
var SnapshotController = class {
	#host;
	#selector;
	#state;
	#cached;
	#unsubscribe = noop;
	constructor(host, state, selector) {
		this.#host = host;
		this.#state = state;
		this.#selector = selector;
		host.addController(this);
	}
	get value() {
		if (!this.#selector) return this.#state.current;
		this.#cached ??= this.#selector(this.#state.current);
		return this.#cached;
	}
	/** Switch to tracking a different state container. */
	track(state) {
		this.#state = state;
		this.#subscribe();
	}
	hostConnected() {
		this.#subscribe();
	}
	hostDisconnected() {
		this.#unsubscribe();
		this.#unsubscribe = noop;
		this.#cached = void 0;
	}
	#subscribe() {
		this.#unsubscribe();
		if (!this.#selector) {
			this.#unsubscribe = this.#state.subscribe(() => this.#host.requestUpdate());
			return;
		}
		const selector = this.#selector;
		this.#cached = selector(this.#state.current);
		this.#unsubscribe = this.#state.subscribe(() => {
			const next = selector(this.#state.current);
			if (!shallowEqual(this.#cached, next)) {
				this.#cached = next;
				this.#host.requestUpdate();
			}
		});
	}
};

//#endregion
//#region ../store/dist/dev/html/store-accessor.js
/**
* Resolves a store from either a direct instance or context.
*
* When given a direct store, provides immediate access. When given a context, sets up a ContextConsumer to receive the
* store.
*
* @example
*   Direct store
*   ```ts
*   const accessor = new StoreAccessor(host, store, (s) => console.log('available', s));
*   accessor.value; // Store (immediately available)
*   ```
*
* @example
*   Context source
*   ```ts
*   const accessor = new StoreAccessor(host, context, (s) => console.log('available', s));
*   accessor.value; // null until context provides store
*   ```
*/
var StoreAccessor = class {
	#onAvailable;
	#consumer;
	#directStore;
	constructor(host, source, onAvailable) {
		this.#onAvailable = onAvailable ?? noop;
		if (isStore(source)) {
			this.#directStore = source;
			this.#consumer = null;
		} else {
			this.#directStore = null;
			this.#consumer = new ContextConsumer(host, {
				context: source,
				callback: (store) => this.#onAvailable(store),
				subscribe: false
			});
		}
		host.addController(this);
	}
	/** Returns the store, or null if not yet available from context. */
	get value() {
		if (this.#consumer) return this.#consumer.value ?? null;
		return this.#directStore;
	}
	hostConnected() {
		if (this.#directStore) this.#onAvailable(this.#directStore);
	}
};

//#endregion
//#region ../store/dist/dev/html/controllers/store-controller.js
/**
* Access store state and actions.
*
* Without selector: Returns the store, does NOT subscribe to changes. With selector: Returns selected state, triggers
* update when selected state changes (shallowEqual).
*
* @example
*   ```ts
*   // Store access (no subscription) - access actions
*   class Controls extends LitElement {
*   #store = new StoreController(this, storeSource);
*
*   handleClick() {
*   this.#store.value.setVolume(0.5);
*   }
*   }
*
*   // Selector-based subscription - re-renders when playback changes
*   class PlayButton extends LitElement {
*   #playback = new StoreController(this, storeSource, selectPlayback);
*
*   render() {
*   const playback = this.#playback.value;
*   if (!playback) return nothing;
*   return html`<button @click=${playback.toggle}>
*   ${playback.paused ? 'Play' : 'Pause'}
*   </button>`;
*   }
*   }
*   ```
*/
var StoreController = class {
	#host;
	#selector;
	#accessor;
	#snapshot = null;
	constructor(host, source, selector) {
		this.#host = host;
		this.#selector = selector;
		this.#accessor = new StoreAccessor(host, source, (store) => this.#connect(store));
		host.addController(this);
	}
	get value() {
		const store = this.#accessor.value;
		if (isNull(store)) throw new Error("Store not available");
		if (isUndefined(this.#selector)) return store;
		return this.#snapshot.value;
	}
	hostConnected() {}
	#connect(store) {
		if (isUndefined(this.#selector)) return;
		if (!this.#snapshot) this.#snapshot = new SnapshotController(this.#host, store.$state, this.#selector);
		else this.#snapshot.track(store.$state);
	}
};

//#endregion
//#region src/player/player-controller.ts
/**
* Reactive controller for accessing player store state.
*
* Without selector: Returns the store, does NOT subscribe to changes. With selector: Returns selected state, subscribes
* with shallowEqual comparison.
*
* @example
*   ```ts
*   // Store access (no subscription)
*   class Controls extends UIElement {
*     #player = new PlayerController(this, playerContext);
*
*     handleClick() {
*       this.#player.value.setVolume(0.5);
*     }
*   }
*
*   // Selector-based subscription
*   class PlayButton extends UIElement {
*     #playback = new PlayerController(this, playerContext, selectPlayback);
*   }
*   ```;
*/
var PlayerController = class {
	#host;
	#selector;
	#consumer;
	#store = null;
	constructor(host, context, selector) {
		this.#host = host;
		this.#selector = selector;
		this.#consumer = new ContextConsumer(host, {
			context,
			callback: (ctx) => this.#connect(ctx),
			subscribe: true
		});
		host.addController(this);
	}
	get value() {
		const store = this.#consumer.value;
		if (!store) return void 0;
		if (!this.#selector) return store;
		return this.#store?.value;
	}
	get displayName() {
		return this.#selector?.displayName;
	}
	hostConnected() {
		const store = this.#consumer.value;
		if (store) this.#connect(store);
	}
	hostDisconnected() {
		this.#store = null;
	}
	#connect(store) {
		if (!this.#store && this.#selector) this.#store = new StoreController(this.#host, store, this.#selector);
	}
};
function createPlayerController(context) {
	class ConfiguredPlayerController extends PlayerController {
		constructor(host, selector) {
			if (selector) super(host, context, selector);
			else super(host, context);
		}
	}
	return ConfiguredPlayerController;
}

//#endregion
//#region src/player/player-element.ts
function resolveInputs(config) {
	return Object.entries(config).map(([key, entry]) => {
		const declared = entry.html?.attribute;
		const attribute = declared ?? kebabCase(key);
		if (declared && declared !== kebabCase(declared)) console.warn(`[vjs-html] config html.attribute "${declared}" is not kebab-case and will never match`);
		return {
			property: camelCase(attribute),
			attribute,
			entry
		};
	});
}
function createPlayerElement(options) {
	const inputs = resolveInputs(options.config);
	class ConfiguredPlayerElement extends UIElement {
		static {
			this.properties = {
				...UIElement.properties,
				...Object.fromEntries(inputs.map(({ property, attribute }) => [property, {
					type: String,
					attribute
				}]))
			};
		}
		#store = options.factory();
		#configuredStore = null;
		#detach = null;
		#connected = false;
		#media = null;
		#nativeMedia = null;
		#container = null;
		#mediaRegistrations = [];
		#containerRegistrations = [];
		#observer = new MutationObserver(() => this.#syncNativeMedia());
		#registerMedia = (media) => {
			const registration = { value: media };
			this.#mediaRegistrations.push(registration);
			this.#syncMedia();
			return () => {
				const index = this.#mediaRegistrations.indexOf(registration);
				if (index < 0) return;
				this.#mediaRegistrations.splice(index, 1);
				this.#syncNativeMedia();
				this.#syncMedia();
			};
		};
		#registerContainer = (container) => {
			const registration = { value: container };
			this.#containerRegistrations.push(registration);
			this.#syncContainer();
			return () => {
				const index = this.#containerRegistrations.indexOf(registration);
				if (index < 0) return;
				this.#containerRegistrations.splice(index, 1);
				this.#syncContainer();
			};
		};
		#playerProvider = new ContextProvider(this, {
			context: options.playerContext,
			initialValue: this.store
		});
		#mediaProvider = new ContextProvider(this, {
			context: options.mediaContext,
			initialValue: {
				media: this.#media,
				registerMedia: this.#registerMedia
			}
		});
		#containerProvider = new ContextProvider(this, {
			context: options.containerContext,
			initialValue: {
				container: this.#container,
				registerContainer: this.#registerContainer
			}
		});
		get store() {
			if (isNull(this.#store)) this.#store = options.factory();
			return this.#store;
		}
		connectedCallback() {
			this.#connected = true;
			super.connectedCallback();
			this.#syncInitialConfig();
			this.#playerProvider.setValue(this.store);
			this.#publishMedia();
			this.#publishContainer();
			this.#observer.observe(this, {
				childList: true,
				subtree: true
			});
			queueMicrotask(() => {
				if (this.#connected) this.#syncNativeMedia();
			});
			this.#tryAttach();
		}
		disconnectedCallback() {
			this.#connected = false;
			this.#observer.disconnect();
			this.#detachStore();
			super.disconnectedCallback();
		}
		destroyCallback() {
			this.#observer.disconnect();
			this.#detachStore();
			this.#store?.destroy();
			this.#store = null;
			super.destroyCallback();
		}
		willUpdate(changed) {
			super.willUpdate(changed);
			for (const { property, entry } of inputs) {
				if (!changed.has(property)) continue;
				const configProperty = property;
				setPlayerConfigValue(this.store, entry, this[configProperty]);
			}
		}
		#syncMedia() {
			const media = this.#mediaRegistrations.at(-1)?.value ?? null ?? this.#nativeMedia;
			if (this.#media === media) return;
			this.#media = media;
			this.#publishMedia();
			this.#tryAttach();
		}
		#syncContainer() {
			const container = this.#containerRegistrations.at(-1)?.value ?? null;
			if (this.#container === container) return;
			this.#container = container;
			this.#publishContainer();
			this.#tryAttach();
		}
		#syncNativeMedia() {
			const media = this.querySelector("video, audio");
			if (this.#nativeMedia === media) return;
			this.#nativeMedia = media;
			this.#syncMedia();
		}
		#publishMedia() {
			this.#mediaProvider.setValue({
				media: this.#media,
				registerMedia: this.#registerMedia
			});
		}
		#publishContainer() {
			this.#containerProvider.setValue({
				container: this.#container,
				registerContainer: this.#registerContainer
			});
		}
		#tryAttach() {
			const store = this.#store;
			if (!this.#connected || !store) return;
			if (!this.#media) {
				this.#detachStore();
				return;
			}
			const target = {
				media: this.#media,
				container: this.#container
			};
			const hasMediaChanged = store.target?.media !== target.media;
			const hasContainerChanged = store.target?.container !== target.container;
			if (hasMediaChanged || hasContainerChanged) {
				this.#detachStore();
				this.#detach = store.attach(target);
			}
		}
		#detachStore() {
			this.#detach?.();
			this.#detach = null;
		}
		#syncInitialConfig() {
			const store = this.store;
			if (this.#configuredStore === store) return;
			for (const { property, entry } of inputs) {
				const configProperty = property;
				setPlayerConfigValue(store, entry, this[configProperty]);
			}
			this.#configuredStore = store;
		}
	}
	return ConfiguredPlayerElement;
}

//#endregion
//#region src/player/create-player.ts
function createPlayer(config) {
	const slice = combine(...config.features);
	const featureConfig = combinePlayerFeatureConfigs(config.features);
	return {
		PlayerElement: createPlayerElement({
			playerContext,
			mediaContext,
			containerContext,
			factory: () => createStore()(slice),
			config: featureConfig
		}),
		PlayerController: createPlayerController(playerContext),
		playerContext
	};
}

//#endregion
export { isMediaSeekCapable as A, fullscreenFeature as C, bufferFeature as D, getGestureCoordinator as E, ContextProvider as F, definePlayerFeature as M, isEditableTarget as N, audioTrackFeature as O, isInteractiveActivation as P, liveFeature as S, controlsFeature as T, qualityFeature as _, backgroundFeatures as a, pipFeature as b, videoFeatures as c, AbortControllerRegistry as d, volumeFeature as f, remotePlaybackFeature as g, sourceFeature as h, audioFeatures as i, isMediaStreamTypeCapable as j, isMediaBufferCapable as k, createState as l, textTrackFeature as m, PlayerController as n, liveAudioFeatures as o, timeFeature as p, SnapshotController as r, liveVideoFeatures as s, createPlayer as t, throwNoTargetError as u, playbackRateFeature as v, errorFeature as w, metadataFeature as x, playbackFeature as y };
//# sourceMappingURL=create-player-Duo2X4dJ.js.map