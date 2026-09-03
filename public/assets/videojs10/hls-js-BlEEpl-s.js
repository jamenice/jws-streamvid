import { t as CustomMediaElement } from "./custom-media-element-C7az7smh.js";
import { i as safeDefine } from "./context-DlE_3NHA.js";
import { t as MediaAttachMixin } from "./media-attach-mixin-CWg-oTmJ.js";
import { t as MuxMedia } from "./media-CyMOaRVc.js";
import { t as MuxVideoMixin } from "./mixin-Y03URjEm.js";

//#region src/media/mux-video/hls-js.ts
const MuxVideoBase = MuxVideoMixin(MediaAttachMixin(CustomMediaElement("video", MuxMedia)));
var MuxVideo = class extends MuxVideoBase {};

//#endregion
//#region src/define/media/mux-video/hls-js.ts
var MuxVideoElement = class extends MuxVideo {
	static {
		this.tagName = "mux-video";
	}
};
safeDefine(MuxVideoElement);

//#endregion
export { MuxVideoElement as t };
//# sourceMappingURL=hls-js-BlEEpl-s.js.map