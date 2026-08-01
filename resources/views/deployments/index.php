<div x-data="{fps:{},errors:{},probe(id,port,csrf){this.errors[id]='';fetch('/deployments/fingerprint',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_csrf='+encodeURIComponent(csrf)+'&server_id='+id+'&port='+port}).then(r=>r.json()).then(r=>{if(r.ok)this.fps[id]=r.fingerprint;else this.errors[id]=r.error})}}" class="space-y-6">
 <div><h1 class="text-3xl font-bold">Instalación y sincronización</h1><p class="text-slate-400">Despliegues SSH como root, con huella verificada y PHP 8.5 configurable.</p></div>
 <div class="grid gap-5">
 <?php foreach($servers as $server):?>
  <article class="bg-slate-900 border border-slate-800 rounded-xl p-5">
   <div class="flex justify-between"><div><h2 class="text-xl font-bold"><?=htmlspecialchars($server['name'])?></h2><p class="text-slate-400"><?=htmlspecialchars($server['private_ip']?:$server['public_ip'])?> · <?=htmlspecialchars($server['region']??'')?></p></div><span class="<?=$server['ssh_configured']?'text-emerald-400':'text-amber-400'?>"><?=$server['ssh_configured']?'SSH configurado':'Pendiente SSH'?></span></div>
   <form method="post" action="/deployments/credentials" class="grid md:grid-cols-4 gap-3 mt-4">
    <?=$csrf->field()?><input type="hidden" name="server_id" value="<?=$server['id']?>">
    <input x-ref="port<?=$server['id']?>" type="number" min="1" max="65535" name="port" value="<?=$server['ssh_port']?:22?>" class="bg-slate-800 p-2 rounded" placeholder="Puerto">
    <input name="ssh_user" value="<?=htmlspecialchars($server['ssh_user']??'root')?>" class="bg-slate-800 p-2 rounded" placeholder="Usuario SSH (root permitido)">
    <button type="button" @click="probe(<?=$server['id']?>,$refs.port<?=$server['id']?>.value,'<?=rawurlencode($csrf->token())?>')" class="bg-indigo-700 rounded">Obtener huella</button>
    <input required name="fingerprint" x-model="fps[<?=$server['id']?>]" value="<?=htmlspecialchars($server['host_fingerprint']??'')?>" class="bg-slate-800 p-2 rounded" placeholder="Huella SSH confirmada">
    <textarea name="private_key" class="md:col-span-2 bg-slate-800 p-2 rounded" placeholder="Clave privada SSH (preferida)"></textarea>
    <input type="password" name="ssh_password" autocomplete="new-password" class="bg-slate-800 p-2 rounded" placeholder="Contraseña SSH alternativa">
    <button class="bg-cyan-700 rounded">Guardar conexión cifrada</button><p class="md:col-span-4 text-red-400" x-text="errors[<?=$server['id']?>]"></p>
   </form>
   <?php if($server['ssh_configured']):?><div class="flex flex-wrap gap-3 mt-4"><form method="post" action="/deployments/run"><?=$csrf->field()?><input type="hidden" name="server_id" value="<?=$server['id']?>"><input type="hidden" name="type" value="install"><button class="bg-emerald-700 px-4 py-2 rounded">Instalar paquetes y agente</button></form><form method="post" action="/deployments/run"><?=$csrf->field()?><input type="hidden" name="server_id" value="<?=$server['id']?>"><input type="hidden" name="type" value="sync"><button class="bg-slate-700 px-4 py-2 rounded">Sincronizar configuración</button></form><form method="post" action="/deployments/run" onsubmit="return confirm('La actualización conservará el estado actual y las sesiones activas. ¿Continuar?')"><?=$csrf->field()?><input type="hidden" name="server_id" value="<?=$server['id']?>"><input type="hidden" name="type" value="update"><button class="bg-amber-600 px-4 py-2 rounded">Actualizar balanceador</button></form></div><?php endif?>
  </article>
 <?php endforeach?>
 </div>
 <section><h2 class="text-xl font-bold mb-3">Historial</h2><div class="space-y-2"><?php foreach($history as $deployment):?><article class="bg-slate-900 border border-slate-800 rounded p-3 flex justify-between"><div><strong><?=htmlspecialchars($deployment['server_name'])?></strong> · <?=htmlspecialchars($deployment['type'])?><div class="text-xs text-red-400"><?=htmlspecialchars($deployment['error_message']?substr($deployment['error_message'],0,300):'')?></div></div><div class="text-right"><?=htmlspecialchars($deployment['status'])?><div class="text-xs text-slate-500"><?=$deployment['created_at']?></div></div></article><?php endforeach?></div></section>
</div>
