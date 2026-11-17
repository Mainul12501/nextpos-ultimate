import{bs as ut,bD as ct,bH as pt,r as c,c2 as L,o as mt,j as f,c as p,e as w,m as s,p as d,q as _,l as o,bF as i,y as a,G as V,H as Q,I as k,k as n,bC as _t,J as vt,K as lt,x as yt,bW as j,bw as W,bO as ft,bS as bt}from"../app.1KxAZIRc.js";import{_ as ht}from"./PageHeader.BewrDvd3.js";import{_ as gt}from"./DateRangePicker.Cld4llGy.js";import{_ as xt}from"./ViewCurrencySelect.BqDoIZtn.js";import{e as wt,a as $t}from"./exporters.CmQ_kk3e.js";import{P as Tt}from"./PrinterOutlined.DYoTA0RZ.js";import{F as St}from"./FileExcelOutlined.Bez0tGw8.js";import{F as kt}from"./FilePdfOutlined.C_77AK41.js";import{N as Dt}from"./NumberOutlined.DRK0-yzQ.js";import{D as Ct}from"./DollarOutlined.CMcZUVEt.js";const Pt={class:"page"},qt={class:"kpi-inner"},It={class:"kpi-text"},zt={class:"kpi-value"},Ot={key:1,class:"summary-table-wrap"},Rt={class:"summary-table"},Nt={style:{width:"55%"}},Et={class:"num",style:{width:"15%"}},Yt={class:"num",style:{width:"30%"}},Ft={class:"group-row"},Gt={colspan:"3"},Ut={class:"num"},At={class:"num"},Mt={class:"subtotal-row"},Bt={class:"num bold"},Vt={class:"num bold"},Ht={class:"num bold"},Lt={class:"grand-row"},Qt={class:"num bold"},jt={class:"num bold"},Wt={class:"num bold"},Jt={__name:"ProductsSoldSummaryReport",setup(Kt){const{t:v}=ct(),{money:q,number:ot}=pt(),D=c([L().startOf("day"),L()]),N=c(void 0),E=c(void 0),Y=c(void 0),F=c(void 0),G=c(""),H=c(80),g=c(!0),U=c(""),I=c([]),y=c({quantity:0,total:0,items:0}),r=c({}),C=c({}),J=c([]),K=c([]),X=c([]),st=W(()=>[{value:"completed",label:a("Completed","Completed")},{value:"pending",label:a("Pending","Pending")},{value:"ordered",label:a("Ordered","Ordered")}]),Z=e=>(e||[]).map(t=>({value:t.id,label:t.name})),nt=W(()=>(K.value||[]).map(e=>({value:e.id,label:e.username})));function $(e){const t=Number(e||0);return Number.isInteger(t)?String(t):t.toFixed(2)}function A(e){return e.category_id===null?a("Uncategorized","Uncategorized"):e.category_name}const rt=W(()=>[{key:"items",label:a("Items_Sold","Items sold"),value:y.value.items,icon:ft,color:"#1677ff",tint:"rgba(22, 119, 255, 0.12)"},{key:"qty",label:a("Total_Qty","Total qty"),value:$(y.value.quantity),icon:Dt,color:"#13c2c2",tint:"rgba(19, 194, 194, 0.12)"},{key:"total",label:a("Grand_Total","Grand total"),value:q(y.value.total),icon:Ct,color:"var(--app-primary, #6d28d9)",tint:"rgba(var(--app-primary-rgb, 109, 40, 217), 0.12)"},{key:"trans",label:`${a("Transactions","Transactions")} / ${a("Cust_Count","Customers")}`,value:`${r.value.transaction_count??0} / ${r.value.customer_count??0}`,icon:bt,color:"#22c55e",tint:"rgba(34, 197, 94, 0.12)"}]),dt=()=>{var e,t;return{...(e=D.value)!=null&&e[0]?{from:D.value[0].format("YYYY-MM-DD")}:{},...(t=D.value)!=null&&t[1]?{to:D.value[1].format("YYYY-MM-DD")}:{},...N.value?{warehouse_id:N.value}:{},...E.value?{user_id:E.value}:{},...Y.value?{category_id:Y.value}:{},...F.value?{statut:F.value}:{},...G.value?{search:G.value}:{}}};async function T(){g.value=!0;try{const e=await yt.get("report/products_sold_summary",dt());I.value=Array.isArray(e.categories)?e.categories:[],y.value=e.totals||{quantity:0,total:0,items:0},r.value=e.meta||{},C.value=e.company||{},J.value=e.warehouses||[],K.value=e.users||[],X.value=e.categories_list||[]}catch(e){j.error((e==null?void 0:e.message)||v("InvalidData"))}finally{g.value=!1}}function tt(){const e=[];return I.value.forEach(t=>{t.items.forEach(m=>e.push({category_name:A(t),product_code:m.product_code||"",product_name:m.product_name,unit:m.unit||"",quantity:m.quantity,total:m.total})),e.push({category_name:A(t),product_code:"",product_name:a("Sub_Total","Sub Total"),unit:"",quantity:t.sub_total_quantity,total:t.sub_total})}),e.length&&e.push({category_name:"",product_code:"",product_name:a("Grand_Total","Grand Total"),unit:"",quantity:y.value.quantity,total:y.value.total}),e}const et=()=>[{title:v("Categorie"),dataIndex:"category_name"},{title:v("Code"),dataIndex:"product_code"},{title:v("ProductName"),dataIndex:"product_name"},{title:a("Unit","Unit"),dataIndex:"unit"},{title:v("Quantity"),dataIndex:"quantity"},{title:v("Total"),dataIndex:"total",exportValue:e=>q(e.total)}];async function at(e){U.value=e;try{const t=`products-sold-summary_${r.value.from}_${r.value.to}`;e==="excel"?await wt(t,et(),tt()):await $t(a("products_sold_summary","Products Sold Summary"),et(),tt())}catch(t){j.error((t==null?void 0:t.message)||v("Gen_Export_Failed","Export failed"))}finally{U.value=""}}function it(){const e=window.open("","_blank","width=420,height=640");if(!e){j.error(v("Gen_Allow_Popups_To_Print","Please allow popups to print"));return}const t=u=>String(u??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"),m=Number(H.value)||80,S=u=>ot(u),z=r.value.day_status==="open"?a("Day_is_still_opened","Day is still opened"):r.value.day_status==="closed"?a("Day_is_closed","Day is closed"):"",h=(u,l)=>`<div class="kv"><span class="k">${t(u)}</span><span class="s">:</span><span class="v">${t(l)}</span></div>`;let b="";I.value.forEach(u=>{b+=`<div class="cat">${t(A(u))}</div>`,b+='<table class="items">',u.items.forEach(l=>{b+=`<tr>
        <td class="name">${t(l.product_name)}</td>
        <td class="qty">${t($(l.quantity))}</td>
        <td class="amt">${t(S(l.total))}</td>
      </tr>`}),b+=`<tr class="sub">
        <td class="name">${t(a("Sub_Total","Sub Total"))}</td>
        <td class="qty">${t($(u.sub_total_quantity))}</td>
        <td class="amt">${t(S(u.sub_total))}</td>
      </tr>`,b+="</table>"});const P=r.value.from===r.value.to?r.value.from:`${r.value.from} — ${r.value.to}`,M=L().format("DD-MM-YYYY HH:mm:ss"),O=e.document;O.open(),O.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${t(a("Day_Report","Day Report"))}</title>
    <style>
      @page { size: ${m}mm auto; margin: 0; }
      @media print { body, body * { visibility: visible !important; } }
      * { box-sizing: border-box; }
      body {
        width: ${m}mm;
        margin: 0;
        padding: 3mm 2mm;
        font-family: "Courier New", Courier, monospace;
        font-size: 11px;
        line-height: 1.35;
        color: #000;
        background: #fff;
      }
      .center { text-align: center; }
      .company { font-weight: bold; font-size: 12px; }
      .rule { border-top: 1px dashed #000; margin: 4px 0; }
      .title { font-weight: bold; font-size: 14px; margin: 4px 0 6px; }
      .kv { display: flex; }
      .kv .k { flex: 0 0 42%; text-transform: uppercase; }
      .kv .s { flex: 0 0 4%; }
      .kv .v { flex: 1 1 auto; }
      .banner { text-align: center; font-weight: bold; text-transform: uppercase; margin: 2px 0; }
      .section { font-weight: bold; text-transform: uppercase; margin: 6px 0 2px; }
      .cat { font-weight: bold; text-decoration: underline; margin-top: 6px; }
      table.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
      table.items td { vertical-align: top; padding: 1px 0; word-wrap: break-word; }
      td.name { width: 58%; }
      td.qty  { width: 12%; text-align: right; padding-right: 2mm; }
      td.amt  { width: 30%; text-align: right; }
      tr.sub td { border-top: 1px solid #000; font-weight: bold; padding-top: 2px; }
      .grand { display: flex; justify-content: space-between; font-weight: bold;
               font-size: 12px; border-top: 1px solid #000; border-bottom: 1px solid #000;
               padding: 3px 0; margin-top: 6px; }
      .foot { text-align: center; margin-top: 8px; font-size: 10px; }
    </style>
  </head>
  <body>
    <div class="center company">${t(C.value.name)}</div>
    <div class="center">${t(C.value.full_address||C.value.address)}</div>
    <div class="center">${t(C.value.email)}</div>
    <div class="center">${t(C.value.phone)}</div>

    <div class="title">${t(a("Day_Report","Day Report"))}</div>

    ${h(a("Till","Till"),r.value.till||v("All"))}
    ${h(a("Sales_Person","Sales person"),r.value.sales_person||v("All"))}
    ${h(a("Cust_Count","Cust. count"),r.value.customer_count??0)}
    ${h(a("Transactions","Transactions"),r.value.transaction_count??0)}
    ${h(a("Trans_Date","Trans. date"),P)}
    ${h(a("Printed_On","Printed on"),M)}
    ${h(a("Printed_By","Printed by"),r.value.printed_by||"")}

    ${z?`<div class="rule"></div><div class="banner">${t(z)}</div>`:""}
    <div class="rule"></div>

    <div class="section">${t(v("Sales"))}</div>
    ${b||'<div class="center">—</div>'}

    <div class="grand">
      <span>${t(a("Grand_Total","Grand Total"))}</span>
      <span>${t(S(y.value.total))}</span>
    </div>

    <div class="foot">${t(a("Items_Sold","Items sold"))}: ${t(y.value.items)} &nbsp;·&nbsp; ${t(a("Total_Qty","Total qty"))}: ${t($(y.value.quantity))}</div>
  </body>
</html>`),O.close(),e.focus();let B=!1;const R=()=>{if(!B){B=!0;try{e.close()}catch{}}};try{e.onafterprint=R}catch{}setTimeout(()=>{try{e.print()}catch{}setTimeout(R,6e4)},400)}return mt(T),(e,t)=>{const m=f("a-select"),S=f("a-button"),z=f("a-space"),h=f("a-input-search"),b=f("a-card"),P=f("a-typography-text"),M=f("a-spin"),O=f("a-col"),B=f("a-row"),R=f("a-tag"),u=f("a-empty");return p(),w("div",Pt,[s(ht,{title:i(a)("products_sold_summary","Products Sold Summary"),breadcrumb:[e.$t("Reports"),i(a)("products_sold_summary","Products Sold Summary")]},{actions:d(()=>[s(z,{wrap:""},{default:d(()=>[s(xt),s(m,{value:H.value,"onUpdate:value":t[0]||(t[0]=l=>H.value=l),style:{width:"100px"},options:[{value:58,label:"58 mm"},{value:80,label:"80 mm"},{value:88,label:"88 mm"}]},null,8,["value"]),s(S,{type:"primary",disabled:g.value,onClick:it},{icon:d(()=>[s(i(Tt))]),default:d(()=>[_(" "+o(i(a)("Print_Receipt","Print Receipt")),1)]),_:1},8,["disabled"]),s(S,{loading:U.value==="excel",disabled:g.value,onClick:t[1]||(t[1]=l=>at("excel"))},{icon:d(()=>[s(i(St))]),default:d(()=>[_(" "+o(e.$t("Export_Excel")),1)]),_:1},8,["loading","disabled"]),s(S,{loading:U.value==="pdf",disabled:g.value,onClick:t[2]||(t[2]=l=>at("pdf"))},{icon:d(()=>[s(i(kt))]),default:d(()=>[_(" "+o(e.$t("Export_PDF")),1)]),_:1},8,["loading","disabled"])]),_:1})]),_:1},8,["title","breadcrumb"]),s(b,{size:"small",style:{"margin-bottom":"16px"}},{default:d(()=>[s(z,{wrap:""},{default:d(()=>[s(gt,{value:D.value,"onUpdate:value":t[3]||(t[3]=l=>D.value=l),"allow-clear":"",onChange:T},null,8,["value"]),s(m,{value:N.value,"onUpdate:value":t[4]||(t[4]=l=>N.value=l),style:{width:"180px"},"allow-clear":"","show-search":"","option-filter-prop":"label",placeholder:i(a)("Till","Till"),options:Z(J.value),onChange:T},null,8,["value","placeholder","options"]),s(m,{value:E.value,"onUpdate:value":t[5]||(t[5]=l=>E.value=l),style:{width:"180px"},"allow-clear":"","show-search":"","option-filter-prop":"label",placeholder:i(a)("Sales_Person","Sales person"),options:nt.value,onChange:T},null,8,["value","placeholder","options"]),s(m,{value:Y.value,"onUpdate:value":t[6]||(t[6]=l=>Y.value=l),style:{width:"180px"},"allow-clear":"","show-search":"","option-filter-prop":"label",placeholder:e.$t("Categorie"),options:Z(X.value),onChange:T},null,8,["value","placeholder","options"]),s(m,{value:F.value,"onUpdate:value":t[7]||(t[7]=l=>F.value=l),style:{width:"150px"},"allow-clear":"",placeholder:i(a)("Sale_Status","Sale status"),options:st.value,onChange:T},null,8,["value","placeholder","options"]),s(h,{value:G.value,"onUpdate:value":t[8]||(t[8]=l=>G.value=l),style:{width:"220px"},"allow-clear":"",placeholder:e.$t("Search"),onSearch:T},null,8,["value","placeholder"])]),_:1})]),_:1}),s(B,{gutter:[16,16],style:{"margin-bottom":"16px"}},{default:d(()=>[(p(!0),w(V,null,Q(rt.value,l=>(p(),k(O,{key:l.key,xs:12,sm:12,md:6},{default:d(()=>[s(b,{size:"small",class:"kpi-card"},{default:d(()=>[n("div",qt,[n("div",{class:"kpi-icon",style:_t({background:l.tint,color:l.color})},[(p(),k(vt(l.icon)))],4),n("div",It,[s(P,{type:"secondary",class:"kpi-label"},{default:d(()=>[_(o(l.label),1)]),_:2},1024),n("div",zt,[g.value?(p(),k(M,{key:0,size:"small"})):(p(),w(V,{key:1},[_(o(l.value),1)],64))])])])]),_:2},1024)]),_:2},1024))),128))]),_:1}),s(b,{size:"small"},{title:d(()=>[_(o(i(a)("Day_Report","Day Report"))+" ",1),r.value.day_status==="open"?(p(),k(R,{key:0,color:"warning",style:{"margin-left":"8px"}},{default:d(()=>[_(o(i(a)("Day_is_still_opened","Day is still opened")),1)]),_:1})):r.value.day_status==="closed"?(p(),k(R,{key:1,style:{"margin-left":"8px"}},{default:d(()=>[_(o(i(a)("Day_is_closed","Day is closed")),1)]),_:1})):lt("",!0)]),extra:d(()=>[s(P,{type:"secondary",style:{"font-size":"12px"}},{default:d(()=>[_(o(i(a)("Till","Till"))+": "+o(r.value.till||e.$t("All"))+" · "+o(i(a)("Sales_Person","Sales person"))+": "+o(r.value.sales_person||e.$t("All"))+" · "+o(r.value.from)+" → "+o(r.value.to),1)]),_:1})]),default:d(()=>[s(M,{spinning:g.value},{default:d(()=>[!I.value.length&&!g.value?(p(),k(u,{key:0,description:e.$t("NodataAvailable"),style:{padding:"48px 0"}},null,8,["description"])):(p(),w("div",Ot,[n("table",Rt,[n("thead",null,[n("tr",null,[n("th",Nt,o(e.$t("ProductName")),1),n("th",Et,o(e.$t("Quantity")),1),n("th",Yt,o(e.$t("Total")),1)])]),(p(!0),w(V,null,Q(I.value,l=>(p(),w("tbody",{key:l.category_id??"none"},[n("tr",Ft,[n("th",Gt,o(A(l)),1)]),(p(!0),w(V,null,Q(l.items,x=>(p(),w("tr",{key:(l.category_id??0)+"-"+x.product_id+"-"+x.product_name},[n("td",null,[_(o(x.product_name)+" ",1),x.product_code?(p(),k(P,{key:0,type:"secondary",style:{display:"block","font-size":"12px"}},{default:d(()=>[_(o(x.product_code),1)]),_:2},1024)):lt("",!0)]),n("td",Ut,[_(o($(x.quantity))+" ",1),s(P,{type:"secondary",style:{"font-size":"12px"}},{default:d(()=>[_(o(x.unit),1)]),_:2},1024)]),n("td",At,o(i(q)(x.total)),1)]))),128)),n("tr",Mt,[n("td",Bt,o(i(a)("Sub_Total","Sub Total")),1),n("td",Vt,o($(l.sub_total_quantity)),1),n("td",Ht,o(i(q)(l.sub_total)),1)])]))),128)),n("tfoot",null,[n("tr",Lt,[n("td",Qt,o(i(a)("Grand_Total","Grand Total")),1),n("td",jt,o($(y.value.quantity)),1),n("td",Wt,o(i(q)(y.value.total)),1)])])])]))]),_:1},8,["spinning"])]),_:1})])}}},de=ut(Jt,[["__scopeId","data-v-265df9d2"]]);export{de as default};
