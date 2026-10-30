// SPDX-License-Identifier: MIT
// Run against a separate headless Chromium started with --remote-debugging-port=9241.
const fs = require('node:fs'), http = require('node:http'), assert = require('node:assert/strict');
(async () => {
const script = fs.readFileSync(require('node:path').join(__dirname, '../resources/js/link-preview.js'), 'utf8');
const server = http.createServer((request, response) => {
 if(request.url === '/pixel.png') {response.setHeader('Content-Type','image/png');response.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS9sAAAAASUVORK5CYII=','base64'));return;}
 const origin = 'http://127.0.0.1:' + server.address().port;
 response.setHeader('Content-Type','text/html; charset=utf-8');
 response.end(`<!doctype html><html><body><form><div id="contentForm_message"><div contenteditable="true"></div></div></form><div class="wall-stream"><div class="wall-entry"><div class="wall-entry-content" id="entry1"><div data-ui-widget="post.Post" id="post-content-1"><a href="${origin}/u/alice/">@Alice</a><p><a href="https://outside.example/story">Website</a></p></div></div></div><div class="wall-entry"><div class="wall-entry-content" id="entry2"><div data-ui-widget="post.Post" id="post-content-2"><a href="${origin}/u/bob/">@Bob</a></div></div></div></div>
 <script>window.calls=[];window.removed=false;window.preference=${!request.url.includes('off=1')};window.localLinkPreviewConfig={enabled:true,showPreviews:${!request.url.includes('off=1')},userId:123,endpoint:'/fetch',preferenceEndpoint:'/preference',selectionEndpoint:'/selection',csrfParam:'csrf',csrfToken:'test',maxPreviews:1};window.fetch=async function(endpoint,options){const values=Object.fromEntries(new URLSearchParams(options.body));window.calls.push({endpoint,values});let result;if(endpoint==='/preference'){window.preference=values.enabled==='1';result={enabled:window.preference};}else if(endpoint==='/selection'){window.removed=values.disabled==='1';result={disabled:window.removed};}else{await new Promise(resolve=>setTimeout(resolve,250));result=window.removed&&values.postId?{disabled:true,canEdit:true}:{url:values.url,title:'Example title',siteName:'Example',description:'Example description',imageUrl:'/pixel.png',canEdit:!!values.postId};}return new Response(JSON.stringify(result),{status:200,headers:{'Content-Type':'application/json'}});};</script><script>${script}</script></body></html>`);
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
let ws;try {
const port=process.env.DEBUG_PORT||9241;
let tab;for(let attempt=0;attempt<30;attempt++){try{tab=await(await fetch(`http://127.0.0.1:${port}/json/new?about:blank`,{method:'PUT'})).json();break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}if(!tab)throw new Error('Browser not ready');
ws=new WebSocket(tab.webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
let id=0;const pending=new Map();ws.addEventListener('message',event=>{const message=JSON.parse(event.data);if(pending.has(message.id)){pending.get(message.id)(message);pending.delete(message.id);}});
const send=(method,params={})=>new Promise(resolve=>{pending.set(++id,resolve);ws.send(JSON.stringify({id,method,params}));});
await send('Page.enable');await send('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/'});
await new Promise(resolve=>setTimeout(resolve,700));
const result=await send('Runtime.evaluate',{awaitPromise:true,returnByValue:true,expression:`(async()=>{
const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));const checks=[];const check=(ok,label)=>{if(!ok)throw new Error(label);checks.push(label);};
const entry=document.getElementById('entry1'), editor=document.querySelector('[contenteditable]');
check(window.calls.filter(c=>c.endpoint==='/fetch').every(c=>c.values.url==='https://outside.example/story'),'internal mentions never fetched');
check(!document.querySelector('#entry2 .llp-post-previews'),'profile-only post has no preview');
check(entry.querySelector('.llp-card img')?.getAttribute('src')==='/pixel.png','external card uses local image');
check(!document.querySelector('input[name="localLinkPreviewUrl"]'),'untouched composer not modified');
let preference=document.querySelector('.llp-preferences input');preference.click();await wait(100);
check(!window.preference&&!document.querySelector('.llp-post-previews'),'personal off removes previews');
preference.click();await wait(350);check(!!entry.querySelector('.llp-card'),'personal on restores previews');
entry.querySelector('.llp-post-controls button').click();check(entry.querySelector('.llp-post-cards').hidden,'individual preview can be hidden');entry.querySelector('.llp-post-controls button').click();await wait(20);
[...entry.querySelectorAll('button')].find(b=>b.textContent==='Vorschau für alle entfernen').click();await wait(350);
check(window.removed&&![...entry.querySelectorAll('.llp-card')].length,'author removal is saved');
[...entry.querySelectorAll('button')].find(b=>b.textContent==='Vorschau für diesen Beitrag aktivieren').click();await wait(350);check(!!entry.querySelector('.llp-card'),'author can restore preview');
editor.textContent='https://outside.example/new';editor.dispatchEvent(new Event('input'));await wait(450);
[...document.querySelectorAll('.llp-editor-preview button')].find(b=>b.textContent==='Vorschau entfernen').click();await wait(350);
check(document.querySelector('input[name="localLinkPreviewUrl"]').value==='__disabled__'&&!document.querySelector('.llp-editor-preview .llp-card'),'remove works while loading');
editor.textContent+=' https://outside.example/another';editor.dispatchEvent(new Event('input'));await wait(450);
check(document.querySelector('input[name="localLinkPreviewUrl"]').value==='__disabled__'&&!document.querySelector('.llp-editor-preview .llp-card'),'adding another link keeps preview disabled');
[...document.querySelectorAll('.llp-editor-preview button')].find(b=>b.textContent==='Vorschau aktivieren').click();await wait(350);check(!!document.querySelector('.llp-editor-preview .llp-card'),'composer preview can be restored');
check(window.calls.every(c=>c.values.csrf==='test'),'all changes include CSRF token');return checks;
})()`});
if(result.result.exceptionDetails)throw new Error(JSON.stringify(result.result.exceptionDetails));
const checks=result.result.result.value;assert(Array.isArray(checks)&&checks.length>=12);console.log('PASS browser: '+checks.join('; '));
await send('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/?off=1'});
await new Promise(resolve=>setTimeout(resolve,500));
const off=await send('Runtime.evaluate',{returnByValue:true,expression:'({calls:window.calls.length,control:!!document.querySelector(".llp-preferences input"),checked:document.querySelector(".llp-preferences input")?.checked})'});
assert.deepEqual(off.result.result.value,{calls:0,control:true,checked:false});
console.log('PASS browser: saved personal off starts without requests and keeps the reenable control visible.');
await send('Browser.close');
} finally {if(ws)ws.close();server.closeAllConnections();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
