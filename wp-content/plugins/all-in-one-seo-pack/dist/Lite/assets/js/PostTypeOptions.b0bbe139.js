import{u as C}from"./links.4e9269a4.js";import{B as P}from"./HighlightToggle.37f6159f.js";import{C as A}from"./index.cd7fac8b.js";import{C as k}from"./Tooltip.fc81232d.js";import{G as B,a as D}from"./Row.1358a527.js";import{r as p,o as l,c as g,a as o,b as _,w as r,t,g as c,f as m,F as N,h as S,d as y,n as w}from"./vue.runtime.esm-bundler.1bf81f69.js";import{_ as R}from"./_plugin-vue_export-helper.4292a25a.js";const L={setup(){return{rootStore:C()}},components:{BaseHighlightToggle:P,CoreAlert:A,CoreTooltip:k,GridColumn:B,GridRow:D},props:{type:{type:String,required:!0},options:{type:Object,required:!0},registeredPostTypes:Object,excluded:{type:Array,default(){return[]}}},data(){return{strings:{label:this.$t.__("Label:",this.$td),name:this.$t.__("Slug:",this.$td),noPostTypes:this.$t.__("No post types available.",this.$td),noTaxonomies:this.$t.__("No taxonomies available.",this.$td),noPostTypesDescription:this.$t.__("All post types are set to noindex or your site does not have any post types registered that are supported by this feature.",this.$td),noTaxonomiesDescription:this.$t.__("All taxonomies are set to noindex or your site does not have any taxonomies registered that are supported by this feature.",this.$td)}}},computed:{getRegisteredPostTypes(){return this.registeredPostTypes||this.rootStore.aioseo.postData},postTypes(){return this.getRegisteredPostTypes[this.type].filter(e=>!this.excluded.includes(e.name))}},methods:{emitInput(e){this.$emit("input",e)},updateValue(e,d){if(e){const u=this.options[this.type].included;u.push(d.name),this.options[this.type].included=u;return}const n=this.options[this.type].included.findIndex(u=>u===d.name);n!==-1&&this.options[this.type].included.splice(n,1)},getValue(e){return this.options[this.type].included.includes(e.name)},isActive(e){return this.options[this.type].included.findIndex(n=>n===e.name)!==-1}}},O={class:"aioseo-post-type-options-toggle"},G={class:"post-type-options-settings"},I={class:"aioseo-description"},U=o("br",null,null,-1);function $(e,d,n,u,a,i){const h=p("core-alert"),f=p("core-tooltip"),x=p("base-highlight-toggle"),T=p("grid-column"),b=p("grid-row");return l(),g("div",O,[o("div",G,[i.postTypes.length===0&&n.type==="postTypes"?(l(),_(h,{key:0,type:"blue"},{default:r(()=>[o("strong",null,t(a.strings.noPostTypes),1),c(" "+t(a.strings.noPostTypesDescription),1)]),_:1})):m("",!0),i.postTypes.length===0&&n.type==="taxonomies"?(l(),_(h,{key:1,type:"blue"},{default:r(()=>[o("strong",null,t(a.strings.noTaxonomies),1),c(" "+t(a.strings.noTaxonomiesDescription),1)]),_:1})):m("",!0),0<i.postTypes.length?(l(),_(b,{key:2},{default:r(()=>[(l(!0),g(N,null,S(i.postTypes,(s,v)=>(l(),_(T,{md:"6",key:v},{default:r(()=>[y(x,{size:"medium",active:i.isActive(s),name:s.name,type:"checkbox",modelValue:i.getValue(s),"onUpdate:modelValue":V=>i.updateValue(V,s)},{default:r(()=>[y(f,null,{tooltip:r(()=>[o("div",I,[c(t(a.strings.label)+" ",1),o("strong",null,t(s.label),1),U,c(" "+t(a.strings.name)+" ",1),o("strong",null,t(s.name),1)])]),default:r(()=>[o("span",{class:w(["icon dashicons",`${s.icon||"dashicons-admin-post"}`])},null,2)]),_:2},1024),c(" "+t(s.label),1)]),_:2},1032,["active","name","modelValue","onUpdate:modelValue"])]),_:2},1024))),128))]),_:1})):m("",!0)])])}const K=R(L,[["render",$]]);export{K as C};
