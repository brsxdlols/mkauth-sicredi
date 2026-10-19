<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/jobs/SICREDIAPI/conciliar_tarifas_sicredi.php');
$src=substr($src,strpos($src,'declare(strict_types=1);'));
$marker=strpos($src,'// Associa a tarifa');
$head=substr($src,0,$marker);
$head=str_replace('function tarifaMovimentos(', 'function realTarifaMovimentos(', $head);
$head=str_replace("require __DIR__ . '/enviar_baixa_manual_sicredi.php';", "require dirname(__DIR__) . '/jobs/SICREDIAPI/enviar_baixa_manual_sicredi.php';", $head);
$argv=[];
eval($head);
function check($condition,$msg){if(!$condition)throw new RuntimeException($msg);}
function tarifaMovimentos(array $c,string $day):array{
    global $fail;
    if($fail)throw new RuntimeException('Falha simulada');
    if($day!==date('Y-m-d'))return [];
    $items=[];
    foreach([[1,0.93],[2,0.77],[3,0.80],[3,0.80],[4,0.93],[5,0.93]] as $entry){
        $items[]=['agencia'=>'0001','posto'=>'01','beneficiario'=>'00001','nossoNumero'=>sprintf('%09d',$entry[0]),'dataMovimento'=>$day,'dataLancamento'=>$day,'valorMovimento'=>$entry[1],'tipoMovimento'=>'DEBITO','descMovimento'=>'TARIFA ENTRADA/LIQUIDACAO'];
    }
    return $items;
}
$mysqli->query("CREATE TEMPORARY TABLE sis_lanc (id INT PRIMARY KEY,id_empresa VARCHAR(30),nossonum VARCHAR(20),login VARCHAR(30),tarifa_paga DECIMAL(15,2),datapag DATETIME,status VARCHAR(20),coletor VARCHAR(30),deltitulo INT) ENGINE=InnoDB");
$mysqli->query("CREATE TEMPORARY TABLE sis_caixa (id INT AUTO_INCREMENT PRIMARY KEY,uuid_caixa VARCHAR(40),usuario VARCHAR(30),data DATE,historico VARCHAR(255),complemento TEXT,entrada DECIMAL(15,2),saida DECIMAL(15,2),tipomov VARCHAR(20),planodecontas VARCHAR(40)) ENGINE=InnoDB");
$mysqli->query("CREATE TEMPORARY TABLE sis_notificacoes (dados TEXT,servico VARCHAR(30),data DATETIME) ENGINE=InnoDB");
$mysqli->query("CREATE TEMPORARY TABLE sis_boleto (id INT PRIMARY KEY,agencia VARCHAR(10),codigo_cliente VARCHAR(10),ponto_venda VARCHAR(10),token VARCHAR(20),token_bcash VARCHAR(20),nome VARCHAR(40),banco VARCHAR(40),utilizar VARCHAR(20)) ENGINE=InnoDB");
$mysqli->query("INSERT INTO sis_boleto VALUES (7000,'0001','00001','01','teste','teste','SICREDI','sicrediapi.php','bancoapi')");
for($id=1;$id<=5;$id++){
    $coletor=$id===5?'manual':'sicrediapi';$nosso=sprintf('%09d',$id);
    $mysqli->query("INSERT INTO sis_lanc VALUES ($id,'$id','$nosso','teste$id',0.99,NOW(),'pago','$coletor',0)");
    if($id===4)continue;
    $d=json_encode(['agencia'=>'0001','posto'=>'01','beneficiario'=>'00001','nossoNumero'=>$nosso,'dataEvento'=>date('Y-m-d'),'movimento'=>'LIQUIDACAO_COMPE_H5','idTituloEmpresa'=>'MKAUTH'.$id.'GGGG']);
    $q=$mysqli->prepare("INSERT INTO sis_notificacoes VALUES (?,'sicredi',NOW())");$q->bind_param('s',$d);$q->execute();
}
$mysqli->query("INSERT INTO sis_caixa (usuario,data,historico,entrada,saida) VALUES ('sicrediapi',CURDATE(),'Tarifa Sicredi do titulo 1 / teste1',0,0.99)");
$body=substr($src,$marker);
$body=str_replace('CREATE TABLE IF NOT EXISTS sicredi_tarifa_api_log','CREATE TEMPORARY TABLE IF NOT EXISTS sicredi_tarifa_api_log',$body);
$body=str_replace(['exit(2);','exit(0);','exit($errors ? 3 : 0);'],['return 2;','return 0;','return ($errors ? 3 : 0);'],$body);
$days=1;$apply=false;$fail=false;
eval($body);
check((float)$mysqli->query('SELECT tarifa_paga FROM sis_lanc WHERE id=1')->fetch_row()[0]===0.99,'dry-run alterou titulo');
$apply=true;
check(eval($body)===0,'apply falhou');
check((float)$mysqli->query('SELECT tarifa_paga FROM sis_lanc WHERE id=1')->fetch_row()[0]===0.93,'tarifa 1 incorreta');
check((float)$mysqli->query('SELECT tarifa_paga FROM sis_lanc WHERE id=2')->fetch_row()[0]===0.77,'tarifa 2 nao respeitou banco');
check((int)$mysqli->query('SELECT COUNT(*) FROM sis_caixa')->fetch_row()[0]===2,'caixa duplicado ou ausente');
check((float)$mysqli->query('SELECT SUM(saida) FROM sis_caixa')->fetch_row()[0]===1.70,'saida de caixa incorreta');
foreach([3,4,5] as $id)check((float)$mysqli->query('SELECT tarifa_paga FROM sis_lanc WHERE id='.$id)->fetch_row()[0]===0.99,'alterou titulo sem evidencia unica');
check(eval($body)===0,'reexecucao falhou');
check((int)$mysqli->query('SELECT COUNT(*) FROM sis_caixa')->fetch_row()[0]===2,'reexecucao duplicou caixa');
check((float)$mysqli->query('SELECT tarifa_anterior FROM sicredi_tarifa_api_log WHERE titulo_id=1')->fetch_row()[0]===0.99,'perdeu tarifa anterior');
$fail=true;
check(eval($body)===2,'falha da API nao interrompeu');
echo "PASS: dry-run, duas tarifas distintas, atualizacao e criacao de caixa, evidencia, idempotencia, vinculo ambiguo/ausente, pagamento manual e falha da API. Apenas tabelas temporarias.\n";
