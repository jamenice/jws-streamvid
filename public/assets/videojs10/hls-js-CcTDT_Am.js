import { t as CustomMediaElement } from "./custom-media-element-C7az7smh.js";
import { i as safeDefine } from "./context-DlE_3NHA.js";
import { t as MediaAttachMixin } from "./media-attach-mixin-CWg-oTmJ.js";
import { t as MuxMedia } from "./media-CyMOaRVc.js";
import { t as MuxAudioMixin } from "./mixin-CYVCHKsG.js";

//#region src/media/mux-audio/hls-js.ts
const MuxAudioBase = MuxAudioMixin(MediaAttachMixin(CustomMediaElement("audio", MuxMedia)));
var MuxAudio = class extends MuxAudioBase {};

//#endregion
//#region src/define/media/mux-audio/hls-js.ts
var MuxAudioElement = class extends MuxAudio {
	static {
		this.tagName = "mux-audio";
	}
};
safeDefine(MuxAudioElement);

//#endregion
export { MuxAudioElement as t };
//# sourceMappingURL=hls-js-CcTDT_Am.js.map