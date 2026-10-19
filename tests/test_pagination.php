<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/addons/rel_conciliacao_sicredi/index.php');
$begin=strpos($src,'$perPage = (int)');$end=strpos($src,'function sicredi_page_url',$begin);
$pagination=substr($src,$begin,$end-$begin);
$rows=range(1,23);$start='2026-10-01';$end='2026-10-19';$origin='conciliadas';$search='cliente & nome';$order='data_asc';
foreach([[[],10,1,10],[['por_pagina'=>20],20,1,20],[['por_pagina'=>50],50,1,23],[['por_pagina'=>100],100,1,23],[['pagina'=>3],10,3,3],[['pagina'=>999],10,3,3],[['pagina'=>-1],10,1,10],[['por_pagina'=>7],10,1,10]] as $test){
    $_GET=$test[0];eval($pagination);
    if($perPage!==$test[1]||$page!==$test[2]||count($pageRows)!==$test[3]||$pageFilters['busca']!==$search||$rowCount!==23)throw new RuntimeException('Paginacao incorreta');
}
$rows=[];$_GET=['pagina'=>999];eval($pagination);
if($page!==1||$pageCount!==1||$pageRows!==[])throw new RuntimeException('Pagina vazia incorreta');
echo "PASS: 10/20/50/100, ultima pagina, limites, filtros e lista vazia.\n";
