<?php
// Gráfica de barras verticales en SVG (una sola serie). $puntos: [['etiqueta','valor','detalle'], ...]
function graficoBarras(array $puntos, string $titulo): string {
    $n=count($puntos);
    if (!$n || max(array_column($puntos,'valor'))<=0) return '<p class="empty">Sin datos en este periodo.</p>';
    $ancho=760; $alto=240; $izq=64; $der=8; $arr=12; $abajo=28;
    $max=max(array_column($puntos,'valor'));
    $paso=escalaBonita($max); $tope=ceil($max/$paso)*$paso;
    $areaW=$ancho-$izq-$der; $areaH=$alto-$arr-$abajo;
    $slot=$areaW/$n; $barra=max(min($slot-2,28),2);
    $cadaEtq=max(1,(int)ceil($n/12));
    $svg='<svg class="grafico" viewBox="0 0 '.$ancho.' '.$alto.'" role="img" aria-label="'.e($titulo).'">';
    for ($v=0; $v<=$tope+0.001; $v+=$paso) {
        $y=$arr+$areaH-($v/$tope)*$areaH;
        $svg.='<line class="grid" x1="'.$izq.'" x2="'.($ancho-$der).'" y1="'.$y.'" y2="'.$y.'"/>'
            .'<text class="eje" x="'.($izq-8).'" y="'.($y+4).'" text-anchor="end">'.e(dineroCorto($v)).'</text>';
    }
    foreach ($puntos as $i=>$p) {
        $h=$tope>0 ? ($p['valor']/$tope)*$areaH : 0;
        $x=$izq+$i*$slot+($slot-$barra)/2; $y=$arr+$areaH-$h;
        $tip=e($p['etiqueta'].': '.dinero($p['valor']).(!empty($p['detalle']) ? ' · '.$p['detalle'] : ''));
        // Zona de hover de todo el alto para que sea fácil apuntar a barras pequeñas.
        $svg.='<g class="barra" data-tip="'.$tip.'"><rect class="hit" x="'.($izq+$i*$slot).'" y="'.$arr.'" width="'.$slot.'" height="'.$areaH.'"/>';
        if ($h>0) $svg.='<path class="bar" d="'.barraRedondeada($x,$y,$barra,$h).'"/>';
        $svg.='</g>';
        if ($i%$cadaEtq===0) $svg.='<text class="eje" x="'.($izq+$i*$slot+$slot/2).'" y="'.($alto-8).'" text-anchor="middle">'.e($p['etiqueta']).'</text>';
    }
    $svg.='<line class="base" x1="'.$izq.'" x2="'.($ancho-$der).'" y1="'.($arr+$areaH).'" y2="'.($arr+$areaH).'"/></svg>';
    return '<div class="grafico-wrap">'.$svg.'</div>';
}
// Barras horizontales en HTML para rankings. $filas: [['etiqueta','valor','texto'], ...]
function barrasHorizontales(array $filas): string {
    if (!$filas) return '<p class="empty">Sin datos en este periodo.</p>';
    $max=max(array_map(fn($f) => abs((float)$f['valor']), $filas)) ?: 1;
    $html='<div class="hbarras">';
    foreach ($filas as $f) {
        $pct=max(abs((float)$f['valor'])/$max*100,0.5);
        $html.='<div class="hbarra" data-tip="'.e($f['etiqueta'].': '.$f['texto']).'"><span class="hb-etq">'.e($f['etiqueta']).'</span>'
            .'<span class="hb-pista"><span class="hb-valor'.((float)$f['valor']<0 ? ' negativo-bg' : '').'" style="width:'.round($pct,2).'%"></span></span>'
            .'<span class="hb-num">'.e($f['texto']).'</span></div>';
    }
    return $html.'</div>';
}
function barraRedondeada(float $x, float $y, float $w, float $h): string {
    $r=min(4,$w/2,$h);
    return sprintf('M%.1f,%.1f V%.1f Q%.1f,%.1f %.1f,%.1f H%.1f Q%.1f,%.1f %.1f,%.1f V%.1f Z',
        $x,$y+$h, $y+$r, $x,$y, $x+$r,$y, $x+$w-$r, $x+$w,$y, $x+$w,$y+$r, $y+$h);
}
function escalaBonita(float $max): float {
    $bruto=$max/4; $mag=10**floor(log10(max($bruto,1)));
    foreach ([1,2,2.5,5,10] as $m) if ($bruto<=$m*$mag) return $m*$mag;
    return 10*$mag;
}
function dineroCorto(float $v): string {
    if ($v>=1000000) return '$'.rtrim(rtrim(number_format($v/1000000,1,',','.'),'0'),',').' M';
    if ($v>=1000) return '$'.number_format($v/1000,0,',','.').' mil';
    return '$'.number_format($v,0,',','.');
}
// Descarga CSV compatible con Excel en español (separador ';' y BOM UTF-8).
function exportarCsv(string $archivo, array $encabezados, array $filas): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_.-]/','_',$archivo).'"');
    $out=fopen('php://output','w');
    fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,$encabezados,';','"','');
    foreach ($filas as $f) {
        // Evita que Excel interprete textos que empiezan por = + - @ como fórmulas.
        fputcsv($out,array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/',$v) ? "'".$v : $v, $f),';','"','');
    }
    fclose($out);
    exit;
}
function numeroCsv(float|string|null $v): string { return $v===null ? '' : str_replace('.',',',(string)round((float)$v,2)); }
