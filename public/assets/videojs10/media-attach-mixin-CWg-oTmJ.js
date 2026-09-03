import { n as mediaContext, o as ContextRequestEvent } from "./context-DlE_3NHA.js";

//#region src/store/media-attach-mixin.ts
/**
* Create a mixin that consumes `mediaContext` and registers the element as the media with the player.
*
* Uses the raw context-request protocol so it works with any `HTMLElement` subclass — no `ReactiveControllerHost`
* required.
*
* @param context - The media context to consume.
*/
function createMediaAttachMixin(context) {
	return (BaseClass) => {
		class MediaAttachElement extends BaseClass {
			#releaseMedia = null;
			#unsubscribe = null;
			getMediaTarget() {
				return this;
			}
			connectedCallback() {
				super.connectedCallback?.();
				this.dispatchEvent(new ContextRequestEvent(context, this, (value, unsubscribe) => {
					if (unsubscribe) this.#unsubscribe = unsubscribe;
					this.#releaseMedia?.();
					this.#releaseMedia = null;
					const target = this.getMediaTarget();
					if (this.isConnected && value && target) this.#releaseMedia = value.registerMedia(target);
				}, false));
			}
			disconnectedCallback() {
				this.#releaseMedia?.();
				this.#releaseMedia = null;
				this.#unsubscribe?.();
				this.#unsubscribe = null;
				super.disconnectedCallback?.();
			}
		}
		return MediaAttachElement;
	};
}
const MediaAttachMixin = createMediaAttachMixin(mediaContext);

//#endregion
export { MediaAttachMixin as t };
//# sourceMappingURL=media-attach-mixin-CWg-oTmJ.js.map