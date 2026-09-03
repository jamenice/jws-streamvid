import { a as isNull, l as isString, n as isBoolean, o as isNumber, r as isFunction, s as isObject, u as isUndefined } from "./predicate-DrcmolBs.js";
import { c as isDefaultLocale, s as DEFAULT_LOCALE$1 } from "./i18n-C51wLeb2.js";
import { i as snapshotAttributes, n as restoreAttributes } from "./attributes-C0ssqa1e.js";
import { r as resolveText$1, t as translateText$1 } from "./translate-text-BWvoW5-r.js";
import { n as ContextConsumer, r as ReactiveElement, t as UIElement } from "./ui-element-DraIA8Ee.js";
import { a as createContext, i as safeDefine, r as playerContext, t as containerContext } from "./context-DlE_3NHA.js";
import { E as getGestureCoordinator, F as ContextProvider, N as isEditableTarget, P as isInteractiveActivation, l as createState, n as PlayerController, r as SnapshotController } from "./create-player-Duo2X4dJ.js";
import { C as createShadowStyle, D as walkAncestors, E as getTabbableElements, O as isDocument, S as applyShadowStyles, T as getDeepActiveElement, _ as selectTime, a as selectBuffer, c as selectFullscreen, d as selectPiP, f as selectPlayback, g as selectTextTrack, k as i18nContext, n as popupGroupContext, o as selectControls, p as selectPlaybackRate, r as applyStateDataAttrs, s as selectError, v as selectVolume, w as ensureGlobalStyle, x as containsComposed, y as I18nController } from "./container-element-DGG9TQ6W.js";
import { t as listen } from "./listen-CdeggIF8.js";
import { t as noop } from "./noop-BxkeRIz9.js";
import { n as kebabCase } from "./casing-Cu0fL85w.js";
import { i as observeResize, n as observeElements } from "./observe-elements-CRk-iY4n.js";
import { n as isCaptionOrSubtitleTrack } from "./text-track-DMA7pa8W.js";
import { t as MediaError } from "./media-error-zO-Hg4un.js";
import { isText, resolveText, translateText } from "./i18n.dev.js";

//#region ../utils/dist/object/defaults.js
/**
* Creates a new object with default values filled in for undefined properties.
*
* Only keys owned by `defaultValues` are read from `object`; any other key on `object` is ignored. Callers pass live
* DOM elements as `object`, and enumerating those would touch hundreds of inherited accessors such as `offsetWidth` and
* `innerHTML`, forcing style recalculation and layout on every call.
*
* @example
*   ```ts
*   const props = { label: undefined, disabled: true };
*   const defaultProps = { label: '', disabled: false };
*   defaults(props, defaultProps); // { label: '', disabled: true }
*   ```;
*/
function defaults(object, defaultValues) {
	const result = { ...defaultValues };
	for (const key of Object.keys(defaultValues)) {
		const value = object[key];
		if (!isUndefined(value)) result[key] = value;
	}
	return result;
}

//#endregion
//#region ../utils/dist/dom/children.js
function getElementChildren(parent, predicate) {
	const children = [];
	for (let index = 0; index < parent.children.length; index++) {
		const child = parent.children.item(index);
		if (child && predicate(child, index)) children.push(child);
	}
	return children;
}
function findElementChild(parent, predicate) {
	for (let index = 0; index < parent.children.length; index++) {
		const child = parent.children.item(index);
		if (child && predicate(child, index)) return child;
	}
	return null;
}

//#endregion
//#region ../utils/dist/dom/direction.js
/** Check whether an element's text direction is right-to-left. */
function isRTL(element) {
	const dir = element.closest("[dir]")?.getAttribute("dir")?.toLowerCase();
	if (dir === "rtl" || dir === "ltr") return dir === "rtl";
	return getComputedStyle(element).direction === "rtl";
}

//#endregion
//#region ../utils/dist/dom/supports.js
function supportsAnchorPositioning() {
	return typeof CSS !== "undefined" && CSS.supports("anchor-name: --a");
}

//#endregion
//#region ../utils/dist/dom/style.js
function normalizeStyleProperty(property) {
	return property.startsWith("--") ? property : kebabCase(property);
}
function getAnchorNames(element) {
	const value = element.style.getPropertyValue("anchor-name").trim();
	if (!value || value === "none") return [];
	return value.split(",").map((name) => name.trim()).filter(Boolean);
}
function applyStyles(element, styles) {
	for (const [prop, value] of Object.entries(styles)) if (typeof value === "string") element.style.setProperty(normalizeStyleProperty(prop), value);
}
/** Capture authored inline values and priorities for the selected properties. */
function snapshotInlineStyles(element, properties) {
	return [...properties].map((property) => {
		const normalizedProperty = normalizeStyleProperty(property);
		return {
			property: normalizedProperty,
			value: element.style.getPropertyValue(normalizedProperty),
			priority: element.style.getPropertyPriority(normalizedProperty)
		};
	});
}
/** Restore a snapshot created by `snapshotInlineStyles`. */
function restoreInlineStyles(element, snapshot) {
	for (const { property, value, priority } of snapshot) if (value) element.style.setProperty(property, value, priority);
	else element.style.removeProperty(property);
}
/** Apply inline styles for a synchronous callback and restore authored styles afterward. */
function withInlineStyles(element, styles, callback) {
	const snapshot = snapshotInlineStyles(element, Object.keys(styles));
	try {
		applyStyles(element, styles);
		return callback();
	} finally {
		restoreInlineStyles(element, snapshot);
	}
}
/** Read and resolve a CSS property as a pixel length. */
function readCSSLength(element, property, { source = "inline-or-computed" } = {}) {
	const normalizedProperty = normalizeStyleProperty(property);
	let value = source !== "computed" && element instanceof HTMLElement ? element.style.getPropertyValue(normalizedProperty) : "";
	if (!value && source !== "inline") value = getComputedStyle(element).getPropertyValue(normalizedProperty);
	return value.trim() ? resolveCSSLength(element, value) : null;
}
function resolveCSSLength(el, value) {
	const trimmed = value.trim();
	if (!trimmed) return 0;
	const parsed = Number.parseFloat(trimmed);
	if (!Number.isNaN(parsed) && (/^-?\d*\.?\d+$/.test(trimmed) || trimmed.endsWith("px"))) return parsed;
	const doc = el.ownerDocument;
	const root = doc?.documentElement;
	if (!Number.isNaN(parsed) && trimmed.endsWith("rem")) return parsed * (root ? Number.parseFloat(getComputedStyle(root).fontSize) || 16 : 16);
	if (!Number.isNaN(parsed) && trimmed.endsWith("em")) return parsed * (el instanceof HTMLElement ? Number.parseFloat(getComputedStyle(el).fontSize) || 16 : 16);
	if (!doc) return Number.isNaN(parsed) ? 0 : parsed;
	const measurementEl = doc.createElement("div");
	measurementEl.style.position = "absolute";
	measurementEl.style.visibility = "hidden";
	measurementEl.style.pointerEvents = "none";
	measurementEl.style.inlineSize = trimmed;
	if (!measurementEl.style.inlineSize) return 0;
	measurementEl.style.blockSize = "0";
	measurementEl.style.padding = "0";
	measurementEl.style.border = "0";
	measurementEl.style.inset = "0";
	const computed = getComputedStyle(el);
	measurementEl.style.fontSize = computed.fontSize;
	for (let i = 0; i < computed.length; i++) {
		const name = computed.item(i);
		if (name.startsWith("--")) measurementEl.style.setProperty(name, computed.getPropertyValue(name));
	}
	const parent = doc.body ?? doc.documentElement;
	if (!parent) return Number.isNaN(parsed) ? 0 : parsed;
	parent.appendChild(measurementEl);
	if (getComputedStyle(measurementEl).inlineSize === "auto") {
		measurementEl.remove();
		return 0;
	}
	const pixels = measurementEl.getBoundingClientRect().width;
	measurementEl.remove();
	if (Number.isFinite(pixels)) return pixels;
	return Number.isNaN(parsed) ? 0 : parsed;
}

//#endregion
//#region ../utils/dist/dom/layout.js
/** Read an element's current rendered size. */
function getElementSize(element, { box = "bounding", overflow = "none" } = {}) {
	const rect = element.getBoundingClientRect();
	let width = box === "layout" ? element.offsetWidth || rect.width : rect.width;
	let height = box === "layout" ? element.offsetHeight || rect.height : rect.height;
	if (overflow === "width" || overflow === "both") width = Math.max(width, element.scrollWidth);
	if (overflow === "height" || overflow === "both") height = Math.max(height, element.scrollHeight);
	return {
		width,
		height
	};
}
/** Measure an element with optional temporary inline style overrides. */
function measureElement(element, options = {}) {
	const { styles, ...sizeOptions } = options;
	const measure = () => getElementSize(element, sizeOptions);
	return styles ? withInlineStyles(element, styles, measure) : measure();
}
/** Read logical padding edges in pixels. */
function getElementPadding(element) {
	const style = getComputedStyle(element);
	return {
		inlineStart: Number.parseFloat(style.paddingInlineStart) || 0,
		inlineEnd: Number.parseFloat(style.paddingInlineEnd) || 0,
		blockStart: Number.parseFloat(style.paddingBlockStart) || 0,
		blockEnd: Number.parseFloat(style.paddingBlockEnd) || 0
	};
}
function getInlineExtent(edges) {
	return edges.inlineStart + edges.inlineEnd;
}
function getBlockExtent(edges) {
	return edges.blockStart + edges.blockEnd;
}
function getPaddingOrigin(element) {
	const style = getComputedStyle(element);
	return {
		x: Number.parseFloat(style.paddingLeft) || 0,
		y: Number.parseFloat(style.paddingTop) || 0
	};
}
function defaultResolveChildrenSize(measurements) {
	if (measurements.length === 0) return {
		width: 0,
		height: 0
	};
	const width = Math.max(...measurements.map(({ offsetLeft, size }) => offsetLeft + size.width));
	const firstTop = measurements[0].offsetTop;
	return {
		width,
		height: measurements.some(({ offsetTop }) => offsetTop !== firstTop) ? Math.max(...measurements.map(({ offsetTop, size }) => offsetTop + size.height)) : measurements.reduce((total, { size }) => total + size.height, 0)
	};
}
/** Measure the layout occupied by a collection of child elements. */
function measureElementChildren(container, { children, includePadding = false, maxWidth = null, measure = (element, width) => measureElement(element, width === void 0 ? void 0 : { styles: { width: `${width}px` } }), resolveSize = defaultResolveChildrenSize } = {}) {
	const elements = [...children ?? Array.from(container.children).filter((child) => child instanceof HTMLElement)].filter((element) => !element.hidden);
	const padding = includePadding ? getElementPadding(container) : {
		inlineStart: 0,
		inlineEnd: 0,
		blockStart: 0,
		blockEnd: 0
	};
	const inlinePadding = getInlineExtent(padding);
	const blockPadding = getBlockExtent(padding);
	const paddingOrigin = includePadding ? getPaddingOrigin(container) : {
		x: 0,
		y: 0
	};
	if (elements.length === 0) return {
		width: inlinePadding,
		height: blockPadding
	};
	const collect = (width) => elements.map((element) => ({
		element,
		size: measure(element, width),
		offsetLeft: element.offsetLeft - paddingOrigin.x,
		offsetTop: element.offsetTop - paddingOrigin.y
	}));
	let measurements = collect();
	const naturalWidth = resolveSize(measurements).width + inlinePadding;
	const width = maxWidth === null ? naturalWidth : Math.min(naturalWidth, Math.max(0, maxWidth));
	if (width < naturalWidth) measurements = collect(Math.max(0, width - inlinePadding));
	return {
		width,
		height: resolveSize(measurements).height + blockPadding
	};
}

//#endregion
//#region ../utils/dist/dom/platform.js
function isMacOS() {
	return typeof navigator !== "undefined" && /mac/i.test(navigator.userAgent);
}

//#endregion
//#region ../utils/dist/dom/popover.js
const ZERO_OFFSETS$1 = {
	sideOffset: 0,
	boundaryOffset: 0
};
const OPPOSITE_SIDE$1 = {
	top: "bottom",
	bottom: "top",
	left: "right",
	right: "left"
};
function getSideAvailable(triggerRect, boundaryRect, side, offsets) {
	const boundaryOffset = offsets.boundaryOffset ?? 0;
	switch (side) {
		case "top": return triggerRect.top - boundaryRect.top - boundaryOffset - offsets.sideOffset;
		case "bottom": return boundaryRect.bottom - triggerRect.bottom - boundaryOffset - offsets.sideOffset;
		case "left": return triggerRect.left - boundaryRect.left - boundaryOffset - offsets.sideOffset;
		case "right": return boundaryRect.right - triggerRect.right - boundaryOffset - offsets.sideOffset;
	}
}
/** Resolve the preferred side against a positioning boundary. */
function getPositionedSide(triggerRect, positionedRect, boundaryRect, opts, offsets = ZERO_OFFSETS$1) {
	const preferred = opts.side;
	const opposite = OPPOSITE_SIDE$1[preferred];
	const size = preferred === "top" || preferred === "bottom" ? positionedRect.height : positionedRect.width;
	const preferredSpace = getSideAvailable(triggerRect, boundaryRect, preferred, offsets);
	if (preferredSpace >= size) return preferred;
	return getSideAvailable(triggerRect, boundaryRect, opposite, offsets) > preferredSpace ? opposite : preferred;
}
function tryShowPopover(el) {
	try {
		el?.showPopover?.();
	} catch {}
}
function tryHidePopover(el) {
	try {
		el?.hidePopover?.();
	} catch {}
}

//#endregion
//#region ../utils/dist/dom/raf-throttle.js
/** Throttle a function to fire at most once per animation frame. */
function rafThrottle(fn) {
	let rafId = null;
	let latestArgs;
	const throttled = (...args) => {
		latestArgs = args;
		if (rafId !== null) return;
		rafId = requestAnimationFrame(() => {
			rafId = null;
			fn(...latestArgs);
		});
	};
	throttled.cancel = () => {
		if (rafId !== null) {
			cancelAnimationFrame(rafId);
			rafId = null;
		}
	};
	return throttled;
}

//#endregion
//#region ../utils/dist/dom/template.js
/** Create an `HTMLTemplateElement` from an HTML string, or `null` when `document` is unavailable (SSR). */
function createTemplate(html) {
	const doc = globalThis.document;
	if (!doc) return null;
	const template = doc.createElement("template");
	template.innerHTML = html;
	return template;
}
/** Return the first direct-child template in a container. */
function getTemplateElement(container) {
	for (const child of container.children) if (child.localName === "template" && "content" in child) return child;
	return null;
}
/** Return a template's only element root, or `null` when it does not contain exactly one. */
function getTemplateRoot(template) {
	const root = template.content.firstElementChild;
	return root && !root.nextElementSibling ? root : null;
}
/** Deep-clone a resolved template root into the target document. */
function cloneTemplateRoot(root, targetDocument = root.ownerDocument) {
	return targetDocument.importNode(root, true);
}
/** Deep-clone a template's content into a container. */
function renderTemplate(container, template) {
	container.appendChild(container.ownerDocument.importNode(template.content, true));
}

//#endregion
//#region ../utils/dist/function/throttle.js
/**
* Throttle: limits `fn` to at most once per `ms` window.
*
* - Default (no options): trailing-edge only — the first call schedules a timer; subsequent calls within the window
*   update the arguments. The function fires once per window with the latest arguments.
* - `{ leading: true }`: leading + trailing — the first call invokes immediately and opens a cooldown window. Subsequent
*   calls within the window are coalesced to a single trailing-edge invocation.
*/
function throttle(fn, ms, options) {
	const leading = options?.leading ?? false;
	let timerId = null;
	let latestArgs;
	let hasPending = false;
	function startCooldown() {
		timerId = setTimeout(() => {
			timerId = null;
			if (hasPending) {
				hasPending = false;
				fn(...latestArgs);
				startCooldown();
			}
		}, ms);
	}
	const throttled = (...args) => {
		latestArgs = args;
		if (leading) if (timerId === null) {
			fn(...latestArgs);
			startCooldown();
		} else hasPending = true;
		else {
			if (timerId !== null) return;
			timerId = setTimeout(() => {
				timerId = null;
				fn(...latestArgs);
			}, ms);
		}
	};
	throttled.cancel = () => {
		if (timerId !== null) {
			clearTimeout(timerId);
			timerId = null;
		}
		hasPending = false;
	};
	return throttled;
}

//#endregion
//#region ../core/dist/dev/dom/media-actions.js
const MEDIA_INPUT_ACTION_OVERRIDES = {
	seekStep({ store, value }) {
		if (isUndefined(value)) return;
		const time = selectTime(store.state);
		if (!time) return;
		time.seek(time.currentTime + value);
	},
	volumeStep({ store, value }) {
		if (isUndefined(value)) return;
		const vol = selectVolume(store.state);
		if (!vol) return;
		vol.setVolume(vol.volume + value);
	},
	speedUp({ store }) {
		const rate = selectPlaybackRate(store.state);
		if (!rate) return;
		const { playbackRates, playbackRate } = rate;
		const idx = playbackRates.indexOf(playbackRate);
		const next = idx < 0 || idx >= playbackRates.length - 1 ? 0 : idx + 1;
		rate.setPlaybackRate(playbackRates[next]);
	},
	speedDown({ store }) {
		const rate = selectPlaybackRate(store.state);
		if (!rate) return;
		const { playbackRates, playbackRate } = rate;
		const idx = playbackRates.indexOf(playbackRate);
		const next = idx <= 0 ? playbackRates.length - 1 : idx - 1;
		rate.setPlaybackRate(playbackRates[next]);
	}
};

//#endregion
//#region ../core/dist/dev/dom/hotkey/actions.js
function isHotkeyToggleAction(action) {
	return action.startsWith("toggle");
}
const HOTKEY_ACTIONS = {
	togglePaused({ store }) {
		const playback = selectPlayback(store.state);
		if (!playback) return;
		playback.paused ? playback.play() : playback.pause();
	},
	toggleMuted({ store }) {
		selectVolume(store.state)?.toggleMuted();
	},
	toggleFullscreen({ store }) {
		const fs = selectFullscreen(store.state);
		if (!fs) return;
		fs.fullscreen ? fs.exitFullscreen() : fs.requestFullscreen();
	},
	toggleSubtitles({ store }) {
		selectTextTrack(store.state)?.toggleSubtitles();
	},
	togglePictureInPicture({ store }) {
		const pip = selectPiP(store.state);
		if (!pip) return;
		pip.pip ? pip.exitPictureInPicture() : pip.requestPictureInPicture();
	},
	seekStep: MEDIA_INPUT_ACTION_OVERRIDES.seekStep,
	volumeStep: MEDIA_INPUT_ACTION_OVERRIDES.volumeStep,
	speedUp: MEDIA_INPUT_ACTION_OVERRIDES.speedUp,
	speedDown: MEDIA_INPUT_ACTION_OVERRIDES.speedDown,
	seekToPercent({ store, value, key }) {
		const time = selectTime(store.state);
		if (!time || time.duration <= 0) return;
		let percent;
		if (!isUndefined(value)) percent = value;
		else if (key >= "0" && key <= "9") percent = Number(key) * 10;
		else return;
		time.seek(percent / 100 * time.duration);
	}
};
function resolveHotkeyAction(name) {
	const resolver = HOTKEY_ACTIONS[name];
	if (!resolver) console.warn(`[vjs-hotkey] Unknown action: "${name}"`);
	return resolver;
}

//#endregion
//#region ../core/dist/dev/dom/hotkey/aria.js
const ARIA_MODIFIER_MAP = {
	shift: "Shift",
	ctrl: "Control",
	alt: "Alt",
	meta: "Meta"
};
const DISPLAY_MODIFIER_MAP = {
	shift: "Shift",
	ctrl: "Ctrl",
	alt: "Alt",
	meta: "Meta"
};
const MODIFIER_ORDER = [
	"ctrl",
	"shift",
	"alt",
	"meta"
];
/**
* Convert parsed key bindings to a WAI-ARIA `aria-keyshortcuts` formatted string.
*
* @example
*   ```ts
*   toAriaKeyShortcut(parseHotkeyPattern('Ctrl+Shift+f'));
*   // "Control+Shift+f"
*
*   toAriaKeyShortcut([...parseHotkeyPattern('k'), ...parseHotkeyPattern('Space')]);
*   // "k Space"
*   ```;
*/
function toAriaKeyShortcut(bindings) {
	return bindings.map((b) => {
		const parts = [];
		for (const mod of MODIFIER_ORDER) if (b.modifiers.has(mod)) parts.push(ARIA_MODIFIER_MAP[mod]);
		parts.push(b.originalKey);
		return parts.join("+");
	}).join(" ");
}
/** Convert a parsed key binding to a compact display shortcut. */
function toDisplayKeyShortcut(binding) {
	const parts = [];
	for (const mod of MODIFIER_ORDER) if (binding.modifiers.has(mod)) parts.push(DISPLAY_MODIFIER_MAP[mod]);
	parts.push(toDisplayKey(binding.originalKey));
	return parts.join("+");
}
function toDisplayKey(key) {
	return key.length === 1 ? key.toUpperCase() : key;
}

//#endregion
//#region ../core/dist/dev/dom/hotkey/coordinator.js
var HotkeyCoordinator = class {
	#target;
	#bindings = [];
	#nextId = 0;
	#disconnect = null;
	#docDisconnect = null;
	#activationSubscribers = /* @__PURE__ */ new Set();
	#shortcutSubscribers = /* @__PURE__ */ new Set();
	#destroyed = false;
	constructor(target) {
		this.#target = target;
	}
	subscribe(callback) {
		this.#activationSubscribers.add(callback);
		return () => this.#activationSubscribers.delete(callback);
	}
	subscribeShortcutChanges(callback) {
		this.#shortcutSubscribers.add(callback);
		return () => this.#shortcutSubscribers.delete(callback);
	}
	add(options) {
		const binding = {
			parsed: parseHotkeyPattern(options.keys),
			options,
			id: this.#nextId++
		};
		this.#bindings.push(binding);
		this.#sortBindings();
		if (options.target === "document") this.#connectDocument();
		else this.#connect();
		this.#notify();
		let removed = false;
		return () => {
			if (removed) return;
			removed = true;
			const idx = this.#bindings.indexOf(binding);
			if (idx !== -1) this.#bindings.splice(idx, 1);
			this.#maybeDisconnect();
			this.#notify();
		};
	}
	getAriaKeys(action) {
		return this.getShortcut(action).aria;
	}
	getShortcut(action, value) {
		const bindings = this.#getActionBindings(action, value);
		if (!bindings.length) return {};
		const parsed = bindings.flatMap((binding) => binding.parsed);
		const preferred = bindings[bindings.length - 1];
		return {
			aria: toAriaKeyShortcut(parsed),
			shortcut: this.#formatDisplayShortcut(preferred)
		};
	}
	destroy() {
		if (this.#destroyed) return;
		this.#destroyed = true;
		this.#disconnect?.abort();
		this.#disconnect = null;
		this.#docDisconnect?.abort();
		this.#docDisconnect = null;
		this.#bindings = [];
		this.#notify();
		this.#activationSubscribers.clear();
		this.#shortcutSubscribers.clear();
	}
	#sortBindings() {
		this.#bindings.sort((a, b) => {
			const specDiff = b.parsed[0].modifiers.size - a.parsed[0].modifiers.size;
			if (specDiff !== 0) return specDiff;
			return a.id - b.id;
		});
	}
	#connect() {
		if (this.#disconnect) return;
		this.#disconnect = new AbortController();
		listen(this.#target, "keydown", this.#handleEvent, { signal: this.#disconnect.signal });
	}
	#connectDocument() {
		if (this.#docDisconnect) return;
		this.#docDisconnect = new AbortController();
		listen(document, "keydown", this.#handleEvent, { signal: this.#docDisconnect.signal });
	}
	#maybeDisconnect() {
		const hasPlayer = this.#bindings.some((b) => b.options.target !== "document");
		const hasDoc = this.#bindings.some((b) => b.options.target === "document");
		if (!hasPlayer) {
			this.#disconnect?.abort();
			this.#disconnect = null;
		}
		if (!hasDoc) {
			this.#docDisconnect?.abort();
			this.#docDisconnect = null;
		}
	}
	#handleEvent = (event) => {
		if (event.key === "Unidentified") return;
		if (isInteractiveActivation(event)) return;
		if (event.defaultPrevented) return;
		const editable = isEditableTarget(event);
		for (const binding of this.#bindings) {
			const { options, parsed } = binding;
			if (options.disabled) continue;
			if (event.repeat && options.repeatable === false) continue;
			if (options.target === "document" !== (event.currentTarget === document)) continue;
			for (const p of parsed) {
				if (!matchesHotkeyEvent(p, event)) continue;
				if (editable && p.modifiers.size === 0) continue;
				if (this.#activationSubscribers.size > 0) {
					const activateEvent = {
						source: "hotkey",
						action: options.action,
						value: options.value,
						event
					};
					for (const cb of this.#activationSubscribers) try {
						cb(activateEvent);
					} catch (error) {
						console.warn("[vjs-hotkey] subscribe callback threw:", error);
					}
				}
				event.preventDefault();
				options.onActivate(event, p.originalKey);
				return;
			}
		}
	};
	#getActionBindings(action, value) {
		return this.#bindings.filter((binding) => {
			if (binding.options.disabled) return false;
			if (binding.options.action !== action) return false;
			if (isUndefined(value)) return true;
			return binding.options.value === value;
		}).sort((a, b) => a.id - b.id);
	}
	#formatDisplayShortcut(binding) {
		if (binding.options.keys === "0-9") return binding.options.keys;
		return toDisplayKeyShortcut(binding.parsed[0]);
	}
	#notify() {
		for (const subscriber of this.#shortcutSubscribers) subscriber();
	}
};

//#endregion
//#region ../core/dist/dev/dom/hotkey/hotkey.js
const MODIFIER_KEYS = /* @__PURE__ */ new Set([
	"shift",
	"ctrl",
	"alt",
	"meta"
]);
/**
* Parse a key pattern string into one or more bindings.
*
* @example
*   ```ts
*   parseHotkeyPattern('>');
*   // [{ modifiers: Set(), key: '>', originalKey: '>' }]
*
*   parseHotkeyPattern('0-9');
*   // 10 bindings, one per digit
*   ```;
*/
function parseHotkeyPattern(pattern) {
	if (pattern === "0-9") return Array.from({ length: 10 }, (_, i) => ({
		modifiers: /* @__PURE__ */ new Set(),
		key: String(i),
		originalKey: String(i)
	}));
	const segments = pattern.split("+");
	const rawKey = segments.pop();
	const modifiers = /* @__PURE__ */ new Set();
	for (const seg of segments) {
		const lower = seg.toLowerCase();
		if (lower === "mod") modifiers.add(isMacOS() ? "meta" : "ctrl");
		else if (MODIFIER_KEYS.has(lower)) modifiers.add(lower);
		else console.warn(`[vjs-hotkey] Unknown modifier: "${seg}" in pattern "${pattern}"`);
	}
	return [{
		modifiers,
		key: rawKey === "Space" ? " " : rawKey.toLowerCase(),
		originalKey: rawKey
	}];
}
/**
* Single non-letter character — layout-dependent modifiers (Shift, Alt/Option) were used to produce the character
* itself, not as deliberate modifiers (e.g. Shift+. → ">", Option+Shift → ">" on some Mac layouts). Letters excluded
* because Shift changes case intentionally (k vs K). Named keys excluded because event.key.length > 1 (ArrowLeft, Tab,
* etc.).
*/
function isImplicitModifierKey(key) {
	return key.length === 1 && !/[a-z]/i.test(key);
}
/** Whether a parsed binding matches a keyboard event. */
function matchesHotkeyEvent(binding, event) {
	if (event.key === "Unidentified") return false;
	if (event.key.toLowerCase() !== binding.key) return false;
	const implicit = isImplicitModifierKey(event.key);
	const shiftKey = implicit ? event.shiftKey && binding.modifiers.has("shift") : event.shiftKey;
	const altKey = implicit ? event.altKey && binding.modifiers.has("alt") : event.altKey;
	if (shiftKey !== binding.modifiers.has("shift")) return false;
	if (event.ctrlKey !== binding.modifiers.has("ctrl")) return false;
	if (altKey !== binding.modifiers.has("alt")) return false;
	if (event.metaKey !== binding.modifiers.has("meta")) return false;
	return true;
}
const coordinators = /* @__PURE__ */ new WeakMap();
/** Look up or create the hotkey coordinator for a target element. */
function getHotkeyCoordinator(target) {
	let coordinator = coordinators.get(target);
	if (!coordinator) {
		coordinator = new HotkeyCoordinator(target);
		coordinators.set(target, coordinator);
	}
	return coordinator;
}
/**
* Register a hotkey binding on a target element.
*
* @example
*   ```ts
*   const cleanup = createHotkey(container, {
*     keys: 'k',
*     onActivate: () => (store.paused ? store.play() : store.pause()),
*   });
*
*   // Later: remove the binding
*   cleanup();
*   ```;
*
* @returns A cleanup function that removes the binding.
*/
function createHotkey(target, options) {
	return getHotkeyCoordinator(target).add(options);
}

//#endregion
//#region ../core/dist/dev/dom/hotkey/hotkey-events.js
/** Dispatched when display shortcut metadata changes (e.g. coordinator updates). Tooltips may listen. */
const HOTKEY_SHORTCUT_CHANGE_EVENT = "hotkey-shortcut-change";

//#endregion
//#region ../core/dist/dev/dom/ui/button.js
function createButton(options) {
	const { onActivate, isDisabled } = options;
	return {
		role: "button",
		tabIndex: 0,
		onClick(event) {
			if (isDisabled()) {
				event.preventDefault();
				return;
			}
			onActivate(event);
		},
		onPointerDown(event) {
			if (isDisabled()) event.preventDefault();
		},
		onMouseDown(event) {
			if (isDisabled()) event.preventDefault();
		},
		onKeyDown(event) {
			if (event.target !== event.currentTarget) return;
			if (isDisabled()) {
				if (event.key !== "Tab") event.preventDefault();
				return;
			}
			if (event.key === "Enter") {
				event.preventDefault();
				onActivate(event);
			} else if (event.key === " ") event.preventDefault();
		},
		onKeyUp(event) {
			if (event.target !== event.currentTarget) return;
			if (isDisabled()) return;
			if (event.key === " ") onActivate(event);
		}
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/dismiss-layer.js
function createDismissLayer(options) {
	const { transition } = options;
	const state = transition.state;
	const abort = new AbortController();
	let docAbort = null;
	function open(element) {
		if (abort.signal.aborted) return null;
		const { active, status } = state.current;
		if (active && status !== "ending") return null;
		if (status === "ending") transition.cancel();
		return transition.open(element);
	}
	function close(element) {
		const { active, status } = state.current;
		if (abort.signal.aborted || !active || status === "ending") return null;
		return transition.close(element);
	}
	function setupDocumentListeners() {
		cleanupDocumentListeners();
		if (typeof document === "undefined") return;
		docAbort = new AbortController();
		const { signal } = docAbort;
		listen(document, "keydown", handleKeydown, { signal });
		options.onDocumentActive?.(signal);
	}
	function cleanupDocumentListeners() {
		docAbort?.abort();
		docAbort = null;
	}
	function handleKeydown(event) {
		if (event.key !== "Escape") return;
		if (event.defaultPrevented) return;
		if (!state.current.active) return;
		if (!(options.closeOnEscape?.() ?? true)) return;
		options.onEscapeDismiss(event);
	}
	const unsubscribe = state.subscribe(() => {
		if (state.current.active && state.current.status !== "ending") setupDocumentListeners();
		else cleanupDocumentListeners();
	});
	abort.signal.addEventListener("abort", () => {
		unsubscribe();
		transition.destroy();
		cleanupDocumentListeners();
	});
	function destroy() {
		if (abort.signal.aborted) return;
		abort.abort();
	}
	return {
		input: state,
		open,
		close,
		signal: abort.signal,
		destroy
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/dialog.js
/** Manages modal dialog transitions, dismissal, initial focus, focus trapping, and focus restoration. */
function createDialog(options) {
	let popupElement = null;
	let triggerElement = null;
	let previousFocus = null;
	let focusFrame = 0;
	const isolatedElements = /* @__PURE__ */ new Map();
	const layer = createDismissLayer({
		transition: options.transition,
		closeOnEscape: options.closeOnEscape,
		onEscapeDismiss(event) {
			event.preventDefault();
			event.stopPropagation();
			applyClose();
		},
		onDocumentActive(signal) {
			listen(document, "keydown", handleDocumentKeydown, {
				capture: true,
				signal
			});
			listen(document, "focusin", handleDocumentFocusin, { signal });
		}
	});
	const state = layer.input;
	function applyOpen() {
		previousFocus = getDeepActiveElement();
		const opening = layer.open(() => popupElement);
		if (!opening) return;
		isolateBackground();
		options.onOpenChange(true);
		scheduleInitialFocus();
		opening.then(() => {
			if (layer.signal.aborted || !state.current.active || state.current.status !== "idle") return;
			options.onOpenChangeComplete?.(true);
		});
	}
	function applyClose() {
		const closing = layer.close(popupElement);
		if (!closing) return;
		cancelAnimationFrame(focusFrame);
		focusFrame = 0;
		options.onOpenChange(false);
		closing.then(() => {
			if (layer.signal.aborted || state.current.active) return;
			restoreBackground();
			const restoreTarget = triggerElement?.isConnected ? triggerElement : previousFocus;
			if (restoreTarget?.isConnected) restoreTarget.focus();
			previousFocus = null;
			options.onOpenChangeComplete?.(false);
		});
	}
	function scheduleInitialFocus() {
		cancelAnimationFrame(focusFrame);
		focusFrame = requestAnimationFrame(() => {
			focusFrame = 0;
			if (layer.signal.aborted || !state.current.active || !popupElement) return;
			(popupElement.querySelector("[autofocus]") ?? getTabbableElements(popupElement)[0] ?? popupElement).focus();
		});
	}
	function handleDocumentKeydown(event) {
		if (event.key !== "Tab" || !state.current.active || !popupElement) return;
		const tabbable = getTabbableElements(popupElement);
		if (tabbable.length === 0) {
			event.preventDefault();
			popupElement.focus();
			return;
		}
		const active = getDeepActiveElement();
		const first = tabbable[0];
		const last = tabbable[tabbable.length - 1];
		if (event.shiftKey && (active === first || !active || !containsComposed(popupElement, active))) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && (active === last || !active || !containsComposed(popupElement, active))) {
			event.preventDefault();
			first.focus();
		}
	}
	function handleDocumentFocusin(event) {
		if (!state.current.active || !popupElement) return;
		if (event.target instanceof Element && containsComposed(popupElement, event.target)) return;
		(getTabbableElements(popupElement)[0] ?? popupElement).focus();
	}
	function setTriggerElement(el) {
		triggerElement = el;
	}
	function setPopupElement(el) {
		if (popupElement !== el) restoreBackground();
		popupElement = el;
		if (el && state.current.active) isolateBackground();
		const active = getDeepActiveElement();
		if (el && state.current.active && (!active || !containsComposed(el, active))) scheduleInitialFocus();
	}
	layer.signal.addEventListener("abort", () => {
		cancelAnimationFrame(focusFrame);
		focusFrame = 0;
		restoreBackground();
		popupElement = null;
		triggerElement = null;
		previousFocus = null;
	});
	return {
		input: state,
		triggerProps: { onClick() {
			applyOpen();
		} },
		open: applyOpen,
		close: applyClose,
		setTriggerElement,
		setPopupElement,
		destroy: layer.destroy
	};
	function isolateBackground() {
		if (!popupElement?.isConnected || isolatedElements.size > 0) return;
		walkAncestors(popupElement, (current) => {
			if (current === document.body) return true;
			if (current.assignedSlot) {
				for (const sibling of current.assignedSlot.assignedElements({ flatten: true })) if (sibling !== current && sibling instanceof HTMLElement) makeInert(sibling);
				return;
			}
			const parent = current.parentElement;
			if (parent) {
				for (const sibling of parent.children) if (sibling !== current && sibling instanceof HTMLElement) makeInert(sibling);
				return;
			}
			const root = current.getRootNode();
			if (!(root instanceof ShadowRoot)) return void 0;
			for (const sibling of root.children) if (sibling !== current && sibling instanceof HTMLElement) makeInert(sibling);
		}, { composed: true });
	}
	function makeInert(element) {
		isolatedElements.set(element, element.hasAttribute("inert"));
		element.setAttribute("inert", "");
	}
	function restoreBackground() {
		for (const [element, wasInert] of isolatedElements) if (!wasInert) element.removeAttribute("inert");
		isolatedElements.clear();
	}
}

//#endregion
//#region ../core/dist/dev/core/ui/transition.js
/** Shared data attributes for open/close transition state. Spread into component data-attrs objects. */
const TransitionDataAttrs = {
	/** Present during the open transition. */
	transitionStarting: "data-starting-style",
	/** Present during the close transition. */
	transitionEnding: "data-ending-style"
};
function getTransitionFlags(status) {
	return {
		transitionStarting: status === "starting",
		transitionEnding: status === "ending"
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/indicator/indicator-lifecycle.js
var IndicatorCloseController = class {
	#timer = null;
	#close;
	#getDelay;
	constructor(close, getDelay) {
		this.#close = close;
		this.#getDelay = getDelay;
	}
	arm() {
		this.clear();
		this.#timer = setTimeout(() => {
			this.#timer = null;
			this.#close();
		}, this.#getDelay());
	}
	clear() {
		if (this.#timer === null) return;
		clearTimeout(this.#timer);
		this.#timer = null;
	}
	close() {
		this.clear();
		this.#close();
	}
	destroy() {
		this.clear();
	}
};
var IndicatorVisibilityCoordinator = class {
	#handles = /* @__PURE__ */ new Set();
	register(handle) {
		this.#handles.add(handle);
		return () => this.#handles.delete(handle);
	}
	show(handle) {
		for (const nextHandle of this.#handles) if (nextHandle !== handle) nextHandle.close();
	}
};
function getIndicatorCloseDelay(props) {
	return props.closeDelay ?? 800;
}
function isIndicatorPresent(current, transition) {
	return current.open || transition.active;
}
function getRenderedIndicatorState(current, snapshot, transition) {
	const payload = current.open ? current : snapshot;
	return {
		...payload,
		open: current.open && transition.active,
		generation: current.open ? current.generation : payload.generation,
		...getTransitionFlags(transition.status)
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/input-action.js
function toInputActionEvent(event) {
	return {
		action: event.action,
		value: event.value,
		source: event.source,
		key: "key" in event.event ? event.event.key : void 0
	};
}
function getMediaSnapshot(store) {
	if (!store) return {};
	const state = store.state;
	const time = selectTime(state);
	const textTrack = selectTextTrack(state);
	return {
		paused: selectPlayback(state)?.paused,
		volume: selectVolume(state)?.volume,
		muted: selectVolume(state)?.muted,
		playbackRate: selectPlaybackRate(state)?.playbackRate,
		fullscreen: selectFullscreen(state)?.fullscreen,
		subtitlesShowing: textTrack?.subtitlesShowing,
		subtitlesAvailable: textTrack ? (textTrack.textTrackList ?? []).some(isCaptionOrSubtitleTrack) : void 0,
		pip: selectPiP(state)?.pip,
		currentTime: time?.currentTime,
		duration: time?.duration,
		seeking: time?.seeking
	};
}
function subscribeToInputActions(container, callback) {
	const handleEvent = (event) => callback(toInputActionEvent(event));
	const gestureUnsubscribe = getGestureCoordinator(container).subscribe(handleEvent);
	const hotkeyUnsubscribe = getHotkeyCoordinator(container).subscribe(handleEvent);
	return () => {
		gestureUnsubscribe();
		hotkeyUnsubscribe();
	};
}
const indicatorVisibilityCoordinators = /* @__PURE__ */ new WeakMap();
function getIndicatorVisibilityCoordinator(container) {
	let coordinator = indicatorVisibilityCoordinators.get(container);
	if (!coordinator) {
		coordinator = new IndicatorVisibilityCoordinator();
		indicatorVisibilityCoordinators.set(container, coordinator);
	}
	return coordinator;
}

//#endregion
//#region ../core/dist/dev/dom/utils/layout.js
function forceLayout(element) {
	element?.getBoundingClientRect();
}
function createDOMRect(left, top, width, height) {
	const right = left + width;
	const bottom = top + height;
	return {
		x: left,
		y: top,
		width,
		height,
		top,
		right,
		bottom,
		left,
		toJSON() {
			return {
				x: left,
				y: top,
				width,
				height,
				top,
				right,
				bottom,
				left
			};
		}
	};
}
function intersectDOMRects(firstRect, secondRect) {
	const left = Math.max(firstRect.left, secondRect.left);
	const top = Math.max(firstRect.top, secondRect.top);
	const right = Math.min(firstRect.right, secondRect.right);
	const bottom = Math.min(firstRect.bottom, secondRect.bottom);
	return createDOMRect(left, top, Math.max(0, right - left), Math.max(0, bottom - top));
}
function getPositioningBoundaryRect(boundaryElement) {
	const viewportRect = document.documentElement.getBoundingClientRect();
	return boundaryElement ? intersectDOMRects(viewportRect, boundaryElement.getBoundingClientRect()) : viewportRect;
}
function resolvePositioningBoundary(boundary, options = {}) {
	if (!boundary) return null;
	if (!isString(boundary)) return boundary;
	if (boundary === "viewport") return null;
	if (boundary === "container") return options.container ?? null;
	try {
		return (options.root ?? document).querySelector(boundary);
	} catch {
		return null;
	}
}

//#endregion
//#region ../core/dist/dev/dom/ui/popover/popover.js
function createPopover(options) {
	const { onOpenChange, closeOnOutsideClick } = options;
	let triggerEl = null;
	let popupEl = null;
	let hoverTimeout = null;
	const capturedPointers = /* @__PURE__ */ new Set();
	let ignoreNextBlurClose = false;
	let blurGuardTimeout = null;
	const layer = createDismissLayer({
		transition: options.transition,
		closeOnEscape: options.closeOnEscape,
		onEscapeDismiss(event) {
			event.preventDefault();
			applyClose("escape", event);
		},
		onDocumentActive(signal) {
			listen(document, "pointerdown", handleDocumentPointerdown, {
				capture: true,
				signal
			});
		}
	});
	const state = layer.input;
	const groupMember = {
		close(reason) {
			applyClose(reason);
		},
		get triggerElement() {
			return triggerEl;
		}
	};
	function clearHoverTimeout() {
		if (hoverTimeout !== null) {
			clearTimeout(hoverTimeout);
			hoverTimeout = null;
		}
	}
	function canHover() {
		return globalThis.matchMedia?.("(hover: hover)")?.matches ?? false;
	}
	function canOpenOnFocus() {
		if (!canHover()) return false;
		return globalThis.matchMedia?.("(pointer: fine)")?.matches ?? false;
	}
	function canToggleOnClick() {
		if (!options.openOnHover?.()) return true;
		return canHover();
	}
	function clearBlurGuard() {
		ignoreNextBlurClose = false;
		if (blurGuardTimeout !== null) {
			clearTimeout(blurGuardTimeout);
			blurGuardTimeout = null;
		}
	}
	function armBlurGuard() {
		ignoreNextBlurClose = true;
		if (blurGuardTimeout !== null) clearTimeout(blurGuardTimeout);
		blurGuardTimeout = setTimeout(clearBlurGuard, 500);
	}
	function consumeBlurGuard() {
		if (!ignoreNextBlurClose) return false;
		clearBlurGuard();
		return true;
	}
	function isTriggerDisabled() {
		if (!triggerEl) return false;
		if (triggerEl.hasAttribute("disabled")) return true;
		return triggerEl.getAttribute("aria-disabled") === "true";
	}
	/**
	* The transition handler manages animation lifecycle via `createState`:
	*
	* **Open:** `transition.open()` patches `{ active: true, status: 'starting' }`. After a double-RAF it patches `{
	* status: 'idle' }`, then waits for the resulting element animations before the promise resolves. Frameworks render
	* `data-starting-style` / `data-ending-style` via `getPopupAttrs(state)` — no imperative DOM mutation needed.
	*
	* **Close:** `transition.close(el)` patches `{ status: 'ending' }` (keeping `active: true` so the element stays
	* mounted). After a double-RAF it waits for `getAnimations()` to settle, then patches `{ active: false, status:
	* 'idle' }`.
	*
	* `onOpenChange` fires immediately (before animations). `onOpenChangeComplete` fires after animations finish.
	*/
	function commitOpen() {
		const opening = layer.open(() => popupEl);
		if (!opening) return;
		options.group?.()?.open(groupMember);
		opening.then(() => {
			if (layer.signal.aborted || !state.current.active || state.current.status !== "idle") return;
			options.onOpenChangeComplete?.(true);
		});
	}
	function commitClose() {
		const closing = layer.close(popupEl);
		if (!closing) return;
		options.group?.()?.close(groupMember);
		closing.then(() => {
			if (layer.signal.aborted || state.current.active) return;
			tryHidePopover(popupEl);
			options.onOpenChangeComplete?.(false);
		});
	}
	function applyOpen(reason, event) {
		if (layer.signal.aborted) return;
		const { active, status } = state.current;
		if (active && status !== "ending") return;
		onOpenChange(true, event ? {
			reason,
			event
		} : { reason });
		if (!options.deferOpenChanges) commitOpen();
	}
	function applyClose(reason, event) {
		if (layer.signal.aborted) return;
		const { active, status } = state.current;
		if (!active || status === "ending") return;
		onOpenChange(false, event ? {
			reason,
			event
		} : { reason });
		if (!options.deferOpenChanges) commitClose();
	}
	function open(reason = "click") {
		applyOpen(reason);
	}
	function close(reason = "click") {
		clearHoverTimeout();
		applyClose(reason);
	}
	function syncOpen(open) {
		if (!options.deferOpenChanges) return;
		if (open) commitOpen();
		else commitClose();
	}
	function handleDocumentPointerdown(event) {
		if (!closeOnOutsideClick() || !state.current.active) return;
		const path = event.composedPath();
		if (triggerEl && path.includes(triggerEl) || popupEl && path.includes(popupEl)) {
			armBlurGuard();
			return;
		}
		clearBlurGuard();
		applyClose("outside-click", event);
	}
	layer.signal.addEventListener("abort", () => {
		options.group?.()?.close(groupMember);
		clearHoverTimeout();
		clearBlurGuard();
		capturedPointers.clear();
		triggerEl = null;
		popupEl = null;
	});
	const triggerProps = {
		onClick(event) {
			if (!canToggleOnClick()) return;
			if (isTriggerDisabled()) return;
			if (state.current.active && state.current.status !== "ending") applyClose("click", event);
			else applyOpen("click", event);
		},
		onPointerEnter(_event) {
			if (!options.openOnHover?.()) return;
			if (!canHover()) return;
			clearHoverTimeout();
			if (state.current.active) return;
			const delay = options.delay?.() ?? 300;
			hoverTimeout = setTimeout(() => applyOpen("hover"), delay);
		},
		onPointerLeave(_event) {
			if (!options.openOnHover?.()) return;
			if (!canHover()) return;
			clearHoverTimeout();
			if (!state.current.active) return;
			const closeDelay = options.closeDelay?.() ?? 0;
			hoverTimeout = setTimeout(() => applyClose("hover"), closeDelay);
		},
		onFocusIn(_event) {
			if (options.openOnHover?.()) {
				if (!canOpenOnFocus()) return;
				applyOpen("focus");
			}
		},
		onFocusOut(event) {
			const relatedTarget = event.relatedTarget;
			if (relatedTarget && (triggerEl?.contains(relatedTarget) || popupEl?.contains(relatedTarget))) return;
			if (options.openOnHover?.()) applyClose("blur");
		}
	};
	const popupProps = {
		onPointerEnter(_event) {
			if (!options.openOnHover?.()) return;
			clearHoverTimeout();
		},
		onPointerLeave(_event) {
			if (!options.openOnHover?.()) return;
			if (capturedPointers.size > 0) return;
			clearHoverTimeout();
			if (!state.current.active) return;
			const closeDelay = options.closeDelay?.() ?? 0;
			hoverTimeout = setTimeout(() => applyClose("hover"), closeDelay);
		},
		onGotPointerCapture(event) {
			capturedPointers.add(event.pointerId);
		},
		onLostPointerCapture(event) {
			capturedPointers.delete(event.pointerId);
		},
		onFocusOut(event) {
			const relatedTarget = event.relatedTarget;
			if (relatedTarget && (triggerEl?.contains(relatedTarget) || popupEl?.contains(relatedTarget))) return;
			if (consumeBlurGuard()) return;
			if (relatedTarget !== null) {
				applyClose("blur");
				return;
			}
			requestAnimationFrame(() => {
				requestAnimationFrame(() => {
					if (!state.current.active || state.current.status === "ending" || state.current.status === "starting") return;
					const active = getDeepActiveElement(popupEl?.ownerDocument);
					if (active && (triggerEl?.contains(active) || popupEl?.contains(active))) return;
					applyClose("blur");
				});
			});
		}
	};
	function setTriggerElement(el) {
		triggerEl = el;
	}
	function setPopupElement(el) {
		if (!el && popupEl && state.current.active) tryHidePopover(popupEl);
		popupEl = el;
		if (el) {
			if (state.current.active) tryShowPopover(el);
		}
	}
	return {
		input: state,
		triggerProps,
		popupProps,
		get triggerElement() {
			return triggerEl;
		},
		setTriggerElement,
		setPopupElement,
		open,
		close,
		syncOpen,
		destroy: layer.destroy
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/menu/item-data.js
/**
* Data attributes set on all navigable menu item elements.
*
* @parts item, radio-item, checkbox-item, trigger
*/
const MenuItemDataAttrs = {
	/**
	* Present on all navigable item types: Item, RadioItem, CheckboxItem, and the Trigger when acting as a submenu
	* trigger inside a parent menu. Use `[data-item]` as a shared selector to target all item types at once.
	*/
	item: "data-item",
	/** Present when the item is highlighted. Set to `pointer` when pointer movement caused the highlight; otherwise empty. */
	highlighted: "data-highlighted"
};

//#endregion
//#region ../core/dist/dev/core/ui/menu/vars.js
/** CSS custom property names for menu layout and positioning. */
const MenuCSSVars = {
	/** Width of the active menu panel (px). */
	width: "--media-menu-width",
	/** Height of the active menu panel (px). */
	height: "--media-menu-height",
	/** Viewport-constrained max width for the menu (px). */
	availableWidth: "--media-menu-available-width",
	/** Viewport-constrained max height for the menu (px). */
	availableHeight: "--media-menu-available-height"
};

//#endregion
//#region ../core/dist/dev/core/ui/popover/vars.js
const PopoverCSSVars = {
	/** Distance between the popup and the trigger along the side axis. */
	sideOffset: "--media-popover-side-offset",
	/** Distance between the popup and the trigger along the alignment axis. */
	alignOffset: "--media-popover-align-offset",
	/** Minimum distance between the popup and the positioning boundary. */
	boundaryOffset: "--media-popover-boundary-offset",
	/** The anchor element's width. */
	anchorWidth: "--media-popover-anchor-width",
	/** The anchor element's height. */
	anchorHeight: "--media-popover-anchor-height",
	/** Available width between the trigger and the boundary edge. */
	availableWidth: "--media-popover-available-width",
	/** Available height between the trigger and the boundary edge. */
	availableHeight: "--media-popover-available-height"
};

//#endregion
//#region ../core/dist/dev/dom/ui/menu/create-menu.js
function isMenuNavigationKey(event) {
	const { key } = event;
	return key === "ArrowDown" || key === "ArrowUp" || key === "ArrowLeft" || key === "ArrowRight" || key === "Home" || key === "End" || key === "Enter" || key === " " || key === "Escape" || key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey;
}
function getRootPositionOptions(side, align) {
	if (!side || !align) return null;
	return {
		side,
		align
	};
}
/** Uses Popover offset inputs while publishing Menu-owned available-size outputs. */
const MenuPositioningCSSVars = {
	...PopoverCSSVars,
	availableWidth: MenuCSSVars.availableWidth,
	availableHeight: MenuCSSVars.availableHeight
};
const parents = /* @__PURE__ */ new WeakMap();
function completeMenuItemSelection(menu) {
	menu.close();
}
function createMenu(options) {
	const items = [];
	let highlightedItem = null;
	let triggerElement = null;
	let contentElement = null;
	let popupElement = null;
	const submenus = /* @__PURE__ */ new Set();
	const submenuUnsubscribes = /* @__PURE__ */ new Map();
	let typeaheadBuffer = "";
	let typeaheadTimer = null;
	let pendingFocusOut = null;
	let openRafId = 0;
	let lastCloseReason = null;
	let api;
	function isItemHidden(item) {
		const availability = item.getAttribute("data-availability");
		return Boolean(item.hidden || item.hasAttribute("data-hidden") || item.getAttribute("aria-hidden") === "true" || availability === "unavailable" || availability === "unsupported");
	}
	function getNavigableItems() {
		return items.filter((item) => !isItemHidden(item));
	}
	function getAdjacentNavigableItem(direction) {
		if (items.length === 0) return null;
		const currentIndex = highlightedItem ? items.indexOf(highlightedItem) : direction === 1 ? -1 : 0;
		for (let offset = 1; offset <= items.length; offset++) {
			const index = (currentIndex + direction * offset + items.length) % items.length;
			const candidate = items[index];
			if (candidate && !isItemHidden(candidate)) return candidate;
		}
		return null;
	}
	function highlight(element, highlightOptions) {
		if (!element && openRafId) {
			cancelAnimationFrame(openRafId);
			openRafId = 0;
		}
		if (element && isItemHidden(element)) {
			if (element === highlightedItem) highlight(getAdjacentNavigableItem(1), highlightOptions);
			return;
		}
		if (highlightedItem === element) {
			element?.setAttribute(MenuItemDataAttrs.highlighted, highlightOptions?.pointer === true ? "pointer" : "");
			return;
		}
		const previousItem = highlightedItem;
		if (previousItem) previousItem.tabIndex = -1;
		highlightedItem = element;
		if (element) {
			element.tabIndex = 0;
			element.setAttribute(MenuItemDataAttrs.highlighted, highlightOptions?.pointer === true ? "pointer" : "");
			if (previousItem && compareItems(element, previousItem) < 0 && highlightOptions?.pointer) forceLayout(element.parentElement);
			previousItem?.removeAttribute(MenuItemDataAttrs.highlighted);
			if (highlightOptions?.focus !== false) if (highlightOptions?.preventScroll) element.focus({ preventScroll: true });
			else element.focus();
		} else previousItem?.removeAttribute(MenuItemDataAttrs.highlighted);
		options.onHighlightChange?.(element);
	}
	function clearHighlight() {
		if (highlightedItem) {
			highlightedItem.tabIndex = -1;
			highlightedItem.removeAttribute(MenuItemDataAttrs.highlighted);
			highlightedItem = null;
			options.onHighlightChange?.(null);
		}
	}
	function highlightFirstItem(options) {
		highlight(getNavigableItems()[0] ?? null, options);
	}
	function restoreFocus(focusOptions) {
		if (lastCloseReason === "imperative-action" || lastCloseReason === "group-open" || lastCloseReason === "blur" || lastCloseReason === "outside-click") return;
		if (focusOptions) triggerElement?.focus(focusOptions);
		else triggerElement?.focus();
	}
	function getInitialHighlightItem() {
		const navigableItems = getNavigableItems();
		return navigableItems.find((item) => item.matches("[role=\"menuitemradio\"][aria-checked=\"true\"], [aria-selected=\"true\"]")) ?? navigableItems[0] ?? null;
	}
	function clearTypeahead() {
		if (typeaheadTimer !== null) {
			clearTimeout(typeaheadTimer);
			typeaheadTimer = null;
		}
		typeaheadBuffer = "";
	}
	function scheduleInitialHighlight() {
		cancelAnimationFrame(openRafId);
		openRafId = requestAnimationFrame(() => {
			openRafId = 0;
			if (!popover.input.current.active || popover.input.current.status === "ending" || highlightedItem) return;
			highlight(getInitialHighlightItem(), { preventScroll: true });
		});
	}
	function handleTypeahead(char) {
		typeaheadBuffer = typeaheadBuffer.length === 1 && typeaheadBuffer.toLowerCase() === char.toLowerCase() ? char : typeaheadBuffer + char;
		if (typeaheadTimer !== null) clearTimeout(typeaheadTimer);
		typeaheadTimer = setTimeout(clearTypeahead, 500);
		const navigableItems = getNavigableItems();
		const searchStart = (highlightedItem ? navigableItems.indexOf(highlightedItem) : -1) + 1;
		const candidates = [...navigableItems.slice(searchStart), ...navigableItems.slice(0, searchStart)];
		const needle = typeaheadBuffer.toLowerCase();
		const match = candidates.find((candidate) => {
			return (candidate.textContent?.trim().toLowerCase() ?? "").startsWith(needle);
		});
		if (match) highlight(match);
	}
	const popover = createPopover({
		transition: options.transition,
		deferOpenChanges: true,
		onOpenChange(open, details) {
			lastCloseReason = open ? null : details.reason;
			options.onOpenChange(open, details);
			if (open) scheduleInitialHighlight();
			else {
				clearHighlight();
				clearTypeahead();
			}
		},
		onOpenChangeComplete(open) {
			options.onOpenChangeComplete?.(open);
			if (!open) restoreFocus();
		},
		closeOnEscape: options.closeOnEscape,
		closeOnOutsideClick: options.closeOnOutsideClick,
		...options.group ? { group: options.group } : {}
	});
	const contentProps = {
		onFocusOut(event) {
			if (event.relatedTarget === null && hasClosingSubmenu()) {
				pendingFocusOut = event;
				return;
			}
			popover.popupProps.onFocusOut(event);
		},
		onKeyDown(event) {
			const { key } = event;
			const navigableItems = getNavigableItems();
			if (key !== "Escape" && isMenuNavigationKey(event) && !event.defaultPrevented) event.preventDefault();
			if (navigableItems.length === 0) return;
			switch (key) {
				case "ArrowDown":
					event.preventDefault();
					highlight(getAdjacentNavigableItem(1));
					break;
				case "ArrowUp":
					event.preventDefault();
					highlight(getAdjacentNavigableItem(-1));
					break;
				case "Home":
					event.preventDefault();
					highlight(navigableItems[0] ?? null);
					break;
				case "End":
					event.preventDefault();
					highlight(navigableItems[navigableItems.length - 1] ?? null);
					break;
				case "Enter":
				case " ":
					event.preventDefault();
					if (highlightedItem && navigableItems.includes(highlightedItem)) highlightedItem.click();
					break;
				default: if (key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) handleTypeahead(key);
			}
		}
	};
	function handleTriggerKeyDown(event) {
		const input = popover.input.current;
		if (!input.active || input.status === "ending") return;
		if (event.key === "Escape") return;
		if (!isMenuNavigationKey(event)) return;
		contentProps.onKeyDown(event);
		event.stopPropagation();
	}
	function setTriggerElement(element) {
		triggerElement = element;
		popover.setTriggerElement(element);
	}
	function setContentElement(element) {
		contentElement = element;
	}
	function setPopupElement(element) {
		popupElement = element;
		popover.setPopupElement(element);
	}
	function compareItems(a, b) {
		if (a === b) return 0;
		const position = a.compareDocumentPosition(b);
		if (position & Node.DOCUMENT_POSITION_FOLLOWING) return -1;
		if (position & Node.DOCUMENT_POSITION_PRECEDING) return 1;
		return 0;
	}
	function registerItem(element) {
		const onFocus = () => highlight(element, { focus: false });
		element.tabIndex = -1;
		element.setAttribute(MenuItemDataAttrs.item, "");
		element.addEventListener("focus", onFocus);
		items.push(element);
		items.sort(compareItems);
		if (popover.input.current.active && popover.input.current.status !== "ending" && !highlightedItem) scheduleInitialHighlight();
		return () => {
			element.removeEventListener("focus", onFocus);
			const index = items.indexOf(element);
			if (index !== -1) items.splice(index, 1);
			if (highlightedItem === element) clearHighlight();
		};
	}
	function registerSubmenu(menu) {
		submenus.add(menu);
		parents.set(menu, api);
		const unsubscribe = menu.input.subscribe(handlePendingFocusOut);
		submenuUnsubscribes.set(menu, unsubscribe);
		return () => {
			submenus.delete(menu);
			if (parents.get(menu) === api) parents.delete(menu);
			submenuUnsubscribes.get(menu)?.();
			submenuUnsubscribes.delete(menu);
			handlePendingFocusOut();
		};
	}
	function hasClosingSubmenu() {
		return [...submenus].some(({ input }) => input.current.status === "ending");
	}
	function handlePendingFocusOut() {
		if (!pendingFocusOut || hasClosingSubmenu()) return;
		const event = pendingFocusOut;
		pendingFocusOut = null;
		popover.popupProps.onFocusOut(event);
	}
	function syncOpen(open) {
		if (open) parents.get(api)?.highlight(null);
		else for (const submenu of submenus) submenu.close("imperative-action");
		popover.syncOpen(open);
	}
	function destroy() {
		cancelAnimationFrame(openRafId);
		openRafId = 0;
		clearTypeahead();
		for (const unsubscribe of submenuUnsubscribes.values()) unsubscribe();
		for (const submenu of submenus) if (parents.get(submenu) === api) parents.delete(submenu);
		submenuUnsubscribes.clear();
		submenus.clear();
		parents.delete(api);
		pendingFocusOut = null;
		popover.destroy();
	}
	api = {
		input: popover.input,
		triggerProps: {
			onClick: popover.triggerProps.onClick,
			onKeyDown: handleTriggerKeyDown
		},
		contentProps,
		get triggerElement() {
			return triggerElement;
		},
		get contentElement() {
			return contentElement;
		},
		get popupElement() {
			return popupElement;
		},
		setTriggerElement,
		setContentElement,
		setPopupElement,
		registerItem,
		registerSubmenu,
		highlight,
		highlightFirstItem,
		restoreFocus,
		open: popover.open,
		close: popover.close,
		syncOpen,
		destroy
	};
	return api;
}

//#endregion
//#region ../core/dist/dev/core/ui/menu/data.js
/** Root popup state used for positioning and surface transitions. */
const MenuPopupDataAttrs = {
	/** Present when the menu is open. */
	open: "data-open",
	/** Rendered positioning side after collision handling. Absent on submenus. */
	side: "data-side",
	/** Popover positioning alignment. Absent on submenus. */
	align: "data-align",
	...TransitionDataAttrs
};
/** State for one root or nested Content. */
const MenuContentDataAttrs = {
	/** Present while this Content is active or transitioning out. */
	open: "data-open",
	/** Present on Content when this menu is nested inside a parent menu. */
	isSubmenu: "data-submenu",
	/** Present when this Content has an open logical child. */
	childOpen: "data-child-open",
	...TransitionDataAttrs
};
/** All public Menu data attributes exposed to component transforms. */
const MenuDataAttrs = {
	...MenuPopupDataAttrs,
	...MenuContentDataAttrs
};

//#endregion
//#region ../core/dist/dev/dom/ui/menu/menu-popup.js
/** Coordinates sibling Contents and sizes their shared Popup. */
function createMenuPopup() {
	const contents = /* @__PURE__ */ new Set();
	let element = null;
	let frame = 0;
	function scheduleSync() {
		cancelAnimationFrame(frame);
		sync();
		frame = requestAnimationFrame(sync);
	}
	function getChildren(parent) {
		return [...contents].filter((content) => content.parent === parent);
	}
	function getActiveChild(parent) {
		return getChildren(parent).find(({ menu }) => {
			const input = menu.input.current;
			return input.active && input.status !== "ending";
		}) ?? null;
	}
	function getCurrentContent() {
		let current = [...contents].find((content) => content.parent === null) ?? null;
		while (current) {
			const child = getActiveChild(current.menu);
			if (!child) return current;
			current = child;
		}
		return null;
	}
	function setInactive(content, inactive) {
		if (inactive) {
			content.element.setAttribute("aria-hidden", "true");
			content.element.setAttribute("inert", "");
		} else restoreAttributes(content.element, content.accessibility);
	}
	function getAvailableWidth(popup) {
		return walkAncestors(popup, (ancestor) => {
			const width = readCSSLength(ancestor, MenuCSSVars.availableWidth);
			return width !== null && width > 0 ? width : void 0;
		}) ?? null;
	}
	function measureContent(content, availableWidth) {
		const children = getElementChildren(content, (child) => child instanceof HTMLElement && !child.hidden);
		if (children.length === 0) return measureElement(content, {
			overflow: "both",
			styles: {
				width: "max-content",
				height: "auto",
				minWidth: "0px",
				maxWidth: "none"
			}
		});
		return measureElementChildren(content, {
			children,
			includePadding: true,
			maxWidth: availableWidth,
			measure: (child, width) => measureElement(child, {
				overflow: "both",
				styles: {
					insetInlineStart: "0px",
					insetInlineEnd: "auto",
					width: width === void 0 ? "max-content" : `${width}px`,
					height: "auto",
					minWidth: "0px",
					maxWidth: "none"
				}
			})
		});
	}
	function sync() {
		if (!element) return;
		for (const content of contents) {
			const activeChild = getActiveChild(content.menu);
			if (activeChild) {
				content.menu.highlight(null);
				content.element.setAttribute(MenuContentDataAttrs.childOpen, "");
			} else content.element.removeAttribute(MenuContentDataAttrs.childOpen);
			const input = content.menu.input.current;
			const isExitingPage = content.parent !== null && input.active && input.status === "ending";
			setInactive(content, activeChild !== null || isExitingPage);
		}
		const current = getCurrentContent();
		if (!current) return;
		const popupPadding = current.parent === null ? getElementPadding(element) : null;
		const inlinePadding = popupPadding ? getInlineExtent(popupPadding) : 0;
		const blockPadding = popupPadding ? getBlockExtent(popupPadding) : 0;
		const availableWidth = getAvailableWidth(element);
		const contentAvailableWidth = availableWidth === null ? null : Math.max(0, availableWidth - inlinePadding);
		const size = measureContent(current.element, contentAvailableWidth);
		element.style.setProperty(MenuCSSVars.width, `${Math.ceil(size.width + inlinePadding)}px`);
		element.style.setProperty(MenuCSSVars.height, `${Math.ceil(size.height + blockPadding)}px`);
	}
	function setElement(next) {
		element = next;
		scheduleSync();
	}
	function registerContent(registration) {
		const registered = {
			...registration,
			accessibility: snapshotAttributes(registration.element, ["aria-hidden", "inert"]),
			stopObserving: () => {},
			unsubscribe: () => {}
		};
		registered.unsubscribe = registration.menu.input.subscribe(scheduleSync);
		registered.stopObserving = observeElements({
			root: registration.element,
			getElements: () => [registration.element],
			mutations: {
				childList: true,
				subtree: true,
				characterData: true
			},
			onChange: scheduleSync
		});
		contents.add(registered);
		registration.menu.setContentElement(registration.element);
		scheduleSync();
		return () => {
			contents.delete(registered);
			registered.unsubscribe();
			registered.stopObserving();
			restoreAttributes(registration.element, registered.accessibility);
			registration.element.removeAttribute(MenuContentDataAttrs.childOpen);
			if (registration.menu.contentElement === registration.element) registration.menu.setContentElement(null);
			scheduleSync();
		};
	}
	function destroy() {
		cancelAnimationFrame(frame);
		for (const content of contents) {
			content.unsubscribe();
			content.stopObserving();
			restoreAttributes(content.element, content.accessibility);
			content.element.removeAttribute(MenuContentDataAttrs.childOpen);
			if (content.menu.contentElement === content.element) content.menu.setContentElement(null);
		}
		contents.clear();
		element = null;
	}
	return {
		get element() {
			return element;
		},
		setElement,
		registerContent,
		sync,
		destroy
	};
}

//#endregion
//#region ../core/dist/dev/dom/utils/event.js
function isEventWithinElement(event, element) {
	if (!element) return false;
	if (isFunction(event.composedPath)) return event.composedPath().includes(element);
	const target = event.target;
	return target instanceof Node && element.contains(target);
}

//#endregion
//#region ../utils/dist/number/number.js
/** Clamp a value between min and max (inclusive). */
function clamp(value, min, max) {
	return Math.max(min, Math.min(max, value));
}
/**
* Convert a value within a range to a clamped percentage (0–100).
*
* @param value - Value to convert.
* @param min - Start of the range.
* @param max - End of the range.
*/
function toPercent(value, min, max) {
	const range = max - min;
	if (!Number.isFinite(range) || range <= 0) return 0;
	return clamp((value - min) / range * 100, 0, 100);
}
/** Snap a value to the nearest step, offset from min. */
function roundToStep(value, step, min) {
	const nearest = Math.round((value - min) / step) * step + min;
	const dot = `${step}`.indexOf(".");
	return dot === -1 ? nearest : Number(nearest.toFixed(`${step}`.length - dot - 1));
}

//#endregion
//#region ../core/dist/dev/dom/ui/popover/popover-positioning.js
const ZERO_OFFSETS = {
	sideOffset: 0,
	alignOffset: 0,
	boundaryOffset: 0
};
const OPPOSITE_SIDE = {
	top: "bottom",
	bottom: "top",
	left: "right",
	right: "left"
};
function formatPixels(value) {
	return `${clamp(value, 0, Infinity)}px`;
}
function shiftCrossAxis(value, boundaryStart, boundaryEnd, size) {
	const max = boundaryEnd - size;
	return max < boundaryStart ? boundaryStart : clamp(value, boundaryStart, max);
}
function getHorizontalAlign({ align, direction = "ltr" }) {
	if (direction !== "rtl") return align;
	return align === "start" ? "end" : align === "end" ? "start" : align;
}
function getAnchorCrossAxisShift(start, end, size, boundaryStart, boundaryEnd, align, alignOffset, boundaryOffset, axis, alignOffsetVar) {
	const base = align === "start" ? start + alignOffset : align === "end" ? end + alignOffset : start + size / 2 + alignOffset;
	const startAnchor = axis === "horizontal" ? "left" : "top";
	const endAnchor = OPPOSITE_SIDE[startAnchor];
	const anchor = align === "start" ? startAnchor : align === "end" ? endAnchor : "center";
	const desiredTranslate = align === "start" ? "0px" : align === "end" ? "-100%" : "-50%";
	return {
		base: `calc(anchor(${anchor}) + ${alignOffsetVar})`,
		translate: `clamp(${boundaryStart + boundaryOffset - base}px, ${desiredTranslate}, calc(${boundaryEnd - boundaryOffset - base}px - 100%))`
	};
}
/**
* Get positioning styles for the popup element.
*
* When the browser supports CSS Anchor Positioning, returns native CSS properties that reference the provided CSS var
* names for side/align offsets — no JS offset values needed.
*
* When rects are provided and anchor positioning is unsupported, falls back to manual JS-computed positioning. The
* caller must resolve offset CSS vars via `getComputedStyle` and pass them as `offsets`.
*
* Returns camelCase keys for standard CSS properties and `--*` keys for custom properties — compatible with both
* React's `style` prop and `applyStyles()` from `@videojs/utils/dom`.
*/
function getAnchorPositionStyle(anchorName, opts, triggerRect, popupRect, boundaryRect, offsets, cssVars = PopoverCSSVars) {
	if (supportsAnchorPositioning()) return {
		...getAnchorPositionCSS(anchorName, opts, cssVars, triggerRect, boundaryRect, offsets),
		...triggerRect && boundaryRect ? getPositioningCSSVars(triggerRect, boundaryRect, opts, offsets, cssVars) : {}
	};
	if (triggerRect && popupRect) {
		const resolved = offsets ?? ZERO_OFFSETS;
		return {
			position: "fixed",
			margin: "0",
			...getManualPositionStyle(triggerRect, popupRect, opts, resolved, boundaryRect),
			...boundaryRect ? getPositioningCSSVars(triggerRect, boundaryRect, opts, resolved, cssVars) : {}
		};
	}
	return {};
}
function getAnchorPositionCSS(anchorName, opts, cssVars = PopoverCSSVars, triggerRect, boundaryRect, offsets = ZERO_OFFSETS) {
	const SIDE_OFFSET_VAR = `var(${cssVars.sideOffset}, 0px)`;
	const ALIGN_OFFSET_VAR = `var(${cssVars.alignOffset}, 0px)`;
	const { side, align } = opts;
	const boundaryOffset = offsets.boundaryOffset ?? 0;
	const style = {
		positionAnchor: `--${anchorName}`,
		position: "fixed",
		inset: "auto",
		margin: "0",
		justifySelf: "normal",
		alignSelf: "normal",
		marginInlineStart: "0",
		marginBlockStart: "0",
		translate: "none"
	};
	const insetProp = OPPOSITE_SIDE[side];
	if (side === "top" || side === "bottom") {
		const horizontalAlign = getHorizontalAlign(opts);
		style[insetProp] = `calc(anchor(${side}) + ${SIDE_OFFSET_VAR})`;
		if (triggerRect && boundaryRect) {
			const { base, translate } = getAnchorCrossAxisShift(triggerRect.left, triggerRect.right, triggerRect.width, boundaryRect.left, boundaryRect.right, horizontalAlign, offsets.alignOffset, boundaryOffset, "horizontal", ALIGN_OFFSET_VAR);
			style.left = base;
			style.translate = `${translate} 0`;
			return style;
		}
		if (horizontalAlign === "start") style.left = `calc(anchor(left) + ${ALIGN_OFFSET_VAR})`;
		else if (horizontalAlign === "end") style.right = `calc(anchor(right) + ${ALIGN_OFFSET_VAR})`;
		else {
			style.justifySelf = "anchor-center";
			style.marginInlineStart = ALIGN_OFFSET_VAR;
		}
	} else {
		style[insetProp] = `calc(anchor(${side}) + ${SIDE_OFFSET_VAR})`;
		if (triggerRect && boundaryRect) {
			const { base, translate } = getAnchorCrossAxisShift(triggerRect.top, triggerRect.bottom, triggerRect.height, boundaryRect.top, boundaryRect.bottom, align, offsets.alignOffset, boundaryOffset, "vertical", ALIGN_OFFSET_VAR);
			style.top = base;
			style.translate = `0 ${translate}`;
			return style;
		}
		if (align === "start") style.top = `calc(anchor(top) + ${ALIGN_OFFSET_VAR})`;
		else if (align === "end") style.bottom = `calc(anchor(bottom) + ${ALIGN_OFFSET_VAR})`;
		else {
			style.alignSelf = "anchor-center";
			style.marginBlockStart = ALIGN_OFFSET_VAR;
		}
	}
	return style;
}
/**
* Compute CSS variables for sizing constraints relative to the anchor/boundary.
*
* Accepts a `cssVars` map so the same logic works for both popover (`--media-popover-*`) and tooltip
* (`--media-tooltip-*`) namespaces.
*/
function getPositioningCSSVars(triggerRect, boundaryRect, opts, offsets = ZERO_OFFSETS, cssVars = PopoverCSSVars) {
	const vars = {};
	const { side } = opts;
	const boundaryOffset = offsets.boundaryOffset ?? 0;
	const boundaryStartX = boundaryRect.left + boundaryOffset;
	const boundaryEndX = boundaryRect.right - boundaryOffset;
	const boundaryStartY = boundaryRect.top + boundaryOffset;
	const boundaryEndY = boundaryRect.bottom - boundaryOffset;
	vars[cssVars.anchorWidth] = `${triggerRect.width}px`;
	vars[cssVars.anchorHeight] = `${triggerRect.height}px`;
	if (side === "top" || side === "bottom") {
		const sideSpace = side === "top" ? triggerRect.top - boundaryStartY : boundaryEndY - triggerRect.bottom;
		vars[cssVars.availableHeight] = formatPixels(sideSpace - offsets.sideOffset);
		vars[cssVars.availableWidth] = formatPixels(boundaryEndX - boundaryStartX);
	} else {
		const sideSpace = side === "left" ? triggerRect.left - boundaryStartX : boundaryEndX - triggerRect.right;
		vars[cssVars.availableWidth] = formatPixels(sideSpace - offsets.sideOffset);
		vars[cssVars.availableHeight] = formatPixels(boundaryEndY - boundaryStartY);
	}
	return vars;
}
/**
* Compute manual positioning when CSS Anchor Positioning is not supported.
*
* Returns inline `top`/`left` styles in **viewport coordinates** for use with `position: fixed` (the popup is in the
* top layer). All rects from `getBoundingClientRect()` are already viewport-relative.
*
* Offsets are resolved by the caller from CSS custom properties via `getComputedStyle()` and passed as `offsets`.
*/
function getManualPositionStyle(triggerRect, popupRect, opts, offsets = {
	sideOffset: 0,
	alignOffset: 0
}, boundaryRect) {
	const { side, align } = opts;
	const { sideOffset, alignOffset } = offsets;
	let top = 0;
	let bottom;
	let left = 0;
	let right;
	if (side === "top") bottom = `calc(100% - ${triggerRect.top}px + ${sideOffset}px)`;
	else if (side === "bottom") top = triggerRect.bottom + sideOffset;
	else if (side === "left") right = `calc(100% - ${triggerRect.left}px + ${sideOffset}px)`;
	else left = triggerRect.right + sideOffset;
	if (side === "top" || side === "bottom") {
		const horizontalAlign = getHorizontalAlign(opts);
		if (horizontalAlign === "start") left = triggerRect.left + alignOffset;
		else if (horizontalAlign === "end") left = triggerRect.right - popupRect.width + alignOffset;
		else left = triggerRect.left + (triggerRect.width - popupRect.width) / 2 + alignOffset;
	} else if (align === "start") top = triggerRect.top + alignOffset;
	else if (align === "end") top = triggerRect.bottom - popupRect.height + alignOffset;
	else top = triggerRect.top + (triggerRect.height - popupRect.height) / 2 + alignOffset;
	if (boundaryRect) {
		const boundaryOffset = offsets.boundaryOffset ?? 0;
		if (side === "top" || side === "bottom") left = shiftCrossAxis(left, boundaryRect.left + boundaryOffset, boundaryRect.right - boundaryOffset, popupRect.width);
		else top = shiftCrossAxis(top, boundaryRect.top + boundaryOffset, boundaryRect.bottom - boundaryOffset, popupRect.height);
	}
	return {
		top: side === "top" ? "auto" : `${top}px`,
		bottom: bottom ?? "auto",
		left: side === "left" ? "auto" : `${left}px`,
		right: right ?? "auto"
	};
}
/**
* Read positioning offset CSS custom properties from the popup element's computed style, returning numeric pixel
* values.
*/
function resolveOffsets(el, cssVars = PopoverCSSVars) {
	const computed = getComputedStyle(el);
	return {
		sideOffset: resolveCSSLength(el, computed.getPropertyValue(cssVars.sideOffset)),
		alignOffset: resolveCSSLength(el, computed.getPropertyValue(cssVars.alignOffset)),
		boundaryOffset: resolveCSSLength(el, computed.getPropertyValue(cssVars.boundaryOffset))
	};
}
/**
* Measure the popup's layout box for positioning.
*
* `getBoundingClientRect()` includes active transforms, which causes the fallback position to drift while
* opening/closing animations scale the popup. Using layout dimensions preserves the untransformed size, while the
* side-axis scroll dimension includes content clipped by size constraints.
*/
function getPopupPositionRect(el, side) {
	const rect = el.getBoundingClientRect();
	const size = getElementSize(el, {
		box: "layout",
		overflow: side === "left" || side === "right" ? "width" : "height"
	});
	return createDOMRect(rect.left, rect.top, size.width, size.height);
}

//#endregion
//#region ../core/dist/dev/dom/ui/popover/popup-positioner.js
const POPUP_STYLE_PROPS = [
	"position",
	"inset",
	"margin",
	"margin-top",
	"margin-right",
	"margin-bottom",
	"margin-left",
	"justify-self",
	"align-self",
	"margin-inline-start",
	"margin-block-start",
	"translate",
	"top",
	"right",
	"bottom",
	"left"
];
/** Positions a popup and tracks layout changes while it is active. */
var PopupPositioner = class {
	#options = null;
	#boundaryElement = null;
	#abort = null;
	#stopObservingResize = null;
	#triggerAnchorName = null;
	#triggerAnchorAdded = false;
	#popupAnchor = null;
	#popupStyles = null;
	#reposition = rafThrottle(() => this.#position());
	sync(options) {
		const { anchorName, position, trigger, popup, boundary, container, cssVars = PopoverCSSVars } = options;
		if (!position || !trigger || !popup) {
			this.cleanup();
			return;
		}
		const boundaryElement = resolvePositioningBoundary(boundary, {
			container: container ?? null,
			root: popup.getRootNode()
		});
		const previous = this.#options;
		if (!previous || previous.anchorName !== anchorName || previous.trigger !== trigger || previous.popup !== popup || (previous.cssVars ?? PopoverCSSVars) !== cssVars || previous.trackResize !== options.trackResize || this.#boundaryElement !== boundaryElement) {
			if (previous?.popup) this.#restorePopupStyles(previous.popup);
			this.#stopTracking();
			this.#options = {
				...options,
				cssVars
			};
			this.#boundaryElement = boundaryElement;
			this.#startTracking();
		} else this.#options = {
			...options,
			cssVars
		};
		this.#position();
	}
	cleanup() {
		if (!this.#options) return;
		if (this.#options.popup) this.#restorePopupStyles(this.#options.popup);
		this.#stopTracking();
		this.#options = null;
		this.#boundaryElement = null;
	}
	#startTracking() {
		const options = this.#options;
		if (!options?.trigger || !options.popup) return;
		this.#applyAnchorStyles(options.trigger, options.popup, options.anchorName);
		this.#abort = new AbortController();
		const { signal } = this.#abort;
		window.addEventListener("scroll", this.#schedule, {
			capture: true,
			passive: true,
			signal
		});
		window.addEventListener("resize", this.#schedule, { signal });
		const resizeTargets = [options.trigger];
		if (options.trackResize !== false) resizeTargets.push(options.popup);
		if (this.#boundaryElement) resizeTargets.push(this.#boundaryElement);
		this.#stopObservingResize = observeResize(resizeTargets, () => this.#schedule());
	}
	#stopTracking() {
		this.#abort?.abort();
		this.#abort = null;
		this.#stopObservingResize?.();
		this.#stopObservingResize = null;
		this.#reposition.cancel();
		this.#restoreAnchorStyles();
	}
	#schedule = (event) => {
		const popup = this.#options?.popup;
		if (!popup || event && isEventWithinElement(event, popup)) return;
		this.#reposition();
	};
	#position() {
		const options = this.#options;
		if (!options?.position || !options.trigger || !options.popup) return;
		const trigger = options.trigger;
		const triggerRect = trigger.getBoundingClientRect();
		const boundaryRect = getPositioningBoundaryRect(this.#boundaryElement);
		const offsets = resolveOffsets(options.popup, options.cssVars);
		const preferredPosition = options.position;
		const anchorSupported = supportsAnchorPositioning();
		const getPosition = (popupRect) => {
			const side = getPositionedSide(triggerRect, popupRect, boundaryRect, preferredPosition, offsets);
			const { positionAnchor: _, ...style } = getAnchorPositionStyle(options.anchorName, {
				...preferredPosition,
				side,
				direction: isRTL(trigger) ? "rtl" : "ltr"
			}, triggerRect, anchorSupported ? void 0 : popupRect, boundaryRect, offsets, options.cssVars);
			return {
				popupRect,
				side,
				style
			};
		};
		const position = getPosition(getPopupPositionRect(options.popup, preferredPosition.side));
		this.#capturePopupStyles(options.popup, options.cssVars ?? PopoverCSSVars);
		applyStyles(options.popup, position.style);
		options.onSideChange?.(position.side);
		if (anchorSupported || !options.onSideChange) return;
		const popupRect = getPopupPositionRect(options.popup, preferredPosition.side);
		if (popupRect.width === position.popupRect.width && popupRect.height === position.popupRect.height) return;
		const nextPosition = getPosition(popupRect);
		applyStyles(options.popup, nextPosition.style);
		if (nextPosition.side !== position.side) options.onSideChange(nextPosition.side);
	}
	#capturePopupStyles(popup, cssVars) {
		if (this.#popupStyles) return;
		const props = [
			...POPUP_STYLE_PROPS,
			cssVars.anchorWidth,
			cssVars.anchorHeight,
			cssVars.availableWidth,
			cssVars.availableHeight
		];
		this.#popupStyles = snapshotInlineStyles(popup, props);
	}
	#restorePopupStyles(popup) {
		if (!this.#popupStyles) return;
		restoreInlineStyles(popup, this.#popupStyles);
		this.#popupStyles = null;
	}
	#applyAnchorStyles(trigger, popup, anchorName) {
		if (!supportsAnchorPositioning()) return;
		const generatedName = `--${anchorName}`;
		const triggerAnchor = this.#readStyle(trigger, "anchor-name");
		this.#popupAnchor = this.#readStyle(popup, "position-anchor");
		const names = getAnchorNames(trigger);
		this.#triggerAnchorName = generatedName;
		this.#triggerAnchorAdded = !names.includes(generatedName);
		if (this.#triggerAnchorAdded) names.push(generatedName);
		trigger.style.setProperty("anchor-name", names.join(", "), triggerAnchor.priority);
		popup.style.setProperty("position-anchor", generatedName);
	}
	#restoreAnchorStyles() {
		const options = this.#options;
		if (!options?.trigger || !options.popup) return;
		if (this.#triggerAnchorName && this.#triggerAnchorAdded) {
			const current = this.#readStyle(options.trigger, "anchor-name");
			const names = getAnchorNames(options.trigger).filter((name) => name !== this.#triggerAnchorName);
			this.#writeStyle(options.trigger, "anchor-name", {
				value: names.join(", "),
				priority: current.priority
			});
		}
		if (this.#popupAnchor) this.#writeStyle(options.popup, "position-anchor", this.#popupAnchor);
		this.#triggerAnchorName = null;
		this.#triggerAnchorAdded = false;
		this.#popupAnchor = null;
	}
	#readStyle(element, prop) {
		const name = prop.startsWith("--") ? prop : kebabCase(prop);
		return {
			value: element.style.getPropertyValue(name),
			priority: element.style.getPropertyPriority(name)
		};
	}
	#writeStyle(element, prop, style) {
		const name = prop.startsWith("--") ? prop : kebabCase(prop);
		if (style.value) element.style.setProperty(name, style.value, style.priority);
		else element.style.removeProperty(name);
	}
};

//#endregion
//#region ../core/dist/dev/dom/utils/pointer.js
/** Convert a pointer event position to a 0–100 percent along an element's rect. */
function getPercentFromPointerEvent(event, rect, orientation) {
	let ratio;
	if (orientation === "vertical") ratio = 1 - (event.clientY - rect.top) / rect.height;
	else ratio = (event.clientX - rect.left) / rect.width;
	if (!Number.isFinite(ratio)) return 0;
	return clamp(ratio * 100, 0, 100);
}

//#endregion
//#region ../core/dist/dev/dom/ui/slider.js
const DRAG_THRESHOLD = 3;
function createSlider(options) {
	const input = createState({
		pointerPercent: 0,
		dragPercent: 0,
		dragging: false,
		pointing: false,
		focused: false
	});
	const abort = new AbortController();
	const changeThrottleMs = options.changeThrottle ?? 0;
	let isPointerDown = false, cachedRect = null, capturedPointerId = null, pointerDownX = 0, pointerDownY = 0, lastDragPercent = 0, committedOnRelease = false, pointingOnRelease = false;
	const throttledChange = changeThrottleMs > 0 ? throttle((percent) => options.onValueChange?.(percent), changeThrottleMs, { leading: true }) : null;
	/** Fire `onValueChange` — throttled during drag when `changeThrottle > 0`. */
	function fireChange(percent, duringDrag) {
		if (duringDrag && throttledChange) throttledChange(percent);
		else options.onValueChange?.(percent);
	}
	function releaseCapture() {
		if (isNull(capturedPointerId)) return;
		const id = capturedPointerId;
		capturedPointerId = null;
		try {
			options.getElement().releasePointerCapture(id);
		} catch {}
	}
	function endDrag() {
		if (!isPointerDown) return;
		const pointing = committedOnRelease && pointingOnRelease;
		const wasDragging = input.current.dragging;
		if (!committedOnRelease) options.onValueCommit?.(lastDragPercent);
		isPointerDown = false;
		input.patch({
			dragging: false,
			pointing
		});
		if (wasDragging) options.onDragEnd?.();
		committedOnRelease = false;
		pointingOnRelease = false;
		cleanup();
	}
	function cleanup() {
		throttledChange?.cancel();
		capturedPointerId = null;
		cachedRect = null;
	}
	const rootProps = {
		onPointerDown(event) {
			if (options.isDisabled()) return;
			event.stopPropagation();
			event.preventDefault();
			const el = options.getElement();
			cachedRect = el.getBoundingClientRect();
			committedOnRelease = false;
			pointingOnRelease = false;
			releaseCapture();
			capturedPointerId = event.pointerId;
			el.setPointerCapture(event.pointerId);
			const percent = getPercentFromPointerEvent(event, cachedRect, options.getOrientation());
			isPointerDown = true;
			pointerDownX = event.clientX;
			pointerDownY = event.clientY;
			lastDragPercent = percent;
			input.patch({
				pointing: true,
				pointerPercent: percent,
				dragPercent: percent
			});
			options.onValueChange?.(percent);
			options.getThumbElement?.()?.focus({
				preventScroll: true,
				focusVisible: false
			});
		},
		onPointerMove(event) {
			if (options.isDisabled()) return;
			if (!isNull(capturedPointerId)) {
				if (event.pointerType !== "touch" && event.buttons === 0) {
					endDrag();
					return;
				}
				const percent = getPercentFromPointerEvent(event, cachedRect, options.getOrientation());
				const startingDrag = !input.current.dragging;
				if (startingDrag) {
					if (Math.hypot(event.clientX - pointerDownX, event.clientY - pointerDownY) < DRAG_THRESHOLD) return;
				}
				lastDragPercent = percent;
				input.patch({
					dragging: true,
					dragPercent: percent,
					pointerPercent: percent
				});
				if (startingDrag) options.onDragStart?.();
				fireChange(percent, true);
				return;
			}
			const rect = options.getElement().getBoundingClientRect();
			const percent = getPercentFromPointerEvent(event, rect, options.getOrientation());
			input.patch({
				pointing: true,
				pointerPercent: percent
			});
		},
		onPointerUp(event) {
			if (options.isDisabled()) return;
			event.stopPropagation();
			if (isNull(capturedPointerId)) return;
			const percent = getPercentFromPointerEvent(event, cachedRect, options.getOrientation());
			const releaseRect = options.getElement().getBoundingClientRect();
			pointingOnRelease = event.pointerType !== "touch" && event.clientX >= releaseRect.left && event.clientX <= releaseRect.right && event.clientY >= releaseRect.top && event.clientY <= releaseRect.bottom;
			throttledChange?.cancel();
			options.onValueChange?.(percent);
			options.onValueCommit?.(percent);
			committedOnRelease = true;
		},
		onPointerLeave() {
			if (!isNull(capturedPointerId)) return;
			input.patch({ pointing: false });
		},
		onLostPointerCapture() {
			endDrag();
		}
	};
	const thumbProps = {
		onKeyDown(event) {
			if (options.isDisabled()) {
				if (event.key !== "Tab") event.preventDefault();
				return;
			}
			const stepPercent = options.getStepPercent();
			const largeStepPercent = options.getLargeStepPercent();
			const currentPercent = options.getPercent();
			const rounded = roundToStep(currentPercent, stepPercent, 0);
			const step = event.shiftKey ? largeStepPercent : stepPercent;
			let newPercent = null;
			switch (event.key) {
				case "ArrowRight":
					newPercent = rounded + step;
					break;
				case "ArrowLeft":
					newPercent = rounded - step;
					break;
				case "ArrowUp":
					newPercent = rounded + step;
					break;
				case "ArrowDown":
					newPercent = rounded - step;
					break;
				case "PageUp":
					newPercent = rounded + largeStepPercent;
					break;
				case "PageDown":
					newPercent = rounded - largeStepPercent;
					break;
				case "Home":
					newPercent = 0;
					break;
				case "End": newPercent = 100;
			}
			if (newPercent !== null) {
				event.preventDefault();
				newPercent = clamp(newPercent, 0, 100);
				input.patch({
					pointerPercent: newPercent,
					dragPercent: newPercent,
					pointing: false
				});
				options.onValueChange?.(newPercent);
				options.onValueCommit?.(newPercent);
			}
		},
		onFocus() {
			input.patch({ focused: true });
		},
		onBlur() {
			input.patch({ focused: false });
		}
	};
	function adjustForAlignment(state) {
		if (!options.adjustPercent || state.thumbAlignment !== "edge") return state;
		const rootEl = options.getElement();
		const thumbEl = options.getThumbElement?.();
		if (!thumbEl) return state;
		const isHorizontal = state.orientation === "horizontal";
		const thumbSize = isHorizontal ? thumbEl.offsetWidth : thumbEl.offsetHeight;
		const trackSize = isHorizontal ? rootEl.offsetWidth : rootEl.offsetHeight;
		return {
			...state,
			fillPercent: options.adjustPercent(state.fillPercent, thumbSize, trackSize),
			pointerPercent: options.adjustPercent(state.pointerPercent, thumbSize, trackSize)
		};
	}
	let stopObservingResize = null;
	if (options.onResize) stopObservingResize = observeResize(options.getElement(), () => options.onResize());
	return {
		input,
		rootProps,
		rootStyle: {
			touchAction: "none",
			userSelect: "none"
		},
		thumbProps,
		adjustForAlignment,
		destroy() {
			if (abort.signal.aborted) return;
			abort.abort();
			stopObservingResize?.();
			releaseCapture();
			cleanup();
		}
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/slider/vars.js
/** CSS custom property names for slider visual state. */
const SliderCSSVars = {
	/** Fill level percentage (0–100). */
	fill: "--media-slider-fill",
	/** Pointer position percentage (0–100). */
	pointer: "--media-slider-pointer",
	/** Buffer level percentage (0–100). */
	buffer: "--media-slider-buffer"
};

//#endregion
//#region ../core/dist/dev/dom/ui/slider-css-vars.js
function getSliderCSSVars(state) {
	return {
		[SliderCSSVars.fill]: `${state.fillPercent.toFixed(3)}%`,
		[SliderCSSVars.pointer]: `${state.pointerPercent.toFixed(3)}%`
	};
}
function getTimeSliderCSSVars(state) {
	return {
		...getSliderCSSVars(state),
		[SliderCSSVars.buffer]: `${state.bufferPercent.toFixed(3)}%`
	};
}
/** Compute structural positioning styles for a slider preview element. */
function getSliderPreviewStyle(width, overflow) {
	const halfWidth = width / 2;
	return {
		position: "absolute",
		left: overflow === "visible" ? `calc(var(${SliderCSSVars.pointer}) - ${halfWidth}px)` : `min(max(0px, calc(var(${SliderCSSVars.pointer}) - ${halfWidth}px)), calc(100% - ${width}px))`,
		width: "max-content",
		pointerEvents: "none"
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/slider-focus.js
function isSliderFocused(root = document) {
	const doc = isDocument(root) ? root : root.ownerDocument;
	const active = getDeepActiveElement(doc);
	if (active?.getAttribute("role") !== "slider") return false;
	return isDocument(root) || containsComposed(root, active);
}

//#endregion
//#region ../core/dist/dev/dom/ui/status-announcer.js
function subscribeToStatusAnnouncer(store, core) {
	let active = true;
	let pending = false;
	let target = store.target;
	let revision = 0;
	const baseline = () => {
		target = store.target;
		pending = true;
		const current = ++revision;
		core.resetSnapshot();
		queueMicrotask(() => {
			if (!active || current !== revision) return;
			pending = false;
			target = store.target;
			if (target) core.processSnapshot(getMediaSnapshot(store));
		});
	};
	const unsubscribe = store.subscribe(() => {
		const nextTarget = store.target;
		if (nextTarget !== target) {
			baseline();
			return;
		}
		if (!nextTarget || pending) return;
		core.processSnapshot(getMediaSnapshot(store));
	});
	baseline();
	return () => {
		active = false;
		unsubscribe();
	};
}
function shouldAnnounceStatusChange(container) {
	return !container || !isSliderFocused(container);
}

//#endregion
//#region ../utils/dist/array/find-last-at-or-before.js
/** Finds the index of the last ordered item whose value is at or before the target, or `-1` if none exists. */
function findLastIndexAtOrBefore(items, value, getValue) {
	let low = 0;
	let high = items.length - 1;
	let index = -1;
	while (low <= high) {
		const mid = low + high >>> 1;
		if (getValue(items[mid]) <= value) {
			index = mid;
			low = mid + 1;
		} else high = mid - 1;
	}
	return index;
}
/** Finds the last ordered item whose value is at or before the target. */
function findLastAtOrBefore(items, value, getValue) {
	const index = findLastIndexAtOrBefore(items, value, getValue);
	return index < 0 ? void 0 : items[index];
}

//#endregion
//#region ../core/dist/dev/core/ui/thumbnail/core.js
var ThumbnailCore = class {
	findActiveThumbnail(thumbnails, time) {
		return findLastAtOrBefore(thumbnails, time, (thumbnail) => thumbnail.startTime);
	}
	/**
	* Parse CSS constraint strings into numeric `ThumbnailConstraints`.
	*
	* Accepts any object with string `minWidth`/`maxWidth`/`minHeight`/`maxHeight` properties — `CSSStyleDeclaration`
	* satisfies this structurally.
	*/
	parseConstraints(raw) {
		const minW = parseFloat(raw.minWidth);
		const maxW = parseFloat(raw.maxWidth);
		const minH = parseFloat(raw.minHeight);
		const maxH = parseFloat(raw.maxHeight);
		return {
			minWidth: Number.isFinite(minW) ? minW : 0,
			maxWidth: Number.isFinite(maxW) ? maxW : Infinity,
			minHeight: Number.isFinite(minH) ? minH : 0,
			maxHeight: Number.isFinite(maxH) ? maxH : Infinity
		};
	}
	/**
	* Calculate a uniform scale factor that fits `tileWidth × tileHeight` within the given CSS min/max constraints while
	* preserving aspect ratio.
	*
	* - Scales down when the tile exceeds max constraints.
	* - Scales up when the tile is smaller than min constraints.
	* - Returns `1` when no scaling is needed.
	*/
	calculateScale(tileWidth, tileHeight, constraints) {
		const { minWidth, maxWidth, minHeight, maxHeight } = constraints;
		const maxRatio = Math.min(maxWidth / tileWidth, maxHeight / tileHeight);
		const minRatio = Math.max(minWidth / tileWidth, minHeight / tileHeight);
		if (Number.isFinite(maxRatio) && maxRatio < 1) return maxRatio;
		if (Number.isFinite(minRatio) && minRatio > 1) return minRatio;
		return 1;
	}
	/**
	* Compute container and image dimensions for the current thumbnail, scaled to fit within the element's CSS min/max
	* constraints.
	*
	* The container clips the sprite sheet via `overflow: hidden`, and the image is positioned with `transform:
	* translate()` to show the correct tile.
	*/
	resize(thumbnail, imgNaturalWidth, imgNaturalHeight, constraints) {
		const tileWidth = thumbnail.width ?? imgNaturalWidth;
		const tileHeight = thumbnail.height ?? imgNaturalHeight;
		if (!tileWidth || !tileHeight) return void 0;
		const scale = this.calculateScale(tileWidth, tileHeight, constraints);
		const coordX = thumbnail.coords?.x ?? 0;
		const coordY = thumbnail.coords?.y ?? 0;
		const inset = scale !== 1 ? 1 : 0;
		return {
			scale,
			containerWidth: Math.max(0, Math.floor(tileWidth * scale) - inset * 2),
			containerHeight: Math.max(0, Math.floor(tileHeight * scale) - inset * 2),
			imageWidth: Math.ceil(imgNaturalWidth * scale),
			imageHeight: Math.ceil(imgNaturalHeight * scale),
			offsetX: Math.ceil(coordX * scale) + inset,
			offsetY: Math.ceil(coordY * scale) + inset
		};
	}
	getState(loading, error, thumbnail) {
		return {
			loading,
			error,
			hidden: !loading && !thumbnail
		};
	}
	getAttrs(_state) {
		return {
			dir: "ltr",
			role: "img",
			"aria-hidden": "true"
		};
	}
};

//#endregion
//#region ../core/dist/dev/dom/ui/thumbnail.js
function createThumbnail(options) {
	const { getContainer, getImg, onStateChange } = options;
	const core = new ThumbnailCore();
	const abort = new AbortController();
	const signal = abort.signal;
	let loading = false;
	let error = false;
	let naturalWidth = 0;
	let naturalHeight = 0;
	let lastSrc = "";
	let imgBound = false;
	let stopObservingResize = null;
	function onImgLoad() {
		const img = getImg();
		if (img) {
			naturalWidth = img.naturalWidth;
			naturalHeight = img.naturalHeight;
		}
		loading = false;
		error = false;
		onStateChange();
	}
	function onImgError() {
		loading = false;
		error = true;
		onStateChange();
	}
	function bindImg(img) {
		listen(img, "load", onImgLoad, { signal });
		listen(img, "error", onImgError, { signal });
	}
	function ensureBindings() {
		if (!imgBound) {
			const img = getImg();
			if (img) {
				bindImg(img);
				imgBound = true;
			}
		}
		if (!stopObservingResize) {
			const container = getContainer();
			if (container) stopObservingResize = observeResize(container, onStateChange);
		}
	}
	function updateSrc(url) {
		ensureBindings();
		const src = url ?? "";
		if (src === lastSrc) return;
		lastSrc = src;
		if (src) {
			loading = true;
			error = false;
		} else {
			loading = false;
			error = false;
			naturalWidth = 0;
			naturalHeight = 0;
		}
	}
	function connect() {
		ensureBindings();
		const img = getImg();
		if (img?.complete && lastSrc) {
			if (img.naturalWidth > 0) {
				naturalWidth = img.naturalWidth;
				naturalHeight = img.naturalHeight;
				loading = false;
				error = false;
			} else {
				loading = false;
				error = true;
			}
			onStateChange();
		}
	}
	function destroy() {
		abort.abort();
		stopObservingResize?.();
		stopObservingResize = null;
	}
	return {
		get loading() {
			return loading;
		},
		get error() {
			return error;
		},
		get naturalWidth() {
			return naturalWidth;
		},
		get naturalHeight() {
			return naturalHeight;
		},
		readConstraints() {
			const el = getContainer();
			if (!el) return {
				minWidth: 0,
				maxWidth: Infinity,
				minHeight: 0,
				maxHeight: Infinity
			};
			return core.parseConstraints(getComputedStyle(el));
		},
		updateSrc,
		connect,
		destroy
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/tooltip/tooltip.js
/** Map popover reasons to tooltip reasons, filtering out click/outside-click. */
const REASON_MAP = {
	hover: "hover",
	focus: "focus",
	escape: "escape",
	blur: "blur",
	"imperative-action": "imperative-action"
};
function createTooltip(options) {
	const popoverOpts = {
		transition: options.transition,
		onOpenChange(open, details) {
			const reason = REASON_MAP[details.reason];
			if (!reason) return;
			const group = options.group?.();
			if (open) group?.notifyOpen();
			else group?.notifyClose();
			const tooltipDetails = details.event ? {
				reason,
				event: details.event
			} : { reason };
			options.onOpenChange(open, tooltipDetails);
		},
		closeOnEscape: () => true,
		closeOnOutsideClick: () => false,
		openOnHover: () => true,
		delay: () => {
			const group = options.group?.();
			if (group?.shouldSkipDelay()) return 0;
			return options.delay?.() ?? group?.delay ?? 600;
		},
		closeDelay: () => {
			const group = options.group?.();
			return options.closeDelay?.() ?? group?.closeDelay ?? 0;
		}
	};
	if (options.onOpenChangeComplete) popoverOpts.onOpenChangeComplete = options.onOpenChangeComplete;
	const popover = createPopover(popoverOpts);
	let isPointerDown = false;
	let popupGroup;
	let unsubscribe;
	function isTriggerPopupOpen() {
		return popupGroup?.isOpenFor(popover.triggerElement) ?? false;
	}
	function isSticky() {
		return options.sticky?.() ?? false;
	}
	function syncPopupGroup() {
		const next = options.popupGroup?.();
		if (next === popupGroup) return;
		unsubscribe?.();
		popupGroup = next;
		unsubscribe = popupGroup?.subscribe(() => {
			if (isTriggerPopupOpen() && !isSticky()) popover.close("imperative-action");
		});
	}
	function setTriggerElement(el) {
		popover.setTriggerElement(el);
		syncPopupGroup();
		if (isTriggerPopupOpen() && !isSticky()) popover.close("imperative-action");
	}
	const { onClick: _, ...baseTriggerProps } = popover.triggerProps;
	const triggerProps = {
		...baseTriggerProps,
		onPointerDown() {
			syncPopupGroup();
			isPointerDown = true;
			if (!isSticky()) popover.close("imperative-action");
		},
		onPointerEnter(event) {
			syncPopupGroup();
			if (options.disabled?.()) return;
			if (isTriggerPopupOpen() && !isSticky()) return;
			if (event.pointerType === "touch") return;
			baseTriggerProps.onPointerEnter(event);
		},
		onFocusIn(event) {
			syncPopupGroup();
			if (options.disabled?.()) return;
			if (isTriggerPopupOpen() && !isSticky()) return;
			if (isPointerDown) {
				isPointerDown = false;
				return;
			}
			baseTriggerProps.onFocusIn(event);
		}
	};
	const popupProps = {
		...popover.popupProps,
		onPointerEnter(event) {
			if (options.disableHoverablePopup?.()) return;
			popover.popupProps.onPointerEnter(event);
		}
	};
	return {
		...popover,
		triggerProps,
		popupProps,
		get triggerElement() {
			return popover.triggerElement;
		},
		setTriggerElement,
		open: () => {
			syncPopupGroup();
			if (!isTriggerPopupOpen() || isSticky()) popover.open("hover");
		},
		close: (reason = "hover") => popover.close(reason),
		destroy() {
			unsubscribe?.();
			popover.destroy();
		}
	};
}

//#endregion
//#region ../core/dist/dev/dom/ui/transition.js
/**
* Manages open/close transition lifecycle via `createState`.
*
* **Open:** patches `{ active: true, status: 'starting' }`, then after a double-RAF patches `{ status: 'idle' }` so the
* browser paints the initial ("from") state before transitioning. It then waits for the resulting element animations to
* finish. Reopening an active transition flushes styles first so CSS transitions can restart.
*
* **Close:** patches `{ status: 'ending' }` (keeping `active: true` so the element stays mounted), then after a
* double-RAF waits for `getAnimations()` to settle before patching `{ active: false, status: 'idle' }`.
*/
function createTransition() {
	const state = createState({
		active: false,
		status: "idle"
	});
	let destroyed = false;
	let rafId1 = 0;
	let rafId2 = 0;
	let operationId = 0;
	let resolvePending = null;
	function cancelFrames() {
		cancelAnimationFrame(rafId1);
		cancelAnimationFrame(rafId2);
		rafId1 = 0;
		rafId2 = 0;
	}
	function beginOperation() {
		operationId++;
		cancelFrames();
		resolvePending?.();
		resolvePending = null;
		return operationId;
	}
	function finishOperation(id) {
		if (id !== operationId) return;
		const resolve = resolvePending;
		resolvePending = null;
		resolve?.();
	}
	function open(el = null) {
		if (destroyed) return Promise.resolve();
		const id = beginOperation();
		const restarting = state.current.active;
		if (restarting) state.patch({ status: "idle" });
		state.patch({
			active: true,
			status: "starting"
		});
		return new Promise((resolve) => {
			resolvePending = resolve;
			rafId1 = requestAnimationFrame(() => {
				rafId1 = 0;
				if (restarting) {
					const element = resolveElement(el);
					cancelAnimations(element);
					flushStyles(element);
				}
				rafId2 = requestAnimationFrame(() => {
					rafId2 = 0;
					if (destroyed || id !== operationId || !state.current.active) return finishOperation(id);
					state.patch({ status: "idle" });
					rafId1 = requestAnimationFrame(() => {
						rafId1 = 0;
						if (destroyed || id !== operationId || !state.current.active) return finishOperation(id);
						waitForAnimations(resolveElement(el)).finally(() => finishOperation(id));
					});
				});
			});
		});
	}
	function close(el) {
		if (destroyed) return Promise.resolve();
		const id = beginOperation();
		state.patch({ status: "ending" });
		return new Promise((resolve) => {
			resolvePending = resolve;
			rafId1 = requestAnimationFrame(() => {
				rafId1 = 0;
				rafId2 = requestAnimationFrame(() => {
					rafId2 = 0;
					if (destroyed || id !== operationId) return finishOperation(id);
					waitForAnimations(el).finally(() => {
						if (destroyed || id !== operationId || state.current.status !== "ending") return finishOperation(id);
						state.patch({
							active: false,
							status: "idle"
						});
						finishOperation(id);
					});
				});
			});
		});
	}
	function cancel() {
		operationId++;
		cancelFrames();
		resolvePending?.();
		resolvePending = null;
		if (state.current.status !== "idle") state.patch({ status: "idle" });
	}
	return {
		state,
		open,
		close,
		cancel,
		destroy() {
			if (destroyed) return;
			destroyed = true;
			cancel();
		}
	};
}
function resolveElement(element) {
	return typeof element === "function" ? element() : element;
}
function flushStyles(el) {
	if (!el) return;
	el.offsetHeight;
}
function cancelAnimations(el) {
	const animations = el?.getAnimations?.({ subtree: true }) ?? [];
	for (const animation of animations) animation.cancel();
}
function waitForAnimations(el) {
	if (!el) return Promise.resolve();
	const animations = el.getAnimations?.() ?? [];
	if (animations.length === 0) return Promise.resolve();
	return Promise.all(animations.map((a) => a.finished)).then(noop, noop);
}

//#endregion
//#region ../core/dist/dev/dom/ui/wheel-step.js
function createWheelStep(options) {
	return { onWheel(event) {
		if (options.isDisabled()) return;
		const direction = Math.sign(event.deltaY);
		if (direction === 0) return;
		event.preventDefault();
		const stepPercent = options.getStepPercent();
		const currentPercent = options.getPercent();
		const newPercent = clamp(currentPercent - direction * stepPercent, 0, 100);
		options.onValueChange?.(newPercent);
	} };
}

//#endregion
//#region ../core/dist/dev/dom/utils/element-props.js
/**
* Apply props to a DOM element.
*
* Handles both attributes and event listeners: - Event props (onClick, onKeyDown, etc.) are attached as listeners -
* Boolean props: `true` sets empty attribute, `false` removes - `undefined` removes the attribute - Other props are set
* as string attributes
*/
function applyElementProps(element, props, options) {
	const signal = options?.signal;
	for (const [key, value] of Object.entries(props)) if (isFunction(value) && key.startsWith("on")) {
		const event = key.slice(2).toLowerCase();
		listen(element, event, value, signal ? { signal } : void 0);
	} else if (isUndefined(value) || value === false) element.removeAttribute(key);
	else if (value === true) element.setAttribute(key, "");
	else element.setAttribute(key, String(value));
}

//#endregion
//#region ../core/dist/dev/dom/utils/log.js
const warned = /* @__PURE__ */ new Set();
function logMissingFeature(displayName, featureName) {
	const key = `${displayName}:${featureName}`;
	if (warned.has(key)) return;
	warned.add(key);
	console.warn(`${displayName} requires ${featureName} feature`);
}

//#endregion
//#region src/define/global.css?inline
var global_default = "video-player, live-video-player, media-i18n, media-dialog, media-alert-dialog, media-error-dialog, media-controls {\n  display: contents;\n}\n\nmedia-container video, media-container [slot=\"poster\"] {\n  width: 100%;\n  height: 100%;\n  display: block;\n}\n\nmedia-container video::-webkit-media-text-track-container {\n  z-index: 1;\n  scale: .98;\n  translate: 0 var(--media-caption-track-y, 0);\n  transition: translate var(--media-caption-track-duration, 0) ease-out;\n  transition-delay: var(--media-caption-track-delay, 0);\n  font-family: inherit;\n}\n";

//#endregion
//#region src/define/shared.css?inline
var shared_default = "media-tooltip-group, media-dialog, media-alert-dialog, media-error-dialog, media-controls {\n  display: contents;\n}\n\n:host {\n  width: 100%;\n  display: grid;\n}\n\nmedia-container {\n  min-width: 0;\n  min-height: 0;\n}\n\n.media-popover--volume:has(media-volume-slider[data-hidden]) {\n  display: none;\n}\n";

//#endregion
//#region src/presets/skin.ts
const STYLES_ID = "__media-styles";
const sharedSheet = createShadowStyle(shared_default);
/**
* Base element for skin definitions. Attaches a shadow root, clones `static template` into it, and applies shared +
* per-skin styles via `adoptedStyleSheets` (or `<style>` fallback).
*/
var SkinElement = class extends ReactiveElement {
	static {
		this.shadowRootOptions = { mode: "open" };
	}
	constructor() {
		super();
		ensureGlobalStyle(STYLES_ID, global_default);
		if (!this.shadowRoot) {
			const ctor = this.constructor;
			this.attachShadow(ctor.shadowRootOptions);
			if (ctor.template) renderTemplate(this.shadowRoot, ctor.template);
			const sheets = [sharedSheet];
			if (ctor.styles) sheets.push(ctor.styles);
			applyShadowStyles(this.shadowRoot, sheets);
		}
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/utils/resolve-label.js
function resolveLabel(label, state) {
	if (isFunction(label)) return label(state) || void 0;
	return label || void 0;
}

//#endregion
//#region ../core/dist/dev/core/ui/dialog/core.js
var DialogCore = class {
	static defaultProps = {
		open: false,
		defaultOpen: false,
		closeOnEscape: true
	};
	#role;
	#input = null;
	#titleId = void 0;
	#descriptionId = void 0;
	constructor(role = "dialog") {
		this.#role = role;
	}
	/** Accept props for API consistency. Props are consumed by platform layers. */
	setProps(_props) {}
	setInput(input) {
		this.#input = input;
	}
	setTitleId(id) {
		this.#titleId = id;
	}
	setDescriptionId(id) {
		this.#descriptionId = id;
	}
	getState() {
		const input = this.#input;
		return {
			open: input.active,
			status: input.status,
			titleId: this.#titleId,
			descriptionId: this.#descriptionId,
			...getTransitionFlags(input.status)
		};
	}
	getTriggerAttrs(state, popupId) {
		return {
			"aria-expanded": state.open && state.status !== "ending" ? "true" : "false",
			"aria-haspopup": "dialog",
			"aria-controls": popupId
		};
	}
	getPopupAttrs(state) {
		return {
			role: this.#role,
			"aria-modal": "true",
			"aria-labelledby": state.titleId,
			"aria-describedby": state.descriptionId
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/alert-dialog/core.js
/** A dialog with alert semantics for urgent messages that require acknowledgement. */
var AlertDialogCore = class extends DialogCore {
	constructor() {
		super("alertdialog");
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/dialog/data.js
const DialogDataAttrs = {
	/** Present when the dialog is open. */
	open: "data-open",
	/** Present during the open transition. */
	transitionStarting: "data-starting-style",
	/** Present during the close transition. */
	transitionEnding: "data-ending-style"
};

//#endregion
//#region ../core/dist/dev/core/ui/buffering-indicator/core.js
var BufferingIndicatorCore = class BufferingIndicatorCore {
	static defaultProps = { delay: 500 };
	state = createState({ visible: false });
	#props = { ...BufferingIndicatorCore.defaultProps };
	#timer = null;
	setProps(props) {
		this.#props = defaults(props, BufferingIndicatorCore.defaultProps);
	}
	destroy() {
		this.#clearTimer();
	}
	update(media) {
		const buffering = media.waiting && !media.paused;
		if (buffering && !this.state.current.visible && !this.#timer) this.#timer = setTimeout(() => {
			this.#timer = null;
			this.state.patch({ visible: true });
		}, this.#props.delay);
		else if (!buffering) {
			this.#clearTimer();
			this.state.patch({ visible: false });
		}
	}
	#clearTimer() {
		if (this.#timer !== null) {
			clearTimeout(this.#timer);
			this.#timer = null;
		}
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/buffering-indicator/data.js
const BufferingIndicatorDataAttrs = { 
/** Present when the buffering indicator is visible (after delay). */
visible: "data-visible" };

//#endregion
//#region ../core/dist/dev/core/ui/controls/core.js
var ControlsCore = class {
	#media = null;
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		return {
			visible: media.controlsVisible,
			userActive: media.userActive
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/controls/data.js
const ControlsDataAttrs = {
	/** Present when controls are visible. */
	visible: "data-visible",
	/** Present when the user has recently interacted. */
	userActive: "data-user-active"
};

//#endregion
//#region ../core/dist/dev/core/ui/error-dialog/core.js
/** Error-dialog core: an alert dialog whose open state is driven by media error state. */
var ErrorDialogCore = class extends AlertDialogCore {
	setProps() {}
};

//#endregion
//#region ../core/dist/dev/core/ui/error-dialog/data.js
const ErrorDialogDataAttrs = { ...DialogDataAttrs };

//#endregion
//#region ../core/dist/dev/i18n/text/common.js
const prefix$6 = "common.";
const emptyText = {
	key: `${prefix$6}empty`,
	text: ""
};
const okText = {
	key: `${prefix$6}ok`,
	text: "OK"
};

//#endregion
//#region ../core/dist/dev/i18n/text/errors.js
const prefix$5 = "errors.";
const abortedText = {
	key: `${prefix$5}aborted`,
	text: "You stopped media playback before it finished."
};
const networkText = {
	key: `${prefix$5}network`,
	text: "This media could not be loaded due to a network or server issue."
};
const decodeText = {
	key: `${prefix$5}decode`,
	text: "This media could not be played. It may be corrupted, or your browser may not support its format."
};
const sourceText = {
	key: `${prefix$5}source`,
	text: "This media could not be loaded. It may be unavailable, or your browser may not support its format."
};
const encryptedText = {
	key: `${prefix$5}encrypted`,
	text: "This media could not be played because it could not be decrypted."
};
const unplayableText = {
	key: `${prefix$5}unplayable`,
	text: "This media is unsupported by the player."
};
const titleText = {
	key: `${prefix$5}title`,
	text: "Something went wrong."
};
const unexpectedText = {
	key: `${prefix$5}unexpected`,
	text: "An unexpected error occurred."
};

//#endregion
//#region ../core/dist/dev/core/ui/error-dialog/error-dialog-i18n.js
/**
* SVTA 99 [Custom] 001 — an engine reporting that it has no pipeline for something the source requires. Not a
* `MediaError.MEDIA_ERR_*` value: engines that report SVTA codes surface them on `error.code` directly.
*
* The literal rather than an import. `@videojs/spf` defines this as `SVTA_UNSUPPORTED_PLAYBACK_FEATURE` and owns its
* meaning, but core doesn't depend on spf, and reaching it through `@videojs/media` would pull an engine entry point
* into a barrel that has no other reason to load one. Same trade `HlsVideoMediaStreamType` makes in the other direction
* — compatibility by value, stated in a comment, instead of a dependency edge neither package wants.
*/
const SVTA_UNSUPPORTED_PLAYBACK_FEATURE = 99001;
const MEDIA_ERROR_TRANSLATIONS = {
	[MediaError.MEDIA_ERR_ABORTED]: abortedText,
	[MediaError.MEDIA_ERR_NETWORK]: networkText,
	[MediaError.MEDIA_ERR_DECODE]: decodeText,
	[MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED]: sourceText,
	[MediaError.MEDIA_ERR_ENCRYPTED]: encryptedText,
	[MediaError.MEDIA_ERR_CUSTOM]: emptyText,
	[SVTA_UNSUPPORTED_PLAYBACK_FEATURE]: unplayableText
};
const STANDARD_CODE_UA_MESSAGES = { [MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED]: ["Failed to open media"] };
function isStandardMediaErrorCode(code) {
	return code >= MediaError.MEDIA_ERR_ABORTED && code <= MediaError.MEDIA_ERR_ENCRYPTED;
}
function getErrorDialogTitleText() {
	return titleText;
}
function getErrorDialogDismissText() {
	return okText;
}
function getErrorDialogUnexpectedText() {
	return unexpectedText;
}
/**
* Resolves dialog body copy: default phrases for known {@link MediaError} defaults, literal text for custom messages,
* otherwise the generic fallback key.
*/
function resolveErrorDialogDescription(error, cachedMessage) {
	if (error) {
		const text = MEDIA_ERROR_TRANSLATIONS[error.code];
		const message = error.message?.trim();
		if (message) {
			const defaultForCode = MediaError.defaultMessages[error.code];
			if (text && defaultForCode && message === defaultForCode) return text;
			const uaVariants = STANDARD_CODE_UA_MESSAGES[error.code];
			if (text && isStandardMediaErrorCode(error.code) && !error.context && uaVariants?.includes(message)) return text;
			return message;
		}
		if (text) return text;
	}
	const cached = cachedMessage?.trim();
	if (cached) return cached;
	return unexpectedText;
}

//#endregion
//#region ../core/dist/dev/i18n/text/fullscreen.js
const prefix$4 = "fullscreen.";
const enterText = {
	key: `${prefix$4}enter`,
	text: "Enter fullscreen"
};
const exitText = {
	key: `${prefix$4}exit`,
	text: "Exit fullscreen"
};

//#endregion
//#region ../core/dist/dev/i18n/text/status.js
const prefix$3 = "status.";
const captionsOnText = {
	key: `${prefix$3}captionsOn`,
	text: "Captions on"
};
const captionsOffText = {
	key: `${prefix$3}captionsOff`,
	text: "Captions off"
};
const pausedText = {
	key: `${prefix$3}paused`,
	text: "Paused"
};
const playingText = {
	key: `${prefix$3}playing`,
	text: "Playing"
};
const fullscreenText = {
	key: `${prefix$3}fullscreen`,
	text: "Fullscreen"
};
const pipText = {
	key: `${prefix$3}pip`,
	text: "Picture in picture"
};
const exitPipText = {
	key: `${prefix$3}exitPip`,
	text: "Exit picture in picture"
};
const seekedToText = {
	key: `${prefix$3}seekedTo`,
	text: "Seeked to {time}"
};

//#endregion
//#region ../core/dist/dev/i18n/text/volume.js
const prefix$2 = "volume.";
const mutedValueText = {
	key: `${prefix$2}mutedValue`,
	text: "{percent}, muted"
};
const mutedText = {
	key: `${prefix$2}muted`,
	text: "Muted"
};
const labelText = {
	key: `${prefix$2}label`,
	text: "Volume"
};
const valueText = {
	key: `${prefix$2}value`,
	text: "Volume {value}"
};

//#endregion
//#region ../core/dist/dev/core/ui/indicator/indicator-labels.js
const DEFAULT_INPUT_INDICATOR_LABELS = {
	muted: translateText$1(mutedText),
	volume: translateText$1(labelText),
	captionsOn: translateText$1(captionsOnText),
	captionsOff: translateText$1(captionsOffText),
	paused: translateText$1(pausedText),
	playing: translateText$1(playingText),
	fullscreen: translateText$1(fullscreenText),
	exitFullscreen: translateText$1(exitText),
	pictureInPicture: translateText$1(pipText),
	exitPictureInPicture: translateText$1(exitPipText)
};
/** Maps i18n indicator keys to {@link InputIndicatorLabels} for status / volume feedback. */
function createInputIndicatorLabels(translator) {
	return {
		muted: translator(mutedText),
		volume: translator(labelText),
		captionsOn: translator(captionsOnText),
		captionsOff: translator(captionsOffText),
		paused: translator(pausedText),
		playing: translator(playingText),
		fullscreen: translator(fullscreenText),
		exitFullscreen: translator(exitText),
		pictureInPicture: translator(pipText),
		exitPictureInPicture: translator(exitPipText)
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/input-action/input-action.js
function isInputActionIncluded(action, actions) {
	if (!action) return false;
	return !actions || actions.includes(action);
}

//#endregion
//#region ../core/dist/dev/core/ui/menu/core.js
/** Base menu logic: ARIA attributes and open/close state computation. */
var MenuCore = class MenuCore {
	static defaultProps = {
		side: "bottom",
		align: "start",
		open: false,
		defaultOpen: false,
		closeOnEscape: true,
		closeOnOutsideClick: true
	};
	#props = { ...MenuCore.defaultProps };
	#input = null;
	get props() {
		return this.#props;
	}
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, MenuCore.defaultProps);
	}
	setInput(input) {
		this.#input = input;
	}
	getState() {
		const input = this.#input;
		const isSubmenu = input.isSubmenu;
		return {
			open: input.active,
			status: input.status,
			side: isSubmenu ? void 0 : this.#props.side,
			align: isSubmenu ? void 0 : this.#props.align,
			isSubmenu,
			...getTransitionFlags(input.status)
		};
	}
	getTriggerAttrs(state, contentId) {
		return {
			...!state.isSubmenu && { tabIndex: 0 },
			"aria-haspopup": "menu",
			"aria-expanded": state.open && state.status !== "ending" ? "true" : "false",
			"aria-controls": contentId
		};
	}
	getContentAttrs() {
		return {
			role: "menu",
			tabIndex: -1
		};
	}
	getPopupAttrs() {
		return { popover: "manual" };
	}
};

//#endregion
//#region ../core/dist/dev/i18n/text/buttons.js
const prefix$1 = "buttons.";
const playText = {
	key: `${prefix$1}play`,
	text: "Play"
};
const pauseText = {
	key: `${prefix$1}pause`,
	text: "Pause"
};
const replayText = {
	key: `${prefix$1}replay`,
	text: "Replay"
};
const muteText = {
	key: `${prefix$1}mute`,
	text: "Mute"
};
const unmuteText = {
	key: `${prefix$1}unmute`,
	text: "Unmute"
};

//#endregion
//#region ../core/dist/dev/core/ui/mute-button/core.js
var MuteButtonCore = class MuteButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		muted: false,
		volumeLevel: "off",
		availability: "unavailable",
		hidden: true,
		label: ""
	});
	#props = { ...MuteButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, MuteButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		return state.muted ? unmuteText : muteText;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": this.#props.disabled ? "true" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		const availability = media.mutedAvailability;
		this.state.patch({
			muted: media.muted || media.volume === 0,
			volumeLevel: getVolumeLevel$1(media),
			availability,
			hidden: availability !== "available"
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	toggle(media) {
		if (this.#props.disabled || media.mutedAvailability !== "available") return;
		media.toggleMuted();
	}
};
function getVolumeLevel$1(media) {
	if (media.muted || media.volume === 0) return "off";
	if (media.volume < .5) return "low";
	if (media.volume < .75) return "medium";
	return "high";
}

//#endregion
//#region ../core/dist/dev/core/ui/mute-button/data.js
const MuteButtonDataAttrs = {
	/** Present when the media is muted. */
	muted: "data-muted",
	/** Indicates the volume level. */
	volumeLevel: "data-volume-level",
	/** Indicates mute availability (`available`, `unavailable`, `unsupported`). */
	availability: "data-availability",
	/** Present when the button is hidden because the media has no mute to toggle. */
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/core/ui/play-button/core.js
var PlayButtonCore = class PlayButtonCore {
	static defaultProps = {
		label: "",
		disabled: false
	};
	state = createState({
		paused: true,
		ended: false,
		started: false,
		label: ""
	});
	#props = { ...PlayButtonCore.defaultProps };
	#media = null;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, PlayButtonCore.defaultProps);
	}
	getLabel(state) {
		const label = resolveLabel(this.#props.label, state);
		if (label) return label;
		if (state.ended) return replayText;
		return state.paused ? playText : pauseText;
	}
	getAttrs(state) {
		return {
			"aria-label": this.getLabel(state),
			"aria-disabled": this.#props.disabled ? "true" : void 0
		};
	}
	setMedia(media) {
		this.#media = media;
	}
	getState() {
		const media = this.#media;
		this.state.patch({
			paused: media.paused,
			ended: media.ended,
			started: media.started
		});
		this.state.patch({ label: resolveText$1(this.getLabel(this.state.current)) });
		return this.state.current;
	}
	async toggle(media) {
		if (this.#props.disabled) return;
		if (media.paused || media.ended) return media.play();
		media.pause();
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/play-button/data.js
const PlayButtonDataAttrs = {
	/** Present when the media is paused. */
	paused: "data-paused",
	/** Present when the media has ended. */
	ended: "data-ended",
	/** Present when playback has started. */
	started: "data-started"
};

//#endregion
//#region ../core/dist/dev/i18n/text/playback.js
const rateText = {
	key: `playback.rate`,
	text: "Playback rate {rate}"
};

//#endregion
//#region ../core/dist/dev/core/ui/popover/core.js
var PopoverCore = class PopoverCore {
	static defaultProps = {
		side: "top",
		align: "center",
		modal: false,
		closeOnEscape: true,
		closeOnOutsideClick: true,
		open: false,
		defaultOpen: false,
		openOnHover: false,
		delay: 300,
		closeDelay: 0
	};
	#props = { ...PopoverCore.defaultProps };
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, PopoverCore.defaultProps);
	}
	#input = null;
	setInput(input) {
		this.#input = input;
	}
	getState() {
		const input = this.#input;
		return {
			open: input.active,
			status: input.status,
			side: this.#props.side,
			align: this.#props.align,
			modal: this.#props.modal,
			...getTransitionFlags(input.status)
		};
	}
	getTriggerAttrs(state, popupId) {
		return {
			"aria-expanded": state.open && state.status !== "ending" ? "true" : "false",
			"aria-haspopup": "dialog",
			"aria-controls": popupId
		};
	}
	getPopupAttrs(state) {
		return {
			popover: "manual",
			role: "dialog",
			"aria-modal": state.modal === true ? "true" : void 0
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/popover/data.js
const PopoverDataAttrs = {
	/** Present when the popover is open. */
	open: "data-open",
	/** Indicates the rendered side of the popover after collision handling. */
	side: "data-side",
	/** Indicates how the popover is aligned relative to the specified side. */
	align: "data-align",
	...TransitionDataAttrs
};

//#endregion
//#region ../core/dist/dev/core/ui/popover/popup-host-attr.js
/**
* Hosted floating UI surfaces (popover, menu, tooltip, and future overlays) that support parent-driven lifecycle may
* set {@link POPUP_HOST_ATTR}. Ancestors can discover them with {@link POPUP_HOST_SELECTOR} and call methods such as
* `close('imperative-action')` when the element implements that contract.
*/
const POPUP_HOST_ATTR = "data-popup";
const POPUP_HOST_SELECTOR = `[${POPUP_HOST_ATTR}]`;

//#endregion
//#region ../utils/dist/time/format.js
const durationFormatters = /* @__PURE__ */ new Map();
function createDurationFormatter(style, hoursDisplay, locale) {
	if (style === "digital") {
		const number = new Intl.NumberFormat(locale, { useGrouping: false });
		const padded = new Intl.NumberFormat(locale, {
			minimumIntegerDigits: 2,
			useGrouping: false
		});
		return { format: (duration) => {
			const body = `${padded.format(duration.minutes ?? 0)}:${padded.format(duration.seconds ?? 0)}`;
			return hoursDisplay === "always" || duration.hours !== void 0 ? `${number.format(duration.hours ?? 0)}:${body}` : body;
		} };
	}
	const units = [
		["hours", new Intl.NumberFormat(locale, {
			style: "unit",
			unit: "hour",
			unitDisplay: style
		})],
		["minutes", new Intl.NumberFormat(locale, {
			style: "unit",
			unit: "minute",
			unitDisplay: style
		})],
		["seconds", new Intl.NumberFormat(locale, {
			style: "unit",
			unit: "second",
			unitDisplay: style
		})]
	];
	const list = new Intl.ListFormat(locale, {
		type: "unit",
		style
	});
	return { format: (duration) => list.format(units.filter(([unit]) => duration[unit] !== void 0).map(([unit, formatter]) => formatter.format(duration[unit] ?? 0))) };
}
function localeCacheKey$1(locale) {
	if (locale === void 0) return "";
	return Array.isArray(locale) ? locale.join(":") : locale;
}
function getDurationFormatter(locale, style = "long", hoursDisplay) {
	const key = `${localeCacheKey$1(locale)}:${style}:${hoursDisplay ?? ""}`;
	let formatter = durationFormatters.get(key);
	if (!formatter) {
		formatter = createDurationFormatter(style, hoursDisplay, locale);
		durationFormatters.set(key, formatter);
	}
	return formatter;
}
function isValidTime(value) {
	return isNumber(value) && Number.isFinite(value);
}
/**
* Format seconds to digital display string.
*
* @example
*   formatTime(90); // "1:30"
*   formatTime(3661); // "1:01:01"
*   formatTime(35, 3600); // "0:00:35" (guided by 1-hour duration)
*   formatTime(35, 600); // "00:35" (guided by 10-minute duration)
*
* @param seconds - Time in seconds (can be negative)
* @param guide - Guide time (typically duration) to determine display format
* @param options - Digital formatting options
* @returns Formatted string like "1:30" or "1:05:30"
*/
function formatTime(seconds, guide, options) {
	if (!isValidTime(seconds)) return "0:00";
	const negative = seconds < 0;
	const totalSeconds = Math.floor(Math.abs(seconds));
	const hours = Math.floor(totalSeconds / 3600);
	const minutes = Math.floor(totalSeconds % 3600 / 60);
	const secondsPart = totalSeconds % 60;
	const guideSeconds = isValidTime(guide ?? 0) ? Math.abs(guide ?? 0) : 0;
	const guideHours = Math.floor(guideSeconds / 3600);
	const guideMinutes = Math.floor(guideSeconds / 60 % 60);
	const showHours = hours > 0 || guideHours > 0;
	const padMinutes = showHours || guideMinutes >= 10;
	const duration = showHours ? {
		hours,
		minutes,
		seconds: secondsPart
	} : {
		minutes,
		seconds: secondsPart
	};
	const { locale = "en" } = options ?? {};
	let body = getDurationFormatter(locale, "digital", showHours ? "always" : "auto").format(duration);
	if (!padMinutes) {
		const zero = new Intl.NumberFormat(locale, { useGrouping: false }).format(0);
		body = body.replace(new RegExp(`^${zero}(?=\\p{Nd}\\D)`, "u"), "");
	}
	return `${negative ? "-" : ""}${body}`;
}
/**
* Convert seconds to ISO 8601 duration for datetime attribute.
*
* @example
*   secondsToIsoDuration(90); // "PT1M30S"
*   secondsToIsoDuration(3661); // "PT1H1M1S"
*
* @param seconds - Time in seconds
* @returns ISO 8601 duration string like "PT1M30S"
*/
function secondsToIsoDuration(seconds) {
	if (!isValidTime(seconds)) return "PT0S";
	const positiveSeconds = Math.abs(seconds);
	const h = Math.floor(positiveSeconds / 3600);
	const m = Math.floor(positiveSeconds / 60 % 60);
	const s = Math.floor(positiveSeconds % 60);
	let duration = "PT";
	if (h > 0) duration += `${h}H`;
	if (m > 0) duration += `${m}M`;
	if (s > 0 || duration === "PT") duration += `${s}S`;
	return duration;
}
/**
* Human-readable duration using `Intl.NumberFormat` and `Intl.ListFormat`.
*
* Negative `seconds` denote remaining time: the absolute value is formatted, then wrapped in a localized phrase via
* {@link TimeFormatOptions.formatRemaining}; otherwise `{duration} remaining`.
*/
function formatTimeAsPhrase(seconds, options) {
	if (!isValidTime(seconds)) return "";
	const { locale = "en", style = "long", formatRemaining } = options ?? {};
	const negative = seconds < 0;
	const totalSeconds = Math.floor(Math.abs(seconds));
	const hours = Math.floor(totalSeconds / 3600);
	const minutes = Math.floor(totalSeconds % 3600 / 60);
	const secondsPart = totalSeconds % 60;
	const record = {};
	if (hours > 0) record.hours = hours;
	if (minutes > 0) record.minutes = minutes;
	if (secondsPart > 0 || hours === 0 && minutes === 0) record.seconds = secondsPart;
	const body = getDurationFormatter(locale, style).format(record);
	if (negative) {
		if (formatRemaining) return formatRemaining(body);
		if (isDefaultLocale(locale)) return `${body} remaining`;
		return body;
	}
	return body;
}

//#endregion
//#region ../core/dist/dev/core/ui/seek-indicator/seek-indicator-status.js
function isSeekIndicatorAction(action) {
	return action === "seekStep" || action === "seekToPercent";
}
function formatCurrentTime(snapshot) {
	return formatTime(snapshot.currentTime ?? 0, snapshot.duration);
}
function getSeekIndicatorDisplayValue(state) {
	return state.value ?? state.currentTime;
}
function getSeekToPercent(event) {
	if (event.value !== void 0) return clamp(event.value, 0, 100);
	if (!event.key || event.key < "0" || event.key > "9") return null;
	return Number(event.key) * 10;
}
function getSeekDirection(event, snapshot) {
	if (event.action === "seekStep" && event.value !== void 0) {
		if (event.value > 0) return "forward";
		if (event.value < 0) return "backward";
	}
	if (event.action === "seekToPercent") {
		const percent = getSeekToPercent(event);
		if (percent === null || snapshot.duration === void 0 || snapshot.duration <= 0) return null;
		const targetTime = percent / 100 * snapshot.duration;
		const currentTime = snapshot.currentTime ?? 0;
		if (targetTime > currentTime) return "forward";
		if (targetTime < currentTime) return "backward";
	}
	return null;
}

//#endregion
//#region ../core/dist/dev/core/ui/seek-indicator/core.js
const INITIAL_STATE$2 = {
	open: false,
	generation: 0,
	direction: null,
	count: 0,
	seekTotal: 0,
	value: null,
	currentTime: "0:00",
	transitionStarting: false,
	transitionEnding: false
};
var SeekIndicatorCore = class {
	state = createState({ ...INITIAL_STATE$2 });
	#props = {};
	#originTime = null;
	#close = new IndicatorCloseController(() => {
		this.#originTime = null;
		this.state.patch({
			open: false,
			direction: null,
			count: 0,
			seekTotal: 0,
			value: null
		});
	}, () => getIndicatorCloseDelay(this.#props));
	setProps(props) {
		this.#props = props;
	}
	destroy() {
		this.#close.destroy();
	}
	close() {
		this.#close.close();
	}
	processEvent(event, snapshot) {
		if (!isSeekIndicatorAction(event.action)) return false;
		const current = this.state.current;
		const direction = getSeekDirection(event, snapshot);
		const rapidRepeat = current.open && event.action === "seekStep" && current.direction === direction;
		if (!rapidRepeat) this.#originTime = snapshot.currentTime ?? null;
		const value = this.#getEffectiveSeekValue(event, snapshot, rapidRepeat);
		const seekTotal = rapidRepeat ? current.seekTotal + Math.abs(value) : Math.abs(value);
		this.state.patch({
			open: true,
			generation: current.generation + 1,
			direction,
			count: rapidRepeat ? current.count + 1 : 1,
			seekTotal,
			value: event.action === "seekStep" && seekTotal > 0 ? `${seekTotal}s` : null,
			currentTime: formatCurrentTime(snapshot)
		});
		this.#close.arm();
		return true;
	}
	#getEffectiveSeekValue(event, snapshot, rapidRepeat) {
		if (event.action !== "seekStep" || event.value === void 0) return 0;
		if (!rapidRepeat || this.#originTime === null) return event.value;
		const originTime = this.#originTime;
		const duration = snapshot.duration ?? Infinity;
		const currentTotal = this.state.current.seekTotal;
		const step = Math.abs(event.value);
		return (event.value < 0 ? Math.max(0, originTime - currentTotal) : Math.max(0, duration - originTime - currentTotal)) >= step ? event.value : 0;
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/seek-indicator/data.js
const SeekIndicatorDataAttrs = {
	/** Present while the indicator is open. */
	open: "data-open",
	/** Direction of the seek as `"forward"` or `"backward"`. */
	direction: "data-direction",
	/** Present during the open transition. */
	transitionStarting: "data-starting-style",
	/** Present during the close transition. */
	transitionEnding: "data-ending-style"
};

//#endregion
//#region ../core/dist/dev/core/ui/slider/core.js
/** Base slider logic: value mapping, ARIA attrs, and step calculations. */
var SliderCore = class SliderCore {
	static defaultProps = {
		label: "",
		step: 1,
		largeStep: 10,
		orientation: "horizontal",
		disabled: false,
		thumbAlignment: "center",
		value: 0,
		min: 0,
		max: 100
	};
	static defaultInput = {
		pointerPercent: 0,
		dragPercent: 0,
		dragging: false,
		pointing: false,
		focused: false
	};
	#props = { ...SliderCore.defaultProps };
	#input = { ...SliderCore.defaultInput };
	get props() {
		return this.#props;
	}
	get input() {
		return this.#input;
	}
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, SliderCore.defaultProps);
	}
	setInput(input) {
		this.#input = input;
	}
	getSliderState(value) {
		const { orientation, disabled, thumbAlignment } = this.#props;
		const { pointerPercent, dragging, pointing, focused } = this.#input;
		return {
			value,
			fillPercent: this.percentFromValue(value),
			pointerPercent,
			dragging,
			pointing,
			interactive: dragging || pointing || focused,
			orientation,
			disabled,
			thumbAlignment
		};
	}
	getLabel(state) {
		return resolveLabel(this.#props.label, state) || "";
	}
	getAttrs(state) {
		return {
			role: "slider",
			tabIndex: state.disabled ? -1 : 0,
			autoComplete: "off",
			"aria-label": this.getLabel(state),
			"aria-valuemin": this.#props.min,
			"aria-valuemax": this.#props.max,
			"aria-valuenow": state.value,
			"aria-orientation": state.orientation,
			"aria-disabled": state.disabled ? "true" : void 0
		};
	}
	valueFromPercent(percent) {
		const { min, max, step } = this.#props;
		const raw = min + percent / 100 * (max - min);
		return roundToStep(clamp(raw, min, max), step, min);
	}
	/** Convert percent to a clamped value without applying step rounding. */
	rawValueFromPercent(percent) {
		const { min, max } = this.#props;
		return clamp(min + percent / 100 * (max - min), min, max);
	}
	percentFromValue(value) {
		const { min, max } = this.#props;
		return toPercent(value, min, max);
	}
	/** Step as a percentage of the slider range. */
	getStepPercent() {
		const { step, min, max } = this.#props;
		const range = max - min;
		return range > 0 ? step / range * 100 : 0;
	}
	/** Large step as a percentage of the slider range. */
	getLargeStepPercent() {
		const { largeStep, min, max } = this.#props;
		const range = max - min;
		return range > 0 ? largeStep / range * 100 : 0;
	}
	adjustPercentForAlignment(rawPercent, thumbSize, trackSize) {
		if (this.#props.thumbAlignment === "center" || trackSize === 0) return rawPercent;
		const thumbHalf = thumbSize / trackSize * 100 / 2;
		const minPercent = thumbHalf;
		const maxPercent = 100 - thumbHalf;
		return minPercent + rawPercent / 100 * (maxPercent - minPercent);
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/slider/data.js
const SliderDataAttrs = {
	/** Present when the user is actively dragging. */
	dragging: "data-dragging",
	/** Present when the pointer is over the slider. */
	pointing: "data-pointing",
	/** Present when dragging or pointing is active. */
	interactive: "data-interactive",
	/** Current axis of slider movement (`horizontal` or `vertical`). */
	orientation: "data-orientation",
	/** Present when the slider is non-interactive. */
	disabled: "data-disabled"
};

//#endregion
//#region ../core/dist/dev/core/ui/status-announcer/status-announcer-labels.js
/** Default English labels used when no translated labels are provided. */
const DEFAULT_STATUS_ANNOUNCER_LABELS = {
	...DEFAULT_INPUT_INDICATOR_LABELS,
	volumeWithValue: (value) => translateText$1(valueText, { value }),
	seekedTo: (time) => translateText$1(seekedToText, { time: formatTimeAsPhrase(time) }),
	playbackRate: (rate) => translateText$1(rateText, { rate })
};
/** Creates translated labels for status, volume, seek, and playback-rate announcements. */
function createStatusAnnouncerLabels(translator, locale = "en") {
	return {
		...createInputIndicatorLabels(translator),
		volumeWithValue: (value) => translator(valueText, { value }),
		seekedTo: (time) => translator(seekedToText, { time: formatTimeAsPhrase(time, { locale }) }),
		playbackRate: (rate) => translator(rateText, { rate })
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/volume-indicator/volume-indicator-status.js
function isVolumeIndicatorAction(action) {
	return action === "toggleMuted" || action === "volumeStep";
}
function getVolumeLevel(volume) {
	if (volume <= 0) return "off";
	return volume <= .5 ? "low" : "high";
}
function formatVolumeValue(volume) {
	return `${Math.round(clamp(volume, 0, 1) * 100)}%`;
}
function getVolumeIndicatorDisplayValue(state) {
	return state.value ?? "";
}
/** Predicted mute/volume after a volume-indicator action. */
function predictVolumeActionOutcome(event, snapshot) {
	const muted = snapshot.muted === true;
	const snapshotVolume = snapshot.volume ?? 0;
	if (event.action === "toggleMuted") return {
		snapshotVolume,
		nextMuted: !muted,
		nextVolume: snapshotVolume
	};
	if (event.action === "volumeStep") {
		const nextVolume = clamp(snapshotVolume + (event.value ?? 0), 0, 1);
		return {
			snapshotVolume,
			nextMuted: muted && nextVolume <= 0,
			nextVolume
		};
	}
	return {
		snapshotVolume,
		nextMuted: muted,
		nextVolume: snapshotVolume
	};
}
/** Labels/value/level for volume actions, shared with `StatusIndicatorCore`. */
function deriveVolumeStatus(event, snapshot, labels = DEFAULT_INPUT_INDICATOR_LABELS, cachedPrediction) {
	const prediction = cachedPrediction ?? predictVolumeActionOutcome(event, snapshot);
	const level = prediction.nextMuted ? "off" : getVolumeLevel(prediction.nextVolume);
	const value = prediction.nextMuted ? "0%" : formatVolumeValue(prediction.nextVolume);
	return {
		status: level === "off" ? "volume-off" : level === "low" ? "volume-low" : "volume-high",
		label: level === "off" ? labels.muted : labels.volume,
		value,
		volumeLevel: level
	};
}

//#endregion
//#region ../core/dist/dev/core/ui/status-announcer/status-announcer-status.js
/** Derives the immediate announcement for changed playback, captions, presentation, and playback-rate state. */
function deriveStatusAnnouncement(previous, snapshot, labels = DEFAULT_STATUS_ANNOUNCER_LABELS) {
	const announcements = [];
	if (hasChanged(previous.paused, snapshot.paused)) announcements.push(snapshot.paused ? labels.paused : labels.playing);
	if (hasChanged(previous.subtitlesShowing, snapshot.subtitlesShowing) && snapshot.subtitlesAvailable !== false) announcements.push(snapshot.subtitlesShowing ? labels.captionsOn : labels.captionsOff);
	if (hasChanged(previous.fullscreen, snapshot.fullscreen)) announcements.push(snapshot.fullscreen ? labels.fullscreen : labels.exitFullscreen);
	if (hasChanged(previous.pip, snapshot.pip)) announcements.push(snapshot.pip ? labels.pictureInPicture : labels.exitPictureInPicture);
	if (hasChanged(previous.playbackRate, snapshot.playbackRate)) announcements.push(labels.playbackRate(`${snapshot.playbackRate}×`));
	return announcements.length > 0 ? announcements.join(". ") : null;
}
/** Derives the announcement for changed volume or mute state. */
function deriveVolumeAnnouncement(previous, snapshot, labels = DEFAULT_STATUS_ANNOUNCER_LABELS) {
	if (!hasChanged(previous.volume, snapshot.volume) && !hasChanged(previous.muted, snapshot.muted)) return null;
	const volume = snapshot.volume ?? previous.volume;
	const muted = snapshot.muted ?? previous.muted;
	if (volume === void 0 && muted === void 0) return null;
	return muted || (volume ?? 0) <= 0 ? labels.muted : labels.volumeWithValue(formatVolumeValue(volume ?? 0));
}
function hasChanged(previous, next) {
	return previous !== void 0 && next !== void 0 && !Object.is(previous, next);
}

//#endregion
//#region ../core/dist/dev/core/ui/status-announcer/core.js
const ANNOUNCEMENT_DEBOUNCE = 200;
var StatusAnnouncerCore = class {
	state = createState({
		generation: 0,
		label: null
	});
	#props = {};
	#snapshot = null;
	#seekStartTime = null;
	#seekTargetTime = null;
	#timer = null;
	#close = new IndicatorCloseController(() => this.state.patch({ label: null }), () => getIndicatorCloseDelay(this.#props));
	setProps(props) {
		this.#props = props;
	}
	resetSnapshot() {
		this.#snapshot = null;
		this.#seekStartTime = null;
		this.#seekTargetTime = null;
		this.#clearTimer();
		this.#close.close();
	}
	destroy() {
		this.#clearTimer();
		this.#close.destroy();
	}
	processSnapshot(snapshot) {
		const previous = this.#snapshot;
		this.#snapshot = snapshot;
		if (!previous) return false;
		const labels = this.#getLabels();
		const statusLabel = deriveStatusAnnouncement(previous, snapshot, labels);
		const statusHandled = statusLabel !== null && this.#announce(statusLabel);
		const seekHandled = this.#processSeekSnapshot(previous, snapshot, labels, statusHandled);
		const volumeHandled = this.#processVolumeSnapshot(previous, snapshot, labels, statusHandled || seekHandled);
		return statusHandled || seekHandled || volumeHandled;
	}
	#getLabels() {
		return {
			...DEFAULT_STATUS_ANNOUNCER_LABELS,
			...this.#props.labels
		};
	}
	#announce(label) {
		this.#clearTimer();
		this.state.patch({
			generation: this.state.current.generation + 1,
			label
		});
		this.#close.arm();
		return true;
	}
	#processVolumeSnapshot(previous, snapshot, labels, alreadyHandled) {
		const label = deriveVolumeAnnouncement(previous, snapshot, labels);
		if (label === null || alreadyHandled || !this.#shouldAnnounce()) return false;
		this.#schedule(label);
		return true;
	}
	#processSeekSnapshot(previous, snapshot, labels, alreadyHandled) {
		if (previous.seeking !== true && snapshot.seeking === true) {
			this.#seekStartTime = previous.currentTime ?? null;
			this.#seekTargetTime = snapshot.currentTime ?? null;
			this.#clearTimer();
			return false;
		}
		if (snapshot.seeking === true) {
			this.#seekTargetTime = snapshot.currentTime ?? this.#seekTargetTime;
			return false;
		}
		if (previous.seeking !== true || snapshot.seeking !== false) return false;
		const targetTime = snapshot.currentTime ?? this.#seekTargetTime;
		const startTime = this.#seekStartTime;
		this.#seekStartTime = null;
		this.#seekTargetTime = null;
		if (targetTime === void 0 || targetTime === null || Object.is(targetTime, startTime)) return false;
		if (alreadyHandled || !this.#shouldAnnounce()) return false;
		this.#schedule(labels.seekedTo(targetTime));
		return true;
	}
	#schedule(label) {
		this.#clearTimer();
		this.#timer = setTimeout(() => {
			this.#timer = null;
			if (!this.#shouldAnnounce()) return;
			this.#announce(label);
		}, ANNOUNCEMENT_DEBOUNCE);
	}
	#shouldAnnounce() {
		return this.#props.shouldAnnounce?.() !== false;
	}
	#clearTimer() {
		if (this.#timer === null) return;
		clearTimeout(this.#timer);
		this.#timer = null;
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/status-indicator/status-indicator-status.js
/** Derives the predicted visual status from an input action and its pre-action media snapshot. */
function deriveStatus(event, snapshot, labels = DEFAULT_INPUT_INDICATOR_LABELS) {
	switch (event.action) {
		case "togglePaused": {
			const paused = snapshot.paused !== void 0 ? !snapshot.paused : true;
			return {
				status: paused ? "pause" : "play",
				label: paused ? labels.paused : labels.playing,
				value: null
			};
		}
		case "toggleMuted":
		case "volumeStep": return deriveVolumeStatus(event, snapshot, labels);
		case "toggleSubtitles": {
			if (snapshot.subtitlesAvailable === false) return null;
			const showing = snapshot.subtitlesShowing !== void 0 ? !snapshot.subtitlesShowing : true;
			return {
				status: showing ? "captions-on" : "captions-off",
				label: showing ? labels.captionsOn : labels.captionsOff,
				value: null
			};
		}
		case "toggleFullscreen": {
			const fullscreen = snapshot.fullscreen !== void 0 ? !snapshot.fullscreen : true;
			return {
				status: fullscreen ? "fullscreen" : "exit-fullscreen",
				label: fullscreen ? labels.fullscreen : labels.exitFullscreen,
				value: null
			};
		}
		case "togglePictureInPicture": {
			const pip = snapshot.pip !== void 0 ? !snapshot.pip : true;
			return {
				status: pip ? "pip" : "exit-pip",
				label: pip ? labels.pictureInPicture : labels.exitPictureInPicture,
				value: null
			};
		}
		default: return null;
	}
}
/** Returns the volume percentage when present, then the translated status label. */
function getStatusIndicatorDisplayValue(state) {
	return state.value ?? state.label ?? "";
}

//#endregion
//#region ../core/dist/dev/core/ui/status-indicator/core.js
const INITIAL_STATE$1 = {
	open: false,
	generation: 0,
	status: null,
	label: null,
	value: null,
	transitionStarting: false,
	transitionEnding: false
};
var StatusIndicatorCore = class {
	state = createState({ ...INITIAL_STATE$1 });
	#props = {};
	#close = new IndicatorCloseController(() => this.state.patch({
		open: false,
		status: null,
		label: null,
		value: null
	}), () => getIndicatorCloseDelay(this.#props));
	setProps(props) {
		this.#props = props;
	}
	destroy() {
		this.#close.destroy();
	}
	close() {
		this.#close.close();
	}
	processEvent(event, snapshot) {
		if (!isInputActionIncluded(event.action, this.#props.actions)) return false;
		const details = deriveStatus(event, snapshot, {
			...DEFAULT_INPUT_INDICATOR_LABELS,
			...this.#props.labels
		});
		if (!details) return false;
		this.state.patch({
			open: true,
			generation: this.state.current.generation + 1,
			status: details.status,
			label: details.label,
			value: details.value
		});
		this.#close.arm();
		return true;
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/status-indicator/data.js
const StatusIndicatorDataAttrs = {
	/** Present while the indicator is open. */
	open: "data-open",
	/** Predicted visual status for the handled input action. */
	status: "data-status",
	/** Present during the open transition. */
	transitionStarting: "data-starting-style",
	/** Present during the close transition. */
	transitionEnding: "data-ending-style"
};

//#endregion
//#region ../core/dist/dev/core/ui/thumbnail/data.js
const ThumbnailDataAttrs = {
	loading: "data-loading",
	error: "data-error",
	hidden: "data-hidden"
};

//#endregion
//#region ../core/dist/dev/core/ui/thumbnail/thumbnail-media-fragment.js
/** Parse `url#xywh=x,y,w,h` into a URL and optional sprite coordinates. */
function parseMediaFragment(text, baseURL) {
	const parts = text.trim().split("#");
	const rawURL = parts[0] ?? "";
	const hash = parts[1];
	const url = baseURL ? new URL(rawURL, baseURL).href : rawURL;
	if (!hash) return { url };
	const eqIndex = hash.indexOf("=");
	if (eqIndex === -1) return { url };
	const keys = hash.slice(0, eqIndex);
	const values = hash.slice(eqIndex + 1).split(",").map(Number);
	const data = {};
	for (let i = 0; i < keys.length; i++) {
		const key = keys[i];
		const value = values[i];
		if (key && isNumber(value) && !Number.isNaN(value)) data[key] = value;
	}
	const result = { url };
	if (isNumber(data.w)) result.width = data.w;
	if (isNumber(data.h)) result.height = data.h;
	if (isNumber(data.x) && isNumber(data.y)) result.coords = {
		x: data.x,
		y: data.y
	};
	return result;
}
/**
* Convert an array of text cues (e.g. `VTTCue` from a `<track>` element) into {@link ThumbnailImage} entries by parsing
* the media-fragment in each cue's text.
*/
function mapCuesToThumbnails(cues, baseURL) {
	const images = [];
	for (const cue of cues) {
		const fragment = parseMediaFragment(cue.text, baseURL);
		const image = {
			url: fragment.url,
			startTime: cue.startTime,
			endTime: cue.endTime
		};
		if (fragment.width) image.width = fragment.width;
		if (fragment.height) image.height = fragment.height;
		if (fragment.coords) image.coords = fragment.coords;
		images.push(image);
	}
	return images;
}

//#endregion
//#region ../core/dist/dev/i18n/text/time.js
const prefix = "time.";
const currentText = {
	key: `${prefix}current`,
	text: "Current time"
};
const durationText = {
	key: `${prefix}duration`,
	text: "Duration"
};
const remainingText = {
	key: `${prefix}remaining`,
	text: "Remaining"
};
const elapsedSuffixText = {
	key: `${prefix}elapsedSuffix`,
	text: "{duration} elapsed"
};
const durationSuffixText = {
	key: `${prefix}durationSuffix`,
	text: "{duration} duration"
};
const remainingSuffixText = {
	key: `${prefix}remainingSuffix`,
	text: "{duration} remaining"
};
const showElapsedText = {
	key: `${prefix}showElapsed`,
	text: "Show elapsed time, {duration}."
};
const showDurationText = {
	key: `${prefix}showDuration`,
	text: "Show duration, {duration}."
};
const showRemainingText = {
	key: `${prefix}showRemaining`,
	text: "Show remaining time, {duration}."
};
const toggleElapsedText = {
	key: `${prefix}toggleElapsed`,
	text: "Toggle between elapsed and remaining time."
};
const toggleDurationText = {
	key: `${prefix}toggleDuration`,
	text: "Toggle between duration and remaining time."
};
const positionText = {
	key: `${prefix}position`,
	text: "{current} of {duration}"
};

//#endregion
//#region ../core/dist/dev/core/ui/time/core.js
const TOGGLE_LABELS = {
	current: showElapsedText,
	duration: showDurationText,
	remaining: showRemainingText
};
const DEFAULT_LABELS = {
	current: currentText,
	duration: durationText,
	remaining: remainingText
};
const TOGGLE_DESCRIPTIONS = {
	current: toggleElapsedText,
	duration: toggleDurationText,
	remaining: toggleDurationText
};
var TimeCore = class TimeCore {
	static defaultProps = {
		type: "current",
		negativeSign: "-",
		label: "",
		toggle: false
	};
	#props = { ...TimeCore.defaultProps };
	#media = null;
	#formatLocale;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, TimeCore.defaultProps);
	}
	setMedia(media) {
		this.#media = media;
	}
	/** @internal Platform adapters set the active i18n locale for digital time formatting. */
	setFormatLocale(locale) {
		this.#formatLocale = locale;
	}
	#getSeconds() {
		const media = this.#media;
		const { type } = this.#props;
		switch (type) {
			case "current": return media.currentTime;
			case "duration": return media.duration;
			case "remaining": return media.currentTime - media.duration;
			default: return 0;
		}
	}
	#getText() {
		const media = this.#media;
		const seconds = this.#getSeconds();
		const options = this.#formatLocale === void 0 ? void 0 : { locale: this.#formatLocale };
		return formatTime(Math.abs(seconds), media.duration, options);
	}
	#getPhrase() {
		const { type } = this.#props;
		const seconds = this.#getSeconds();
		if (type === "remaining") return formatTimeAsPhrase(seconds < 0 ? seconds : -Math.abs(seconds));
		return formatTimeAsPhrase(seconds);
	}
	#getDatetime() {
		const seconds = this.#getSeconds();
		return secondsToIsoDuration(Math.abs(seconds));
	}
	#getToggleType(type, currentType) {
		if (type === "current") return currentType === "remaining" ? "current" : "remaining";
		return currentType === "duration" ? "remaining" : "duration";
	}
	getLabel(state, type = this.#props.type) {
		const custom = resolveLabel(this.#props.label, state);
		if (custom !== void 0) return custom;
		if (!this.#props.toggle) return DEFAULT_LABELS[this.#props.type];
		const toggleType = this.#getToggleType(type, state.type);
		return TOGGLE_LABELS[toggleType];
	}
	getLabelParams(state) {
		if (resolveLabel(this.#props.label, state) !== void 0 || !this.#props.toggle) return void 0;
		const options = this.#formatLocale === void 0 ? void 0 : { locale: this.#formatLocale };
		const duration = formatTimeAsPhrase(Math.abs(state.seconds), options);
		switch (state.type) {
			case "current": return { duration: `${duration} elapsed` };
			case "duration": return { duration: `${duration} duration` };
			case "remaining": return { duration: `${duration} remaining` };
		}
	}
	getDescription(type = this.#props.type) {
		return this.#props.toggle ? TOGGLE_DESCRIPTIONS[type] : void 0;
	}
	getAttrs(state, type = this.#props.type) {
		return {
			"aria-label": this.getLabel(state, type),
			"aria-description": this.getDescription(type),
			role: this.#props.toggle ? "button" : void 0,
			tabIndex: this.#props.toggle ? 0 : void 0
		};
	}
	getState() {
		const seconds = this.#getSeconds();
		return {
			type: this.#props.type,
			seconds,
			negative: this.#props.type === "remaining" && seconds < 0,
			text: this.#getText(),
			phrase: this.#getPhrase(),
			datetime: this.#getDatetime()
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/time/data.js
const TimeDataAttrs = { 
/** The type of time being displayed. */
type: "data-type" };

//#endregion
//#region ../core/dist/dev/i18n/text/slider.js
const seekText = {
	key: `slider.seek`,
	text: "Seek"
};

//#endregion
//#region ../core/dist/dev/core/ui/time-slider/core.js
/** Time-domain slider: maps media time/buffer state to slider state. */
var TimeSliderCore = class TimeSliderCore extends SliderCore {
	static defaultProps = {
		...SliderCore.defaultProps,
		label: "",
		changeThrottle: 100,
		pauseOnDrag: false
	};
	#props = { ...TimeSliderCore.defaultProps };
	#media = null;
	#formatLocale;
	#wasPlayingBeforeDrag = false;
	constructor(props) {
		super();
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, TimeSliderCore.defaultProps);
		super.setProps({
			...props,
			min: 0
		});
	}
	setMedia(media) {
		this.#media = media;
	}
	/** @internal Platform adapters set the active i18n locale for `aria-valuetext` time formatting. */
	setFormatLocale(locale) {
		this.#formatLocale = locale;
	}
	getState() {
		const { duration, currentTime, seeking, buffered } = this.#media;
		super.setProps({
			...this.#props,
			min: 0,
			max: duration
		});
		const base = super.getSliderState(currentTime);
		const bufferedEnd = buffered.length > 0 ? buffered[buffered.length - 1][1] : 0;
		const bufferPercent = toPercent(bufferedEnd, 0, duration);
		return {
			...base,
			currentTime,
			duration,
			seeking,
			bufferPercent
		};
	}
	getLabel(state) {
		return super.getLabel(state) || seekText;
	}
	#announceValue(state) {
		return state.dragging ? this.rawValueFromPercent(state.pointerPercent) : state.value;
	}
	#formatTimeAsPhrase(seconds) {
		return this.#formatLocale === void 0 ? formatTimeAsPhrase(seconds) : formatTimeAsPhrase(seconds, { locale: this.#formatLocale });
	}
	getValueText(state) {
		return Number.isFinite(state.duration) ? positionText : this.getValueTextParams(state).current;
	}
	getValueTextParams(state) {
		const current = this.#formatTimeAsPhrase(this.#announceValue(state));
		if (!Number.isFinite(state.duration)) return { current };
		return {
			current,
			duration: this.#formatTimeAsPhrase(state.duration)
		};
	}
	/**
	* Pause playback when a drag begins if `pauseOnDrag` is enabled, remembering whether media was playing so `endDrag`
	* can resume it.
	*/
	startDrag(playback) {
		this.#wasPlayingBeforeDrag = false;
		if (this.#props.pauseOnDrag && playback && !playback.paused) {
			this.#wasPlayingBeforeDrag = true;
			playback.pause();
		}
	}
	/**
	* Resume playback if `startDrag` paused it. Resume depends only on the intent captured at drag start, so it survives
	* `pauseOnDrag` being toggled mid-drag. Safe to call on teardown — a no-op unless a drag paused playback.
	*/
	endDrag(playback) {
		if (this.#wasPlayingBeforeDrag) playback?.play().catch(() => {});
		this.#wasPlayingBeforeDrag = false;
	}
	getAttrs(state) {
		const base = super.getAttrs(state);
		const announceValue = this.#announceValue(state);
		return {
			...base,
			"aria-valuenow": announceValue,
			"aria-valuetext": this.getValueText(state)
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/time-slider/data.js
const TimeSliderDataAttrs = {
	...SliderDataAttrs,
	/** Present when a seek operation is in progress. */
	seeking: "data-seeking"
};

//#endregion
//#region ../core/dist/dev/core/ui/tooltip/core.js
var TooltipCore = class TooltipCore {
	static defaultProps = {
		side: "top",
		align: "center",
		open: false,
		defaultOpen: false,
		delay: 600,
		closeDelay: 0,
		disableHoverablePopup: true,
		disabled: false,
		sticky: false
	};
	#props = { ...TooltipCore.defaultProps };
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, TooltipCore.defaultProps);
	}
	#input = null;
	setInput(input) {
		this.#input = input;
	}
	getState() {
		const input = this.#input;
		return {
			open: input.active,
			status: input.status,
			side: this.#props.side,
			align: this.#props.align,
			...getTransitionFlags(input.status)
		};
	}
	getPopupAttrs(_state) {
		return {
			popover: "manual",
			role: "presentation"
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/tooltip/data.js
const TooltipDataAttrs = {
	/** Present when the tooltip is open. */
	open: "data-open",
	/** Indicates the rendered side of the tooltip after collision handling. */
	side: "data-side",
	/** Indicates how the tooltip is aligned relative to the specified side. */
	align: "data-align",
	...TransitionDataAttrs
};

//#endregion
//#region ../core/dist/dev/core/ui/tooltip/group-core.js
var TooltipGroupCore = class TooltipGroupCore {
	static defaultProps = {
		delay: 600,
		closeDelay: 0,
		timeout: 400
	};
	#props = { ...TooltipGroupCore.defaultProps };
	#lastCloseTime = 0;
	#isOpen = false;
	constructor(props) {
		if (props) this.setProps(props);
	}
	setProps(props) {
		this.#props = defaults(props, TooltipGroupCore.defaultProps);
	}
	get delay() {
		return this.#props.delay;
	}
	get closeDelay() {
		return this.#props.closeDelay;
	}
	shouldSkipDelay() {
		if (this.#isOpen) return true;
		return Date.now() - this.#lastCloseTime < this.#props.timeout;
	}
	notifyOpen() {
		this.#isOpen = true;
	}
	notifyClose() {
		this.#isOpen = false;
		this.#lastCloseTime = Date.now();
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/tooltip/vars.js
const TooltipCSSVars = {
	/** Distance between the popup and the trigger along the side axis. */
	sideOffset: "--media-tooltip-side-offset",
	/** Distance between the popup and the trigger along the alignment axis. */
	alignOffset: "--media-tooltip-align-offset",
	/** Minimum distance between the popup and the positioning boundary. */
	boundaryOffset: "--media-tooltip-boundary-offset",
	/** The anchor element's width. */
	anchorWidth: "--media-tooltip-anchor-width",
	/** The anchor element's height. */
	anchorHeight: "--media-tooltip-anchor-height",
	/** Available width between the trigger and the boundary edge. */
	availableWidth: "--media-tooltip-available-width",
	/** Available height between the trigger and the boundary edge. */
	availableHeight: "--media-tooltip-available-height"
};

//#endregion
//#region ../core/dist/dev/core/ui/volume-indicator/core.js
const BOUNDARY_CLEAR_DELAY = 300;
const INITIAL_STATE = {
	open: false,
	generation: 0,
	level: null,
	value: null,
	fill: null,
	min: false,
	max: false,
	transitionStarting: false,
	transitionEnding: false
};
var VolumeIndicatorCore = class {
	state = createState({ ...INITIAL_STATE });
	#props = {};
	#boundaryTimer = null;
	#boundaryRestartTimer = null;
	#close = new IndicatorCloseController(() => this.state.patch({
		open: false,
		level: null,
		value: null,
		fill: null,
		min: false,
		max: false
	}), () => getIndicatorCloseDelay(this.#props));
	setProps(props) {
		this.#props = props;
	}
	destroy() {
		this.#close.destroy();
		this.#clearBoundaryTimers();
	}
	close() {
		this.#clearBoundaryTimers();
		this.#close.close();
	}
	processEvent(event, snapshot) {
		if (!isVolumeIndicatorAction(event.action)) return false;
		const current = this.state.current;
		const prediction = predictVolumeActionOutcome(event, snapshot);
		const details = deriveVolumeStatus(event, snapshot, {
			...DEFAULT_INPUT_INDICATOR_LABELS,
			...this.#props.labels
		}, prediction);
		const boundary = getVolumeBoundary(event, prediction.snapshotVolume, prediction.nextVolume);
		const repeatedBoundary = boundary !== null && current[boundary] === true;
		if (!boundary) this.#clearBoundaryTimers();
		this.state.patch({
			open: true,
			generation: current.generation + 1,
			level: details.volumeLevel,
			value: details.value,
			fill: details.value,
			min: boundary === "min" && !repeatedBoundary,
			max: boundary === "max" && !repeatedBoundary
		});
		if (boundary) if (repeatedBoundary) this.#restartBoundary(boundary);
		else this.#scheduleBoundaryClear();
		this.#close.arm();
		return true;
	}
	#scheduleBoundaryClear() {
		this.#clearBoundaryTimer();
		this.#boundaryTimer = setTimeout(() => {
			this.#boundaryTimer = null;
			this.state.patch({
				min: false,
				max: false
			});
		}, BOUNDARY_CLEAR_DELAY);
	}
	#restartBoundary(boundary) {
		this.#clearBoundaryTimers();
		this.state.patch({
			min: false,
			max: false
		});
		this.#boundaryRestartTimer = setTimeout(() => {
			this.#boundaryRestartTimer = null;
			this.state.patch({ [boundary]: true });
			this.#scheduleBoundaryClear();
		}, 0);
	}
	#clearBoundaryTimer() {
		if (this.#boundaryTimer === null) return;
		clearTimeout(this.#boundaryTimer);
		this.#boundaryTimer = null;
	}
	#clearBoundaryRestartTimer() {
		if (this.#boundaryRestartTimer === null) return;
		clearTimeout(this.#boundaryRestartTimer);
		this.#boundaryRestartTimer = null;
	}
	#clearBoundaryTimers() {
		this.#clearBoundaryTimer();
		this.#clearBoundaryRestartTimer();
	}
};
function getVolumeBoundary(event, currentVolume, nextVolume) {
	if (event.action !== "volumeStep" || event.value === void 0 || event.value === 0) return null;
	if (nextVolume !== currentVolume) return null;
	return event.value < 0 ? "min" : "max";
}

//#endregion
//#region ../core/dist/dev/core/ui/volume-indicator/data.js
const VolumeIndicatorDataAttrs = {
	/** Present while the indicator is open. */
	open: "data-open",
	/** Predicted volume level as `"off"`, `"low"`, or `"high"`. */
	level: "data-level",
	/** Present briefly when a downward step cannot lower the volume further. */
	min: "data-min",
	/** Present briefly when an upward step cannot raise the volume further. */
	max: "data-max",
	/** Present during the open transition. */
	transitionStarting: "data-starting-style",
	/** Present during the close transition. */
	transitionEnding: "data-ending-style"
};

//#endregion
//#region ../core/dist/dev/core/ui/volume-indicator/vars.js
const VolumeIndicatorCSSVars = { 
/** Current predicted volume percentage, set on the Fill part. */
fill: "--media-volume-fill" };

//#endregion
//#region ../utils/dist/percent/percent.js
const formatters = /* @__PURE__ */ new Map();
function localeCacheKey(locale) {
	if (locale === void 0) return "";
	return Array.isArray(locale) ? locale.join(":") : locale;
}
function getFormatter(locale) {
	const key = localeCacheKey(locale);
	let formatter = formatters.get(key);
	if (!formatter) try {
		formatter = new Intl.NumberFormat(locale, {
			style: "percent",
			maximumFractionDigits: 0
		});
		formatters.set(key, formatter);
	} catch {
		return;
	}
	return formatter;
}
function formatFallback(fraction) {
	return `${Math.round(Math.min(1, Math.max(0, fraction)) * 100)}%`;
}
/** Format a fraction (0-1) with {@link Intl.NumberFormat} `style: "percent"`. */
function formatPercent(fraction, locale) {
	const value = !isNumber(fraction) || !Number.isFinite(fraction) ? 0 : Math.min(1, Math.max(0, fraction));
	try {
		const formatter = getFormatter(locale) ?? getFormatter(void 0);
		if (formatter) return formatter.format(value);
	} catch {}
	return formatFallback(value);
}

//#endregion
//#region ../core/dist/dev/core/ui/volume-slider/core.js
/** Volume-domain slider: maps media volume/mute state to slider state. */
var VolumeSliderCore = class VolumeSliderCore extends SliderCore {
	static defaultProps = {
		...SliderCore.defaultProps,
		label: "",
		wheelStep: 5
	};
	#media = null;
	#formatLocale;
	constructor(props) {
		super();
		if (props) this.setProps(props);
	}
	setProps(props) {
		super.setProps(defaults(props, VolumeSliderCore.defaultProps));
	}
	setMedia(media) {
		this.#media = media;
	}
	/** @internal Platform adapters set the active i18n locale for `aria-valuetext` percent formatting. */
	setFormatLocale(locale) {
		this.#formatLocale = locale;
	}
	getState() {
		const media = this.#media;
		const { volume, muted } = media;
		const effectivelyMuted = muted || volume === 0;
		const { dragging, dragPercent } = this.input;
		const volumePercent = volume * 100;
		const value = dragging ? this.valueFromPercent(dragPercent) : volumePercent;
		const base = super.getSliderState(value);
		const availability = media.volumeAvailability;
		return {
			...base,
			disabled: base.disabled || availability !== "available",
			fillPercent: effectivelyMuted ? 0 : base.fillPercent,
			volume,
			muted: effectivelyMuted,
			availability,
			hidden: availability !== "available"
		};
	}
	/** Wheel step as a percentage of the slider range. */
	getWheelStepPercent() {
		const props = this.props;
		const range = props.max - props.min;
		return range > 0 ? props.wheelStep / range * 100 : 0;
	}
	getLabel(state) {
		return super.getLabel(state) || labelText;
	}
	getValueText(state) {
		return state.muted ? mutedValueText : this.getValueTextParams(state).percent;
	}
	getValueTextParams(state) {
		return { percent: formatPercent(state.value / 100, this.#formatLocale) };
	}
	getAttrs(state) {
		return {
			...super.getAttrs(state),
			"aria-valuetext": this.getValueText(state)
		};
	}
};

//#endregion
//#region ../core/dist/dev/core/ui/volume-slider/data.js
const VolumeSliderDataAttrs = {
	...SliderDataAttrs,
	availability: "data-availability",
	hidden: "data-hidden"
};

//#endregion
//#region src/ui/position-controller.ts
let popupId = 0;
/** Connects a popup element to the shared positioning lifecycle. */
var PositionController = class {
	#host;
	#positioner = new PopupPositioner();
	#implicitBinding = null;
	constructor(host) {
		this.#host = host;
		host.addController(this);
	}
	/** Discover an explicit trigger by ID or one linked via `commandfor`. */
	findTrigger(trigger) {
		const root = this.#host.getRootNode();
		if (root.nodeType !== Node.DOCUMENT_NODE && root.nodeType !== Node.DOCUMENT_FRAGMENT_NODE) {
			this.#releaseImplicitBinding();
			return null;
		}
		const scopedRoot = root;
		if (trigger) {
			this.#releaseImplicitBinding();
			return scopedRoot.getElementById(trigger);
		}
		if (this.#implicitBinding) {
			const { id, trigger: boundTrigger } = this.#implicitBinding;
			if (this.#host.id === id && boundTrigger.getAttribute("commandfor") === id && this.#host.previousElementSibling === boundTrigger) return boundTrigger;
			this.#releaseImplicitBinding();
		}
		if (this.#host.id) return scopedRoot.querySelector(`[commandfor="${this.#host.id}"]`);
		const adjacent = this.#host.previousElementSibling;
		if (!(adjacent instanceof HTMLElement)) {
			console.warn(`[${this.#host.localName}] No trigger was found. Place the popup immediately after its trigger or link them explicitly.`);
			return null;
		}
		const claimedTarget = adjacent.getAttribute("commandfor");
		if (claimedTarget) {
			console.warn(`[${this.#host.localName}] The adjacent trigger already targets \`${claimedTarget}\`; link this popup explicitly.`);
			return null;
		}
		const id = nextPopupId(scopedRoot);
		this.#host.id = id;
		adjacent.setAttribute("commandfor", id);
		this.#implicitBinding = {
			id,
			trigger: adjacent
		};
		return adjacent;
	}
	sync(options) {
		this.#positioner.sync({
			...options,
			popup: this.#host
		});
	}
	cleanup() {
		this.#positioner.cleanup();
	}
	hostDisconnected() {
		this.cleanup();
		this.#releaseImplicitBinding();
	}
	hostDestroyed() {
		this.cleanup();
		this.#releaseImplicitBinding();
	}
	#releaseImplicitBinding() {
		const binding = this.#implicitBinding;
		if (!binding) return;
		if (binding.trigger.getAttribute("commandfor") === binding.id) binding.trigger.removeAttribute("commandfor");
		if (this.#host.id === binding.id) this.#host.removeAttribute("id");
		this.#implicitBinding = null;
	}
};
function nextPopupId(root) {
	let id;
	do
		id = `vjs-popup-${++popupId}`;
	while (root.getElementById(id));
	return id;
}

//#endregion
//#region src/ui/dialog/context.ts
const DIALOG_CONTEXT_KEY = Symbol("@videojs/dialog");
const dialogContext = createContext(DIALOG_CONTEXT_KEY);

//#endregion
//#region src/ui/context-part-element.ts
/**
* Abstract base for compound-component part elements that consume a parent context and apply data attributes from
* `ctx.state` + `ctx.stateAttrMap`.
*
* Subclasses only need to declare the `consumer` property:
*
* ```ts
* export class SliderTrackElement extends ContextPartElement<SliderState> {
*   static readonly tagName = 'media-slider-track';
*   protected readonly consumer = new ContextConsumer(this, { context: sliderContext, subscribe: true });
* }
* ```
*/
var ContextPartElement = class extends UIElement {
	connectedCallback() {
		super.connectedCallback();
		this.#applyState();
	}
	update(_changed) {
		super.update(_changed);
		this.#applyState();
	}
	#applyState() {
		const ctx = this.consumer.value;
		if (ctx) applyStateDataAttrs(this, ctx.state, ctx.stateAttrMap);
	}
};

//#endregion
//#region src/ui/controls/context.ts
const CONTROLS_CONTEXT_KEY = Symbol("@videojs/controls");
const controlsContext = createContext(CONTROLS_CONTEXT_KEY);

//#endregion
//#region src/ui/controls/controls-backdrop-element.ts
/** Presentational backdrop that reflects its owning controls surface's state. */
var ControlsBackdropElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: controlsContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-controls-backdrop";
	}
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("role", "presentation");
		this.setAttribute("aria-hidden", "true");
	}
};

//#endregion
//#region src/ui/controls/controls-content-element.ts
/** Interactive surface that reflects its owning controls state. */
var ControlsContentElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: controlsContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-controls-content";
	}
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("data-interactive", "");
	}
};

//#endregion
//#region src/ui/controls/controls-element.ts
var ControlsElement = class extends UIElement {
	static {
		this.tagName = "media-controls";
	}
	#core = new ControlsCore();
	#mediaState = new PlayerController(this, playerContext, selectControls);
	#provider = new ContextProvider(this, { context: controlsContext });
	#visible = true;
	connectedCallback() {
		super.connectedCallback();
		if (!this.#mediaState.value && this.#mediaState.displayName) logMissingFeature(this.localName, this.#mediaState.displayName);
	}
	update(_changed) {
		super.update(_changed);
		const media = this.#mediaState.value;
		if (!media) return;
		this.#core.setMedia(media);
		const state = this.#core.getState();
		applyStateDataAttrs(this, state, ControlsDataAttrs);
		this.#provider.setValue({
			state,
			stateAttrMap: ControlsDataAttrs
		});
		const wasVisible = this.#visible;
		this.#visible = state.visible;
		if (wasVisible && !state.visible) this.#closeOwnedOverlays();
	}
	#closeOwnedOverlays() {
		for (const element of this.querySelectorAll(POPUP_HOST_SELECTOR)) {
			const host = element;
			if (!isFunction(host.close)) continue;
			host.close("imperative-action");
		}
	}
};

//#endregion
//#region src/ui/controls/controls-group-element.ts
var ControlsGroupElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: controlsContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-controls-group";
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.hasAttribute("aria-label") || this.hasAttribute("aria-labelledby")) this.setAttribute("role", "group");
	}
};

//#endregion
//#region src/ui/dialog/dialog-backdrop-element.ts
/** Presentational backdrop that reflects its owning dialog's state. */
var DialogBackdropElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: dialogContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-dialog-backdrop";
	}
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("role", "presentation");
		this.setAttribute("aria-hidden", "true");
	}
};

//#endregion
//#region src/ui/dialog/dialog-close-element.ts
var DialogCloseElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.#ctx = new ContextConsumer(this, {
			context: dialogContext,
			subscribe: true
		});
		this.#disconnect = null;
	}
	static {
		this.tagName = "media-dialog-close";
	}
	static {
		this.properties = { disabled: { type: Boolean } };
	}
	#ctx;
	#disconnect;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
		const buttonProps = createButton({
			onActivate: () => this.#ctx.value?.close(),
			isDisabled: () => this.disabled
		});
		applyElementProps(this, buttonProps, { signal: this.#disconnect.signal });
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (ctx) applyStateDataAttrs(this, ctx.state, ctx.stateAttrMap);
	}
};

//#endregion
//#region src/ui/dialog/dialog-description-element.ts
var DialogDescriptionElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: dialogContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-dialog-description";
	}
	update(changed) {
		super.update(changed);
		const descriptionId = this.consumer.value?.state.descriptionId;
		if (descriptionId) this.id = descriptionId;
	}
};

//#endregion
//#region src/ui/dialog/dialog-popup-element.ts
/** Semantic popup that owns focus management and dialog transition completion. */
var DialogPopupElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: dialogContext,
			subscribe: true
		});
		this.#dialog = null;
	}
	static {
		this.tagName = "media-dialog-popup";
	}
	#dialog;
	disconnectedCallback() {
		this.#dialog?.setPopupElement(null);
		this.#dialog = null;
		super.disconnectedCallback();
	}
	update(changed) {
		super.update(changed);
		const ctx = this.consumer.value;
		if (!ctx) return;
		if (this.#dialog !== ctx.dialog) {
			this.#dialog?.setPopupElement(null);
			this.#dialog = ctx.dialog;
			this.#dialog.setPopupElement(this);
		}
		applyElementProps(this, {
			id: ctx.popupId,
			tabIndex: -1,
			...ctx.popupAttrs
		});
	}
};

//#endregion
//#region src/ui/dialog/dialog-title-element.ts
var DialogTitleElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: dialogContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-dialog-title";
	}
	update(changed) {
		super.update(changed);
		const titleId = this.consumer.value?.state.titleId;
		if (titleId) this.id = titleId;
	}
};

//#endregion
//#region src/ui/error-dialog/error-dialog-element.ts
let idCounter$2 = 0;
function hasAuthoredContent$1(host) {
	return Array.from(host.childNodes).some((node) => !!node.textContent?.trim());
}
var ErrorDialogElement = class extends UIElement {
	static {
		this.tagName = "media-error-dialog";
	}
	#core = new ErrorDialogCore();
	#provider = new ContextProvider(this, { context: dialogContext });
	#popupId = `vjs-error-dialog-popup-${idCounter$2++}`;
	#titleId = `vjs-error-dialog-title-${idCounter$2++}`;
	#descriptionId = `vjs-error-dialog-desc-${idCounter$2++}`;
	#errorState = new PlayerController(this, playerContext, selectError);
	#i18n = new I18nController(this, i18nContext);
	#dialog = null;
	#snapshot = null;
	#lastError = null;
	#lastDescription = null;
	#seenCopyParts = /* @__PURE__ */ new WeakSet();
	#authoredCopyParts = /* @__PURE__ */ new WeakSet();
	constructor() {
		super();
		this.#core.setTitleId(this.#titleId);
		this.#core.setDescriptionId(this.#descriptionId);
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#dialog = createDialog({
			transition: createTransition(),
			onOpenChange: (nextOpen) => {
				if (!nextOpen) this.#errorState.value?.dismissError();
			}
		});
		if (this.#snapshot) this.#snapshot.track(this.#dialog.input);
		else this.#snapshot = new SnapshotController(this, this.#dialog.input);
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#dialog?.destroy();
		this.#dialog = null;
	}
	willUpdate(_changed) {
		super.willUpdate(_changed);
		if (!this.#dialog) return;
		const errorState = this.#errorState.value;
		const hasError = Boolean(errorState?.error);
		const { active: isOpen } = this.#dialog.input.current;
		if (errorState?.error) this.#lastError = errorState.error;
		const errorForCopy = errorState?.error ?? (isOpen ? this.#lastError : null);
		this.#syncDialogCopy(errorForCopy);
		if (!hasError && !isOpen) {
			this.#lastError = null;
			this.#lastDescription = null;
		}
		if (hasError && !isOpen) this.#dialog.open();
		else if (!hasError && isOpen) this.#dialog.close();
	}
	update(_changed) {
		super.update(_changed);
		if (!this.#dialog) return;
		const input = this.#dialog.input.current;
		this.#core.setInput(input);
		const state = this.#core.getState();
		applyStateDataAttrs(this, state, ErrorDialogDataAttrs);
		this.#provider.setValue({
			state,
			stateAttrMap: ErrorDialogDataAttrs,
			dialog: this.#dialog,
			popupId: this.#popupId,
			popupAttrs: this.#core.getPopupAttrs(state),
			close: () => this.#dialog?.close()
		});
	}
	#syncDialogCopy(error) {
		const t = this.#i18n.value;
		const title = this.querySelector("media-dialog-title");
		if (title && !this.#hasAuthoredCopy(title)) title.textContent = translateText(getErrorDialogTitleText(), t);
		const desc = this.querySelector("media-dialog-description");
		if (desc && !this.#hasAuthoredCopy(desc)) {
			const description = error ? resolveErrorDialogDescription(error) : null;
			if (description) this.#lastDescription = description;
			const copy = description ?? this.#lastDescription;
			desc.textContent = copy ? translateText(copy, t) : translateText(getErrorDialogUnexpectedText(), t);
		}
		const close = this.querySelector("media-dialog-close");
		if (close && !this.#hasAuthoredCopy(close)) close.textContent = translateText(getErrorDialogDismissText(), t);
	}
	#hasAuthoredCopy(el) {
		if (!this.#seenCopyParts.has(el)) {
			this.#seenCopyParts.add(el);
			if (hasAuthoredContent$1(el)) this.#authoredCopyParts.add(el);
		}
		return this.#authoredCopyParts.has(el);
	}
};

//#endregion
//#region src/ui/radio-group/context.ts
const RADIO_GROUP_CONTEXT_KEY = Symbol("@videojs/radio-group");
const radioGroupContext = createContext(RADIO_GROUP_CONTEXT_KEY);

//#endregion
//#region src/ui/menu/context.ts
const MENU_CONTEXT_KEY = Symbol("@videojs/menu");
const MENU_GROUP_CONTEXT_KEY = Symbol("@videojs/menu-group");
const menuContext = createContext(MENU_CONTEXT_KEY);
const menuGroupContext = createContext(MENU_GROUP_CONTEXT_KEY);

//#endregion
//#region src/ui/menu/menu-checkbox-item-element.ts
/** @fires checked-change - Fired when the checked state changes. */
var MenuCheckboxItemElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.checked = false;
		this.disabled = false;
		this.#ctx = new ContextConsumer(this, {
			context: menuContext,
			subscribe: true
		});
		this.#disconnect = null;
		this.#registered = false;
		this.#cleanupRegistration = null;
	}
	static {
		this.tagName = "media-menu-checkbox-item";
	}
	static {
		this.properties = {
			checked: { type: Boolean },
			disabled: { type: Boolean }
		};
	}
	#ctx;
	#disconnect;
	#registered;
	#cleanupRegistration;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
		this.#registered = false;
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#cleanupRegistration?.();
		this.#cleanupRegistration = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
		this.#registered = false;
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (!ctx || !this.#disconnect) return;
		if (!this.#registered) {
			this.#registered = true;
			this.#cleanupRegistration = ctx.menu.registerItem(this);
			applyElementProps(this, {
				onClick: () => {
					if (!this.#ctx.value || this.disabled) return;
					this.checked = !this.checked;
					this.dispatchEvent(new CustomEvent("checked-change", {
						detail: { checked: this.checked },
						bubbles: true
					}));
				},
				onPointerenter: () => {
					const currentCtx = this.#ctx.value;
					if (!this.disabled) currentCtx?.menu.highlight(this, {
						focus: false,
						pointer: true
					});
				}
			}, { signal: this.#disconnect.signal });
		}
		applyElementProps(this, {
			role: "menuitemcheckbox",
			"aria-checked": String(this.checked),
			"aria-disabled": this.disabled ? "true" : void 0
		});
	}
};

//#endregion
//#region src/ui/menu/menu-content-element.ts
const defaultTriggerState = {
	hint: "",
	disabled: false
};
let idCounter$1 = 0;
/** One accessible menu page. Root and nested pages are sibling children of `<media-menu>`. */
var MenuContentElement = class MenuContentElement extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.open = false;
		this.defaultOpen = false;
		this.#root = new ContextConsumer(this, {
			context: menuContext,
			subscribe: true
		});
		this.#provider = new ContextProvider(this, { context: menuContext });
		this.#core = new MenuCore();
		this.#generatedId = `vjs-menu-content-${idCounter$1++}`;
		this.#context = null;
		this.#menu = null;
		this.#parentMenu = null;
		this.#rootMenu = null;
		this.#ownsMenu = false;
		this.#disconnect = null;
		this.#cleanupRegistration = null;
		this.#cleanupParentRegistration = null;
		this.#triggerState = defaultTriggerState;
		this.#stateTrigger = null;
		this.#wasActive = false;
		this.#normalizing = false;
		this.#handleKeyDown = (event) => {
			const isNavigationKey = isMenuNavigationKey(event);
			const defaultPrevented = event.defaultPrevented;
			this.#menu?.contentProps.onKeyDown(event);
			if (this.#parentMenu && (event.key === "ArrowLeft" || event.key === "Escape") && !defaultPrevented) {
				event.preventDefault();
				this.#menu?.close("escape");
			}
			if (event.key !== "Escape" && isNavigationKey) event.stopPropagation();
		};
		this.#handleFocusOut = (event) => {
			this.#menu?.contentProps.onFocusOut(event);
		};
		this.#setTriggerState = (triggerState) => {
			this.#triggerState = triggerState;
			if (triggerState.disabled && this.open && this.#parentMenu) this.close("imperative-action");
			this.#syncTriggerState(this.#findTrigger());
		};
	}
	static {
		this.tagName = "media-menu-content";
	}
	static {
		this.properties = {
			open: { type: Boolean },
			defaultOpen: {
				type: Boolean,
				attribute: "default-open"
			}
		};
	}
	#root;
	#provider;
	#core;
	#generatedId;
	#context;
	#menu;
	#parentMenu;
	#rootMenu;
	#ownsMenu;
	#disconnect;
	#cleanupRegistration;
	#cleanupParentRegistration;
	#triggerState;
	#stateTrigger;
	#wasActive;
	#normalizing;
	get context() {
		return this.#context;
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.#normalizing) return;
		this.#disconnect = new AbortController();
		applyElementProps(this, {
			onKeyDown: this.#handleKeyDown,
			onFocusOut: this.#handleFocusOut
		}, { signal: this.#disconnect.signal });
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		if (this.#normalizing) return;
		this.#cleanupMenu();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	openMenu(reason = "imperative-action") {
		this.#menu?.open(reason);
	}
	close(reason = "imperative-action") {
		this.#menu?.close(reason);
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		if (!this.hasUpdated && this.defaultOpen && !this.open) this.open = true;
		if (this.#ownsMenu && this.#menu && changed.has("open")) this.#menu.syncOpen(this.open);
	}
	update(changed) {
		super.update(changed);
		const root = this.#root.value ?? null;
		if (!root) return;
		if (!this.id) this.id = this.#generatedId;
		const trigger = this.#findTrigger();
		const parentContent = trigger?.closest(MenuContentElement.tagName) ?? null;
		if (parentContent && !parentContent.context) {
			this.hidden = true;
			requestAnimationFrame(() => this.requestUpdate());
			return;
		}
		const parentMenu = parentContent?.context?.menu ?? null;
		const isSubmenu = parentMenu !== null;
		if (root.menu !== this.#rootMenu || parentMenu !== this.#parentMenu) {
			this.#cleanupMenu();
			this.#rootMenu = root.menu;
			this.#parentMenu = parentMenu;
			this.#setupMenu(root, parentMenu);
		}
		const menu = this.#menu;
		if (!menu) return;
		const input = menu.input.current;
		this.#core.setInput({
			...input,
			isSubmenu
		});
		const state = this.#core.getState();
		const active = !isSubmenu || state.open || state.status === "ending";
		applyElementProps(this, {
			...this.#core.getContentAttrs(),
			hidden: !active
		});
		applyStateDataAttrs(this, state, MenuContentDataAttrs);
		if (trigger) {
			menu.setTriggerElement(trigger);
			applyElementProps(trigger, this.#core.getTriggerAttrs(state, active ? this.id : void 0));
			this.#syncTriggerState(trigger);
		}
		if (isSubmenu && active && !this.#wasActive) menu.highlightFirstItem({ preventScroll: true });
		this.#wasActive = active;
		this.#context = {
			core: this.#core,
			menu,
			popup: root.popup,
			state,
			setTriggerState: this.#setTriggerState
		};
		this.#provider.setValue(this.#context);
		root.popup.sync();
		this.#normalize();
	}
	#setupMenu(root, parentMenu) {
		if (parentMenu !== null) {
			this.#ownsMenu = true;
			this.#menu = createMenu({
				transition: createTransition(),
				onOpenChange: (nextOpen, details) => {
					if (this.dispatchEvent(new CustomEvent("open-change", {
						bubbles: true,
						cancelable: true,
						composed: true,
						detail: {
							open: nextOpen,
							...details
						}
					}))) this.open = nextOpen;
				},
				closeOnEscape: () => true,
				closeOnOutsideClick: () => false
			});
			this.#menu.setPopupElement(this);
			this.#cleanupParentRegistration = parentMenu.registerSubmenu(this.#menu);
			const signal = this.#disconnect?.signal;
			if (signal) this.#menu.input.subscribe(() => this.requestUpdate(), { signal });
			this.#menu.syncOpen(this.open);
		} else {
			this.#ownsMenu = false;
			this.#menu = root.menu;
		}
		this.#cleanupRegistration = root.popup.registerContent({
			menu: this.#menu,
			parent: parentMenu,
			element: this
		});
	}
	#cleanupMenu() {
		this.#cleanupRegistration?.();
		this.#cleanupRegistration = null;
		this.#cleanupParentRegistration?.();
		this.#cleanupParentRegistration = null;
		this.#clearTriggerState();
		if (this.#ownsMenu) this.#menu?.destroy();
		this.#menu = null;
		this.#context = null;
		this.#rootMenu = null;
		this.#parentMenu = null;
		this.#ownsMenu = false;
		this.#wasActive = false;
	}
	#findTrigger() {
		if (!this.id) return null;
		return [...this.getRootNode().querySelectorAll("[commandfor], media-menu-item")].find((element) => element.getAttribute("commandfor") === this.id || element.commandfor === this.id) ?? null;
	}
	/** Keep every page as a direct popup child, including authored nested pages. */
	#normalize() {
		const popup = this.closest("media-menu");
		if (!popup || this.parentElement === popup) return;
		this.#normalizing = true;
		popup.append(this);
		this.#normalizing = false;
	}
	#handleKeyDown;
	#handleFocusOut;
	#setTriggerState;
	#syncTriggerState(trigger) {
		if (trigger !== this.#stateTrigger) {
			this.#clearTriggerState();
			this.#stateTrigger = trigger;
		}
		if (!trigger) return;
		const disabled = this.#triggerState.disabled || isTriggerExplicitlyDisabled(trigger);
		applyElementProps(trigger, {
			"aria-disabled": disabled ? "true" : void 0,
			"data-availability": this.#triggerState.availability
		});
		const hint = trigger.querySelector("[data-part~=\"hint\"]");
		if (hint && hint.textContent !== this.#triggerState.hint) hint.textContent = this.#triggerState.hint;
	}
	#clearTriggerState() {
		const trigger = this.#stateTrigger;
		if (!trigger) return;
		applyElementProps(trigger, {
			"aria-disabled": isTriggerExplicitlyDisabled(trigger) ? "true" : void 0,
			"data-availability": void 0
		});
		const hint = trigger.querySelector("[data-part~=\"hint\"]");
		if (hint?.textContent) hint.textContent = "";
		this.#stateTrigger = null;
	}
};
function isTriggerExplicitlyDisabled(trigger) {
	return trigger.hasAttribute("disabled") || "disabled" in trigger && trigger.disabled === true;
}

//#endregion
//#region src/ui/menu/menu-element.ts
/**
* Root menu state and positioned popup. Content pages are direct children.
*
* @fires open-change - Fired before the menu's open state changes. Cancel the event to prevent the change.
*/
var MenuElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.open = MenuCore.defaultProps.open;
		this.defaultOpen = MenuCore.defaultProps.defaultOpen;
		this.side = MenuCore.defaultProps.side;
		this.align = MenuCore.defaultProps.align;
		this.closeOnEscape = MenuCore.defaultProps.closeOnEscape;
		this.closeOnOutsideClick = MenuCore.defaultProps.closeOnOutsideClick;
		this.boundary = "container";
		this.#core = new MenuCore();
		this.#provider = new ContextProvider(this, { context: menuContext });
		this.#position = new PositionController(this);
		this.#controlsState = new PlayerController(this, playerContext, selectControls);
		this.#containerCtx = new ContextConsumer(this, {
			context: containerContext,
			subscribe: true
		});
		this.#popupGroupCtx = new ContextConsumer(this, { context: popupGroupContext });
		this.#menu = null;
		this.#popup = null;
		this.#snapshot = null;
		this.#disconnect = null;
		this.#triggerAbort = null;
		this.#currentTrigger = null;
		this.#releaseControlsLock = null;
		this.#handleFocusOut = (event) => {
			this.#menu?.contentProps.onFocusOut(event);
		};
	}
	static {
		this.tagName = "media-menu";
	}
	static {
		this.properties = {
			open: { type: Boolean },
			defaultOpen: {
				type: Boolean,
				attribute: "default-open"
			},
			side: { type: String },
			align: { type: String },
			closeOnEscape: {
				type: Boolean,
				attribute: "close-on-escape"
			},
			closeOnOutsideClick: {
				type: Boolean,
				attribute: "close-on-outside-click"
			},
			boundary: { type: String }
		};
	}
	#core;
	#provider;
	#position;
	#controlsState;
	#containerCtx;
	#popupGroupCtx;
	#menu;
	#popup;
	#snapshot;
	#disconnect;
	#triggerAbort;
	#currentTrigger;
	#releaseControlsLock;
	get menu() {
		return this.#menu;
	}
	get popup() {
		return this.#popup;
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.setAttribute(POPUP_HOST_ATTR, "");
		this.#disconnect = new AbortController();
		this.#popup = createMenuPopup();
		this.#popup.setElement(this);
		this.#menu = createMenu({
			transition: createTransition(),
			onOpenChange: (nextOpen, details) => {
				if (this.dispatchEvent(new CustomEvent("open-change", {
					bubbles: true,
					cancelable: true,
					composed: true,
					detail: {
						open: nextOpen,
						...details
					}
				}))) this.open = nextOpen;
			},
			closeOnEscape: () => this.closeOnEscape,
			closeOnOutsideClick: () => this.closeOnOutsideClick,
			group: () => this.#popupGroupCtx.value
		});
		this.#menu.setPopupElement(this);
		applyElementProps(this, { onFocusOut: this.#handleFocusOut }, { signal: this.#disconnect.signal });
		if (this.#snapshot) this.#snapshot.track(this.#menu.input);
		else this.#snapshot = new SnapshotController(this, this.#menu.input);
	}
	disconnectedCallback() {
		this.#releaseControlsVisibilityLock();
		super.disconnectedCallback();
		this.#position.cleanup();
		this.#cleanupTrigger();
		this.#popup?.destroy();
		this.#popup = null;
		this.#menu?.destroy();
		this.#menu = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	close(reason = "imperative-action") {
		this.#menu?.close(reason);
	}
	openMenu(reason = "imperative-action") {
		this.#menu?.open(reason);
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		if (!this.hasUpdated && this.defaultOpen && !this.open) this.open = true;
		this.#core.setProps({
			open: this.open,
			defaultOpen: this.defaultOpen,
			side: this.side,
			align: this.align,
			closeOnEscape: this.closeOnEscape,
			closeOnOutsideClick: this.closeOnOutsideClick
		});
		if (this.#menu && changed.has("open")) this.#menu.syncOpen(this.open);
	}
	update(changed) {
		super.update(changed);
		if (!this.#menu || !this.#popup) return;
		const input = this.#menu.input.current;
		this.#core.setInput({
			...input,
			isSubmenu: false
		});
		const state = this.#core.getState();
		if (state.open) this.#releaseControlsLock ??= this.#controlsState.value?.requestControlsLock() ?? null;
		else this.#releaseControlsVisibilityLock();
		const triggerElement = this.#position.findTrigger();
		this.#syncTrigger(triggerElement);
		applyElementProps(this, this.#core.getPopupAttrs());
		applyStateDataAttrs(this, state, MenuPopupDataAttrs);
		if (state.open) tryShowPopover(this);
		else tryHidePopover(this);
		if (this.#currentTrigger) applyElementProps(this.#currentTrigger, this.#core.getTriggerAttrs(state, this.#menu.contentElement?.id));
		if (!state.open) this.#position.cleanup();
		else {
			this.#popup.sync();
			const positionOptions = getRootPositionOptions(state.side, state.align);
			if (positionOptions && this.#currentTrigger) this.#position.sync({
				anchorName: this.id,
				position: positionOptions,
				trigger: this.#currentTrigger,
				boundary: this.boundary,
				container: this.#containerCtx.value?.container ?? null,
				cssVars: MenuPositioningCSSVars,
				trackResize: false,
				onSideChange: (side) => this.setAttribute(MenuPopupDataAttrs.side, side)
			});
		}
		this.#provider.setValue({
			core: this.#core,
			menu: this.#menu,
			popup: this.#popup,
			state,
			setTriggerState: () => {}
		});
	}
	#releaseControlsVisibilityLock() {
		this.#releaseControlsLock?.();
		this.#releaseControlsLock = null;
	}
	#handleFocusOut;
	#syncTrigger(triggerElement) {
		if (triggerElement === this.#currentTrigger) return;
		this.#position.cleanup();
		this.#cleanupTrigger();
		this.#currentTrigger = triggerElement;
		this.#menu?.setTriggerElement(triggerElement);
		if (triggerElement && this.#menu) {
			this.#triggerAbort = new AbortController();
			applyElementProps(triggerElement, this.#menu.triggerProps, { signal: this.#triggerAbort.signal });
		}
	}
	#cleanupTrigger() {
		if (this.#currentTrigger) applyElementProps(this.#currentTrigger, {
			"aria-expanded": void 0,
			"aria-haspopup": void 0,
			"aria-controls": void 0
		});
		this.#triggerAbort?.abort();
		this.#triggerAbort = null;
		this.#currentTrigger = null;
	}
};

//#endregion
//#region src/ui/menu/menu-group-controller.ts
var MenuGroupController = class {
	#host;
	#provider;
	#contextValue = { registerLabel: (id) => this.#registerLabel(id) };
	#labelId;
	#appliedLabelId;
	constructor(host) {
		this.#host = host;
		this.#provider = new ContextProvider(host, {
			context: menuGroupContext,
			initialValue: this.#contextValue
		});
	}
	applyProps() {
		const currentLabelledBy = this.#host.getAttribute("aria-labelledby") ?? void 0;
		const hasExplicitLabelledBy = currentLabelledBy !== void 0 && currentLabelledBy !== this.#appliedLabelId;
		if (this.#host.hasAttribute("aria-label") || hasExplicitLabelledBy) {
			if (this.#appliedLabelId && currentLabelledBy === this.#appliedLabelId) this.#host.removeAttribute("aria-labelledby");
			this.#appliedLabelId = void 0;
			applyElementProps(this.#host, { role: "group" });
			return;
		}
		this.#appliedLabelId = this.#labelId;
		applyElementProps(this.#host, {
			role: "group",
			"aria-labelledby": this.#labelId
		});
	}
	#registerLabel(id) {
		this.#labelId = id;
		this.#provider.setValue(this.#contextValue);
		this.#host.requestUpdate();
		return () => {
			if (this.#labelId !== id) return;
			this.#labelId = void 0;
			this.#host.requestUpdate();
		};
	}
};

//#endregion
//#region src/ui/menu/menu-group-element.ts
var MenuGroupElement = class extends UIElement {
	static {
		this.tagName = "media-menu-group";
	}
	#group = new MenuGroupController(this);
	update(_changed) {
		super.update(_changed);
		this.#group.applyProps();
	}
};

//#endregion
//#region src/ui/menu/menu-group-label-element.ts
let idCounter = 0;
var MenuGroupLabelElement = class extends UIElement {
	static {
		this.tagName = "media-menu-group-label";
	}
	#groupCtx = new ContextConsumer(this, {
		context: menuGroupContext,
		subscribe: true
	});
	#generatedId = `vjs-menu-group-label-${idCounter++}`;
	#cleanupRegistration = null;
	#registeredId = null;
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#cleanupRegistration?.();
		this.#cleanupRegistration = null;
		this.#registeredId = null;
	}
	update(_changed) {
		super.update(_changed);
		if (!this.id) this.id = this.#generatedId;
		this.#registerLabel();
	}
	#registerLabel() {
		const groupCtx = this.#groupCtx.value;
		if (!groupCtx) {
			this.#cleanupRegistration?.();
			this.#cleanupRegistration = null;
			this.#registeredId = null;
			return;
		}
		if (this.#registeredId === this.id) return;
		this.#cleanupRegistration?.();
		this.#registeredId = this.id;
		this.#cleanupRegistration = groupCtx.registerLabel(this.id);
	}
};

//#endregion
//#region src/ui/menu/menu-item-element.ts
var MenuItemElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.commandfor = void 0;
		this.#ctx = new ContextConsumer(this, {
			context: menuContext,
			subscribe: true
		});
		this.#disconnect = null;
		this.#registeredMenu = null;
		this.#cleanupRegistration = null;
	}
	static {
		this.tagName = "media-menu-item";
	}
	static {
		this.properties = {
			disabled: { type: Boolean },
			commandfor: { type: String }
		};
	}
	#ctx;
	#disconnect;
	#registeredMenu;
	#cleanupRegistration;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#cleanupRegistration?.();
		this.#cleanupRegistration = null;
		this.#registeredMenu = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (!ctx || !this.#disconnect) return;
		if (this.#registeredMenu !== ctx.menu) {
			this.#cleanupRegistration?.();
			this.#registeredMenu = ctx.menu;
			this.#cleanupRegistration = ctx.menu.registerItem(this);
			applyElementProps(this, {
				onClick: (event) => {
					const currentCtx = this.#ctx.value;
					if (!currentCtx || this.#isDisabled()) return;
					const target = this.commandfor;
					if (target) this.#openSubmenu(target);
					else {
						const select = new CustomEvent("select", {
							bubbles: true,
							cancelable: true
						});
						if (!this.dispatchEvent(select)) {
							event.preventDefault();
							return;
						}
						completeMenuItemSelection(currentCtx.menu);
					}
					event.preventDefault();
				},
				onKeyDown: (event) => {
					if (!this.#ctx.value || this.#isDisabled() || event.key !== "ArrowRight") return;
					const target = this.commandfor;
					if (!target) return;
					this.#openSubmenu(target);
					event.preventDefault();
				},
				onPointerenter: () => {
					const currentCtx = this.#ctx.value;
					if (!this.#isDisabled()) currentCtx?.menu.highlight(this, {
						focus: false,
						pointer: true
					});
				}
			}, { signal: this.#disconnect.signal });
		}
		const hasSubmenu = Boolean(this.commandfor);
		applyElementProps(this, {
			role: "menuitem",
			"aria-disabled": this.#isDisabled() ? "true" : void 0,
			...hasSubmenu && {
				"aria-haspopup": "menu",
				"aria-expanded": "false",
				"data-has-submenu": ""
			}
		});
	}
	#openSubmenu(id) {
		this.getRootNode().querySelector(`#${CSS.escape(id)}`)?.openMenu?.("click");
	}
	#isDisabled() {
		return this.disabled || this.getAttribute("aria-disabled") === "true";
	}
};

//#endregion
//#region src/ui/menu/menu-item-indicator-element.ts
var MenuItemIndicatorElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.checked = false;
		this.forceMount = false;
	}
	static {
		this.tagName = "media-menu-item-indicator";
	}
	static {
		this.properties = {
			checked: { type: Boolean },
			forceMount: {
				type: Boolean,
				attribute: "force-mount"
			}
		};
	}
	update(_changed) {
		super.update(_changed);
		const hidden = !this.checked && !this.forceMount;
		applyElementProps(this, {
			"aria-hidden": "true",
			hidden
		});
	}
};

//#endregion
//#region src/ui/radio-group/radio-group-element.ts
/** @fires value-change - Fired when the selected value changes. */
var RadioGroupElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.value = "";
		this.#provider = new ContextProvider(this, { context: radioGroupContext });
	}
	static {
		this.properties = { value: { type: String } };
	}
	#provider;
	update(changed) {
		super.update(changed);
		this.#provider.setValue({
			value: this.value,
			onValueChange: (next) => {
				this.value = next;
				this.dispatchEvent(new CustomEvent("value-change", {
					detail: { value: next },
					bubbles: true
				}));
			}
		});
	}
};

//#endregion
//#region src/ui/menu/menu-radio-item-element.ts
var MenuRadioItemElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.value = "";
		this.disabled = false;
		this.#menuCtx = new ContextConsumer(this, {
			context: menuContext,
			subscribe: true
		});
		this.#groupCtx = new ContextConsumer(this, {
			context: radioGroupContext,
			subscribe: true
		});
		this.#disconnect = null;
		this.#registered = false;
		this.#cleanupRegistration = null;
	}
	static {
		this.tagName = "media-menu-radio-item";
	}
	static {
		this.properties = {
			value: { type: String },
			disabled: { type: Boolean }
		};
	}
	#menuCtx;
	#groupCtx;
	#disconnect;
	#registered;
	#cleanupRegistration;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
		this.#registered = false;
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#cleanupRegistration?.();
		this.#cleanupRegistration = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
		this.#registered = false;
	}
	update(_changed) {
		super.update(_changed);
		const menuCtx = this.#menuCtx.value;
		const groupCtx = this.#groupCtx.value;
		if (!menuCtx || !groupCtx || !this.#disconnect) return;
		if (!this.#registered) {
			this.#registered = true;
			this.#cleanupRegistration = menuCtx.menu.registerItem(this);
			applyElementProps(this, {
				onClick: () => {
					const currentMenuCtx = this.#menuCtx.value;
					const currentGroupCtx = this.#groupCtx.value;
					if (!currentMenuCtx || !currentGroupCtx || this.disabled) return;
					currentGroupCtx.onValueChange(this.value);
					completeMenuItemSelection(currentMenuCtx.menu);
				},
				onPointerenter: () => {
					const currentMenuCtx = this.#menuCtx.value;
					if (!this.disabled) currentMenuCtx?.menu.highlight(this, {
						focus: false,
						pointer: true
					});
				}
			}, { signal: this.#disconnect.signal });
		}
		const checked = groupCtx.value === this.value;
		applyElementProps(this, {
			role: "menuitemradio",
			"aria-checked": String(checked),
			"aria-disabled": this.disabled ? "true" : void 0
		});
	}
};

//#endregion
//#region src/ui/menu/menu-radio-group-element.ts
var MenuRadioGroupElement = class extends RadioGroupElement {
	static {
		this.tagName = "media-menu-radio-group";
	}
	#group = new MenuGroupController(this);
	#menu = new ContextConsumer(this, {
		context: menuContext,
		subscribe: true
	});
	#ariaLabel = null;
	#triggerMenu = null;
	#setTriggerState = null;
	disconnectedCallback() {
		this.#clearMenuTriggerState();
		super.disconnectedCallback();
	}
	update(changed) {
		super.update(changed);
		this.#group.applyProps();
	}
	setItemLabel(item, label) {
		const labelPart = item.querySelector("[data-part~=\"label\"]");
		if (labelPart) labelPart.textContent = label;
		else item.textContent = label;
	}
	/** Applies a generated fallback without replacing an author-provided accessible name. */
	applyDefaultAriaLabel(label) {
		if (this.hasAttribute("aria-labelledby")) return;
		const current = this.getAttribute("aria-label");
		if (current !== null && current !== this.#ariaLabel) return;
		this.#ariaLabel = label;
		this.setAttribute("aria-label", label);
	}
	publishMenuTriggerState(disabled, availability) {
		const context = this.#menu.value ?? null;
		if (context?.menu !== this.#triggerMenu) {
			this.#clearMenuTriggerState();
			this.#triggerMenu = context?.menu ?? null;
			this.#setTriggerState = context?.setTriggerState ?? null;
		}
		if (!this.#setTriggerState) return;
		const selectedItem = findElementChild(this, (item) => item instanceof MenuRadioItemElement && item.value === this.value);
		const hint = selectedItem?.querySelector("[data-part~=\"label\"]")?.textContent ?? selectedItem?.textContent?.trim() ?? "";
		this.#setTriggerState({
			hint,
			disabled,
			availability
		});
	}
	#clearMenuTriggerState() {
		this.#setTriggerState?.({
			hint: "",
			disabled: false
		});
		this.#triggerMenu = null;
		this.#setTriggerState = null;
	}
};

//#endregion
//#region src/ui/menu/menu-separator-element.ts
var MenuSeparatorElement = class extends UIElement {
	static {
		this.tagName = "media-menu-separator";
	}
	update(_changed) {
		super.update(_changed);
		applyElementProps(this, { role: "separator" });
	}
};

//#endregion
//#region src/ui/input-indicator/input-indicator-element.ts
var InputIndicatorElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.player = new PlayerController(this, playerContext);
		this.container = new ContextConsumer(this, {
			context: containerContext,
			callback: () => this.#reconnect(),
			subscribe: true
		});
		this.#disconnect = null;
		this.#inputActionUnsubscribe = null;
		this.#visibilityUnsubscribe = null;
		this.#visibilityHandle = null;
		this.#lastGeneration = 0;
		this.#snapshot = null;
	}
	get options() {
		return {};
	}
	#disconnect;
	#inputActionUnsubscribe;
	#visibilityUnsubscribe;
	#visibilityHandle;
	#lastGeneration;
	#snapshot;
	#getVisibilityHandle() {
		return this.#visibilityHandle ??= { close: () => this.core.close() };
	}
	#payloadSnapshot() {
		return this.#snapshot ?? this.core.state.current;
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#snapshot = this.core.state.current;
		this.#disconnect = new AbortController();
		this.core.state.subscribe(() => this.requestUpdate(), { signal: this.#disconnect.signal });
		this.transition.state.subscribe(() => this.requestUpdate(), { signal: this.#disconnect.signal });
		this.hidden = true;
		this.#reconnect();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#inputActionUnsubscribe?.();
		this.#visibilityUnsubscribe?.();
		this.#inputActionUnsubscribe = null;
		this.#visibilityUnsubscribe = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	destroyCallback() {
		this.#inputActionUnsubscribe?.();
		this.#visibilityUnsubscribe?.();
		this.core.destroy();
		this.transition.destroy();
		this.liveIndicator.remove();
		super.destroyCallback();
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.syncCoreProps();
	}
	update(changed) {
		super.update(changed);
		this.#syncTransition();
		const currentState = this.core.state.current;
		const transitionState = this.transition.state.current;
		if (!isIndicatorPresent(currentState, transitionState)) {
			this.liveIndicator.remove();
			return;
		}
		const state = getRenderedIndicatorState(currentState, this.#payloadSnapshot(), transitionState);
		this.liveIndicator.render(state);
	}
	#syncTransition() {
		const currentState = this.core.state.current;
		if (currentState.open) {
			this.#snapshot = currentState;
			if (this.#lastGeneration !== currentState.generation) {
				this.#lastGeneration = currentState.generation;
				const transitionState = this.transition.state.current;
				if (!transitionState.active || this.options.replayOnUpdate !== false) this.transition.open(this.liveIndicator.element);
				else if (transitionState.status === "ending") this.transition.cancel();
			}
			return;
		}
		const { active, status } = this.transition.state.current;
		if (active && status !== "ending") this.transition.close(this.liveIndicator.element);
	}
	#reconnect() {
		if (!this.container) return;
		this.#inputActionUnsubscribe?.();
		this.#visibilityUnsubscribe?.();
		this.#inputActionUnsubscribe = null;
		this.#visibilityUnsubscribe = null;
		const container = this.container.value?.container;
		if (!container) return;
		const visibility = getIndicatorVisibilityCoordinator(container);
		const visibilityHandle = this.#getVisibilityHandle();
		this.#visibilityUnsubscribe = visibility.register(visibilityHandle);
		this.#inputActionUnsubscribe = subscribeToInputActions(container, (event) => {
			if (this.core.processEvent(event, getMediaSnapshot(this.player.value))) visibility.show(visibilityHandle);
		});
	}
};

//#endregion
//#region src/ui/input-indicator/live-indicator.ts
var LiveIndicator = class {
	#host;
	#dataAttrs;
	#render;
	constructor(options) {
		this.#host = options.host;
		this.#dataAttrs = options.dataAttrs;
		this.#render = options.render;
	}
	get element() {
		return this.#host;
	}
	render(state) {
		this.#host.hidden = false;
		applyStateDataAttrs(this.#host, state, this.#dataAttrs);
		this.#render(this.#host, state);
		return this.#host;
	}
	remove() {
		this.#host.hidden = true;
		for (const key in this.#dataAttrs) {
			const name = this.#dataAttrs[key];
			if (name) this.#host.removeAttribute(name);
		}
	}
};

//#endregion
//#region src/ui/seek-indicator/seek-indicator-element.ts
var SeekIndicatorElement = class extends InputIndicatorElement {
	static {
		this.tagName = "media-seek-indicator";
	}
	static {
		this.properties = { closeDelay: {
			type: Number,
			attribute: "close-delay"
		} };
	}
	#core = new SeekIndicatorCore();
	#transition = createTransition();
	#liveIndicator = new LiveIndicator({
		host: this,
		dataAttrs: SeekIndicatorDataAttrs,
		render: renderSeekIndicator
	});
	get core() {
		return this.#core;
	}
	get transition() {
		return this.#transition;
	}
	get liveIndicator() {
		return this.#liveIndicator;
	}
	syncCoreProps() {
		this.#core.setProps({ closeDelay: this.closeDelay });
	}
};
function renderSeekIndicator(element, state) {
	const value = element.querySelector("media-seek-indicator-value");
	if (!value) return;
	value.textContent = getSeekIndicatorDisplayValue(state);
}

//#endregion
//#region src/ui/seek-indicator/seek-indicator-value-element.ts
var SeekIndicatorValueElement = class extends UIElement {
	static {
		this.tagName = "media-seek-indicator-value";
	}
};

//#endregion
//#region src/ui/slider/context.ts
const SLIDER_CONTEXT_KEY = Symbol("@videojs/slider");
const sliderContext = createContext(SLIDER_CONTEXT_KEY);

//#endregion
//#region src/ui/slider/slider-buffer-element.ts
var SliderBufferElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: sliderContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-slider-buffer";
	}
};

//#endregion
//#region src/ui/slider/slider-fill-element.ts
var SliderFillElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: sliderContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-slider-fill";
	}
};

//#endregion
//#region src/ui/slider/slider-preview-element.ts
var SliderPreviewElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.overflow = "clamp";
		this.#ctx = new ContextConsumer(this, {
			context: sliderContext,
			subscribe: true
		});
		this.#stopObservingResize = null;
		this.#width = 0;
	}
	static {
		this.tagName = "media-slider-preview";
	}
	static {
		this.properties = { overflow: { type: String } };
	}
	#ctx;
	#stopObservingResize;
	#width;
	connectedCallback() {
		super.connectedCallback();
		this.#stopObservingResize = observeResize(this, ([entry]) => {
			this.#width = entry.contentRect.width;
			this.#applyPosition();
		});
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#stopObservingResize?.();
		this.#stopObservingResize = null;
	}
	#applyPosition() {
		applyStyles(this, getSliderPreviewStyle(this.#width, this.overflow));
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (ctx) applyStateDataAttrs(this, ctx.state, ctx.stateAttrMap);
		this.#applyPosition();
	}
};

//#endregion
//#region src/ui/slider/slider-thumb-element.ts
var SliderThumbElement = class extends UIElement {
	static {
		this.tagName = "media-slider-thumb";
	}
	#ctx = new ContextConsumer(this, {
		context: sliderContext,
		subscribe: true
	});
	#disconnect = null;
	#thumbPropsApplied = false;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
		this.#thumbPropsApplied = false;
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
		this.#thumbPropsApplied = false;
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (!ctx) return;
		if (!this.#thumbPropsApplied && this.#disconnect) {
			applyElementProps(this, ctx.thumbProps, { signal: this.#disconnect.signal });
			this.#thumbPropsApplied = true;
		}
		applyElementProps(this, ctx.thumbAttrs);
		applyStateDataAttrs(this, ctx.state, ctx.stateAttrMap);
	}
};

//#endregion
//#region src/ui/thumbnail/thumbnail-element.ts
const SHADOW_CSS = `\
:host {
  display: inline-block;
  overflow: hidden;
}
img {
  display: block;
}`;
var ThumbnailElement = class extends UIElement {
	static {
		this.tagName = "media-thumbnail";
	}
	static {
		this.properties = {
			time: { type: Number },
			crossOrigin: {
				type: String,
				attribute: "crossorigin"
			},
			loading: { type: String },
			fetchPriority: {
				type: String,
				attribute: "fetchpriority"
			}
		};
	}
	#core;
	#img;
	#textTracks;
	#thumbnails;
	#externalThumbnails;
	#lastTextTrack;
	#api;
	constructor() {
		super();
		this.time = 0;
		this.#core = new ThumbnailCore();
		this.#img = document.createElement("img");
		this.#textTracks = new PlayerController(this, playerContext, selectTextTrack);
		this.#thumbnails = [];
		this.#api = null;
		const shadow = this.attachShadow({ mode: "open" });
		const style = document.createElement("style");
		style.textContent = SHADOW_CSS;
		shadow.appendChild(style);
		this.#img.alt = "";
		this.#img.setAttribute("part", "img");
		this.#img.setAttribute("aria-hidden", "true");
		this.#img.setAttribute("decoding", "async");
		shadow.appendChild(this.#img);
	}
	/**
	* Set thumbnail images directly, bypassing the automatic `<track>` detection. When set, this takes priority over the
	* text track path.
	*/
	get thumbnails() {
		return this.#externalThumbnails;
	}
	set thumbnails(value) {
		this.#externalThumbnails = value;
		this.requestUpdate();
	}
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#api = createThumbnail({
			getContainer: () => this,
			getImg: () => this.#img,
			onStateChange: () => this.requestUpdate()
		});
	}
	disconnectedCallback() {
		super.disconnectedCallback();
	}
	destroyCallback() {
		this.#api?.destroy();
		super.destroyCallback();
	}
	update(changed) {
		super.update(changed);
		const textTrack = this.#textTracks.value;
		if (this.#externalThumbnails) this.#thumbnails = this.#externalThumbnails;
		else if (textTrack !== this.#lastTextTrack) {
			this.#lastTextTrack = textTrack;
			this.#thumbnails = textTrack && textTrack.thumbnailCues.length > 0 ? mapCuesToThumbnails(textTrack.thumbnailCues, textTrack.thumbnailTrackSrc ?? void 0) : [];
		}
		const thumbnail = this.#core.findActiveThumbnail(this.#thumbnails, this.time);
		applyElementProps(this.#img, {
			crossorigin: this.#resolveCrossOrigin(textTrack),
			loading: this.loading,
			fetchpriority: this.fetchPriority
		});
		this.#api?.updateSrc(thumbnail?.url);
		if (!thumbnail) {
			this.#img.removeAttribute("src");
			this.#resetStyles();
			const state = this.#core.getState(false, false, void 0);
			applyElementProps(this, this.#core.getAttrs(state));
			applyStateDataAttrs(this, state, ThumbnailDataAttrs);
			return;
		}
		if (this.#img.getAttribute("src") !== thumbnail.url) this.#img.src = thumbnail.url;
		const api = this.#api;
		const state = this.#core.getState(api?.loading ?? false, api?.error ?? false, thumbnail);
		applyElementProps(this, this.#core.getAttrs(state));
		applyStateDataAttrs(this, state, ThumbnailDataAttrs);
		if (api?.naturalWidth && api.naturalHeight) {
			const constraints = api.readConstraints();
			const result = this.#core.resize(thumbnail, api.naturalWidth, api.naturalHeight, constraints);
			if (result) this.#applyResize(result);
		}
	}
	/**
	* Leaving `crossOrigin` unset means "follow the media element", so thumbnails keep working on a CORS-enabled player
	* without a skin having to thread an attribute through. `null` opts out and fetches the sprites no-CORS, which is
	* also what removing the attribute produces. A bare `crossorigin` is passed straight through, since the CORS-settings
	* attribute reads it as Anonymous.
	*
	* Only the `<track>` path inherits: `thumbnails` set directly may point at a host that has nothing to do with the
	* media element.
	*/
	#resolveCrossOrigin(textTrack) {
		if (isNull(this.crossOrigin)) return void 0;
		if (!isUndefined(this.crossOrigin)) return this.crossOrigin;
		if (this.#externalThumbnails) return void 0;
		return textTrack?.thumbnailTrackCrossOrigin ?? void 0;
	}
	#applyResize(result) {
		this.style.width = `${result.containerWidth}px`;
		this.style.height = `${result.containerHeight}px`;
		const imgStyle = this.#img.style;
		imgStyle.width = `${result.imageWidth}px`;
		imgStyle.height = `${result.imageHeight}px`;
		imgStyle.maxWidth = "none";
		imgStyle.transform = result.offsetX || result.offsetY ? `translate(-${result.offsetX}px, -${result.offsetY}px)` : "";
	}
	#resetStyles() {
		this.style.width = "";
		this.style.height = "";
		const imgStyle = this.#img.style;
		imgStyle.width = "";
		imgStyle.height = "";
		imgStyle.maxWidth = "";
		imgStyle.transform = "";
	}
};

//#endregion
//#region src/ui/slider/slider-thumbnail-element.ts
var SliderThumbnailElement = class extends ThumbnailElement {
	static {
		this.tagName = "media-slider-thumbnail";
	}
	#ctx = new ContextConsumer(this, {
		context: sliderContext,
		subscribe: true
	});
	update(changed) {
		const ctx = this.#ctx.value;
		if (ctx) this.time = ctx.pointerValue;
		super.update(changed);
	}
};

//#endregion
//#region src/ui/slider/slider-track-element.ts
var SliderTrackElement = class extends ContextPartElement {
	constructor(..._args) {
		super(..._args);
		this.consumer = new ContextConsumer(this, {
			context: sliderContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-slider-track";
	}
};

//#endregion
//#region src/ui/slider/slider-value-element.ts
var SliderValueElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.type = "current";
		this.#ctx = new ContextConsumer(this, {
			context: sliderContext,
			subscribe: true
		});
	}
	static {
		this.tagName = "media-slider-value";
	}
	static {
		this.properties = { type: { type: String } };
	}
	#ctx;
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("aria-live", "off");
	}
	update(_changed) {
		super.update(_changed);
		const ctx = this.#ctx.value;
		if (!ctx) return;
		const value = this.type === "pointer" ? ctx.pointerValue : ctx.state.value;
		this.textContent = ctx.formatValue ? ctx.formatValue(value, this.type) : String(Math.round(value));
		applyStateDataAttrs(this, ctx.state, ctx.stateAttrMap);
	}
};

//#endregion
//#region src/ui/status-announcer/status-announcer-element.ts
var StatusAnnouncerElement = class extends UIElement {
	static {
		this.tagName = "media-status-announcer";
	}
	static {
		this.properties = { closeDelay: {
			type: Number,
			attribute: "close-delay"
		} };
	}
	#i18n = new I18nController(this, i18nContext);
	#core = new StatusAnnouncerCore();
	#storeUnsubscribe = null;
	#player = new ContextConsumer(this, {
		context: playerContext,
		callback: (store) => this.#reconnect(store),
		subscribe: true
	});
	#container = new ContextConsumer(this, {
		context: containerContext,
		subscribe: true
	});
	#disconnect = null;
	#liveText = null;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.setAttribute("role", "status");
		this.#ensureLiveText();
		this.#disconnect = new AbortController();
		this.#core.state.subscribe(() => this.requestUpdate(), { signal: this.#disconnect.signal });
		this.#reconnect();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#storeUnsubscribe?.();
		this.#storeUnsubscribe = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	destroyCallback() {
		this.#storeUnsubscribe?.();
		this.#core.destroy();
		super.destroyCallback();
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.#core.setProps({
			closeDelay: this.closeDelay,
			labels: createStatusAnnouncerLabels(this.#i18n.value, this.#i18n.locale),
			shouldAnnounce: () => shouldAnnounceStatusChange(this.#container.value?.container)
		});
	}
	update(changed) {
		super.update(changed);
		const label = this.#core.state.current.label;
		const liveText = this.#ensureLiveText();
		if (label === null) liveText.replaceChildren();
		else liveText.replaceChildren(document.createTextNode(label));
	}
	#reconnect(store = this.#player.value) {
		this.#storeUnsubscribe?.();
		this.#storeUnsubscribe = null;
		if (!store) {
			this.#core.resetSnapshot();
			return;
		}
		this.#storeUnsubscribe = subscribeToStatusAnnouncer(store, this.#core);
	}
	#ensureLiveText() {
		if (this.#liveText?.isConnected) return this.#liveText;
		const existing = this.querySelector("[data-status-announcer-content]");
		this.#liveText = existing ?? document.createElement("span");
		this.#liveText.setAttribute("data-status-announcer-content", "");
		if (!existing) this.append(this.#liveText);
		return this.#liveText;
	}
};

//#endregion
//#region src/ui/status-indicator/status-indicator-element.ts
var StatusIndicatorElement = class extends InputIndicatorElement {
	static {
		this.tagName = "media-status-indicator";
	}
	static {
		this.properties = {
			actions: { type: String },
			closeDelay: {
				type: Number,
				attribute: "close-delay"
			}
		};
	}
	#i18n = new I18nController(this, i18nContext);
	#core = new StatusIndicatorCore();
	#transition = createTransition();
	#liveIndicator = new LiveIndicator({
		host: this,
		dataAttrs: StatusIndicatorDataAttrs,
		render: renderStatusIndicator
	});
	#options = { replayOnUpdate: false };
	get core() {
		return this.#core;
	}
	get transition() {
		return this.#transition;
	}
	get liveIndicator() {
		return this.#liveIndicator;
	}
	get options() {
		return this.#options;
	}
	syncCoreProps() {
		this.#core.setProps({
			actions: parseActions(this.actions),
			closeDelay: this.closeDelay,
			labels: createInputIndicatorLabels(this.#i18n.value)
		});
	}
};
function parseActions(actions) {
	return actions?.split(/[\s,]+/).filter(Boolean);
}
function renderStatusIndicator(element, state) {
	const value = element.querySelector("media-status-indicator-value");
	if (!value) return;
	value.textContent = getStatusIndicatorDisplayValue(state);
}

//#endregion
//#region src/ui/status-indicator/status-indicator-value-element.ts
var StatusIndicatorValueElement = class extends UIElement {
	static {
		this.tagName = "media-status-indicator-value";
	}
};

//#endregion
//#region src/ui/time-slider/time-slider-element.ts
/**
* @fires drag-start - Fired when a pointer drag starts.
* @fires drag-end - Fired when a pointer drag ends.
*/
var TimeSliderElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.label = "";
		this.changeThrottle = TimeSliderCore.defaultProps.changeThrottle;
		this.step = TimeSliderCore.defaultProps.step;
		this.largeStep = TimeSliderCore.defaultProps.largeStep;
		this.orientation = TimeSliderCore.defaultProps.orientation;
		this.disabled = TimeSliderCore.defaultProps.disabled;
		this.thumbAlignment = TimeSliderCore.defaultProps.thumbAlignment;
		this.pauseOnDrag = TimeSliderCore.defaultProps.pauseOnDrag;
		this.#core = new TimeSliderCore();
		this.#controlsState = new PlayerController(this, playerContext, selectControls);
		this.#provider = new ContextProvider(this, { context: sliderContext });
		this.#timeState = new PlayerController(this, playerContext, selectTime);
		this.#bufferState = new PlayerController(this, playerContext, selectBuffer);
		this.#playbackState = new PlayerController(this, playerContext, selectPlayback);
		this.#i18n = new I18nController(this, i18nContext);
		this.#slider = null;
		this.#disconnect = null;
		this.#releaseControlsLock = null;
	}
	static {
		this.tagName = "media-time-slider";
	}
	static {
		this.properties = {
			label: { type: String },
			changeThrottle: {
				type: Number,
				attribute: "change-throttle"
			},
			step: { type: Number },
			largeStep: {
				type: Number,
				attribute: "large-step"
			},
			orientation: { type: String },
			disabled: { type: Boolean },
			thumbAlignment: {
				type: String,
				attribute: "thumb-alignment"
			},
			pauseOnDrag: {
				type: Boolean,
				attribute: "pause-on-drag"
			}
		};
	}
	#core;
	#controlsState;
	#provider;
	#timeState;
	#bufferState;
	#playbackState;
	#i18n;
	#slider;
	#disconnect;
	#releaseControlsLock;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#disconnect = new AbortController();
		const signal = this.#disconnect.signal;
		this.#slider = createSlider({
			getElement: () => this,
			getThumbElement: () => this.querySelector("media-slider-thumb"),
			getOrientation: () => this.orientation,
			isDisabled: () => this.disabled || !this.#timeState.value,
			getPercent: () => {
				const media = this.#timeState.value;
				if (!media) return 0;
				return this.#core.percentFromValue(media.currentTime);
			},
			getStepPercent: () => this.#core.getStepPercent(),
			getLargeStepPercent: () => this.#core.getLargeStepPercent(),
			onValueCommit: (percent) => {
				const media = this.#timeState.value;
				if (media) media.seek(this.#core.rawValueFromPercent(percent));
			},
			changeThrottle: this.changeThrottle,
			onDragStart: () => {
				this.#releaseControlsLock ??= this.#controlsState.value?.requestControlsLock() ?? null;
				this.#core.startDrag(this.#playbackState.value);
				this.dispatchEvent(new CustomEvent("drag-start", { bubbles: true }));
			},
			onDragEnd: () => {
				this.#releaseControlsVisibilityLock();
				this.#core.endDrag(this.#playbackState.value);
				this.dispatchEvent(new CustomEvent("drag-end", { bubbles: true }));
			},
			adjustPercent: (raw, thumbSize, trackSize) => this.#core.adjustPercentForAlignment(raw, thumbSize, trackSize),
			onResize: () => this.requestUpdate()
		});
		applyElementProps(this, this.#slider.rootProps, { signal });
		applyStyles(this, this.#slider.rootStyle);
		this.#slider.input.subscribe(() => this.requestUpdate(), { signal });
		if (!this.#timeState.value) logMissingFeature(this.localName, this.#timeState.displayName);
	}
	disconnectedCallback() {
		this.#releaseControlsVisibilityLock();
		this.#resumeIfDragPaused();
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	destroyCallback() {
		this.#releaseControlsVisibilityLock();
		this.#resumeIfDragPaused();
		this.#slider?.destroy();
		super.destroyCallback();
	}
	#resumeIfDragPaused() {
		this.#core.endDrag(this.#playbackState.value);
	}
	#releaseControlsVisibilityLock() {
		this.#releaseControlsLock?.();
		this.#releaseControlsLock = null;
	}
	willUpdate(_changed) {
		super.willUpdate(_changed);
		this.#core.setProps({
			label: this.label,
			changeThrottle: this.changeThrottle,
			step: this.step,
			largeStep: this.largeStep,
			orientation: this.orientation,
			disabled: this.disabled,
			thumbAlignment: this.thumbAlignment,
			pauseOnDrag: this.pauseOnDrag
		});
		this.#core.setFormatLocale(this.#i18n.locale);
	}
	update(_changed) {
		super.update(_changed);
		if (!this.#slider) return;
		const time = this.#timeState.value;
		const buffer = this.#bufferState.value;
		if (!time) return;
		this.#core.setInput(this.#slider.input.current);
		const media = {
			...time,
			...buffer ?? {
				buffered: [],
				seekable: []
			}
		};
		this.#core.setMedia(media);
		const state = this.#core.getState();
		const cssVars = getTimeSliderCSSVars(this.#slider.adjustForAlignment(state));
		const thumbAttrs = this.#core.getAttrs(state);
		applyStyles(this, cssVars);
		applyStateDataAttrs(this, state, TimeSliderDataAttrs);
		this.#provider.setValue({
			state,
			stateAttrMap: TimeSliderDataAttrs,
			pointerValue: this.#core.rawValueFromPercent(state.pointerPercent),
			thumbAttrs: {
				...thumbAttrs,
				"aria-label": translateText(thumbAttrs["aria-label"], this.#i18n.value),
				"aria-valuetext": translateText(thumbAttrs["aria-valuetext"], this.#i18n.value, this.#core.getValueTextParams(state))
			},
			thumbProps: this.#slider.thumbProps,
			formatValue: (value) => formatTime(value, state.duration, { locale: this.#i18n.locale })
		});
	}
};

//#endregion
//#region src/ui/time/time-element.ts
var TimeElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.type = TimeCore.defaultProps.type;
		this.negativeSign = TimeCore.defaultProps.negativeSign;
		this.label = "";
		this.toggle = TimeCore.defaultProps.toggle;
		this.#core = new TimeCore();
		this.#state = new PlayerController(this, playerContext, selectTime);
		this.#i18n = new I18nController(this, i18nContext);
		this.#signSpan = document.createElement("span");
		this.#textNode = new Text();
		this.#disconnect = null;
		this.#listening = false;
		this.#activeType = TimeCore.defaultProps.type;
		this.#handleClick = (event) => {
			if (event.defaultPrevented || !this.toggle || !this.#state.value) return;
			this.#toggleType();
		};
		this.#handleKeyDown = (event) => {
			if (event.defaultPrevented || !isInteractiveActivation(event)) return;
			if (!this.toggle || !this.#state.value) return;
			event.preventDefault();
			if (event.repeat) return;
			this.#toggleType();
		};
	}
	static {
		this.tagName = "media-time";
	}
	static {
		this.properties = {
			type: { type: String },
			negativeSign: {
				type: String,
				attribute: "negative-sign"
			},
			label: { type: String },
			toggle: { type: Boolean }
		};
	}
	#core;
	#state;
	#i18n;
	#signSpan;
	#textNode;
	#disconnect;
	#listening;
	#activeType;
	connectedCallback() {
		super.connectedCallback();
		this.#disconnect = new AbortController();
		this.#syncListeners();
		if (!this.#signSpan.parentNode) {
			this.#signSpan.setAttribute("aria-hidden", "true");
			this.#signSpan.hidden = true;
			this.append(this.#signSpan, this.#textNode);
		}
		if (!this.#state.value) logMissingFeature(this.localName, this.#state.displayName);
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
		this.#listening = false;
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		if (changed.has("type") || changed.has("toggle")) this.#activeType = this.type;
	}
	update(changed) {
		super.update(changed);
		if (changed.has("toggle")) this.#syncListeners();
		const media = this.#state.value;
		if (!media) {
			this.#clearAttrs();
			return;
		}
		this.#core.setProps({
			type: this.toggle ? this.#activeType : this.type,
			negativeSign: this.negativeSign,
			label: this.label,
			toggle: this.toggle
		});
		this.#core.setMedia(media);
		this.#core.setFormatLocale(this.#i18n.locale);
		const state = this.#core.getState();
		this.#signSpan.hidden = !state.negative;
		this.#signSpan.textContent = state.negative ? this.negativeSign : "";
		this.#textNode.textContent = state.text;
		const attrs = this.#core.getAttrs(state, this.type);
		const label = translateText(attrs["aria-label"], this.#i18n.value, this.#getLabelParams(state));
		const description = attrs["aria-description"] ? translateText(attrs["aria-description"], this.#i18n.value) : void 0;
		applyElementProps(this, {
			"aria-label": label,
			"aria-description": description,
			role: this.toggle ? attrs.role : "time",
			tabIndex: attrs.tabIndex,
			datetime: this.toggle ? void 0 : state.datetime
		});
		applyStateDataAttrs(this, state, TimeDataAttrs);
	}
	#getLabelParams(state) {
		if (!this.#core.getLabelParams(state)) return void 0;
		const duration = formatTimeAsPhrase(Math.abs(state.seconds), { locale: this.#i18n.locale });
		const text = {
			current: elapsedSuffixText,
			duration: durationSuffixText,
			remaining: remainingSuffixText
		}[state.type];
		return { duration: translateText(text, this.#i18n.value, { duration }) };
	}
	#handleClick;
	#handleKeyDown;
	#toggleType() {
		if (this.type === "current") this.#activeType = this.#activeType === "remaining" ? "current" : "remaining";
		else this.#activeType = this.#activeType === "duration" ? "remaining" : "duration";
		this.requestUpdate();
	}
	#syncListeners() {
		if (!this.toggle || !this.#disconnect || this.#listening) return;
		this.#listening = true;
		applyElementProps(this, {
			onClick: this.#handleClick,
			onKeyDown: this.#handleKeyDown
		}, { signal: this.#disconnect.signal });
	}
	#clearAttrs() {
		applyElementProps(this, {
			"aria-label": void 0,
			"aria-description": void 0,
			role: void 0,
			tabIndex: void 0,
			datetime: void 0,
			"data-type": void 0
		});
	}
};

//#endregion
//#region src/ui/time/time-group-element.ts
var TimeGroupElement = class extends UIElement {
	static {
		this.tagName = "media-time-group";
	}
};

//#endregion
//#region src/ui/time/time-separator-element.ts
var TimeSeparatorElement = class extends UIElement {
	static {
		this.tagName = "media-time-separator";
	}
	connectedCallback() {
		super.connectedCallback();
		this.setAttribute("aria-hidden", "true");
		if (!this.textContent?.trim()) this.textContent = "/";
	}
};

//#endregion
//#region src/ui/tooltip/context.ts
const TOOLTIP_GROUP_CONTEXT_KEY = Symbol("@videojs/tooltip-group");
const tooltipGroupContext = createContext(TOOLTIP_GROUP_CONTEXT_KEY);

//#endregion
//#region src/ui/tooltip/tooltip-label-element.ts
function hasAuthoredContent(host) {
	return Array.from(host.childNodes).some((node) => !!node.textContent?.trim());
}
/** Label region inside `media-tooltip`; parent syncs text from the trigger when linked to a media button. */
var TooltipLabelElement = class TooltipLabelElement extends UIElement {
	static {
		this.tagName = "media-tooltip-label";
	}
	#hasAuthoredContent = false;
	static findIn(host) {
		return host.querySelector(TooltipLabelElement.tagName);
	}
	static create() {
		return document.createElement(TooltipLabelElement.tagName);
	}
	connectedCallback() {
		this.#hasAuthoredContent ||= hasAuthoredContent(this);
		super.connectedCallback();
	}
	setSyncedText(text) {
		if (this.#hasAuthoredContent) return;
		this.textContent = text;
	}
};

//#endregion
//#region src/ui/tooltip/tooltip-shortcut-element.ts
/**
* Shortcut hint inside `media-tooltip`. CSS skins: `class="media-tooltip__kbd"`; Tailwind skins: `class` from
* `popup.tooltipShortcut`.
*/
var TooltipShortcutElement = class TooltipShortcutElement extends UIElement {
	static {
		this.tagName = "media-tooltip-shortcut";
	}
	static findIn(host) {
		return host.querySelector(TooltipShortcutElement.tagName);
	}
	static create() {
		return document.createElement(TooltipShortcutElement.tagName);
	}
	setSyncedShortcut(shortcut) {
		if (shortcut) {
			this.textContent = shortcut;
			this.hidden = false;
		} else {
			this.textContent = "";
			this.hidden = true;
		}
	}
};

//#endregion
//#region src/ui/tooltip/tooltip-element.ts
function isLabelTrigger(el) {
	return "$state" in el;
}
/** @fires open-change - Fired when the tooltip's open state changes. */
var TooltipElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.open = TooltipCore.defaultProps.open;
		this.defaultOpen = TooltipCore.defaultProps.defaultOpen;
		this.side = TooltipCore.defaultProps.side;
		this.align = TooltipCore.defaultProps.align;
		this.delay = TooltipCore.defaultProps.delay;
		this.closeDelay = TooltipCore.defaultProps.closeDelay;
		this.disableHoverablePopup = TooltipCore.defaultProps.disableHoverablePopup;
		this.disabled = TooltipCore.defaultProps.disabled;
		this.sticky = TooltipCore.defaultProps.sticky;
		this.boundary = "container";
		this.trigger = "";
		this.#core = new TooltipCore();
		this.#i18n = new I18nController(this, i18nContext);
		this.#groupConsumer = new ContextConsumer(this, { context: tooltipGroupContext });
		this.#containerCtx = new ContextConsumer(this, {
			context: containerContext,
			subscribe: true
		});
		this.#popupGroupCtx = new ContextConsumer(this, { context: popupGroupContext });
		this.#position = new PositionController(this);
		this.#tooltip = null;
		this.#snapshot = null;
		this.#disconnect = null;
		this.#triggerAbort = null;
		this.#currentTrigger = null;
	}
	static {
		this.tagName = "media-tooltip";
	}
	static {
		this.properties = {
			open: { type: Boolean },
			defaultOpen: {
				type: Boolean,
				attribute: "default-open"
			},
			side: { type: String },
			align: { type: String },
			delay: { type: Number },
			closeDelay: {
				type: Number,
				attribute: "close-delay"
			},
			disableHoverablePopup: {
				type: Boolean,
				attribute: "disable-hoverable-popup"
			},
			disabled: { type: Boolean },
			sticky: { type: Boolean },
			boundary: { type: String },
			trigger: { type: String }
		};
	}
	#core;
	#i18n;
	#groupConsumer;
	#containerCtx;
	#popupGroupCtx;
	#position;
	#tooltip;
	#snapshot;
	#disconnect;
	#triggerAbort;
	#currentTrigger;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.setAttribute(POPUP_HOST_ATTR, "");
		this.#disconnect = new AbortController();
		this.#tooltip = createTooltip({
			transition: createTransition(),
			onOpenChange: (nextOpen, details) => {
				this.open = nextOpen;
				this.dispatchEvent(new CustomEvent("open-change", { detail: {
					open: nextOpen,
					...details
				} }));
			},
			delay: () => this.delay,
			closeDelay: () => this.closeDelay,
			disableHoverablePopup: () => this.disableHoverablePopup,
			disabled: () => this.disabled,
			sticky: () => this.sticky,
			group: () => this.#groupConsumer.value,
			popupGroup: () => this.#popupGroupCtx.value
		});
		this.#tooltip.setPopupElement(this);
		applyElementProps(this, this.#tooltip.popupProps, { signal: this.#disconnect.signal });
		if (this.#snapshot) this.#snapshot.track(this.#tooltip.input);
		else this.#snapshot = new SnapshotController(this, this.#tooltip.input);
	}
	firstUpdated(changed) {
		super.firstUpdated(changed);
		if (this.defaultOpen && !this.open) this.#tooltip?.open();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#cleanupTrigger();
		this.#tooltip?.destroy();
		this.#tooltip = null;
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	close(reason = "imperative-action") {
		this.#tooltip?.close(reason);
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.#core.setProps(this);
		if (this.#tooltip && changed.has("open")) {
			const { active: interactionOpen } = this.#tooltip.input.current;
			if (this.open !== interactionOpen) if (this.open) this.#tooltip.open();
			else this.#tooltip.close();
		}
	}
	update(_changed) {
		super.update(_changed);
		if (!this.#tooltip) return;
		const triggerEl = this.#position.findTrigger(this.trigger);
		this.#syncTrigger(triggerEl);
		if (this.#currentTrigger && isLabelTrigger(this.#currentTrigger)) this.#syncContent(this.#currentTrigger);
		const input = this.#tooltip.input.current;
		this.#core.setInput(input);
		const state = this.#core.getState();
		applyElementProps(this, this.#core.getPopupAttrs(state));
		applyStateDataAttrs(this, state, TooltipDataAttrs);
		if (state.open) tryShowPopover(this);
		else tryHidePopover(this);
		if (!state.open) {
			this.#position.cleanup();
			return;
		}
		this.#position.sync({
			anchorName: this.id,
			position: {
				side: state.side,
				align: state.align
			},
			trigger: this.#currentTrigger,
			boundary: this.boundary,
			container: this.#containerCtx.value?.container ?? null,
			cssVars: TooltipCSSVars,
			onSideChange: (side) => this.setAttribute(TooltipDataAttrs.side, side)
		});
	}
	#syncTrigger(triggerEl) {
		if (triggerEl === this.#currentTrigger) return;
		this.#position.cleanup();
		this.#cleanupTrigger();
		this.#currentTrigger = triggerEl;
		this.#tooltip?.setTriggerElement(triggerEl);
		if (triggerEl && this.#tooltip) {
			this.#triggerAbort = new AbortController();
			applyElementProps(triggerEl, this.#tooltip.triggerProps, { signal: this.#triggerAbort.signal });
			if (isLabelTrigger(triggerEl)) {
				this.#syncContent(triggerEl);
				triggerEl.$state.subscribe(() => this.#syncContent(triggerEl), { signal: this.#triggerAbort.signal });
				listen(triggerEl, HOTKEY_SHORTCUT_CHANGE_EVENT, () => this.#syncContent(triggerEl), { signal: this.#triggerAbort.signal });
			}
		}
	}
	#syncContent(triggerEl) {
		const label = triggerEl.getLabel();
		let resolved = isFunction(triggerEl.getResolvedLabel) ? triggerEl.getResolvedLabel() : void 0;
		if (resolved === void 0 && label) resolved = translateText(label, this.#i18n.value);
		const shortcut = triggerEl.getShortcut?.();
		let labelEl = TooltipLabelElement.findIn(this);
		let shortcutEl = TooltipShortcutElement.findIn(this);
		if (!labelEl && !shortcutEl) {
			if (this.#hostHasAuthoredTooltipContent()) return;
			labelEl = TooltipLabelElement.create();
			shortcutEl = TooltipShortcutElement.create();
			this.replaceChildren(labelEl, shortcutEl);
		}
		labelEl?.setSyncedText(resolved ?? "");
		shortcutEl?.setSyncedShortcut(shortcut);
	}
	#hostHasAuthoredTooltipContent() {
		return Array.from(this.childNodes).some((node) => !!node.textContent?.trim());
	}
	#cleanupTrigger() {
		this.#triggerAbort?.abort();
		this.#triggerAbort = null;
		this.#currentTrigger = null;
	}
};

//#endregion
//#region src/ui/tooltip/tooltip-group-element.ts
var TooltipGroupElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.delay = TooltipGroupCore.defaultProps.delay;
		this.closeDelay = TooltipGroupCore.defaultProps.closeDelay;
		this.timeout = TooltipGroupCore.defaultProps.timeout;
		this.#core = new TooltipGroupCore();
		this.#provider = new ContextProvider(this, {
			context: tooltipGroupContext,
			initialValue: this.#core
		});
	}
	static {
		this.tagName = "media-tooltip-group";
	}
	static {
		this.properties = {
			delay: { type: Number },
			closeDelay: {
				type: Number,
				attribute: "close-delay"
			},
			timeout: { type: Number }
		};
	}
	#core;
	#provider;
	update(_changed) {
		super.update(_changed);
		this.#core.setProps(this);
		this.#provider.setValue(this.#core);
	}
};

//#endregion
//#region src/ui/volume-indicator/volume-indicator-element.ts
var VolumeIndicatorElement = class extends InputIndicatorElement {
	static {
		this.tagName = "media-volume-indicator";
	}
	static {
		this.properties = { closeDelay: {
			type: Number,
			attribute: "close-delay"
		} };
	}
	#i18n = new I18nController(this, i18nContext);
	#core = new VolumeIndicatorCore();
	#transition = createTransition();
	#liveIndicator = new LiveIndicator({
		host: this,
		dataAttrs: VolumeIndicatorDataAttrs,
		render: renderVolumeIndicator
	});
	#options = { replayOnUpdate: false };
	get core() {
		return this.#core;
	}
	get transition() {
		return this.#transition;
	}
	get liveIndicator() {
		return this.#liveIndicator;
	}
	get options() {
		return this.#options;
	}
	syncCoreProps() {
		this.#core.setProps({
			closeDelay: this.closeDelay,
			labels: createInputIndicatorLabels(this.#i18n.value)
		});
	}
};
function renderVolumeIndicator(element, state) {
	const fill = element.querySelector("media-volume-indicator-fill");
	const value = element.querySelector("media-volume-indicator-value");
	if (state.fill) fill?.style.setProperty(VolumeIndicatorCSSVars.fill, state.fill);
	else fill?.style.removeProperty(VolumeIndicatorCSSVars.fill);
	if (value) value.textContent = getVolumeIndicatorDisplayValue(state);
}

//#endregion
//#region src/ui/volume-indicator/volume-indicator-fill-element.ts
var VolumeIndicatorFillElement = class extends UIElement {
	static {
		this.tagName = "media-volume-indicator-fill";
	}
};

//#endregion
//#region src/ui/volume-indicator/volume-indicator-value-element.ts
var VolumeIndicatorValueElement = class extends UIElement {
	static {
		this.tagName = "media-volume-indicator-value";
	}
};

//#endregion
//#region src/ui/volume-slider/volume-slider-element.ts
/**
* @fires drag-start - Fired when a pointer drag starts.
* @fires drag-end - Fired when a pointer drag ends.
*/
var VolumeSliderElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.label = "";
		this.step = VolumeSliderCore.defaultProps.step;
		this.largeStep = VolumeSliderCore.defaultProps.largeStep;
		this.wheelStep = VolumeSliderCore.defaultProps.wheelStep;
		this.orientation = VolumeSliderCore.defaultProps.orientation;
		this.disabled = VolumeSliderCore.defaultProps.disabled;
		this.thumbAlignment = VolumeSliderCore.defaultProps.thumbAlignment;
		this.#core = new VolumeSliderCore();
		this.#controlsState = new PlayerController(this, playerContext, selectControls);
		this.#provider = new ContextProvider(this, { context: sliderContext });
		this.#volumeState = new PlayerController(this, playerContext, selectVolume);
		this.#i18n = new I18nController(this, i18nContext);
		this.#slider = null;
		this.#disconnect = null;
		this.#releaseControlsLock = null;
	}
	static {
		this.tagName = "media-volume-slider";
	}
	static {
		this.properties = {
			label: { type: String },
			step: { type: Number },
			largeStep: {
				type: Number,
				attribute: "large-step"
			},
			wheelStep: {
				type: Number,
				attribute: "wheel-step"
			},
			orientation: { type: String },
			disabled: { type: Boolean },
			thumbAlignment: {
				type: String,
				attribute: "thumb-alignment"
			}
		};
	}
	#core;
	#controlsState;
	#provider;
	#volumeState;
	#i18n;
	#slider;
	#disconnect;
	#releaseControlsLock;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#disconnect = new AbortController();
		const signal = this.#disconnect.signal;
		const isDisabled = () => {
			const volume = this.#volumeState.value;
			return this.disabled || !volume || volume.volumeAvailability !== "available";
		};
		const getPercent = () => (this.#volumeState.value?.volume ?? 0) * 100;
		const getStepPercent = () => this.#core.getStepPercent();
		const setVolume = (percent) => this.#setVolume(percent);
		this.#slider = createSlider({
			getElement: () => this,
			getThumbElement: () => this.querySelector("media-slider-thumb"),
			getOrientation: () => this.orientation,
			isDisabled,
			getPercent,
			getStepPercent,
			getLargeStepPercent: () => this.#core.getLargeStepPercent(),
			onValueChange: setVolume,
			onValueCommit: setVolume,
			onDragStart: () => {
				this.#releaseControlsLock ??= this.#controlsState.value?.requestControlsLock() ?? null;
				this.dispatchEvent(new CustomEvent("drag-start", { bubbles: true }));
			},
			onDragEnd: () => {
				this.#releaseControlsVisibilityLock();
				this.dispatchEvent(new CustomEvent("drag-end", { bubbles: true }));
			},
			adjustPercent: (raw, thumbSize, trackSize) => this.#core.adjustPercentForAlignment(raw, thumbSize, trackSize),
			onResize: () => this.requestUpdate()
		});
		const wheelProps = createWheelStep({
			isDisabled,
			getPercent,
			getStepPercent: () => this.#core.getWheelStepPercent(),
			onValueChange: setVolume
		});
		applyElementProps(this, this.#slider.rootProps, { signal });
		applyElementProps(this, wheelProps, { signal });
		applyStyles(this, this.#slider.rootStyle);
		this.#slider.input.subscribe(() => this.requestUpdate(), { signal });
		if (!this.#volumeState.value) logMissingFeature(this.localName, this.#volumeState.displayName);
	}
	disconnectedCallback() {
		this.#releaseControlsVisibilityLock();
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	destroyCallback() {
		this.#releaseControlsVisibilityLock();
		this.#slider?.destroy();
		super.destroyCallback();
	}
	#releaseControlsVisibilityLock() {
		this.#releaseControlsLock?.();
		this.#releaseControlsLock = null;
	}
	willUpdate(_changed) {
		super.willUpdate(_changed);
		this.#core.setProps(this);
		this.#core.setFormatLocale(this.#i18n.locale);
	}
	update(_changed) {
		super.update(_changed);
		if (!this.#slider) return;
		const media = this.#volumeState.value;
		if (!media) return;
		this.#core.setInput(this.#slider.input.current);
		this.#core.setMedia(media);
		const state = this.#core.getState();
		const cssVars = getSliderCSSVars(this.#slider.adjustForAlignment(state));
		const thumbAttrs = this.#core.getAttrs(state);
		applyStyles(this, cssVars);
		applyStateDataAttrs(this, state, VolumeSliderDataAttrs);
		applyElementProps(this, { hidden: state.hidden ? "" : void 0 });
		this.#provider.setValue({
			state,
			stateAttrMap: VolumeSliderDataAttrs,
			pointerValue: this.#core.valueFromPercent(state.pointerPercent),
			thumbAttrs: {
				...thumbAttrs,
				"aria-label": translateText(thumbAttrs["aria-label"], this.#i18n.value),
				"aria-valuetext": translateText(thumbAttrs["aria-valuetext"], this.#i18n.value, this.#core.getValueTextParams(state))
			},
			thumbProps: this.#slider.thumbProps,
			formatValue: (value) => `${Math.round(value)}%`
		});
	}
	#setVolume(percent) {
		this.#volumeState.value?.setVolume(this.#core.valueFromPercent(percent) / 100);
	}
};

//#endregion
//#region src/registration/ui-compounds.ts
function defineMenu() {
	safeDefine(MenuElement);
	safeDefine(MenuContentElement);
	safeDefine(MenuItemElement);
	safeDefine(MenuGroupLabelElement);
	safeDefine(MenuSeparatorElement);
	safeDefine(MenuGroupElement);
	safeDefine(MenuRadioGroupElement);
	safeDefine(MenuRadioItemElement);
	safeDefine(MenuCheckboxItemElement);
	safeDefine(MenuItemIndicatorElement);
}
function defineControls() {
	safeDefine(ControlsElement);
	safeDefine(ControlsBackdropElement);
	safeDefine(ControlsContentElement);
	safeDefine(ControlsGroupElement);
}
function defineDialogParts() {
	safeDefine(DialogBackdropElement);
	safeDefine(DialogPopupElement);
	safeDefine(DialogCloseElement);
	safeDefine(DialogDescriptionElement);
	safeDefine(DialogTitleElement);
}
function defineErrorDialog() {
	safeDefine(ErrorDialogElement);
	defineDialogParts();
}
function defineInputIndicators() {
	safeDefine(StatusAnnouncerElement);
	safeDefine(StatusIndicatorElement);
	safeDefine(StatusIndicatorValueElement);
	safeDefine(VolumeIndicatorElement);
	safeDefine(VolumeIndicatorFillElement);
	safeDefine(VolumeIndicatorValueElement);
	safeDefine(SeekIndicatorElement);
	safeDefine(SeekIndicatorValueElement);
}
/** Shared slider sub-elements used by all slider types. */
function defineSliderParts() {
	safeDefine(SliderFillElement);
	safeDefine(SliderPreviewElement);
	safeDefine(SliderThumbElement);
	safeDefine(SliderTrackElement);
	safeDefine(SliderValueElement);
}
function defineTime() {
	safeDefine(TimeElement);
	safeDefine(TimeGroupElement);
	safeDefine(TimeSeparatorElement);
}
function defineTimeSlider() {
	safeDefine(TimeSliderElement);
	defineSliderParts();
	safeDefine(SliderBufferElement);
	safeDefine(SliderThumbnailElement);
}
function defineTooltip() {
	safeDefine(TooltipGroupElement);
	safeDefine(TooltipLabelElement);
	safeDefine(TooltipShortcutElement);
	safeDefine(TooltipElement);
}
function defineVolumeSlider() {
	safeDefine(VolumeSliderElement);
	defineSliderParts();
}
function defineSliders() {
	safeDefine(TimeSliderElement);
	safeDefine(VolumeSliderElement);
	defineSliderParts();
	safeDefine(SliderBufferElement);
	safeDefine(SliderThumbnailElement);
}

//#endregion
//#region src/ui/hotkey/aria-key-shortcuts-controller.ts
/** Provides hotkey shortcut metadata for a given hotkey action name. */
var AriaKeyShortcutsController = class {
	#host;
	#action;
	#getValue;
	#container;
	#unsubscribe = null;
	/**
	* @param host - Host element whose nearest player container supplies hotkey registrations.
	* @param action - Registered hotkey action to look up.
	* @param options - Optional value resolver for value-dependent shortcuts.
	*/
	constructor(host, action, options = {}) {
		this.#host = host;
		this.#action = action;
		this.#getValue = options.value;
		this.#container = new ContextConsumer(host, {
			context: containerContext,
			callback: (ctx) => this.#connect(ctx?.container),
			subscribe: true
		});
		host.addController(this);
	}
	get value() {
		return this.aria;
	}
	get aria() {
		return this.details.aria;
	}
	get shortcut() {
		return this.details.shortcut;
	}
	get details() {
		const container = this.#container.value?.container;
		if (!container) return {};
		return getHotkeyCoordinator(container).getShortcut(this.#action, this.#getValue?.());
	}
	hostConnected() {
		this.#connect(this.#container.value?.container);
	}
	hostDisconnected() {
		this.#disconnect();
	}
	#connect(container) {
		this.#disconnect();
		if (!container) return;
		const coordinator = getHotkeyCoordinator(container);
		const notify = () => {
			this.#host.requestUpdate();
		};
		this.#unsubscribe = coordinator.subscribeShortcutChanges(notify);
		notify();
	}
	#disconnect() {
		this.#unsubscribe?.();
		this.#unsubscribe = null;
	}
};

//#endregion
//#region src/ui/media-button-element.ts
function getLabelParams(core, state) {
	return core.getLabelParams?.(state);
}
/** Abstract base for HTML custom elements that render a media-control button. */
var MediaButtonElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.disabled = false;
		this.label = "";
		this.hotkeyAction = void 0;
		this.#disconnect = null;
		this.#hotkeyRegistry = null;
		this.#i18n = new I18nController(this, i18nContext);
	}
	static {
		this.properties = {
			label: { type: String },
			disabled: { type: Boolean }
		};
	}
	getIsButtonDisabled() {
		return this.disabled || !this.mediaState.value;
	}
	handleActivate(event) {
		Promise.resolve(this.activate(this.mediaState.value, event)).catch((error) => {
			console.error(`[${this.localName}]`, error);
		});
	}
	/** Override to match hotkeys that use action values, such as seek steps. */
	get hotkeyValue() {}
	get $state() {
		return this.core.state;
	}
	#disconnect;
	#hotkeyRegistry;
	#lastHotkeyShortcut;
	#i18n;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		if (this.hotkeyAction && !this.#hotkeyRegistry) this.#hotkeyRegistry = new AriaKeyShortcutsController(this, this.hotkeyAction, { value: () => this.hotkeyValue });
		this.#disconnect = new AbortController();
		const buttonProps = createButton({
			onActivate: (event) => this.handleActivate(event),
			isDisabled: () => this.getIsButtonDisabled()
		});
		applyElementProps(this, buttonProps, { signal: this.#disconnect.signal });
		if (!this.mediaState.value && this.mediaState.displayName) logMissingFeature(this.localName, this.mediaState.displayName);
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	/** Returns the button's current label derived from media state. */
	getLabel() {
		return this.core.state.current.label ? resolveText(this.core.state.current.label) : void 0;
	}
	getShortcut() {
		return this.#hotkeyRegistry?.shortcut;
	}
	/** Resolved label for tooltips and other display surfaces. */
	getResolvedLabel() {
		const media = this.mediaState.value;
		if (!media) return void 0;
		this.core.setMedia(media);
		const state = this.core.getState();
		return translateText(this.core.getLabel(state), this.#i18n.value, getLabelParams(this.core, state));
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.core.setProps?.(this);
	}
	update(changed) {
		super.update(changed);
		const media = this.mediaState.value;
		this.#syncHotkeyShortcut();
		if (!media) return;
		this.core.setMedia(media);
		const state = this.core.getState();
		const attrs = this.core.getAttrs?.(state) ?? {};
		if (isText(attrs["aria-label"])) attrs["aria-label"] = translateText(attrs["aria-label"], this.#i18n.value, getLabelParams(this.core, state));
		applyElementProps(this, {
			...attrs,
			"aria-keyshortcuts": this.#hotkeyRegistry?.aria,
			...isHideable(state) && { hidden: state.hidden ? "" : void 0 }
		});
		applyStateDataAttrs(this, state, this.stateAttrMap);
	}
	#syncHotkeyShortcut() {
		const shortcut = this.getShortcut();
		if (shortcut === this.#lastHotkeyShortcut) return;
		this.#lastHotkeyShortcut = shortcut;
		this.dispatchEvent(new CustomEvent(HOTKEY_SHORTCUT_CHANGE_EVENT));
	}
};
/** Whether a button's core reports whether it should be shown at all. */
function isHideable(state) {
	return isObject(state) && isBoolean(state.hidden);
}

//#endregion
//#region src/ui/buffering-indicator/buffering-indicator-element.ts
var BufferingIndicatorElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.delay = BufferingIndicatorCore.defaultProps.delay;
		this.#core = new BufferingIndicatorCore();
		this.#state = new PlayerController(this, playerContext, selectPlayback);
		this.#disconnect = null;
	}
	static {
		this.tagName = "media-buffering-indicator";
	}
	static {
		this.properties = { delay: { type: Number } };
	}
	#core;
	#state;
	#disconnect;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.#disconnect = new AbortController();
		this.#core.state.subscribe(() => this.requestUpdate(), { signal: this.#disconnect.signal });
		if (!this.#state.value) logMissingFeature(this.localName, this.#state.displayName);
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.#core.setProps(this);
	}
	update(changed) {
		super.update(changed);
		const media = this.#state.value;
		if (!media) return;
		this.#core.update(media);
		applyStateDataAttrs(this, this.#core.state.current, BufferingIndicatorDataAttrs);
	}
};

//#endregion
//#region src/ui/hotkey/hotkey-element.ts
var HotkeyElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.keys = "";
		this.action = "";
		this.value = void 0;
		this.disabled = false;
		this.target = "player";
		this.#player = new PlayerController(this, playerContext);
		this.#container = new ContextConsumer(this, {
			context: containerContext,
			callback: () => this.requestUpdate(),
			subscribe: true
		});
		this.#cleanup = null;
	}
	static {
		this.tagName = "media-hotkey";
	}
	static {
		this.properties = {
			keys: { type: String },
			action: { type: String },
			value: { type: Number },
			disabled: { type: Boolean },
			target: { type: String }
		};
	}
	#player;
	#container;
	#cleanup;
	connectedCallback() {
		super.connectedCallback();
		this.style.display = "none";
		this.#register();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#unregister();
	}
	update(changed) {
		super.update(changed);
		if (this.isConnected) {
			this.#unregister();
			this.#register();
		}
	}
	#register() {
		const store = this.#player.value;
		const container = this.#container.value?.container;
		if (!this.keys || !this.action || !store || !container) return;
		const resolver = resolveHotkeyAction(this.action);
		if (!resolver) return;
		const { value, action } = this;
		this.#cleanup = createHotkey(container, {
			keys: this.keys,
			action,
			value,
			target: this.target,
			disabled: this.disabled,
			repeatable: !isHotkeyToggleAction(action),
			onActivate: (_event, key) => {
				resolver({
					store,
					key,
					value
				});
			}
		});
	}
	#unregister() {
		this.#cleanup?.();
		this.#cleanup = null;
	}
};

//#endregion
//#region src/ui/mute-button/mute-button-element.ts
var MuteButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new MuteButtonCore();
		this.stateAttrMap = MuteButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectVolume);
		this.hotkeyAction = "toggleMuted";
	}
	static {
		this.tagName = "media-mute-button";
	}
	activate(state) {
		this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/play-button/play-button-element.ts
var PlayButtonElement = class extends MediaButtonElement {
	constructor(..._args) {
		super(..._args);
		this.core = new PlayButtonCore();
		this.stateAttrMap = PlayButtonDataAttrs;
		this.mediaState = new PlayerController(this, playerContext, selectPlayback);
		this.hotkeyAction = "togglePaused";
	}
	static {
		this.tagName = "media-play-button";
	}
	activate(state) {
		this.core.toggle(state);
	}
};

//#endregion
//#region src/ui/popover/popover-element.ts
/** @fires open-change - Fired when the popover's open state changes. */
var PopoverElement = class extends UIElement {
	constructor(..._args) {
		super(..._args);
		this.open = PopoverCore.defaultProps.open;
		this.defaultOpen = PopoverCore.defaultProps.defaultOpen;
		this.side = PopoverCore.defaultProps.side;
		this.align = PopoverCore.defaultProps.align;
		this.modal = PopoverCore.defaultProps.modal;
		this.closeOnEscape = PopoverCore.defaultProps.closeOnEscape;
		this.closeOnOutsideClick = PopoverCore.defaultProps.closeOnOutsideClick;
		this.openOnHover = PopoverCore.defaultProps.openOnHover;
		this.delay = PopoverCore.defaultProps.delay;
		this.closeDelay = PopoverCore.defaultProps.closeDelay;
		this.boundary = "container";
		this.#core = new PopoverCore();
		this.#containerCtx = new ContextConsumer(this, {
			context: containerContext,
			subscribe: true
		});
		this.#popupGroupCtx = new ContextConsumer(this, { context: popupGroupContext });
		this.#position = new PositionController(this);
		this.#popover = null;
		this.#snapshot = null;
		this.#disconnect = null;
		this.#triggerAbort = null;
		this.#currentTrigger = null;
	}
	static {
		this.tagName = "media-popover";
	}
	static {
		this.properties = {
			open: { type: Boolean },
			defaultOpen: {
				type: Boolean,
				attribute: "default-open"
			},
			side: { type: String },
			align: { type: String },
			modal: { type: Boolean },
			closeOnEscape: {
				type: Boolean,
				attribute: "close-on-escape"
			},
			closeOnOutsideClick: {
				type: Boolean,
				attribute: "close-on-outside-click"
			},
			openOnHover: {
				type: Boolean,
				attribute: "open-on-hover"
			},
			delay: { type: Number },
			closeDelay: {
				type: Number,
				attribute: "close-delay"
			},
			boundary: { type: String }
		};
	}
	#core;
	#containerCtx;
	#popupGroupCtx;
	#position;
	#popover;
	#snapshot;
	#disconnect;
	#triggerAbort;
	#currentTrigger;
	connectedCallback() {
		super.connectedCallback();
		if (this.destroyed) return;
		this.setAttribute(POPUP_HOST_ATTR, "");
		this.#disconnect = new AbortController();
		this.#popover = createPopover({
			transition: createTransition(),
			onOpenChange: (nextOpen, details) => {
				this.open = nextOpen;
				this.dispatchEvent(new CustomEvent("open-change", { detail: {
					open: nextOpen,
					...details
				} }));
			},
			closeOnEscape: () => this.closeOnEscape,
			closeOnOutsideClick: () => this.closeOnOutsideClick,
			openOnHover: () => this.openOnHover,
			delay: () => this.delay,
			closeDelay: () => this.closeDelay,
			group: () => this.#popupGroupCtx.value
		});
		this.#popover.setPopupElement(this);
		applyElementProps(this, this.#popover.popupProps, { signal: this.#disconnect.signal });
		if (this.#snapshot) this.#snapshot.track(this.#popover.input);
		else this.#snapshot = new SnapshotController(this, this.#popover.input);
	}
	firstUpdated(changed) {
		super.firstUpdated(changed);
		if (this.defaultOpen && !this.open) this.#popover?.open();
	}
	disconnectedCallback() {
		super.disconnectedCallback();
		this.#disconnect?.abort();
		this.#disconnect = null;
	}
	destroyCallback() {
		this.#cleanupTrigger();
		this.#popover?.destroy();
		super.destroyCallback();
	}
	close(reason = "imperative-action") {
		this.#popover?.close(reason);
	}
	get triggerElement() {
		return this.#currentTrigger;
	}
	willUpdate(changed) {
		super.willUpdate(changed);
		this.#core.setProps(this);
		if (this.#popover && changed.has("open")) {
			const { active: interactionOpen } = this.#popover.input.current;
			if (this.open !== interactionOpen) if (this.open) this.#popover.open();
			else this.#popover.close();
		}
	}
	update(_changed) {
		super.update(_changed);
		if (!this.#popover) return;
		const triggerEl = this.#position.findTrigger();
		this.#syncTrigger(triggerEl);
		const input = this.#popover.input.current;
		this.#core.setInput(input);
		const state = this.#core.getState();
		applyElementProps(this, this.#core.getPopupAttrs(state));
		applyStateDataAttrs(this, state, PopoverDataAttrs);
		if (state.open) tryShowPopover(this);
		else tryHidePopover(this);
		if (this.#currentTrigger) applyElementProps(this.#currentTrigger, this.#core.getTriggerAttrs(state, this.id));
		if (!state.open) {
			this.#position.cleanup();
			return;
		}
		this.#position.sync({
			anchorName: this.id,
			position: {
				side: state.side,
				align: state.align
			},
			trigger: this.#currentTrigger,
			boundary: this.boundary,
			container: this.#containerCtx.value?.container ?? null,
			onSideChange: (side) => this.setAttribute(PopoverDataAttrs.side, side)
		});
	}
	#syncTrigger(triggerEl) {
		if (triggerEl === this.#currentTrigger) return;
		this.#position.cleanup();
		this.#cleanupTrigger();
		this.#currentTrigger = triggerEl;
		this.#popover?.setTriggerElement(triggerEl);
		if (triggerEl && this.#popover) {
			this.#triggerAbort = new AbortController();
			applyElementProps(triggerEl, this.#popover.triggerProps, { signal: this.#triggerAbort.signal });
		}
	}
	#cleanupTrigger() {
		if (this.#currentTrigger) applyElementProps(this.#currentTrigger, {
			"aria-expanded": void 0,
			"aria-haspopup": void 0,
			"aria-controls": void 0
		});
		this.#triggerAbort?.abort();
		this.#triggerAbort = null;
		this.#currentTrigger = null;
	}
};

//#endregion
export { MEDIA_INPUT_ACTION_OVERRIDES as A, resolveLabel as C, findLastIndexAtOrBefore as D, applyElementProps as E, defaults as F, createTemplate as M, getTemplateElement as N, toPercent as O, getTemplateRoot as P, exitText as S, logMissingFeature as T, MenuRadioGroupElement as _, BufferingIndicatorElement as a, rateText as b, defineErrorDialog as c, defineSliders as d, defineTime as f, sliderContext as g, defineVolumeSlider as h, HotkeyElement as i, cloneTemplateRoot as j, createButton as k, defineInputIndicators as l, defineTooltip as m, PlayButtonElement as n, MediaButtonElement as o, defineTimeSlider as p, MuteButtonElement as r, defineControls as s, PopoverElement as t, defineMenu as u, MenuRadioItemElement as v, SkinElement as w, enterText as x, MenuItemIndicatorElement as y };
//# sourceMappingURL=popover-element-DYgnWFFt.js.map