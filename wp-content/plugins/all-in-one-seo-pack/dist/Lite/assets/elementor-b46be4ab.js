import"./js/_plugin-vue_export-helper.4292a25a.js";import{E as f,x as g}from"./js/vue.runtime.esm-bundler.1bf81f69.js";import{c as h,a as y}from"./js/vue-router.05c74492.js";import{e as k,l as E}from"./js/index.f62253a4.js";import{l as b}from"./js/index.cd7fac8b.js";import{l as v}from"./js/index.0b123ab1.js";import{j as S,k as _,l as $}from"./js/links.4e9269a4.js";import{g as A,m as c,T as I}from"./js/postContent.164a8a2e.js";import{i as C}from"./js/isEqual.1d48c5d0.js";import{i as P}from"./js/isEmpty.356d2658.js";import{j as r,_ as n}from"./js/default-i18n.41786823.js";import{A as s}from"./js/App.93196b03.js";import"./js/translations.f6b76bbe.js";import"./js/constants.a78fc4cb.js";import"./js/Caret.0b57d359.js";import"./js/isArrayLikeObject.76f0d098.js";import"./js/cleanForSlug.10bf84e5.js";import"./js/toString.16b91dfe.js";import"./js/_baseTrim.8725856f.js";import"./js/_stringToArray.4de3b1f3.js";import"./js/html.3d4eb4ad.js";import"./js/get.d55c557c.js";import"./js/_baseIsEqual.a58f1c11.js";import"./js/_getAllKeys.d9066207.js";import"./js/_getTag.a3b733ba.js";/* empty css                */import"./js/allowed.11aae9a6.js";import"./js/params.f0608262.js";/* empty css                                               *//* empty css                                                 */import"./js/JsonValues.870a4901.js";/* empty css                                                 */import"./js/SettingsRow.2432af31.js";import"./js/Row.1358a527.js";import"./js/Checkbox.1a2fc75a.js";import"./js/Checkmark.20d31f86.js";import"./js/LicenseKeyBar.1b5c44d1.js";import"./js/LogoGear.8ca170d6.js";import"./js/Tabs.23866df2.js";import"./js/TruSeoScore.177d3103.js";import"./js/Ellipse.ca34fa71.js";import"./js/Information.379a165f.js";import"./js/Slide.c4e68d01.js";import"./js/Portal.eef1ce3a.js";import"./js/Index.d52a1c7e.js";import"./js/MaxCounts.12b45bab.js";import"./js/Tags.bc14d949.js";import"./js/tags.9c0199b3.js";import"./js/Tooltip.fc81232d.js";import"./js/Plus.bd65010b.js";import"./js/Editor.87ec1d9f.js";import"./js/Blur.7ed1854b.js";import"./js/RadioToggle.6c005687.js";import"./js/GoogleSearchPreview.45c17759.js";import"./js/HtmlTagsEditor.2a4955ac.js";import"./js/UnfilteredHtml.2d041b8c.js";import"./js/popup.6fe74774.js";import"./js/addons.b699e1f7.js";import"./js/upperFirst.65f07810.js";import"./js/Index.632f6288.js";import"./js/WpTable.623c3ca8.js";import"./js/Table.9759233a.js";import"./js/numbers.cd5a4c70.js";import"./js/PostTypes.9ab32454.js";import"./js/InternalOutbound.fa7c9832.js";import"./js/RequiredPlans.b62db276.js";import"./js/license.1ec1762f.js";import"./js/Image.db916bd7.js";import"./js/FacebookPreview.86376109.js";import"./js/Img.1587831b.js";import"./js/Profile.9b5df52d.js";import"./js/ImageUploader.9f5fb282.js";import"./js/TwitterPreview.dd33ac7c.js";import"./js/Book.067600d2.js";import"./js/Settings.6e3cf579.js";import"./js/Build.a21243d9.js";import"./js/Redirects.684b50e3.js";import"./js/Index.4b5cac99.js";import"./js/strings.03d6ae29.js";import"./js/isString.197b32a2.js";import"./js/ProBadge.5f8b58d0.js";import"./js/External.95afe855.js";import"./js/Exclamation.c5d4ba67.js";import"./js/Gear.a693d6ea.js";import"./js/Card.37225977.js";import"./js/Eye.186bb5fe.js";import"./js/Upsell.7e4e6ca5.js";class x extends window.$e.modules.hookUI.Base{constructor(o,i,t){super(),this.hook=o,this.id=i,this.callback=t}getCommand(){return this.hook}getId(){return this.id}apply(){return this.callback()}}class D extends window.$e.modules.hookData.Base{constructor(o,i,t){super(),this.hook=o,this.id=i,this.callback=t}getCommand(){return this.hook}getId(){return this.id}apply(){return this.callback()}}function a(e,o,i){window.$e.hooks.registerUIAfter(new x(e,o,i))}function O(e,o,i){window.$e.hooks.registerDataAfter(new D(e,o,i))}let m={},p=!1;const l=()=>{const e=window.elementor.documents.getCurrent();if(!["wp-post","wp-page"].includes(e.config.type))return;const o={...m},i=A();C(o,i)||(m=i,c())},B=()=>{const e=S();P(e.currentPost)||window.elementor.config.document.id===window.elementor.config.document.revisions.current_id&&e.saveCurrentPost(e.currentPost).then(()=>{_().fetch()})},U=()=>{window.$e.internal("document/save/set-is-modified",{status:!0})},H=()=>{p||(p=!0,a("editor/documents/attach-preview","aioseo-content-scraper-attach-preview",l),a("document/save/set-is-modified","aioseo-content-scraper-on-modified",l),O("document/save/save","aioseo-save",B),k.on("postSettingsUpdated",U))},L=()=>{if(window.elementor.config.user.introduction["aioseo-introduction"]===!0)return;const e=new window.elementorModules.editor.utils.Introduction({introductionKey:"aioseo-introduction",dialogType:"alert",dialogOptions:{id:"aioseo-introduction",headerMessage:r(n("New: %1$s %2$s integration","all-in-one-seo-pack"),"AIOSEO","Elementor"),message:r(n("You can now manage your SEO settings inside of %1$s via %2$s before you publish your post!","all-in-one-seo-pack"),"Elementor","All in One SEO"),position:{my:"center center",at:"center center"},strings:{confirm:n("Got It!","all-in-one-seo-pack")},hide:{onButtonClick:!1},onConfirm:()=>{e.setViewed(),e.getDialog().hide()}}});e.show()},T=()=>{let e=window.elementor.getPreferences("ui_theme")||"auto";e==="auto"&&(e=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"),document.body.classList.forEach(o=>{o.startsWith("aioseo-elementor-")&&document.body.classList.remove(o)}),document.body.classList.add("aioseo-elementor-"+e)},M=()=>{window.$e.routes.on("run:after",function(e,o){T(),o==="panel/page-settings/aioseo"&&d("#elementor-panel-page-settings-controls")}),window.elementor.modules.layouts.panel.pages.menu.Menu.addItem({name:"aioseo",icon:"aioseo aioseo-element-menu-icon aioseo-element-menu-icon-"+window.elementor.getPreferences("ui_theme"),title:"All in One SEO",type:"page",callback:()=>{try{window.$e.routes.run("panel/page-settings/aioseo")}catch{window.$e.routes.run("panel/page-settings/settings"),window.$e.routes.run("panel/page-settings/aioseo")}}},"more"),window.elementor.once("preview:loaded",function(){window.$e.components.get("panel/elements").addTab("aioseo",{title:"AIOSEO"})}),window.elementor.hooks.addFilter("panel/elements/regionViews",e=>(e.aioseo={region:e.global.region,view:window.Marionette.ItemView.extend({template:!1,id:"elementor-panel-aioseo",className:"aioseo-elementor aioseo-sidebar-panel",initialize(){document.getElementById("elementor-panel-elements-search-area").hidden=!0},onShow(){d("#elementor-panel-aioseo")},onDestroy(){document.getElementById("elementor-panel-elements-search-area").hidden=!1}}),options:{}},e))},d=e=>{const o=document.querySelector(e);o.classList.add("edit-post-sidebar","aioseo-elementor-panel"),o.appendChild(document.createElement("div"));const i=h({history:y(),routes:[{path:"/",component:s}]});let t=f({name:"Standalone/Elementor",data(){return{tableContext:window.aioseo.currentPost.context,screenContext:"sidebar"}},render:()=>g(s)});t=E(t),t=b(t),t=v(t),t.use(i),i.app=t,$(t,i),H(),t.config.globalProperties.$truSeo=new I,t.mount(`${e} > div`),c()},u=()=>{M(),L()};let w=!1;window.elementor&&(setTimeout(u),w=!0);(function(e){w||e(window).on("elementor:init",()=>{window.elementor.on("panel:init",()=>{setTimeout(u)})})})(window.jQuery);
