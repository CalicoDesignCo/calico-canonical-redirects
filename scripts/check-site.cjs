// Read-only public GET requests. No cookies, authentication, settings, or writes.
// Usage: node scripts/check-site.cjs https://example.com /menu/ test
const http=require('node:http');
const https=require('node:https');
const preferred=new URL(process.argv[2]||'https://example.com');
const pathname=process.argv[3]||'/';
const expectedMode=process.argv[4]||'test';
if(preferred.protocol!=='https:'||preferred.port||preferred.username||preferred.password||!pathname.startsWith('/')||pathname.startsWith('//')||!['test','live'].includes(expectedMode))throw Error('Provide an HTTPS website origin, absolute page path, and test or live.');
const alternate=preferred.hostname.startsWith('www.')?preferred.hostname.slice(4):'www.'+preferred.hostname;
const expected=new URL(pathname,preferred);expected.searchParams.set('utm_source','calico-test');expected.searchParams.set('gclid','example+123');
function get(url){return new Promise((resolve,reject)=>{
  const req=(url.protocol==='https:'?https:http).get(url,{headers:{'User-Agent':'Calico-Canonical-Redirect-Checker/1.0','Cache-Control':'no-cache'}},res=>{
    res.resume();resolve({status:res.statusCode,location:res.headers.location,by:res.headers['x-redirect-by']||'',cache:res.headers['cache-control']||''});
  });req.setTimeout(15000,()=>req.destroy(Error('Timeout')));req.on('error',reject);
});}
(async()=>{
  let failed=false;
  for(const scheme of ['http:','https:'])for(const host of [preferred.hostname,alternate]){
    let url=new URL(expected);url.protocol=scheme;url.hostname=host;
    console.log(`\nChecking ${url.href}`);
    const canonical=url.href===expected.href;let seen=new Set();let hops=0;
    try{
      while(true){
        if(seen.has(url.href)||hops>5)throw Error('Redirect loop or more than 5 hops');seen.add(url.href);
        const r=await get(url);console.log(`${r.status} ${r.location||''} ${r.by?'['+r.by+']':''}`);
        if(r.status>=300&&r.status<400&&r.location){
          if(hops===0&&!canonical&&r.status!== (expectedMode==='live'?301:302))console.log(`NOTE: First hop differs from expected ${expectedMode==='live'?301:302}; inspect the redirect owner (host/CDN/WordPress/plugin).`);
          const next=new URL(r.location,url);
          if(!['http:','https:'].includes(next.protocol)||![preferred.hostname,alternate].includes(next.hostname)||next.username||next.password||next.port)throw Error('Redirect leaves expected origins');
          url=next;++hops;continue;
        }
        if(url.href!==expected.href)throw Error('Final URL is not the preferred HTTPS URL (scheme, host, path, or query mismatch)');
        if(r.status!==200)throw Error(`Expected a real page returning 200; received ${r.status}`);
        if(canonical&&hops!==0)throw Error('Preferred HTTPS URL unexpectedly redirected');
        console.log(`PASS: final URL exact; ${hops} redirect(s).`);break;
      }
    }catch(e){failed=true;console.error('FAIL: '+e.message);}
  }
  console.log('\nCertificates are checked by Node on each HTTPS connection. Results show this network vantage only; inspect browser/CDN behavior separately.');
  if(failed)process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
