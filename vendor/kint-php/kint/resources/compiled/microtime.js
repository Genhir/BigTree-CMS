void 0===window.kintMicrotimeInitialized&&(window.kintMicrotimeInitialized=1,window.addEventListener("load",function(){"use strict";var l={},t=Array.prototype.slice.call(document.querySelectorAll("[data-kint-microtime-group]"),0);t.forEach(function(t){if(t.querySelector(".kint-microtime-lap")){var i=t.getAttribute("data-kint-microtime-group"),e=parseFloat(t.querySelector(".kint-microtime-lap").innerHTML),r=parseFloat(t.querySelector(".kint-microtime-avg").innerHTML);void 0===l[i]&&(l[i]={}),(void 0===l[i].min||l[i].min>e)&&(l[i].min=e),(void 0===l[i].max||l[i].max<e)&&(l[i].max=e),l[i].avg=r}}),t.forEach(function(t){var i=t.querySelector(".kint-microtime-lap");if(null!==i){var e,r=parseFloat(i.textContent),o=t.dataset.kintMicrotimeGroup,n=l[o].avg,a=l[o].max,c=l[o].min;r===(t.querySelector(".kint-microtime-avg").textContent=n)&&r===c&&r===a||(n<r?(e=(r-n)/(a-n),i.style.background="hsl("+(40-40*e)+", 100%, 65%)"):(e=n===c?0:(n-r)/(n-c),i.style.background="hsl("+(40+80*e)+", 100%, 65%)"))}})}));