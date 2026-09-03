import { n as EMPTY_TEXT_TRACKS, r as EMPTY_TIME_RANGES, t as EMPTY_REMOTE } from "./constants-CsSwyIX6.js";
import { t as MediaStreamTypes } from "./types-IVY0D06G.js";

//#region ../media/dist/dev/dom/utils/media-components.js
const componentRegistry = /* @__PURE__ */ new WeakMap();
function getMediaComponents(host) {
	let map = componentRegistry.get(host);
	if (!map) componentRegistry.set(host, map = /* @__PURE__ */ new Map());
	return map;
}
function addMediaComponent(host, component) {
	const components = getMediaComponents(host);
	const ctor = component.constructor;
	const previous = components.get(ctor);
	if (previous && previous !== component) previous.detach?.();
	components.set(ctor, component);
	component.setMedia?.(host);
	if (host.target) component.attach?.(host.target);
	return () => {
		if (components.get(ctor) === component) {
			component.detach?.();
			components.delete(ctor);
		}
	};
}
function getMediaProp(host, prop) {
	return getMediaOwner(host, prop)?.[prop];
}
function setMediaProp(host, prop, value) {
	const own = getMediaOwner(host, prop);
	if (own) own[prop] = value;
}
/**
* Find the object that owns a media property: the first component `override` exposing it, otherwise the attached
* target.
*/
function getMediaOwner(host, prop) {
	for (const component of getMediaComponents(host).values()) {
		const override = component.targetOverride;
		if (override?.[prop] !== void 0) return override;
	}
	return host.target;
}

//#endregion
//#region ../media/dist/dev/dom/media-host/media-host.js
var HTMLMediaElementHost = class extends EventTarget {
	#target = null;
	#eventTypes = /* @__PURE__ */ new Set();
	#streamType = MediaStreamTypes.UNKNOWN;
	get target() {
		return this.#target;
	}
	attach(target) {
		if (!target || this.#target === target) return;
		this.#target = target;
		for (const type of this.#eventTypes) target.addEventListener(type, this.#forwardEvent);
		for (const component of getMediaComponents(this).values()) component.attach?.(target);
	}
	detach() {
		if (!this.#target) return;
		for (const component of getMediaComponents(this).values()) component.detach?.();
		for (const type of this.#eventTypes) this.#target.removeEventListener(type, this.#forwardEvent);
		this.#target = null;
	}
	destroy() {
		this.detach();
		this.#eventTypes.clear();
		getMediaComponents(this).clear();
	}
	querySelectorAll(selectors) {
		return this.target?.querySelectorAll(selectors) ?? [];
	}
	querySelector(selectors) {
		return this.target?.querySelector(selectors) ?? null;
	}
	addEventListener(type, listener, options) {
		if (!this.#eventTypes.has(type)) {
			this.#eventTypes.add(type);
			this.target?.addEventListener(type, this.#forwardEvent);
		}
		super.addEventListener(type, listener, options);
	}
	removeEventListener(type, listener, options) {
		super.removeEventListener(type, listener, options);
	}
	#forwardEvent = (event) => {
		this.dispatchEvent(new event.constructor(event.type, event));
	};
	/**
	* Current stream type (`'on-demand'`, `'live'`, or `'unknown'`). Defaults to `'unknown'`; detecting hosts update it
	* automatically, and consumers can set it to override detection.
	*/
	get streamType() {
		return getMediaProp(this, "streamType") ?? this.#streamType;
	}
	set streamType(value) {
		if (this.streamType === value) return;
		this.#streamType = value;
		setMediaProp(this, "streamType", value);
		this.dispatchEvent(new Event("streamtypechange"));
	}
	get liveEdgeStart() {
		return getMediaProp(this, "liveEdgeStart") ?? NaN;
	}
	get targetLiveWindow() {
		return getMediaProp(this, "targetLiveWindow") ?? NaN;
	}
	get contentData() {
		return getMediaProp(this, "contentData");
	}
	get title() {
		return getMediaProp(this, "title") ?? "";
	}
	set title(value) {
		setMediaProp(this, "title", value);
	}
	get controls() {
		return getMediaProp(this, "controls") ?? false;
	}
	set controls(value) {
		setMediaProp(this, "controls", value);
	}
	get paused() {
		return getMediaProp(this, "paused") ?? true;
	}
	get ended() {
		return getMediaProp(this, "ended") ?? false;
	}
	get loop() {
		return getMediaProp(this, "loop") ?? false;
	}
	set loop(value) {
		setMediaProp(this, "loop", value);
	}
	play() {
		return getMediaOwner(this, "play")?.play?.() ?? Promise.reject(new DOMException("No media is attached.", "NotSupportedError"));
	}
	pause() {
		getMediaOwner(this, "pause")?.pause?.();
	}
	get autoplay() {
		return getMediaProp(this, "autoplay") ?? false;
	}
	set autoplay(value) {
		setMediaProp(this, "autoplay", value);
	}
	get currentTime() {
		return getMediaProp(this, "currentTime") ?? 0;
	}
	set currentTime(value) {
		setMediaProp(this, "currentTime", value);
	}
	get duration() {
		return getMediaProp(this, "duration") ?? NaN;
	}
	get seeking() {
		return getMediaProp(this, "seeking") ?? false;
	}
	get src() {
		return getMediaProp(this, "src") ?? "";
	}
	set src(value) {
		setMediaProp(this, "src", value);
	}
	get currentSrc() {
		return getMediaProp(this, "currentSrc") ?? "";
	}
	get readyState() {
		return getMediaProp(this, "readyState") ?? 0;
	}
	get preload() {
		return getMediaProp(this, "preload") ?? "metadata";
	}
	set preload(value) {
		setMediaProp(this, "preload", value);
	}
	get crossOrigin() {
		return getMediaProp(this, "crossOrigin") ?? null;
	}
	set crossOrigin(value) {
		setMediaProp(this, "crossOrigin", value);
	}
	load() {
		return getMediaOwner(this, "load")?.load?.();
	}
	canPlayType(type) {
		return getMediaOwner(this, "canPlayType")?.canPlayType?.(type) ?? "";
	}
	get volume() {
		return getMediaProp(this, "volume") ?? 1;
	}
	set volume(value) {
		setMediaProp(this, "volume", value);
	}
	get muted() {
		return getMediaProp(this, "muted") ?? false;
	}
	set muted(value) {
		setMediaProp(this, "muted", value);
	}
	get defaultMuted() {
		return getMediaProp(this, "defaultMuted") ?? false;
	}
	set defaultMuted(value) {
		setMediaProp(this, "defaultMuted", value);
	}
	get playbackRate() {
		return getMediaProp(this, "playbackRate") ?? 1;
	}
	set playbackRate(value) {
		setMediaProp(this, "playbackRate", value);
	}
	get defaultPlaybackRate() {
		return getMediaProp(this, "defaultPlaybackRate") ?? 1;
	}
	set defaultPlaybackRate(value) {
		setMediaProp(this, "defaultPlaybackRate", value);
	}
	get buffered() {
		return getMediaProp(this, "buffered") ?? EMPTY_TIME_RANGES;
	}
	get seekable() {
		return getMediaProp(this, "seekable") ?? EMPTY_TIME_RANGES;
	}
	get played() {
		return getMediaProp(this, "played") ?? EMPTY_TIME_RANGES;
	}
	get error() {
		return getMediaProp(this, "error") ?? null;
	}
	get textTracks() {
		return getMediaProp(this, "textTracks") ?? EMPTY_TEXT_TRACKS;
	}
	addTextTrack(kind, label, language) {
		return getMediaOwner(this, "addTextTrack")?.addTextTrack?.(kind, label, language);
	}
	get remote() {
		return getMediaProp(this, "remote") ?? EMPTY_REMOTE;
	}
	get disableRemotePlayback() {
		return getMediaProp(this, "disableRemotePlayback") ?? false;
	}
	set disableRemotePlayback(value) {
		setMediaProp(this, "disableRemotePlayback", value);
	}
};

//#endregion
export { setMediaProp as i, addMediaComponent as n, getMediaProp as r, HTMLMediaElementHost as t };
//# sourceMappingURL=media-host-D7BMz4NW.js.map