import { o as pick, r as serializeAttributes, t as namedNodeMapToObject } from "./attributes-C0ssqa1e.js";
import { n as kebabCase } from "./casing-Cu0fL85w.js";

//#region ../utils/dist/object/omit.js
/**
* Creates a new object without the specified keys.
*
* @example
*   const obj = { a: 1, b: 2, c: 3 };
*   omit(obj, ['b']); // { a: 1, c: 3 }
*/
function omit(obj, keys) {
	const result = {};
	for (const key in obj) if (!keys.includes(key)) result[key] = obj[key];
	return result;
}

//#endregion
//#region ../media/dist/dev/dom/custom-media-element/custom-media-element.js
/** CSS custom property names for video elements. */
const VideoCSSVars = {
	/** Border radius of the video element. */
	borderRadius: "--media-video-border-radius",
	/** Object fit for the video. */
	objectFit: "--media-object-fit",
	/** Object position for the video. */
	objectPosition: "--media-object-position",
	/** Duration of the caption track transition. */
	captionTrackDuration: "--media-caption-track-duration",
	/** Delay before the caption track transition. */
	captionTrackDelay: "--media-caption-track-delay",
	/** Vertical offset of the caption track. */
	captionTrackY: "--media-caption-track-y"
};
/** Helper function to generate the HTML template for video elements. */
function getVideoTemplateHTML(attrs) {
	return `
    <style>
      :host {
        display: contents;
      }

      video {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: var(${VideoCSSVars.borderRadius});
        object-fit: var(${VideoCSSVars.objectFit}, contain);
        object-position: var(${VideoCSSVars.objectPosition}, center);
      }

      video::-webkit-media-text-track-container {
        transition: translate var(${VideoCSSVars.captionTrackDuration}, 0) ease-out;
        transition-delay: var(${VideoCSSVars.captionTrackDelay}, 0);
        translate: 0 var(${VideoCSSVars.captionTrackY}, 0);
        scale: 0.98;
        z-index: 1;
        font-family: inherit;
      }
    </style>
    <slot name="media">
      <video${serializeAttributes(attrs)}></video>
    </slot>
    <slot></slot>
  `;
}
/** Helper function to generate the HTML template for other elements. */
function getCommonTemplateHTML(tag) {
	return (attrs) => {
		return `
      <style>
        :host {
          display: inline-flex;
          line-height: 0;
          flex-direction: column;
          justify-content: end;
        }

        ${tag} {
          width: 100%;
        }
      </style>
      <slot name="media">
        <${tag}${serializeAttributes(attrs)}></${tag}>
      </slot>
      <slot></slot>
    `;
	};
}
const excludedProperties = [
	"attach",
	"detach",
	"destroy"
];
function CustomMediaElement(tag, MediaHost) {
	const syncTargetAttributes = tag !== "iframe";
	const mediaHostAttrToProp = /* @__PURE__ */ new Map();
	let isDefined = false;
	class CustomMedia extends (globalThis.HTMLElement ?? class {}) {
		static getTemplateHTML = tag.endsWith("video") ? getVideoTemplateHTML : getCommonTemplateHTML(tag);
		static shadowRootOptions = { mode: "open" };
		static properties = {
			autoPictureInPicture: { type: Boolean },
			autoplay: { type: Boolean },
			controls: { type: Boolean },
			controlsList: { type: String },
			crossOrigin: { type: String },
			defaultMuted: {
				type: Boolean,
				attribute: "muted"
			},
			disablePictureInPicture: { type: Boolean },
			disableRemotePlayback: { type: Boolean },
			loading: { type: String },
			loop: { type: Boolean },
			playsInline: { type: Boolean },
			poster: {
				type: String,
				empty: ""
			},
			preload: {
				type: String,
				empty: null
			},
			src: {
				type: String,
				empty: ""
			},
			streamType: {
				type: String,
				attribute: "stream-type",
				empty: "unknown"
			}
		};
		static get observedAttributes() {
			CustomMedia.#define(this);
			return [...getAttrsFromProps(this.properties)];
		}
		static #define(ctor) {
			if (isDefined) return;
			isDefined = true;
			const properties = ctor.properties;
			for (let proto = MediaHost.prototype; proto && proto !== Object.prototype; proto = Object.getPrototypeOf(proto)) for (const prop of Object.getOwnPropertyNames(proto)) {
				if (prop in CustomMedia.prototype || excludedProperties.includes(prop)) continue;
				const propConfig = properties[prop];
				if (propConfig && (propConfig.attribute ?? prop.toLowerCase()) !== kebabCase(prop)) continue;
				const descriptor = Object.getOwnPropertyDescriptor(proto, prop);
				if (!descriptor) continue;
				const config = {
					enumerable: true,
					configurable: true
				};
				if (typeof descriptor.value === "function") config.value = function(...args) {
					return this.#mediaHost[prop](...args);
				};
				else if (descriptor.get) {
					config.get = function() {
						return this.#mediaHost[prop];
					};
					if (descriptor.set) {
						const attr = kebabCase(prop);
						if (ctor.observedAttributes.includes(attr)) {
							mediaHostAttrToProp.set(attr, prop);
							config.set = function(val) {
								if (val === true || val === false || val == null) this.toggleAttribute(attr, Boolean(val));
								else this.setAttribute(attr, String(val));
							};
						} else config.set = function(val) {
							this.#mediaHost[prop] = val;
						};
					}
				}
				Object.defineProperty(CustomMedia.prototype, prop, config);
			}
			for (const [prop, { type, attribute }] of Object.entries(properties)) {
				if (prop in CustomMedia.prototype) continue;
				const attr = attribute ?? prop.toLowerCase();
				Object.defineProperty(CustomMedia.prototype, prop, {
					get: function() {
						return type === Boolean ? this.hasAttribute(attr) : this.getAttribute(attr);
					},
					set: function(val) {
						if (type === Boolean) this.toggleAttribute(attr, Boolean(val));
						else this.setAttribute(attr, val);
					},
					enumerable: true,
					configurable: true
				});
			}
		}
		#mediaHost;
		#bridgedEventTypes = /* @__PURE__ */ new Set();
		#childMap = /* @__PURE__ */ new Map();
		#childObserver;
		constructor() {
			super();
			if (!this.shadowRoot) {
				const ctor = this.constructor;
				this.attachShadow(ctor.shadowRootOptions);
				const allowedKeys = getAttrsFromProps(ctor.properties);
				const disallowedKeys = [...mediaHostAttrToProp.keys()];
				const pickedAttrs = pick(namedNodeMapToObject(this.attributes), allowedKeys);
				const attrs = syncTargetAttributes ? omit(pickedAttrs, disallowedKeys) : pickedAttrs;
				if (tag && !attrs.part) attrs.part = tag;
				this.shadowRoot.innerHTML = ctor.getTemplateHTML(attrs);
			}
			this.#mediaHost = new MediaHost();
			this.#attachToTarget();
			this.#childObserver = new MutationObserver(this.#syncMediaChildAttribute.bind(this));
			this.shadowRoot.addEventListener("slotchange", () => {
				this.#attachToTarget();
				this.#syncMediaChildren();
			});
			this.#syncMediaChildren();
		}
		#attachToTarget() {
			const target = this.target;
			if (target === this.#mediaHost.target) return;
			if (this.#mediaHost.target) this.#mediaHost.detach();
			this.#mediaHost.attach(target);
		}
		get host() {
			return this.#mediaHost;
		}
		get target() {
			return this.querySelector(":scope > [slot=media]") ?? this.querySelector(tag) ?? this.shadowRoot?.querySelector(tag) ?? null;
		}
		connectedCallback() {
			if (tag !== "iframe") return;
			if (!this.hasAttribute("data-cross-origin-frame")) this.setAttribute("data-cross-origin-frame", "");
		}
		disconnectedCallback() {
			if (this.hasAttribute("keep-alive")) return;
			queueMicrotask(() => {
				if (!this.isConnected) this.#mediaHost.destroy();
			});
		}
		addEventListener(type, listener, options) {
			super.addEventListener(type, listener, options);
			if (!this.#bridgedEventTypes.has(type)) {
				this.#bridgedEventTypes.add(type);
				this.#mediaHost.addEventListener(type, this.#bridgeEvent);
			}
		}
		removeEventListener(type, listener, options) {
			super.removeEventListener(type, listener, options);
		}
		#bridgeEvent = (event) => {
			if (!event.composed) this.dispatchEvent(new event.constructor(event.type, event));
		};
		attributeChangedCallback(attrName, oldValue, newValue) {
			const prop = mediaHostAttrToProp.get(attrName);
			if (prop) {
				if (oldValue !== newValue) {
					const valueType = typeof this.#mediaHost[prop];
					const propConfig = this.constructor.properties[prop];
					const emptyValue = propConfig && "empty" in propConfig ? propConfig.empty : "";
					this.#mediaHost[prop] = valueType === "boolean" ? newValue !== null : valueType === "number" ? Number(newValue) : newValue ?? emptyValue;
				}
				return;
			}
			if (!CustomMedia.observedAttributes.includes(attrName) && this.constructor.observedAttributes.includes(attrName)) return;
			if (!syncTargetAttributes) return;
			if (newValue === null) this.target?.removeAttribute(attrName);
			else if (this.target?.getAttribute(attrName) !== newValue) this.target?.setAttribute(attrName, newValue);
		}
		#syncMediaChildren() {
			if (tag === "iframe") return;
			const defaultSlot = this.shadowRoot?.querySelector("slot:not([name])");
			const mediaChildren = new Set(defaultSlot?.assignedElements({ flatten: true }).filter((el) => el.localName === "track" || el.localName === "source"));
			for (const [el, clone] of this.#childMap) if (!mediaChildren.has(el)) {
				clone.remove();
				this.#childMap.delete(el);
			}
			for (const el of mediaChildren) {
				let clone = this.#childMap.get(el);
				if (!clone) {
					clone = el.cloneNode();
					this.#childMap.set(el, clone);
					this.#childObserver?.observe(el, { attributes: true });
				}
				this.target?.append(clone);
				this.#enableDefaultTrack(clone);
			}
		}
		#syncMediaChildAttribute(mutations) {
			for (const mutation of mutations) if (mutation.type === "attributes") {
				const { target, attributeName } = mutation;
				const clone = this.#childMap.get(target);
				if (clone && attributeName) {
					clone.setAttribute(attributeName, target.getAttribute(attributeName) ?? "");
					this.#enableDefaultTrack(clone);
				}
			}
		}
		#enableDefaultTrack(trackEl) {
			if (trackEl && trackEl.localName === "track" && trackEl.default && (trackEl.kind === "chapters" || trackEl.kind === "metadata") && trackEl.track.mode === "disabled") trackEl.track.mode = "hidden";
		}
	}
	return CustomMedia;
}
function getAttrsFromProps(props) {
	return Object.keys(props).map((prop) => props[prop]?.attribute ?? prop.toLowerCase());
}

//#endregion
export { CustomMediaElement as t };
//# sourceMappingURL=custom-media-element-C7az7smh.js.map