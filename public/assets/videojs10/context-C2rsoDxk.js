/**
* @license
* Copyright 2021 Google LLC
* SPDX-License-Identifier: BSD-3-Clause
*/
var e=class extends Event{constructor(e,t,n,r){super(`context-request`,{bubbles:!0,composed:!0}),this.context=e,this.contextTarget=t,this.callback=n,this.subscribe=r??!1}};
/**
* @license
* Copyright 2021 Google LLC
* SPDX-License-Identifier: BSD-3-Clause
*/
function t(e){return e}function n(e,t=e.tagName){let n=globalThis.customElements;!n||n.get(t)||n.define(t,e)}const r=t(Symbol.for(`@videojs/player`)),i=t(Symbol.for(`@videojs/media`)),a=t(Symbol.for(`@videojs/container`));export{t as a,n as i,i as n,e as o,r,a as t};
//# sourceMappingURL=context-C2rsoDxk.js.map