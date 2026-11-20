import http from 'node:http';
import {createReadStream,statSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {resolve,extname,sep} from 'node:path';
const root=fileURLToPath(new URL('./dist/',import.meta.url));
const types={'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'text/javascript; charset=utf-8','.json':'application/json','.jpg':'image/jpeg','.png':'image/png','.svg':'image/svg+xml','.woff2':'font/woff2'};
const port=Number(process.env.PORT||4173);
http.createServer((req,res)=>{let name;try{name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);}catch{res.writeHead(400);res.end();return;}const path=resolve(root,`.${name==='/'?'/index.html':name}`);if(!path.startsWith(root.endsWith(sep)?root:root+sep)){res.writeHead(403);res.end();return;}try{const stat=statSync(path);if(!stat.isFile())throw new Error('Not a file');res.writeHead(200,{'Content-Type':types[extname(path)]||'application/octet-stream','Cache-Control':'no-cache','Content-Length':stat.size});createReadStream(path).pipe(res);}catch{res.writeHead(404);res.end('Não encontrado');}}).listen(port,'127.0.0.1',()=>console.log(`Local: http://localhost:${port}`));
