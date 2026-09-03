import { n as ContextConsumer, t as UIElement } from "./ui-element-DraIA8Ee.js";
import { n as mediaContext } from "./context-DlE_3NHA.js";
import { n as addMediaComponent, t as HTMLMediaElementHost } from "./media-host-D7BMz4NW.js";

//#region src/media/media-component-element.ts
/** Resolve the media host from a context media value (a media custom element or the host itself). */
function resolveMediaHost(media) {
	if (media instanceof HTMLMediaElementHost) return media;
	const host = media?.host;
	return host instanceof HTMLMediaElementHost ? host : null;
}
/**
* Abstract base for elements that register a media component (e.g. Mux Data, Google Cast) with the media provided by
* the surrounding player.
*
* Place inside a player, as a sibling of the media element. The component is registered when a media host becomes
* available, follows the media when it changes, is removed when this element disconnects, and is destroyed with this
* element.
*/
var MediaComponentElement = class extends UIElement {
	#component = null;
	#host = null;
	#removeComponent = null;
	/** The media component instance registered with the media host. */
	get component() {
		return this.#component ??= this.createComponent();
	}
	constructor() {
		super();
		new ContextConsumer(this, {
			context: mediaContext,
			subscribe: true,
			callback: (value) => this.#setHost(resolveMediaHost(value.media))
		});
	}
	disconnectedCallback() {
		this.#setHost(null);
		super.disconnectedCallback();
	}
	destroyCallback() {
		this.#setHost(null);
		this.#component?.destroy?.();
		super.destroyCallback();
	}
	#setHost(host) {
		if (this.#host === host) return;
		this.#removeComponent?.();
		this.#removeComponent = null;
		this.#host = host;
		if (host) this.#removeComponent = addMediaComponent(host, this.component);
	}
};

//#endregion
export { MediaComponentElement as t };
//# sourceMappingURL=media-component-element-Fztp1LKL.js.map