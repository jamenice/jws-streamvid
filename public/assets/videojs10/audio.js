import{a as e,c as t,d as n,f as r,i,j as a,m as o,n as s,r as c,t as l,u,w as d}from"./popover-element-BlqlJ30i.js";import{n as f,t as p}from"./text-element-CUBx_3Dk.js";import{i as m}from"./context-C2rsoDxk.js";import{C as h,t as g}from"./container-element-C28_9xEa.js";import{t as _}from"./gesture-element-DwdBnLeH.js";import{t as v}from"./default-IZWfBrDM.js";import{t as y}from"./live-button-element-CdfSebKZ.js";import{n as b,r as x,t as S}from"./seek-button-element-DHl8rc_2.js";import"./player-B1iKMUBW.js";var C=`video-player, live-video-player, media-i18n, media-dialog, media-alert-dialog, media-error-dialog, media-controls {
  display: contents;
}

media-container video, media-container [slot="poster"] {
  width: 100%;
  height: 100%;
  display: block;
}

media-container video::-webkit-media-text-track-container {
  z-index: 1;
  scale: .98;
  translate: 0 var(--media-caption-track-y, 0);
  transition: translate var(--media-caption-track-duration, 0) ease-out;
  transition-delay: var(--media-caption-track-delay, 0);
  font-family: inherit;
}

media-tooltip-group, media-dialog, media-alert-dialog, media-error-dialog, media-controls {
  display: contents;
}

:host {
  width: 100%;
  display: grid;
}

media-container {
  min-width: 0;
  min-height: 0;
}

.media-popover--volume:has(media-volume-slider[data-hidden]) {
  display: none;
}

.media-sr-only {
  white-space: nowrap;
  clip: rect(0, 0, 0, 0);
  border: 0;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  position: absolute;
  overflow: hidden;
}

.media-default-skin *, .media-default-skin :before, .media-default-skin :after {
  box-sizing: border-box;
}

.media-default-skin img, .media-default-skin video, .media-default-skin svg {
  max-width: 100%;
  display: block;
}

.media-default-skin button {
  font: inherit;
}

.media-default-skin [hidden][hidden] {
  display: none;
}

@media (prefers-reduced-motion: no-preference) {
  .media-default-skin {
    interpolate-size: allow-keywords;
  }
}

.media-default-skin {
  --media-internal-accent-color: var(--media-accent-color, var(--media-default-accent-color));
  --media-accent-contrast-color: contrast-color(var(--media-internal-accent-color));
  --media-accent-background-color: var(--media-accent-color, oklch(from var(--media-default-accent-color) l c h / calc(alpha * .1)));
  --media-internal-accent-text-color: var(--media-accent-text-color, contrast-color(var(--media-accent-color, oklch(0% 0 0))));
  --media-shadow-current-color: oklch(from currentColor 0 0 0 / clamp(0, calc((l - .5) * .5), .15));
  --media-shadow-subtle-current-color: oklch(from var(--media-shadow-current-color) l c h / calc(alpha * .4));
  --media-scrollbar-thumb-color: oklch(from currentColor l c h / .3);
  --media-scale: 1;
  --media-internal-scale-unit: var(--media-scale-unit, 16px);
  --media-size: calc(var(--media-internal-scale-unit) * var(--media-scale));
  --media-spacing: calc(var(--media-size) / 4);
  --media-font-size-medium: calc(.9375 * var(--media-size));
  --media-font-size-base: calc(.8125 * var(--media-size));
  --media-font-size-small: calc(.6875 * var(--media-size));
  --media-font-size-tiny: calc(.5625 * var(--media-size));
  --media-icon-size: calc(1.125 * var(--media-size));
  --media-container-border-radius: var(--media-border-radius, 1.75rem);
  width: 100%;
  height: 100%;
  font-family: Inter Variable, Inter, ui-sans-serif, system-ui, sans-serif;
  font-size: var(--media-font-size-base);
  -webkit-font-smoothing: auto;
  -moz-osx-font-smoothing: auto;
  letter-spacing: normal;
  outline-offset: -4px;
  scrollbar-color: var(--media-scrollbar-thumb-color) transparent;
  scrollbar-width: thin;
  border-radius: var(--media-container-border-radius, 1.75rem);
  isolation: isolate;
  outline: 2px solid #0000;
  line-height: 1.5;
  transition-property: outline-offset, outline-color;
  transition-duration: .1s;
  transition-timing-function: ease-out;
  display: block;
  position: relative;
  container: media-root / inline-size;

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }

  &::-webkit-scrollbar-thumb {
    background: var(--media-scrollbar-thumb-color);
    border-radius: 9999px;
  }

  @media (prefers-reduced-transparency: reduce) or (prefers-contrast: more) {
    --media-scrollbar-thumb-color: oklch(from currentColor l c h / .8);
    scrollbar-width: auto;
  }
}

.media-default-skin .media-surface {
  background-color: var(--media-surface-background-color);
  box-shadow: 0 0 0 1px var(--media-surface-outer-border-color),
    0 1px 3px 0 var(--media-surface-shadow-color),
    0 1px 2px -1px var(--media-surface-shadow-color);
  backdrop-filter: var(--media-surface-backdrop-filter);

  &:after {
    z-index: 10;
    pointer-events: none;
    content: "";
    border-radius: inherit;
    box-shadow: inset 0 1px 0 0 var(--media-surface-inner-border-color),
      inset 0 0 0 1px oklch(from var(--media-surface-inner-border-color) l c h / calc(alpha * .5));
    position: absolute;
    inset: 0;
  }
}

.media-default-skin .media-buffering-indicator {
  z-index: 10;
  color: oklch(100% 0 0);
  pointer-events: none;
  place-content: center;
  display: none;
  position: absolute;
  inset: 0;

  &:before {
    content: "";
    backdrop-filter: blur(8px);
    background: oklch(0% 0 0 / .35);
    position: absolute;
    inset: 0;
  }

  & > * {
    z-index: 20;
    position: relative;
  }

  &:not([data-visible]) {
    --media-spinner-animation: none;
  }

  &[data-visible] {
    display: grid;
  }

  @media (prefers-reduced-motion: reduce) {
    --media-spinner-animation: none;
  }
}

.media-default-skin {
  & media-error-dialog {
    z-index: 20;
    outline: none;
    justify-content: center;
    align-items: center;
    display: flex;
    position: absolute;
    inset: 0;

    &:not([data-open]) {
      display: none;
    }
  }

  & .media-dialog__backdrop {
    z-index: 10;
    pointer-events: none;
    backdrop-filter: blur(16px) saturate(1.5);
    opacity: 1;
    transition-timing-function: var(--media-dialog-transition-timing-function);
    transition-duration: var(--media-dialog-transition-duration);
    transition-property: opacity;
    transition-delay: var(--media-dialog-transition-delay);
    background: oklch(0% 0 0 / .2);
    position: absolute;
    inset: 0;

    &[data-starting-style], &[data-ending-style] {
      opacity: 0;
    }

    &[data-ending-style] {
      transition-delay: 0s;
    }

    &:not([data-open]) {
      display: none;
    }
  }

  & .media-dialog__popup {
    outline: none;
  }

  & .media-dialog__title {
    font-weight: 600;
    line-height: 1.25;
  }

  & .media-dialog__description {
    overflow-wrap: anywhere;
    opacity: .7;
  }

  & .media-dialog__actions {
    gap: calc(var(--media-spacing) * 2);
    display: flex;

    & > * {
      flex: 1;
    }
  }
}

.media-default-skin .media-controls {
  --media-popover-side-offset: calc(var(--media-spacing) * (var(--media-base-side-offset, 2) + 1));
  --media-tooltip-side-offset: var(--media-popover-side-offset);
  --media-popover-boundary-offset: calc(var(--media-spacing) * var(--media-base-boundary-offset, 2));
  --media-tooltip-boundary-offset: var(--media-popover-boundary-offset);
  padding: calc(var(--media-spacing) * 1);
  text-shadow: 0 1px 0 var(--media-shadow-current-color);
  border-radius: 3.40282e38px;
  align-items: center;
  display: flex;
  container: media-controls / inline-size;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }
}

.media-default-skin .media-time-controls {
  gap: calc(var(--media-spacing) * 2.5);
  flex: 1;
  align-items: center;
  display: flex;
  container: media-time-controls / inline-size;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }

  & > .media-time:last-child {
    @container media-time-controls (width < 16rem) {
      display: none;
    }
  }
}

.media-default-skin .media-time {
  font-variant-numeric: tabular-nums;
}

.media-default-skin .media-time[role="button"] {
  cursor: pointer;
  outline-offset: -2px;
  border-radius: calc(var(--media-spacing) * 1);
  outline: 2px solid #0000;
  transition-property: outline-color, outline-offset;
  transition-duration: .1s;
  transition-timing-function: ease-out;

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }
}

.media-default-skin .media-button {
  height: calc(var(--media-spacing) * 9);
  min-height: 0;
  padding: calc(var(--media-spacing) * 2) calc(var(--media-spacing) * 4);
  text-align: center;
  touch-action: manipulation;
  cursor: pointer;
  user-select: none;
  outline-offset: -2px;
  will-change: scale;
  border: none;
  border-radius: 3.40282e38px;
  outline: 2px solid #0000;
  flex-shrink: 0;
  justify-content: center;
  align-items: center;
  transition-property: background-color, color, outline-offset, scale;
  transition-duration: .15s;
  transition-timing-function: ease-out;
  display: flex;

  &:focus-visible {
    outline-color: var(--media-focus-ring-color);
    outline-offset: 2px;
  }

  &:active:not([aria-disabled="true"]) {
    scale: .97;
  }

  &[aria-disabled="true"] {
    cursor: not-allowed;
    opacity: .5;
  }
}

.media-default-skin .media-button--primary {
  color: var(--media-accent-contrast-color);
  text-shadow: none;
  background: var(--media-internal-accent-color);
  font-weight: 500;
}

.media-default-skin .media-button--subtle {
  color: inherit;
  text-shadow: inherit;
  background: none;

  &:not([aria-disabled="true"]) {
    &:hover, &:focus-visible, &[aria-expanded="true"] {
      color: var(--media-internal-accent-text-color);
      background-color: var(--media-accent-background-color);
      text-decoration: none;
    }
  }
}

.media-default-skin .media-button--icon {
  aspect-ratio: 1;
  padding: 0;
  display: grid;

  &:active:not([aria-disabled="true"]) {
    scale: .97;
  }

  & .media-icon__container {
    display: grid;
  }

  & .media-icon {
    filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
    grid-area: 1 / 1;
    transition-property: opacity, scale;
    transition-duration: .15s;
    transition-timing-function: ease-out;
  }
}

.media-default-skin .media-button--seek {
  & .media-icon__label {
    font-variant-numeric: tabular-nums;
    letter-spacing: -.05em;
    font-size: .715em;
    font-weight: 500;
    position: absolute;
    bottom: -3px;
    right: -1px;
  }

  &:has(.media-icon--flipped) .media-icon__label {
    right: unset;
    left: -1px;
  }
}

.media-default-skin .media-button--playback-rate {
  font-variant-numeric: tabular-nums;
  padding: 0;

  &:after {
    content: attr(data-rate) "×";
    width: 4ch;
  }

  &[data-inline-rate-label]:after {
    content: none;
  }
}

.media-default-skin .media-button--settings {
  & .media-icon--settings {
    transition: transform .15s ease-in-out;

    @media (prefers-reduced-motion: reduce) {
      transition-duration: 0s;
    }
  }

  &[aria-expanded="true"] .media-icon--settings {
    transform: rotate(90deg);
  }
}

.media-default-skin .media-button--live {
  gap: calc(var(--media-spacing) * 1.5);
  aspect-ratio: auto;
  width: auto;
  padding: calc(var(--media-spacing) * 2) calc(var(--media-spacing) * 3);
  font-size: var(--media-font-size-small);
  text-transform: uppercase;
  letter-spacing: .05em;
  align-items: center;
  font-weight: 600;
  line-height: 1;
  display: inline-flex;

  &:before {
    width: calc(var(--media-spacing) * 2);
    height: calc(var(--media-spacing) * 2);
    content: "";
    background-color: oklch(from currentColor l c h / .4);
    border-radius: 50%;
    flex-shrink: 0;
    transition: background-color .15s ease-out;
    display: inline-block;
  }

  &[data-live-edge]:before {
    background-color: oklch(65% .22 27);
  }
}

@media (prefers-reduced-motion: reduce) {
  .media-default-skin .media-button {
    will-change: auto;
    transition-property: background-color, color;
    scale: 1;
  }
}

.media-default-skin .media-button-group {
  align-items: center;
  gap: 1px;
  display: flex;

  &:dir(rtl) {
    flex-direction: row-reverse;
  }
}

.media-default-skin .media-badge {
  padding: calc(var(--media-spacing) * .5) calc(var(--media-spacing) * 1.5);
  font-size: var(--media-font-size-small);
  color: oklch(from currentColor l c h / .85);
  white-space: nowrap;
  background-color: oklch(from currentColor l c h / .1);
  border-radius: 3.40282e38px;
  font-weight: 500;
  line-height: 1;
}

.media-default-skin .media-icon__container {
  position: relative;
}

.media-default-skin .media-icon {
  width: var(--media-icon-size);
  height: var(--media-icon-size);
  flex-shrink: 0;
}

.media-default-skin .media-icon--flipped, .media-default-skin:dir(rtl) .media-menu__chevron {
  scale: -1 1;
}

.media-default-skin:dir(rtl) .media-menu__chevron.media-icon--flipped {
  scale: 1;
}

.media-default-skin .media-slider {
  --media-track-size: calc(var(--media-spacing) * 1);
  --media-track-highlighted-size: calc(var(--media-spacing) * 1.75);
  --media-track-border-radius: 99px;
  --media-track-transition-duration: .1s;
  --media-thumb-size: calc(var(--media-spacing) * 3);
  --media-chapter-gap: calc(var(--media-spacing) * 1);
  --media-internal-chapter-inset-start: calc(var(--media-chapter-gap) / 2);
  --media-internal-chapter-inset-end: calc(var(--media-chapter-gap) / 2);
  cursor: pointer;
  outline: none;
  flex: 1;
  justify-content: center;
  align-items: center;
  display: flex;
  position: relative;

  &[data-orientation="horizontal"] {
    width: var(--media-slider-width, 100%);
    min-width: calc(var(--media-spacing) * 20);
    height: var(--media-slider-height, calc(var(--media-spacing) * 8));
  }

  &[data-orientation="vertical"] {
    width: var(--media-slider-width, calc(var(--media-spacing) * 8));
    height: var(--media-slider-height, calc(var(--media-spacing) * 20));
  }

  & .media-slider__track {
    user-select: none;
    background-color: oklch(from currentColor l c h / .2);
    border-radius: var(--media-track-border-radius);
    isolation: isolate;
    position: relative;
    overflow: hidden;

    &[data-orientation="horizontal"] {
      width: 100%;
      height: var(--media-track-size);
    }

    &[data-orientation="vertical"] {
      width: var(--media-track-size);
      height: 100%;
    }
  }

  & .media-slider__buffer, & .media-slider__fill {
    pointer-events: none;
    border-radius: inherit;
    position: absolute;

    &[data-orientation="horizontal"] {
      inset-block: 0;
      width: 100%;
      left: 0;
    }

    &[data-orientation="vertical"] {
      inset-inline: 0;
      height: 100%;
      bottom: 0;
    }

    @media (prefers-reduced-motion: no-preference) {
      transition: clip-path var(--media-track-transition-duration) ease-out;
    }
  }

  &[data-dragging] {
    & .media-slider__fill, & .media-slider__buffer {
      transition-duration: 0s;
    }
  }

  & .media-slider__buffer {
    background-color: oklch(from currentColor l c h / .2);

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-buffer)) 0 0 round var(--media-track-border-radius));
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-buffer)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  & .media-slider__fill {
    background-color: var(--media-internal-accent-color);

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-fill)) 0 0 round var(--media-track-border-radius));
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-fill)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  &[data-dragging] {
    & .media-slider__fill[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-pointer)) 0 0 round var(--media-track-border-radius));
    }

    & .media-slider__fill[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-pointer)) 0 0 0 round var(--media-track-border-radius));
    }
  }

  & .media-slider__chapters {
    border-radius: inherit;
    flex: 1;
    align-items: center;
    min-width: 0;
    min-height: 0;
    display: flex;
    position: relative;

    &[data-orientation="horizontal"] {
      width: 100%;
      height: 100%;
    }

    &[data-orientation="vertical"] {
      flex-direction: column-reverse;
      width: 100%;
      height: 100%;
    }
  }

  & .media-slider__chapter {
    border-radius: inherit;
    justify-content: center;
    align-items: center;
    min-width: 0;
    min-height: 0;
    display: flex;
    position: absolute;
    inset: 0;

    &:first-child {
      --media-internal-chapter-inset-start: 0px;
    }

    &:last-child {
      --media-internal-chapter-inset-end: 0px;
    }

    & .media-slider__chapter-track {
      border-radius: inherit;

      @media (prefers-reduced-motion: no-preference) {
        transition: height .2s ease-out, width .2s ease-out;
      }
    }

    &[data-orientation="horizontal"] {
      clip-path: inset(0 calc(100% - var(--media-slider-chapter-end)) 0 var(--media-slider-chapter-start));

      & .media-slider__chapter-track {
        height: var(--media-track-size);
        clip-path: inset(0 calc(100% - var(--media-slider-chapter-end) + var(--media-internal-chapter-inset-end)) 0
            calc(var(--media-slider-chapter-start) + var(--media-internal-chapter-inset-start)) round
            var(--media-track-border-radius));
      }

      &[data-highlighted] .media-slider__chapter-track {
        height: var(--media-track-highlighted-size);
      }
    }

    &[data-orientation="vertical"] {
      clip-path: inset(calc(100% - var(--media-slider-chapter-end)) 0 var(--media-slider-chapter-start) 0);

      & .media-slider__chapter-track {
        width: var(--media-track-size);
        clip-path: inset(calc(100% - var(--media-slider-chapter-end) + var(--media-internal-chapter-inset-end)) 0
            calc(var(--media-slider-chapter-start) + var(--media-internal-chapter-inset-start)) 0 round
            var(--media-track-border-radius));
      }

      &[data-highlighted] .media-slider__chapter-track {
        width: var(--media-track-highlighted-size);
      }
    }
  }

  & .media-slider__thumb {
    z-index: 10;
    width: var(--media-thumb-size);
    height: var(--media-thumb-size);
    user-select: none;
    outline-offset: -4px;
    box-shadow: 0 0 0 1px var(--media-shadow-current-color, oklch(0% 0 0 / .1)),
      0 1px 3px 0 oklch(0% 0 0 / .35),
      0 1px 2px -1px oklch(0% 0 0 / .35);
    opacity: 0;
    background-color: currentColor;
    border-radius: 3.40282e38px;
    outline: 4px solid #0000;
    position: absolute;
    translate: -50% -50%;
    scale: .8;

    &[data-orientation="horizontal"] {
      top: 50%;
      left: var(--media-slider-fill);
    }

    &[data-orientation="vertical"] {
      top: calc(100% - var(--media-slider-fill));
      left: 50%;
    }

    &:focus-visible {
      outline-color: oklch(from currentColor l c h / .15);
      outline-offset: 0;
      opacity: 1;
    }

    &:after {
      content: "";
      border-radius: inherit;
      position: absolute;
      inset: -4px;
      box-shadow: 0 0 0 2px;
    }

    &:not(:focus-visible):after {
      opacity: 0;
      scale: .5;
    }

    &.media-slider__thumb--persistent {
      opacity: 1;
      scale: 1;
    }

    @media (hover: hover) and (pointer: fine) {
      &:hover {
        outline-color: oklch(from currentColor l c h / .15);
        outline-offset: 0;
      }
    }

    @media (prefers-reduced-motion: no-preference) {
      transition-timing-function: ease-out;
      transition-duration: var(--media-track-transition-duration);
      transition-property: opacity, outline-offset, left, top, scale;

      &:after {
        transition-property: opacity, scale;
        transition-duration: .15s;
        transition-timing-function: ease-out;
      }
    }
  }

  @media (hover: hover) and (pointer: fine) {
    &:hover .media-slider__thumb {
      opacity: 1;
      scale: 1;
    }
  }

  &[data-dragging] .media-slider__thumb {
    opacity: 1;
    scale: .9;

    @media (prefers-reduced-motion: no-preference) {
      transition-property: opacity, outline-offset, scale;
    }

    &[data-orientation="horizontal"] {
      left: var(--media-slider-pointer);
    }

    &[data-orientation="vertical"] {
      top: calc(100% - var(--media-slider-pointer));
    }
  }

  & .media-slider__preview {
    --media-max-size-factor: 36;
    --media-max-size: min(calc(var(--media-spacing) * var(--media-max-size-factor)), 100cqi);
    min-width: var(--media-max-size);
    height: calc(var(--media-spacing) * 1);

    @container media-root (width > 42rem) {
      --media-max-size-factor: 48;
    }

    & .media-slider__thumbnail, & .media-slider__value {
      max-width: var(--media-max-size);
      opacity: 0;
      filter: blur(8px);
      transform-origin: bottom;
      scale: .8;
      translate: -50% calc(var(--media-spacing) * 2);
      transition-duration: .15s;
      transition-timing-function: ease-out;
      position: absolute;
      left: 50%;
    }

    & .media-slider__thumbnail {
      --media-thumbnail-max-width: var(--media-max-size);
      --media-thumbnail-max-height: var(--media-max-size);
      bottom: calc(100% + (var(--media-spacing) * 9));
    }

    & .media-slider__value {
      bottom: calc(100% + (var(--media-spacing) * 10.5));
      flex-direction: column;
      align-items: center;
      display: flex;
    }

    & .media-slider__chapter-title {
      min-width: 0;
      max-width: var(--media-max-size);
      padding-inline: calc(var(--media-spacing) * 6);
      text-overflow: ellipsis;
      white-space: nowrap;
      overflow: hidden;

      &:empty {
        display: none;
      }
    }

    &:before {
      z-index: 1;
      width: calc(var(--media-spacing) * 1);
      height: calc(var(--media-spacing) * 1);
      pointer-events: none;
      content: "";
      box-shadow: 0 0 0 1px var(--media-shadow-current-color, oklch(0% 0 0 / .15)),
        0 1px 2px 0 oklch(0% 0 0 / .35);
      opacity: 0;
      background-color: currentColor;
      border-radius: 100%;
      transition-property: opacity, scale;
      transition-duration: .2s;
      transition-timing-function: ease-out;
      position: absolute;
      top: 50%;
      left: 50%;
      translate: -50% -50%;
      scale: .5;
    }
  }

  &:is([data-pointing], :has(:focus-visible)) .media-slider__preview :is(.media-slider__value, .media-slider__thumbnail), & .media-slider__preview[data-pointing]:not([data-dragging]):before {
    opacity: 1;
    filter: blur();
    scale: 1;
  }
}

.media-default-skin {
  --media-popup-transition: opacity var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    filter var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    transform var(--media-popup-transition-timing-function) var(--media-popup-transition-duration),
    scale var(--media-popup-transition-timing-function) var(--media-popup-transition-duration);
}

.media-default-skin .media-popover, .media-default-skin .media-tooltip {
  --media-popup-translate-distance: calc(.5 * var(--media-internal-scale-unit));
  color: inherit;
  transition: var(--media-popup-transition);
  border: 0;
  margin: 0;
  overflow: visible;

  &[data-starting-style], &[data-ending-style] {
    opacity: 0;
    filter: blur(4px);
    transform: translate(var(--media-popup-translate-x-distance, 0), var(--media-popup-translate-y-distance, 0));
    scale: .95;
  }

  &[data-ending-style] {
    transition-duration: max(0s, calc(var(--media-popup-transition-duration) - 50ms));
    transform: none;
  }

  &[data-side="top"] {
    --media-popup-translate-y-distance: var(--media-popup-translate-distance);
    transform-origin: bottom;
  }

  &[data-side="bottom"] {
    --media-popup-translate-y-distance: calc(var(--media-popup-translate-distance) * -1);
    transform-origin: top;
  }

  &[data-side="left"] {
    --media-popup-translate-x-distance: var(--media-popup-translate-distance);
    transform-origin: 100%;
  }

  &[data-side="right"] {
    --media-popup-translate-x-distance: calc(var(--media-popup-translate-distance) * -1);
    transform-origin: 0;
  }

  &:before {
    pointer-events: inherit;
    content: "";
    position: absolute;
  }

  &[data-side="top"]:before, &[data-side="bottom"]:before {
    width: 100%;
    inset-inline: 0;
  }

  &[data-side="top"]:before {
    top: 100%;
  }

  &[data-side="bottom"]:before {
    bottom: 100%;
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    height: 100%;
    inset-block: 0;
  }

  &[data-side="left"]:before {
    left: 100%;
  }

  &[data-side="right"]:before {
    right: 100%;
  }
}

.media-default-skin .media-popover {
  &[data-side="top"]:before, &[data-side="bottom"]:before {
    height: var(--media-popover-side-offset);
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    width: var(--media-popover-side-offset);
  }
}

.media-default-skin .media-popover--volume {
  padding: calc(var(--media-spacing) * 3) 0;
  border-radius: 3.40282e38px;

  &:has(media-volume-slider[data-hidden]) {
    display: none;
  }
}

.media-default-skin .media-tooltip {
  padding: calc(var(--media-spacing) * 1) calc(var(--media-spacing) * 2.5);
  font-size: var(--media-font-size-base);
  white-space: nowrap;
  border-radius: 3.40282e38px;

  &[data-open] {
    column-gap: calc(var(--media-spacing) * 1);
    align-items: center;
    display: flex;
  }

  &[data-side="top"]:before, &[data-side="bottom"]:before {
    height: var(--media-tooltip-side-offset);
  }

  &[data-side="left"]:before, &[data-side="right"]:before {
    width: var(--media-tooltip-side-offset);
  }

  & .media-tooltip__kbd {
    min-width: 1.5em;
    font-family: inherit;
    font-size: var(--media-font-size-small);
    text-align: center;
    background-color: oklch(from currentColor l c h / .3);
    border-radius: calc(var(--media-spacing) * 1);
    padding: .1em;
    font-weight: 600;
    line-height: 1.25;
  }
}

.media-default-skin .media-menu {
  --media-menu-transition-duration: .25s;
  --media-menu-max-height: calc(var(--media-spacing) * 56);
  --media-menu-padding: calc(var(--media-spacing) * 1);
  --media-menu-border-radius: calc(var(--media-spacing) * 3);
  --media-menu-item-border-radius: calc(var(--media-menu-border-radius) - var(--media-menu-padding));
  box-sizing: border-box;
  min-width: max-content;
  max-width: var(--media-menu-available-width, none);
  max-height: min(var(--media-menu-available-height, var(--media-menu-max-height)), var(--media-menu-max-height));
  padding: var(--media-menu-padding);
  overscroll-behavior: none;
  border-radius: var(--media-menu-border-radius);
  overflow: auto;

  @media (prefers-reduced-motion: reduce) {
    --media-menu-transition-duration: 0s;
  }

  & > .media-menu__panel {
    --media-menu-content-enter-translate: 100%;
    inset-inline: 0;
    z-index: 10;
    max-height: inherit;
    padding: var(--media-menu-padding);
    overscroll-behavior: none;
    transition-timing-function: ease-out;
    transition-duration: var(--media-menu-transition-duration);
    outline: none;
    transition-property: translate, filter;
    position: absolute;
    top: 0;
    overflow: auto;
    translate: 0;

    &:where([data-starting-style], [data-ending-style]) {
      pointer-events: none;
      filter: blur(8px);
      translate: var(--media-menu-content-enter-translate) 0;
      overflow: hidden;
    }

    &:dir(rtl):where([data-starting-style], [data-ending-style]) {
      --media-menu-content-enter-translate: -100%;
    }
  }

  & .media-menu__separator {
    margin-block: calc(var(--media-spacing) * 1);
    border-bottom: 1px solid oklch(0% 0 0 / .1);
    box-shadow: 0 1px oklch(100% 0 0 / .075);
  }

  & .media-menu__content, & .media-menu__group {
    anchor-scope: --menu-item-highlight-anchor;
    gap: calc(var(--media-spacing) * .5);
    flex-direction: column;
    display: flex;

    @supports (top: anchor(top)) {
      &:before {
        position-anchor: --menu-item-highlight-anchor;
        inset: anchor(inside);
        overflow-anchor: none;
        pointer-events: none;
        content: "";
        background-color: var(--media-accent-background-color);
        border-radius: var(--media-menu-item-border-radius);
        transition: inset .1s ease-in-out;
        position: absolute;
      }

      &:has([data-highlighted=""]):before {
        transition-duration: 0s;
      }
    }
  }

  & .media-menu__item, & .media-menu__back {
    gap: calc(var(--media-spacing) * 1.5);
    padding: calc(var(--media-spacing) * 1.5) calc(var(--media-spacing) * 2);
    text-align: start;
    white-space: nowrap;
    text-shadow: 0 1px 0 var(--media-shadow-current-color);
    cursor: pointer;
    user-select: none;
    outline-offset: -2px;
    border-radius: var(--media-menu-item-border-radius);
    outline: 2px solid #0000;
    align-items: center;
    transition: background-color .1s ease-in-out, color .1s ease-in-out;
    display: flex;
    position: relative;

    & .media-icon {
      color: oklch(from currentColor l c h / .65);
      filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
      flex-shrink: 0;
    }

    &:focus-visible {
      outline-color: var(--media-focus-ring-color);
      outline-offset: 2px;
    }

    &:hover, &[data-highlighted] {
      color: var(--media-internal-accent-text-color);
      background-color: var(--media-accent-background-color);

      & .media-icon {
        color: inherit;
      }
    }

    @supports (top: anchor(top)) {
      transition-duration: 50ms;

      &:hover, &[data-highlighted] {
        transition-duration: .2s;
      }
    }
  }

  & .media-menu__indicator {
    margin-inline: auto calc(var(--media-spacing) * -1);
    opacity: 0;
    flex-shrink: 0;

    & .media-icon {
      filter: drop-shadow(0 1px 0 var(--media-shadow-current-color));
    }
  }

  & .media-menu__item {
    font-variant-numeric: tabular-nums;
    color: inherit;
    justify-content: space-between;

    &[aria-disabled="true"] {
      pointer-events: none;
      cursor: not-allowed;
      opacity: .5;
    }

    &[aria-checked="true"] .media-menu__indicator {
      opacity: 1;
    }

    &[data-availability="unavailable"], &[data-availability="unsupported"] {
      display: none;
    }

    &[data-highlighted] {
      @supports (top: anchor(top)) {
        anchor-name: --menu-item-highlight-anchor;
        background-color: #0000;
      }
    }
  }

  & .media-menu__tier {
    padding-inline-start: calc(var(--media-spacing) * .5);
    font-size: var(--media-font-size-tiny);
    color: oklch(from currentColor l c h / .7);
    padding-top: 1px;
    font-weight: 600;
    line-height: 1;
  }

  & .media-menu__back {
    width: 100%;
    margin-bottom: calc(var(--media-spacing) * .5);
  }

  & .media-menu__hint {
    gap: calc(var(--media-spacing) * 1);
    min-width: 0;
    color: oklch(from currentColor l c h / .65);
    align-items: center;
    margin-inline-start: auto;
    padding-inline-start: calc(var(--media-spacing) * 2);
    display: inline-flex;
  }

  & .media-menu__hint-label {
    max-width: calc(var(--media-spacing) * 24);
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
  }

  & .media-menu__chevron {
    width: calc(var(--media-spacing) * 3.5);
    height: calc(var(--media-spacing) * 3.5);
  }

  &.media-menu--settings {
    width: var(--media-menu-width);
    min-width: calc(var(--media-spacing) * 44);
    height: var(--media-menu-height);
    transition: var(--media-popup-transition),
      width var(--media-popup-transition-timing-function) var(--media-menu-transition-duration),
      height var(--media-popup-transition-timing-function) var(--media-menu-transition-duration);
    overflow: hidden;

    & > .media-menu__content {
      --media-menu-content-exit-translate: -100%;
      transition: translate var(--media-menu-transition-duration) ease-out,
        filter var(--media-menu-transition-duration) ease-out;
      translate: 0;

      &:dir(rtl) {
        --media-menu-content-exit-translate: 100%;
      }
    }

    & > .media-menu__content[data-child-open] {
      filter: blur(8px);
      translate: var(--media-menu-content-exit-translate) 0;
    }

    & > .media-menu__content[data-child-open]:before, &:has( > .media-menu__panel[data-ending-style]) > .media-menu__content:before {
      display: none;
    }

    &[data-starting-style], &[data-ending-style] {
      transition: var(--media-popup-transition);
    }
  }
}

.media-button--play .media-icon--restart, .media-button--play .media-icon--play, .media-button--play .media-icon--pause, .media-button--mute .media-icon--volume-off, .media-button--mute .media-icon--volume-low, .media-button--mute .media-icon--volume-high {
  opacity: 0;
  display: none;
}

.media-button--play[data-ended] .media-icon--restart, .media-button--play:not([data-ended])[data-paused] .media-icon--play, .media-button--play:not([data-ended]):not([data-started]) .media-icon--play, .media-button--play[data-started]:not([data-paused]):not([data-ended]) .media-icon--pause, .media-button--mute[data-muted] .media-icon--volume-off, .media-button--mute:not([data-muted])[data-volume-level="low"] .media-icon--volume-low, .media-button--mute:not([data-muted]):not([data-volume-level="low"]) .media-icon--volume-high {
  opacity: 1;
  display: block;
}

.media-default-skin--audio {
  --media-default-accent-color: light-dark(oklch(0% 0 0), oklch(100% 0 0));
  --media-text-color: light-dark(oklch(0% 0 0), oklch(100% 0 0));
  --media-border-color: oklch(0% 0 0 / .1);
  --media-focus-ring-color: light-dark(oklch(0% 0 0), oklch(100% 0 0));
  --media-surface-background-color: light-dark(oklch(100% 0 0 / .5), oklch(0% 0 0 / .4));
  --media-surface-inner-border-color: oklch(100% 0 0 / .1);
  --media-surface-outer-border-color: oklch(0% 0 0 / .05);
  --media-surface-shadow-color: oklch(0% 0 0 / .15);
  --media-surface-backdrop-filter: blur(16px) saturate(1.5);
  --media-dialog-transition-duration: .25s;
  --media-dialog-transition-delay: .1s;
  --media-popup-transition-duration: .1s;
  --media-popup-transition-timing-function: ease-out;

  @media (prefers-reduced-motion: reduce) {
    --media-dialog-transition-duration: 50ms;
    --media-dialog-transition-delay: 0s;
    --media-popup-transition-duration: 0s;
  }

  @media (prefers-reduced-transparency: reduce) or (prefers-contrast: more) {
    --media-surface-background-color: light-dark(oklch(100% 0 0), oklch(0% 0 0));
    --media-surface-inner-border-color: light-dark(oklch(100% 0 0 / .1), oklch(100% 0 0 / .2));
    --media-surface-outer-border-color: light-dark(oklch(0% 0 0 / .05), #0000);
  }
}

.media-default-skin--audio .media-dialog__dialog {
  z-index: 20;
  gap: calc(var(--media-spacing) * 3);
  padding-inline: calc(var(--media-spacing) * 5) calc(var(--media-spacing) * 1);
  color: var(--media-text-color);
  background-color: var(--media-surface-background-color);
  backdrop-filter: var(--media-surface-backdrop-filter);
  transition-delay: var(--media-dialog-transition-delay);
  transition-timing-function: ease-out;
  transition-duration: var(--media-dialog-transition-duration);
  border-radius: 3.40282e38px;
  align-items: center;
  transition-property: opacity, filter;
  display: flex;
  position: absolute;
  inset: 0;
}

.media-default-skin .media-dialog__popup[data-starting-style] .media-dialog__dialog, .media-default-skin .media-dialog__popup[data-ending-style] .media-dialog__dialog, .media-default-skin media-error-dialog[data-starting-style] .media-dialog__dialog, .media-default-skin media-error-dialog[data-ending-style] .media-dialog__dialog {
  opacity: 0;
  filter: blur(4px);
}

.media-default-skin .media-dialog__popup[data-ending-style] .media-dialog__dialog, .media-default-skin media-error-dialog[data-ending-style] .media-dialog__dialog {
  transition-delay: 0s;
}

.media-default-skin--audio .media-dialog__content {
  gap: calc(var(--media-spacing) * 2);
  flex: 1;
  align-items: center;
  display: flex;
}

.media-default-skin--audio .media-controls {
  --media-base-boundary-offset: 2;
  color: var(--media-text-color);

  & .media-time-controls {
    padding-inline: calc(var(--media-spacing) * 3);
  }

  & .media-button--seek {
    @container media-root (width < 32rem) {
      display: none;
    }
  }
}

.media-default-skin--audio .media-button--play__wrapper {
  display: inline-flex;
  position: relative;
}

.media-default-skin--audio .media-button--play__wrapper .media-buffering-indicator {
  color: inherit;
}

.media-default-skin--audio .media-button--play__wrapper:has(.media-buffering-indicator[data-visible]) .media-button--play .media-icon {
  opacity: 0;
}

.media-default-skin--audio .media-slider__track {
  background-color: light-dark(oklch(0% 0 0 / .1), oklch(100% 0 0 / .2));
  box-shadow: 0 0 0 1px light-dark(#0000, oklch(0% 0 0 / .05));
}

.media-default-skin--audio .media-slider .media-slider__preview .media-slider__value {
  bottom: calc(var(--media-spacing) * 10);
}
`;function w(){return`<media-container class="media-default-skin media-default-skin--audio"><slot name="media"></slot><slot></slot><media-error-dialog><media-dialog-popup class="media-dialog__popup"><div class="media-dialog__dialog"><div class="media-dialog__content"><media-dialog-title class="media-dialog__title"></media-dialog-title><media-dialog-description class="media-dialog__description"></media-dialog-description></div><div class="media-dialog__actions"><media-dialog-close class="media-button media-button--subtle"></media-dialog-close></div></div></media-dialog-popup></media-error-dialog><div class="media-surface media-controls"><media-tooltip-group><div class="media-button-group"><span class="media-button--play__wrapper"><media-buffering-indicator class="media-buffering-indicator"> ${v(`spinner`,{class:`media-icon`})} </media-buffering-indicator><media-play-button commandfor="play-tooltip" class="media-button media-button--subtle media-button--icon media-button--play"> ${v(`restart`,{class:`media-icon media-icon--restart`})} ${v(`play`,{class:`media-icon media-icon--play`})} ${v(`pause`,{class:`media-icon media-icon--pause`})} </media-play-button><media-tooltip id="play-tooltip" side="top" boundary="viewport" class="media-surface media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip></span><media-seek-button commandfor="seek-backward-tooltip" seconds="-10" class="media-button media-button--subtle media-button--icon media-button--seek"> <span class="media-icon__container"> ${v(`seek`,{class:`media-icon media-icon--seek media-icon--flipped`})} <span class="media-icon__label">10</span></span></media-seek-button><media-tooltip id="seek-backward-tooltip" side="top" boundary="viewport" class="media-surface media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-seek-button commandfor="seek-forward-tooltip" seconds="10" class="media-button media-button--subtle media-button--icon media-button--seek"> <span class="media-icon__container"> ${v(`seek`,{class:`media-icon media-icon--seek`})} <span class="media-icon__label">10</span></span></media-seek-button><media-tooltip id="seek-forward-tooltip" side="top" boundary="viewport" class="media-surface media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip></div><div class="media-time-controls"><media-time type="current" class="media-time"></media-time><media-time-slider class="media-slider"><media-slider-track class="media-slider__track"><media-slider-buffer class="media-slider__buffer"></media-slider-buffer><media-slider-fill class="media-slider__fill"></media-slider-fill></media-slider-track><media-slider-thumb class="media-slider__thumb"></media-slider-thumb><media-slider-preview overflow="visible" class="media-slider__preview"><media-slider-value type="pointer" class="media-surface media-tooltip media-slider__value media-time"></media-slider-value></media-slider-preview></media-time-slider><media-time toggle type="remaining" class="media-time"></media-time></div><div class="media-button-group"><media-playback-rate-button id="playback-rate-trigger" commandfor="playback-rate-menu" class="media-button media-button--subtle media-button--icon media-button--playback-rate"></media-playback-rate-button><media-menu id="playback-rate-menu" side="top" align="center" boundary="viewport" class="media-surface media-popover media-menu"><media-menu-content class="media-menu__content"><media-playback-rate-radio-group class="media-menu__group"><template><media-menu-radio-item class="media-menu__item"><span data-part="label"></span><media-menu-item-indicator force-mount class="media-menu__indicator"> ${v(`check`,{class:`media-icon`})} </media-menu-item-indicator></media-menu-radio-item></template></media-playback-rate-radio-group></media-menu-content></media-menu><media-tooltip trigger="playback-rate-trigger" side="top" boundary="viewport" class="media-surface media-tooltip"><media-tooltip-label></media-tooltip-label><media-tooltip-shortcut class="media-tooltip__kbd"></media-tooltip-shortcut></media-tooltip><media-mute-button commandfor="audio-volume-popover" class="media-button media-button--subtle media-button--icon media-button--mute"> ${v(`volume-off`,{class:`media-icon media-icon--volume-off`})} ${v(`volume-low`,{class:`media-icon media-icon--volume-low`})} ${v(`volume-high`,{class:`media-icon media-icon--volume-high`})} </media-mute-button><media-popover id="audio-volume-popover" open-on-hover delay="200" close-delay="100" side="top" boundary="viewport" class="media-surface media-popover media-popover--volume"><media-volume-slider class="media-slider" orientation="vertical" thumb-alignment="edge"><media-slider-track class="media-slider__track"><media-slider-fill class="media-slider__fill"></media-slider-fill></media-slider-track><media-slider-thumb class="media-slider__thumb media-slider__thumb--persistent"></media-slider-thumb></media-volume-slider></media-popover></div></media-tooltip-group></div><media-hotkey keys="Space" action="togglePaused"></media-hotkey><media-hotkey keys="k" action="togglePaused"></media-hotkey><media-hotkey keys="m" action="toggleMuted"></media-hotkey><media-hotkey keys="ArrowRight" action="seekStep" value="5"></media-hotkey><media-hotkey keys="ArrowLeft" action="seekStep" value="-5"></media-hotkey><media-hotkey keys="l" action="seekStep" value="10"></media-hotkey><media-hotkey keys="j" action="seekStep" value="-10"></media-hotkey><media-hotkey keys="ArrowUp" action="volumeStep" value="0.05"></media-hotkey><media-hotkey keys="ArrowDown" action="volumeStep" value="-0.05"></media-hotkey><media-hotkey keys="0-9" action="seekToPercent"></media-hotkey><media-hotkey keys="Home" action="seekToPercent" value="0"></media-hotkey><media-hotkey keys="End" action="seekToPercent" value="100"></media-hotkey><media-hotkey keys=">" action="speedUp"></media-hotkey><media-hotkey keys="<" action="speedDown"></media-hotkey></media-container>`}var T=class extends d{static{this.tagName=`audio-skin`}static{this.styles=h(C)}static{this.template=a(w())}};m(g),m(f),t(),n(),r(),u(),o(),m(_),m(i),m(e),m(y),m(c),m(s),m(x),m(b),m(l),m(S),m(p),m(T);
//# sourceMappingURL=audio.js.map