(()=>{"use strict";var e={n:t=>{var r=t&&t.__esModule?()=>t.default:()=>t;return e.d(r,{a:r}),r},d:(t,r)=>{for(var a in r)e.o(r,a)&&!e.o(t,a)&&Object.defineProperty(t,a,{enumerable:!0,get:r[a]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=jQuery;var r=e.n(t);const a=devowlWp_utils;let s;const n={path:"/newsletter/subscribe",method:a.RouteHttpVerb.POST};async function o(e){await function(){for(var e=arguments.length,t=new Array(e),r=0;r<e;r++)t[r]=arguments[r];return(s||(s=(0,a.createRequestFactory)(window[a.BaseOptions.getPureSlug("real-utils",!0)]))).request(...t)}({location:n,request:e})}let l;const i=function(){for(var e=arguments.length,t=new Array(e),r=0;r<e;r++)t[r]=arguments[r];return(l||(l=(0,a.createLocalizationFactory)("devowl-wp-real-utils"))).__(...t)};r()(document).on("click",".about-wrap .real-utils-newsletter-box .button-hero",(async function(e){e.preventDefault();const t=r()(this).parents(".real-utils-newsletter-box"),a=r()(this).parents(".wrap").data("slug"),s=`${r()("input[name='newsletter-email']").val()}`,n=t.find("input[type='checkbox']").is(":checked"),l=t.find(".error-msg").hide();r()(this).html(i("Loading...")).addClass("button-primary");try{await o({slug:a,email:s,privacy:n}),r()(this).html(i("Successfully subscribed!")).removeClass("button-primary")}catch(e){r()(this).html(i("Subscribe")),l.html(e.responseJSON.message).show()}}))})();
//# sourceMappingURL=https://sourcemap.devowl.io/real-thumbnail-generator/2.6.93/c8c92e60038e0045c2021d92fe17e4d1/welcome.js.map
