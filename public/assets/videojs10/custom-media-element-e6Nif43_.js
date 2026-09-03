import{o as e,r as t,t as n}from"./attributes-p1v3UGHJ.js";import{n as r}from"./casing-CxqC0yQL.js";function i(e,t){let n={};for(let r in e)t.includes(r)||(n[r]=e[r]);return n}const a={borderRadius:`--media-video-border-radius`,objectFit:`--media-object-fit`,objectPosition:`--media-object-position`,captionTrackDuration:`--media-caption-track-duration`,captionTrackDelay:`--media-caption-track-delay`,captionTrackY:`--media-caption-track-y`};function o(e){return`
    <style>
      :host {
        display: contents;
      }

      video {
        display: block;
        width: 100%;
        height: 100%;
        border-radius: var(${a.borderRadius});
        object-fit: var(${a.objectFit}, contain);
        object-position: var(${a.objectPosition}, center);
      }

      video::-webkit-media-text-track-container {
        transition: translate var(${a.captionTrackDuration}, 0) ease-out;
        transition-delay: var(${a.captionTrackDelay}, 0);
        translate: 0 var(${a.captionTrackY}, 0);
        scale: 0.98;
        z-index: 1;
        font-family: inherit;
      }
    </style>
    <slot name="media">
      <video${t(e)}></video>
    </slot>
    <slot></slot>
  `}function s(e){return n=>`
      <style>
        :host {
          display: inline-flex;
          line-height: 0;
          flex-direction: column;
          justify-content: end;
        }

        ${e} {
          width: 100%;
        }
      </style>
      <slot name="media">
        <${e}${t(n)}></${e}>
      </slot>
      <slot></slot>
    `}const c=[`attach`,`detach`,`destroy`];function l(t,a){let l=t!==`iframe`,d=new Map,f=!1;class p extends (globalThis.HTMLElement??class{}){static getTemplateHTML=t.endsWith(`video`)?o:s(t);static shadowRootOptions={mode:`open`};static properties={autoPictureInPicture:{type:Boolean},autoplay:{type:Boolean},controls:{type:Boolean},controlsList:{type:String},crossOrigin:{type:String},defaultMuted:{type:Boolean,attribute:`muted`},disablePictureInPicture:{type:Boolean},disableRemotePlayback:{type:Boolean},loading:{type:String},loop:{type:Boolean},playsInline:{type:Boolean},poster:{type:String,empty:``},preload:{type:String,empty:null},src:{type:String,empty:``},streamType:{type:String,attribute:`stream-type`,empty:`unknown`}};static get observedAttributes(){return p.#define(this),[...u(this.properties)]}static#define(e){if(f)return;f=!0;let t=e.properties;for(let n=a.prototype;n&&n!==Object.prototype;n=Object.getPrototypeOf(n))for(let i of Object.getOwnPropertyNames(n)){if(i in p.prototype||c.includes(i))continue;let a=t[i];if(a&&(a.attribute??i.toLowerCase())!==r(i))continue;let o=Object.getOwnPropertyDescriptor(n,i);if(!o)continue;let s={enumerable:!0,configurable:!0};if(typeof o.value==`function`)s.value=function(...e){return this.#mediaHost[i](...e)};else if(o.get&&(s.get=function(){return this.#mediaHost[i]},o.set)){let t=r(i);e.observedAttributes.includes(t)?(d.set(t,i),s.set=function(e){e===!0||e===!1||e==null?this.toggleAttribute(t,!!e):this.setAttribute(t,String(e))}):s.set=function(e){this.#mediaHost[i]=e}}Object.defineProperty(p.prototype,i,s)}for(let[e,{type:n,attribute:r}]of Object.entries(t)){if(e in p.prototype)continue;let t=r??e.toLowerCase();Object.defineProperty(p.prototype,e,{get:function(){return n===Boolean?this.hasAttribute(t):this.getAttribute(t)},set:function(e){n===Boolean?this.toggleAttribute(t,!!e):this.setAttribute(t,e)},enumerable:!0,configurable:!0})}}#mediaHost;#bridgedEventTypes=new Set;#childMap=new Map;#childObserver;constructor(){if(super(),!this.shadowRoot){let r=this.constructor;this.attachShadow(r.shadowRootOptions);let a=u(r.properties),o=[...d.keys()],s=e(n(this.attributes),a),c=l?i(s,o):s;t&&!c.part&&(c.part=t),this.shadowRoot.innerHTML=r.getTemplateHTML(c)}this.#mediaHost=new a,this.#attachToTarget(),this.#childObserver=new MutationObserver(this.#syncMediaChildAttribute.bind(this)),this.shadowRoot.addEventListener(`slotchange`,()=>{this.#attachToTarget(),this.#syncMediaChildren()}),this.#syncMediaChildren()}#attachToTarget(){let e=this.target;e!==this.#mediaHost.target&&(this.#mediaHost.target&&this.#mediaHost.detach(),this.#mediaHost.attach(e))}get host(){return this.#mediaHost}get target(){return this.querySelector(`:scope > [slot=media]`)??this.querySelector(t)??this.shadowRoot?.querySelector(t)??null}connectedCallback(){t===`iframe`&&(this.hasAttribute(`data-cross-origin-frame`)||this.setAttribute(`data-cross-origin-frame`,``))}disconnectedCallback(){this.hasAttribute(`keep-alive`)||queueMicrotask(()=>{this.isConnected||this.#mediaHost.destroy()})}addEventListener(e,t,n){super.addEventListener(e,t,n),this.#bridgedEventTypes.has(e)||(this.#bridgedEventTypes.add(e),this.#mediaHost.addEventListener(e,this.#bridgeEvent))}removeEventListener(e,t,n){super.removeEventListener(e,t,n)}#bridgeEvent=e=>{e.composed||this.dispatchEvent(new e.constructor(e.type,e))};attributeChangedCallback(e,t,n){let r=d.get(e);if(r){if(t!==n){let e=typeof this.#mediaHost[r],t=this.constructor.properties[r],i=t&&`empty`in t?t.empty:``;this.#mediaHost[r]=e===`boolean`?n!==null:e===`number`?Number(n):n??i}return}!p.observedAttributes.includes(e)&&this.constructor.observedAttributes.includes(e)||l&&(n===null?this.target?.removeAttribute(e):this.target?.getAttribute(e)!==n&&this.target?.setAttribute(e,n))}#syncMediaChildren(){if(t===`iframe`)return;let e=this.shadowRoot?.querySelector(`slot:not([name])`),n=new Set(e?.assignedElements({flatten:!0}).filter(e=>e.localName===`track`||e.localName===`source`));for(let[e,t]of this.#childMap)n.has(e)||(t.remove(),this.#childMap.delete(e));for(let e of n){let t=this.#childMap.get(e);t||(t=e.cloneNode(),this.#childMap.set(e,t),this.#childObserver?.observe(e,{attributes:!0})),this.target?.append(t),this.#enableDefaultTrack(t)}}#syncMediaChildAttribute(e){for(let t of e)if(t.type===`attributes`){let{target:e,attributeName:n}=t,r=this.#childMap.get(e);r&&n&&(r.setAttribute(n,e.getAttribute(n)??``),this.#enableDefaultTrack(r))}}#enableDefaultTrack(e){e&&e.localName===`track`&&e.default&&(e.kind===`chapters`||e.kind===`metadata`)&&e.track.mode===`disabled`&&(e.track.mode=`hidden`)}}return p}function u(e){return Object.keys(e).map(t=>e[t]?.attribute??t.toLowerCase())}export{l as t};
//# sourceMappingURL=custom-media-element-e6Nif43_.js.map