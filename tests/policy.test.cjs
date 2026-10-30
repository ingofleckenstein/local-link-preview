// SPDX-License-Identifier: MIT
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../resources/js/link-preview.js'),'utf8');
const context={window:{localLinkPreviewConfig:{enabled:true}},localStorage:{getItem:()=>null},location:{href:'https://community.example/dashboard',hostname:'community.example'},URL,Map,Set};
vm.createContext(context);vm.runInContext(source.slice(0,source.indexOf('    async function post('))+'globalThis.api = {eligible, editorUrls};})();',context);
for(const url of ['https://community.example/u/alice/','/user/profile/home','/index.php?r=user%2Fprofile%2Fstream','#reply','javascript:alert(1)'])assert.equal(context.api.eligible(url),false,url);
for(const url of ['https://outside.example/story','https://outside.example/u/alice/','/s/group/'])assert.equal(context.api.eligible(url),true,url);
const urls=context.api.editorUrls({innerText:'https://community.example/u/alice/ https://outside.example/wiki/Test_(example). (https://outside.example/next)',querySelectorAll:()=>[]});
assert.equal(JSON.stringify(urls),JSON.stringify(['https://outside.example/wiki/Test_(example)','https://outside.example/next']));
console.log('PASS: client profile exclusion, anchor filtering and balanced URL punctuation.');
