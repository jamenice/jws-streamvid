import { u as isUndefined } from "./predicate-DrcmolBs.js";
import { o as pick } from "./attributes-C0ssqa1e.js";
import { n as ContextConsumer, t as UIElement } from "./ui-element-DraIA8Ee.js";
import { a as createContext, r as playerContext, t as containerContext } from "./context-DlE_3NHA.js";
import { A as isMediaSeekCapable, C as fullscreenFeature, D as bufferFeature, F as ContextProvider, M as definePlayerFeature, O as audioTrackFeature, S as liveFeature, T as controlsFeature, _ as qualityFeature, b as pipFeature, d as AbortControllerRegistry, f as volumeFeature, g as remotePlaybackFeature, h as sourceFeature, j as isMediaStreamTypeCapable, k as isMediaBufferCapable, m as textTrackFeature, n as PlayerController, p as timeFeature, u as throwNoTargetError, v as playbackRateFeature, w as errorFeature, x as metadataFeature, y as playbackFeature } from "./create-player-Duo2X4dJ.js";
import { t as listen } from "./listen-CdeggIF8.js";
import { t as MediaStreamTypes } from "./types-IVY0D06G.js";
import { DEFAULT_LOCALE, createTranslator, getI18nTranslations, onI18nRegistryChange } from "./i18n.dev.js";

//#region src/i18n/context.ts
const I18N_CONTEXT_KEY = Symbol.for("@videojs/i18n");
/**
* The default HTML context carrying the active translator and locale.
*
* @public
*/
const i18nContext = createContext(I18N_CONTEXT_KEY);

//#endregion
//#region ../utils/dist/dom/predicates.js
function isDocument(value) {
	return value instanceof Node && value.nodeType === 9;
}
function isShadowRoot(value) {
	return value instanceof Node && value.nodeType === 11 && "host" in value;
}

//#endregion
//#region ../utils/dist/dom/walk-ancestors.js
/** Walks an element and its ancestors until the callback returns a defined value. */
function walkAncestors(start, callback, options = {}) {
	if (!start || typeof document === "undefined") return;
	let node = start;
	while (node) {
		const value = callback(node);
		if (!isUndefined(value)) return value;
		node = options.composed ? getComposedParent(node) : node.parentElement;
	}
}
function getComposedParent(element) {
	if (element.assignedSlot) return element.assignedSlot;
	if (element.parentElement) return element.parentElement;
	const root = element.getRootNode();
	return isShadowRoot(root) ? root.host : null;
}

//#endregion
//#region ../utils/dist/dom/focus.js
const TABBABLE_SELECTOR = [
	"a[href]",
	"button:not([disabled])",
	"input:not([disabled])",
	"select:not([disabled])",
	"textarea:not([disabled])",
	"audio[controls]",
	"video[controls]",
	"iframe",
	"[contenteditable]:not([contenteditable=\"false\"])",
	"[tabindex]:not([tabindex=\"-1\"])"
].join(",");
function getDeepActiveElement(root = document) {
	let active = root.activeElement;
	while (active?.shadowRoot?.activeElement) active = active.shadowRoot.activeElement;
	return active;
}
/** Returns the elements in a composed subtree that participate in sequential keyboard navigation. */
function getTabbableElements(root) {
	const tabbable = [];
	const visited = /* @__PURE__ */ new Set();
	visitChildren(root);
	return tabbable;
	function visitChildren(parent) {
		for (const child of parent.children) visitElement(child);
	}
	function visitElement(element) {
		if (visited.has(element)) return;
		visited.add(element);
		if (element instanceof HTMLElement && isTabbableElement(element)) tabbable.push(element);
		if (element instanceof HTMLSlotElement) {
			const assigned = element.assignedElements({ flatten: true });
			if (assigned.length > 0) for (const child of assigned) visitElement(child);
			else visitChildren(element);
			return;
		}
		if (element.shadowRoot) visitChildren(element.shadowRoot);
		else visitChildren(element);
	}
}
function isTabbableElement(element) {
	if (!element.matches(TABBABLE_SELECTOR) || element.tabIndex < 0 || element.matches(":disabled")) return false;
	return !walkAncestors(element, (ancestor) => {
		if (ancestor instanceof HTMLElement && (ancestor.hidden || ancestor.hasAttribute("inert") || ancestor.getAttribute("aria-hidden") === "true")) return true;
	}, { composed: true });
}

//#endregion
//#region ../utils/dist/dom/shadow-styles.js
/** Inject a `<style>` tag into `document.head` once (idempotent by `id`). */
function ensureGlobalStyle(id, css) {
	const doc = globalThis.document;
	if (!doc || doc.getElementById(id)) return;
	const style = doc.createElement("style");
	style.id = id;
	style.textContent = css;
	doc.head.appendChild(style);
}
function isConstructableStyleSheet(value) {
	return typeof globalThis.CSSStyleSheet !== "undefined" && value instanceof globalThis.CSSStyleSheet;
}
function getStyleText(style) {
	if (typeof style === "string") return style;
	return Array.from(style.cssRules).map((rule) => rule.cssText).join("\n");
}
/** Create a constructable stylesheet when available, otherwise return raw CSS. */
function createShadowStyle(css) {
	if (typeof globalThis.CSSStyleSheet === "undefined") return css;
	const sheet = new globalThis.CSSStyleSheet();
	sheet.replaceSync(css);
	return sheet;
}
/** Apply styles to a shadow root using `adoptedStyleSheets` when available, falling back to `<style>` injection. */
function applyShadowStyles(shadowRoot, styles) {
	if (styles.every(isConstructableStyleSheet) && "adoptedStyleSheets" in shadowRoot) {
		shadowRoot.adoptedStyleSheets = styles;
		return;
	}
	const doc = shadowRoot.ownerDocument;
	for (const styleText of styles.map(getStyleText)) {
		const style = doc.createElement("style");
		style.textContent = styleText;
		shadowRoot.appendChild(style);
	}
}

//#endregion
//#region ../utils/dist/dom/tree.js
function containsComposed(root, element) {
	let current = element;
	while (current) {
		if (current === root || root.contains(current)) return true;
		const nodeRoot = current.getRootNode();
		current = isShadowRoot(nodeRoot) ? nodeRoot.host : current.parentNode;
	}
	return false;
}

//#endregion
//#region src/i18n/controller.ts
let fallbackTranslator;
function getFallbackTranslator() {
	fallbackTranslator ??= createTranslator(getI18nTranslations(DEFAULT_LOCALE), DEFAULT_LOCALE);
	return fallbackTranslator;
}
/** Consumes an i18n context and updates its host when the translator or locale changes. */
var I18nController = class {
	#host;
	#consumer;
	#unsubscribeRegistry;
	/**
	* @param host - Reactive host updated when the i18n value changes.
	* @param context - I18n context to consume.
	*/
	constructor(host, context) {
		this.#host = host;
		this.#consumer = new ContextConsumer(host, {
			context,
			callback: () => this.#host.requestUpdate(),
			subscribe: true
		});
		host.addController(this);
	}
	get value() {
		return this.#consumer.value?.translator ?? getFallbackTranslator();
	}
	get locale() {
		return this.#consumer.value?.locale ?? DEFAULT_LOCALE;
	}
	hostConnected() {
		fallbackTranslator = void 0;
		this.#unsubscribeRegistry = onI18nRegistryChange(() => {
			fallbackTranslator = void 0;
			if (!this.#consumer.value) this.#host.requestUpdate();
		});
	}
	hostDisconnected() {
		this.#unsubscribeRegistry?.();
		this.#unsubscribeRegistry = void 0;
	}
};

//#endregion
//#region ../core/dist/dev/dom/store/features/stream-type.js
const streamTypeFeature = definePlayerFeature({
	name: "streamType",
	state: () => ({ streamType: MediaStreamTypes.UNKNOWN }),
	attach({ target, signal, set }) {
		const { media } = target;
		if (isMediaStreamTypeCapable(media)) {
			const sync = () => set({ streamType: media.streamType });
			sync();
			listen(media, "streamtypechange", sync, { signal });
			return;
		}
		if (!isMediaSeekCapable(media)) return;
		const detect = () => {
			const { duration } = media;
			if (duration === Number.POSITIVE_INFINITY) return MediaStreamTypes.LIVE;
			if (Number.isFinite(duration) && duration > 0) return MediaStreamTypes.ON_DEMAND;
			return MediaStreamTypes.UNKNOWN;
		};
		const sync = () => set({ streamType: detect() });
		sync();
		listen(media, "durationchange", sync, { signal });
		listen(media, "loadedmetadata", sync, { signal });
		listen(media, "emptied", sync, { signal });
		if (isMediaBufferCapable(media)) listen(media, "progress", sync, { signal });
	}
});

//#endregion
//#region ../store/dist/dev/core/selector.js
const stateContext = {
	target: throwNoTargetError,
	signals: new AbortControllerRegistry(),
	get: throwNoTargetError,
	set: throwNoTargetError
};
/**
* Create a type-safe selector for a slice's state.
*
* The selector returns the slice's state, or `undefined` if the slice is not configured in the store.
*
* @example
*   ```ts
*   const selectPlayback = createSelector(playbackSlice);
*   selectPlayback(store.state); // { paused, play, pause, ... } | undefined
*   selectPlayback.displayName; // 'playback' (from slice name)
*   ```;
*
* @param slice - The slice to create a selector for.
*/
function createSelector(slice) {
	const initialState = slice.state(stateContext);
	const keys = [...Object.keys(initialState), ...Object.keys(slice.derived ?? {})];
	const firstKey = keys[0];
	if (!firstKey) return Object.assign(() => void 0, { displayName: slice.name });
	return Object.assign((state) => {
		if (!(firstKey in state)) return void 0;
		return pick(state, keys);
	}, { displayName: slice.name });
}

//#endregion
//#region ../core/dist/dev/dom/store/selectors.js
/** Select the audio track state (audioTrackList, selectAudioTrack). */
const selectAudioTrack = createSelector(audioTrackFeature);
/** Select the buffer state (buffered and seekable ranges). */
const selectBuffer = createSelector(bufferFeature);
/** Select the controls state (controls visible, user-active). */
const selectControls = createSelector(controlsFeature);
/** Select the error state (error, dismissed, dismissError). */
const selectError = createSelector(errorFeature);
/** Select the fullscreen state (fullscreen active, availability). */
const selectFullscreen = createSelector(fullscreenFeature);
/** Select the live state (`liveEdgeStart`, `targetLiveWindow`). */
const selectLive = createSelector(liveFeature);
/** Select resolved content metadata and its user-config writers. */
const selectMetadata = createSelector(metadataFeature);
/** Select the PiP state (picture-in-picture active, availability). */
const selectPiP = createSelector(pipFeature);
/** Select the playback state (paused, ended, play, pause, toggle). */
const selectPlayback = createSelector(playbackFeature);
/** Select the playback rate state (playbackRate, playbackRates, setPlaybackRate). */
const selectPlaybackRate = createSelector(playbackRateFeature);
/** Select the quality state (videoRenditionList, activeVideoRendition, selectVideoRendition). */
const selectQuality = createSelector(qualityFeature);
/** Select the remote playback state (remote playback connection state, availability). */
const selectRemotePlayback = createSelector(remotePlaybackFeature);
/** Select the source state (src, type). */
const selectSource = createSelector(sourceFeature);
/** Select the stream type state (`'on-demand' | 'live' | 'unknown'`). */
const selectStreamType = createSelector(streamTypeFeature);
/** Select the text track state (chapters cues, thumbnail cues). */
const selectTextTrack = createSelector(textTrackFeature);
/** Select the time state (currentTime, duration, seek). */
const selectTime = createSelector(timeFeature);
/** Select the volume state (volume, muted, setVolume, setMuted). */
const selectVolume = createSelector(volumeFeature);

//#endregion
//#region ../core/dist/dev/dom/ui/container-attrs.js
const DEFAULT_CONTAINER_ROLE = "group";
function applyContainerAttrs(element) {
	if (!element.hasAttribute("role")) element.setAttribute("role", DEFAULT_CONTAINER_ROLE);
	if (!element.hasAttribute("tabindex")) element.setAttribute("tabindex", String(0));
}
function focusContainer(element) {
	const active = getDeepActiveElement(element.ownerDocument);
	if (!active || active === element.ownerDocument.body || !containsComposed(element, active)) element.focus({ preventScroll: true });
}

//#endregion
//#region ../core/dist/dev/dom/ui/popover/popup-group.js
function createPopupGroup() {
	let current = null;
	const listeners = /* @__PURE__ */ new Set();
	function notify() {
		for (const listener of listeners) listener();
	}
	return {
		open(member) {
			if (current === member) return;
			const previous = current;
			current = member;
			previous?.close("group-open");
			notify();
		},
		close(member) {
			if (current !== member) return;
			current = null;
			notify();
		},
		isOpenFor(trigger) {
			return trigger !== null && current?.triggerElement === trigger;
		},
		subscribe(listener) {
			listeners.add(listener);
			return () => listeners.delete(listener);
		}
	};
}

//#endregion
//#region ../core/dist/dev/dom/utils/state-data-attrs.js
/**
* Apply state as data attributes to an element.
*
* - `true` → sets `data-keyname=""`
* - Truthy string/number → sets `data-keyname="value"`
* - Falsy → removes the attribute
*
* @example
*   ```ts
*   const state = { paused: true, ended: false };
*   applyStateDataAttrs(element, state);
*   // element has data-paused="", data-ended is removed
*   ```;
*/
function applyStateDataAttrs(element, state, map) {
	for (const key in state) {
		if (map && !(key in map)) continue;
		const name = map?.[key] ?? toDataAttrName(key), value = state[key];
		if (value === true) element.setAttribute(name, "");
		else if (value) element.setAttribute(name, String(value));
		else element.removeAttribute(name);
	}
}
function toDataAttrName(key) {
	return `data-${key.toLowerCase()}`;
}

//#endregion
//#region ../core/dist/dev/core/ui/container/core.js
var ContainerCore = class {
	#media = null;
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		return { controlsVisible: this.#media.controlsVisible };
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/container/data.js
const ContainerDataAttrs = { 
/** Present when player controls are visible. */
controlsVisible: "data-controls-visible" };

//#endregion
//#region src/player/popup-group-context.ts
const POPUP_GROUP_CONTEXT_KEY = Symbol.for("@videojs/popup-group");
const popupGroupContext = createContext(POPUP_GROUP_CONTEXT_KEY);

//#endregion
//#region ../core/dist/dev/i18n/text/container.js
const labelText = {
	key: `container.label`,
	text: "Media player"
};

//#endregion
//#region src/ui/container/container-element.ts
/**
* The visual, interactive player boundary.
*
* A container registers itself with its closest player and provides popup coordination to the controls it contains.
*/
var ContainerElement = class extends UIElement {
	static {
		this.tagName = "media-container";
	}
	#releaseContainer = null;
	#disconnect = null;
	#label = null;
	#core = new ContainerCore();
	#controls = new PlayerController(this, playerContext, selectControls);
	#i18n = new I18nController(this, i18nContext);
	#popupGroup = createPopupGroup();
	#popupGroupProvider = new ContextProvider(this, {
		context: popupGroupContext,
		initialValue: this.#popupGroup
	});
	#container = new ContextConsumer(this, {
		context: containerContext,
		callback: (value) => this.#register(value)
	});
	connectedCallback() {
		super.connectedCallback();
		this.#popupGroupProvider.setValue(this.#popupGroup);
		this.#register(this.#container.value);
		applyContainerAttrs(this);
		this.#applyLabel();
		this.#disconnect = new AbortController();
		listen(this, "pointerup", this.#onPointerUp, { signal: this.#disconnect.signal });
	}
	disconnectedCallback() {
		this.#releaseContainer?.();
		this.#releaseContainer = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
		super.disconnectedCallback();
	}
	update(changed) {
		super.update(changed);
		this.#applyLabel();
		const controls = this.#controls.value;
		if (controls) {
			this.#core.setMedia(controls);
			applyStateDataAttrs(this, this.#core.getState(), ContainerDataAttrs);
		} else this.removeAttribute(ContainerDataAttrs.controlsVisible);
	}
	#register(value) {
		this.#releaseContainer?.();
		this.#releaseContainer = null;
		if (this.isConnected && value) this.#releaseContainer = value.registerContainer(this);
	}
	#applyLabel() {
		const current = this.getAttribute("aria-label");
		if (current && current !== this.#label) return;
		if (this.hasAttribute("aria-labelledby")) {
			if (current === this.#label) {
				this.removeAttribute("aria-label");
				this.#label = null;
			}
			return;
		}
		const label = this.#i18n.value(labelText);
		this.setAttribute("aria-label", label);
		this.#label = label;
	}
	#onPointerUp = () => {
		focusContainer(this);
	};
};

//#endregion
export { createShadowStyle as C, walkAncestors as D, getTabbableElements as E, isDocument as O, applyShadowStyles as S, getDeepActiveElement as T, selectTime as _, selectBuffer as a, getFallbackTranslator as b, selectFullscreen as c, selectPiP as d, selectPlayback as f, selectTextTrack as g, selectRemotePlayback as h, selectAudioTrack as i, i18nContext as k, selectLive as l, selectQuality as m, popupGroupContext as n, selectControls as o, selectPlaybackRate as p, applyStateDataAttrs as r, selectError as s, ContainerElement as t, selectMetadata as u, selectVolume as v, ensureGlobalStyle as w, containsComposed as x, I18nController as y };
//# sourceMappingURL=container-element-DGG9TQ6W.js.map