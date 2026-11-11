<?php
// Número consecutivo a partir del id de la venta: FAC-000001.
function generarNumeroFactura(PDO $db, int $idVenta): string {
    $s=$db->prepare("SELECT valor FROM configuracion WHERE clave='prefijo_factura' LIMIT 1");
    $s->execute(); $prefijo=trim((string)($s->fetchColumn() ?: 'FAC'));
    return $prefijo.'-'.str_pad((string)$idVenta, 6, '0', STR_PAD_LEFT);
}
// Totales de una venta. $items: filas con cantidad y precio_unitario. $impuestoPct: porcentaje (ej. 19).
function calcularTotales(array $items, float $descuento, float $impuestoPct): array {
    $subtotal=0.0;
    foreach ($items as $i) $subtotal+=round((float)$i['cantidad']*(float)$i['precio_unitario'], 2);
    $descuento=min(max($descuento,0), $subtotal);
    $impuesto=round(($subtotal-$descuento)*$impuestoPct/100, 2);
    return ['subtotal'=>round($subtotal,2),'descuento'=>round($descuento,2),'impuesto'=>$impuesto,'impuesto_pct'=>$impuestoPct,'total'=>round($subtotal-$descuento+$impuesto,2)];
}
function cantidad(float|string $valor): string {
    $v=(float)$valor;
    return fmod($v,1.0)==0.0 ? number_format($v,0,',','.') : rtrim(rtrim(number_format($v,3,',','.'),'0'),',');
}
