const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["chunks/jspdf.es.min.mpjpE3rU.js","app.1KxAZIRc.js"])))=>i.map(i=>d[i]);
import{_ as w,y as x,B as R,c2 as F}from"../app.1KxAZIRc.js";function A(){try{return R().exportSettings}catch{return{scope:"all",totals:!0,pdf_orientation:"landscape",filename_date:!1,pdf_meta:!1}}}function E(t){return t.filename_date?`_${F().format("YYYY-MM-DD")}`:""}function h(t){return String(t.title??t.key??"")}function y(t,e){if(typeof t.exportValue=="function")return t.exportValue(e);const d=t.dataIndex||t.key,n=d?e[d]:"";return n==null?"":typeof n=="object"?JSON.stringify(n):n}function f(t){return t.filter(e=>e.key!=="actions"&&e.key!=="image"&&e.exportable!==!1)}function C(t,e,d,n=x("Total","Total")){const o=f(t);return o.some(r=>r.sum)?o.map((r,u)=>{if(r.sum){const i=r.dataIndex??r.key,l=e.reduce((c,a)=>c+(Number(a==null?void 0:a[i])||0),0);return d(r,l)}return u===0?n:""}):null}const _=/[֐-׿؀-ۿ܀-ݏݐ-ݿࢠ-ࣿיִ-﷿ﹰ-﻿]/;function L(t,e,d){if(_.test(String(t??"")))return!0;const n=f(e);return n.some(o=>_.test(h(o)))?!0:d.some(o=>n.some(r=>_.test(String(y(r,o)??""))))}function z(t,e,d,{subtitle:n=[],landscape:o=!0,foot:r=null,rtl:u=!1}={}){const i=f(e),l=a=>String(a??"").replace(/[&<>"]/g,s=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"})[s]),c=Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(a=>a.outerHTML).join(`
`);return`<!doctype html><html${u?' dir="rtl"':""}><head>
    <base href="${window.location.origin}/" />
    <title>${l(t)}</title>
    ${c}
    <style>
      @page { size: A4 ${o?"landscape":"portrait"}; margin: 0.3cm; }
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 6px; font-size: 14px; }
      .print-sub { font-size: 11px; line-height: 1.5; margin-bottom: 10px; }
      table { width: 100%; border-collapse: collapse; }
      /* Cells may carry newlines (a variant product stacks one value per line);
         without pre-line the browser would collapse them onto one row. */
      th, td { border: 1px solid #ddd; padding: 6px 8px; font-size: 10px; text-align: ${u?"right":"left"}; white-space: pre-line; }
      th { background: #f5f5f5; font-weight: bold; }
      tr:nth-child(even) { background: #f9f9f9; }
    </style></head><body>
    <div class="print-header">${l(t)}</div>
    ${n.length?`<div class="print-sub">${n.map(l).join(" &nbsp;·&nbsp; ")}</div>`:""}
    <table>
      <thead><tr>${i.map(a=>`<th>${l(h(a))}</th>`).join("")}</tr></thead>
      <tbody>${d.map(a=>`<tr>${i.map(s=>`<td>${l(y(s,a))}</td>`).join("")}</tr>`).join("")}</tbody>
      ${r?`<tfoot><tr>${r.map(a=>`<td style="font-weight:bold;background:#f0f0f0">${l(a)}</td>`).join("")}</tr></tfoot>`:""}
    </table></body></html>`}const m="Vazirmatn",k="/fonts/Vazirmatn-Bold.ttf",D=["ar","fa","ur","he"];function j(t){try{return t.addFont(k,m,"normal"),t.addFont(k,m,"bold"),t.setFont(m,"normal"),m}catch{return"helvetica"}}function O(){return typeof document>"u"?!1:document.documentElement.dir==="rtl"?!0:D.includes(String(document.documentElement.lang||"").slice(0,2))}async function V(t,e,d,{foot:n=null}={}){const o=await w(()=>import("./xlsx.BGhuli8m.js"),[]),r=f(e),u=[r.map(h),...d.map(a=>r.map(s=>y(s,a))),...n?[n]:[]],i=o.utils.aoa_to_sheet(u),l=o.utils.book_new(),c=String(x("Report","Report")).replace(/[\\/?*[\]:]/g,"").slice(0,31)||"Report";o.utils.book_append_sheet(l,i,c),o.writeFile(l,`${t}${E(A())}.xlsx`)}async function M(t,e,d,{landscape:n=null,foot:o=null}={}){var T;const[{jsPDF:r},u]=await Promise.all([w(()=>import("./jspdf.es.min.mpjpE3rU.js"),__vite__mapDeps([0,1])),w(()=>import("./jspdf.plugin.autotable.CxxEjV4y.js"),[])]),i=u.default||u.autoTable,l=f(e),c=A(),a=n??c.pdf_orientation!=="portrait",s=new r({orientation:a?"landscape":"portrait"}),b=j(s),g=O(),S=g?s.internal.pageSize.getWidth()-14:14,$=g?{align:"right"}:void 0;s.setFontSize(14),s.text(String(t),S,15,$);let v=22;if(c.pdf_meta){let p="";try{p=((T=R().user)==null?void 0:T.company)||""}catch{}s.setFontSize(9),s.setTextColor(120),s.text(`${p?p+" — ":""}${F().format("YYYY-MM-DD HH:mm")}`,S,21,$),s.setTextColor(0),v=26}i(s,{head:[l.map(h)],body:d.map(p=>l.map(P=>String(y(P,p)))),startY:v,styles:{fontSize:8,font:b,halign:g?"right":"left"},headStyles:{fillColor:[109,40,217],font:b,fontStyle:"bold"},...o?{foot:[o.map(p=>String(p))],footStyles:{fillColor:[240,240,240],textColor:20,fontStyle:"bold",font:b},showFoot:"lastPage"}:{}}),s.save(`${String(t).replace(/\s+/g,"_")}${E(c)}.pdf`)}function I(t,e,d,{subtitle:n=[],landscape:o=!0,win:r=null,foot:u=null}={}){const i=r||window.open("","_blank");if(!i){window.alert(x("Gen_Allow_Popups_To_Print","Please allow popups to print"));return}const l=L(t,e,d);i.document.open(),i.document.write(z(t,e,d,{subtitle:n,landscape:o,foot:u,rtl:l})),i.document.close(),i.focus(),setTimeout(()=>{i.print(),i.close()},400)}export{M as a,A as b,V as e,I as p,C as t};
