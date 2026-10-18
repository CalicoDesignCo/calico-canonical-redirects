const {spawn} = require('node:child_process');
const http = require('node:http');
const net = require('node:net');
const path = require('node:path');
const assert = require('node:assert/strict');
const php = process.argv[2] || 'php';
function request(port, headers = {}, method = 'GET', uri = '/menu/?utm_source=meta&gclid=A%2BB&x=1&x=2') {
  return new Promise((resolve, reject) => {
    const req = http.request({hostname:'127.0.0.1', port, path:uri, method, headers:{Host:'www.example.com', ...headers}}, res => {
      let body=''; res.on('data',c=>body+=c); res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body}));
    }); req.on('error',reject); req.end();
  });
}
(async()=>{
  const port = await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const server=spawn(php,['-S',`127.0.0.1:${port}`,path.join(__dirname,'router.php')],{stdio:['ignore','pipe','pipe'],windowsHide:true});
  let logs='';server.stderr.on('data',c=>logs+=c);server.on('error',e=>logs+=e.message);
  let count=0;
  const target='https://example.com/menu/?utm_source=meta&gclid=A%2BB&x=1&x=2';
  async function test(label, headers, status, location, method='GET') {
    const result=await request(port,headers,method);
    assert.equal(result.status,status,label);assert.equal(result.headers.location,location,label);
    if(location){assert.match(result.headers['cache-control'],/no-store/);assert.equal(result.headers['x-redirect-by'],'Calico Canonical Redirects');}
    ++count;process.stdout.write(`PASS: ${label}\n`);
  }
  try {
    let ready=false;
    for(let i=0;i<60;i++){try{await request(port);ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
    if(!ready) throw Error('PHP test server failed: '+logs);
    await test('Preview emits no redirect',{},200,undefined);
    await test('Test emits actual 302 with exact query',{'X-Fixture-Mode':'test'},302,target);
    await test('Live emits actual 301',{'X-Fixture-Mode':'live'},301,target);
    await test('HTTPS alternate normalizes in one hop',{'X-Fixture-Mode':'test','X-Fixture-HTTPS':'1'},302,target);
    await test('Destination has no second plugin redirect',{'X-Fixture-Mode':'test','X-Fixture-HTTPS':'1',Host:'example.com'},200,undefined);
    await test('HEAD status and location',{'X-Fixture-Mode':'test'},302,target,'HEAD');
    await test('POST is untouched',{'X-Fixture-Mode':'live'},200,undefined,'POST');
    await test('Unrelated host is untouched',{'X-Fixture-Mode':'live',Host:'evil.com'},200,undefined);
    await test('Emergency disable works',{'X-Fixture-Mode':'live','X-Fixture-Disabled':'1'},200,undefined);
    await test('Admin bypass works',{'X-Fixture-Mode':'live','X-Fixture-Admin':'1'},200,undefined);
    await test('REST bypass works',{'X-Fixture-Mode':'live','X-Fixture-REST':'1'},200,undefined);
    await test('Trusted TLS proxy no loop',{'X-Fixture-Mode':'test','X-Fixture-Proxy':'1','X-Forwarded-Proto':'https',Host:'example.com'},200,undefined);
    await test('Spoofed forwarded HTTPS is ignored',{'X-Fixture-Mode':'test','X-Forwarded-Proto':'https',Host:'example.com'},302,target);
    console.log(`HTTP: ${count} checks passed (real PHP HTTP responses; WordPress boundaries stubbed).`);
  } finally {server.kill();}
})().catch(e=>{console.error(e);process.exitCode=1;});
