<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganttist — Benchmark</title>
    <style>
        :root{font-family:Inter,Segoe UI,system-ui,sans-serif;color:#182034;background:#f5f6fa}*{box-sizing:border-box}body{margin:0}.top{padding:24px 28px;background:#0b1020;color:#fff}.top h1{margin:0 0 6px;font-size:24px}.top p{margin:0;color:#aeb5c8;font-size:13px}.panel{margin:22px 28px;padding:18px;background:#fff;border:1px solid #e3e5ed;border-radius:12px;display:flex;align-items:end;gap:12px;flex-wrap:wrap}.field{display:flex;flex-direction:column;gap:6px;font-size:11px;font-weight:700;color:#626a7c}.field select,.run{height:38px;border:1px solid #dfe2ea;border-radius:8px;padding:0 11px;background:#fff;color:#182034}.run{background:#6557dc;border:0;color:#fff;font-weight:700;cursor:pointer}.hint{font-size:11px;color:#7c8495;margin-left:auto}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:0 28px 14px}.metric{padding:14px;background:#fff;border:1px solid #e3e5ed;border-radius:10px}.metric small{display:block;color:#8991a1;font-size:10px}.metric b{font-size:22px}.viewport{margin:0 28px 28px;background:#fff;border:1px solid #dfe2ea;border-radius:12px;overflow:auto;height:calc(100vh - 260px);position:relative}.grid{position:relative;min-width:1050px;background:repeating-linear-gradient(90deg,#fff 0,#fff 41px,#f0f1f5 42px)}.row{height:28px;border-bottom:1px solid #f0f1f4;position:absolute;left:0;right:0}.name{position:absolute;left:0;top:0;width:320px;height:27px;background:#fff;padding:6px 10px;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-right:1px solid #e5e7ed}.bar{position:absolute;top:6px;height:15px;border-radius:4px;background:#7566e8;opacity:.9}.warning{margin:0 28px 14px;padding:10px 12px;border-radius:8px;background:#fff7e6;color:#8f6b22;font-size:11px}
    </style>
</head>
<body>
<header class="top"><h1>Ganttist — Benchmark de volume</h1><p>Dados sintéticos no navegador. Mede o mesmo princípio do renderer: lista virtual, barras e colunas em janela.</p></header>
<section class="panel"><label class="field">Tamanho<select id="size"><option value="500">500 tarefas</option><option value="2000" selected>2.000 tarefas</option><option value="5000">5.000 tarefas</option></select></label><button class="run" id="run">Gerar e renderizar</button><span class="hint">Role até o fim e registre os números no gate de release.</span></section>
<div class="warning">Ambiente de medição manual: registre navegador, dispositivo e resultados; os dados são descartados ao recarregar.</div>
<section class="metrics"><div class="metric"><small>TAREFAS</small><b id="count">—</b></div><div class="metric"><small>GERAÇÃO</small><b id="generation">—</b></div><div class="metric"><small>JANELA INICIAL</small><b id="rendering">—</b></div><div class="metric"><small>NÓS NO DOM</small><b id="nodes">—</b></div></section>
<main id="viewport" class="viewport"><div id="grid" class="grid"></div></main>
<script>
const $=id=>document.getElementById(id),ROW=28,OVERSCAN=12;let tasks=[];
const requestedSize=new URLSearchParams(location.search).get('size');if(['500','2000','5000'].includes(requestedSize??''))$('size').value=requestedSize;
function generate(size){return Array.from({length:size},(_,i)=>({id:i,title:`Tarefa sintética ${String(i+1).padStart(5,'0')}`,start:i%120,span:1+(i%10)}))}
function windowFor(){const viewport=$('viewport'),start=Math.max(0,Math.floor(viewport.scrollTop/ROW)-OVERSCAN),visible=Math.ceil(viewport.clientHeight/ROW),end=Math.min(tasks.length,start+visible+OVERSCAN*2);return {start,end}}
function renderWindow(){const before=performance.now(),range=windowFor(),fragment=document.createDocumentFragment();for(let i=range.start;i<range.end;i++){const task=tasks[i],row=document.createElement('div');row.className='row';row.style.top=`${i*ROW}px`;const name=document.createElement('div');name.className='name';name.textContent=`${task.title} · #${task.id}`;const bar=document.createElement('div');bar.className='bar';bar.style.left=`${330+task.start*42}px`;bar.style.width=`${task.span*42-7}px`;row.append(name,bar);fragment.append(row)}$('grid').replaceChildren(fragment);$('rendering').textContent=`${(performance.now()-before).toFixed(1)} ms`;$('nodes').textContent=(range.end-range.start).toLocaleString('pt-BR')}
function run(){const size=Number($('size').value),before=performance.now();tasks=generate(size);const generated=performance.now();$('grid').style.height=`${tasks.length*ROW}px`;$('viewport').scrollTop=0;renderWindow();$('count').textContent=tasks.length.toLocaleString('pt-BR');$('generation').textContent=`${(generated-before).toFixed(1)} ms`}
$('run').addEventListener('click',run);$('viewport').addEventListener('scroll',()=>requestAnimationFrame(renderWindow),{passive:true});run();
</script>
</body>
</html>
