<?php
declare(strict_types=1);
// Regla del descuento máximo por rol (contracts/regla-descuento.md).
// Funciones puras: no usan base de datos, sesión ni E/S, para poder probarlas sin MySQL.
// Todo el cálculo se hace en enteros (centavos y centésimas de punto) para evitar errores de coma flotante.

// La venta se rechaza porque el descuento supera el máximo del rol. Al ser RuntimeException,
// las vistas la muestran como cualquier otro error de la venta.
final class DescuentoExcedidoException extends RuntimeException {
    public function __construct(
        public readonly float $subtotal,
        public readonly float $descuento,
        public readonly float $porcentaje,
        public readonly float $maximo,
        public readonly string $rol = '',
    ) {
        parent::__construct('El descuento (' . formatearPorcentaje($porcentaje) . ' %) supera el máximo permitido para tu rol ('
            . formatearPorcentaje($maximo) . ' %).');
    }
}

// Porcentaje del descuento sobre el subtotal, redondeado HACIA ARRIBA a 2 decimales. Solo para mostrar:
// un descuento rechazado nunca aparece igual al límite (10,004 % → 10,01).
function porcentajeDescuento(float $subtotal, float $descuento): float {
    $s = (int)round($subtotal * 100);
    $d = (int)round($descuento * 100);
    if ($s <= 0 || $d <= 0) return 0.0;
    return intdiv($d * 10000 + $s - 1, $s) / 100;
}

// Lanza DescuentoExcedidoException si el descuento supera $maximoPct del subtotal.
// $maximoPct null = sin límite (Administrador). El límite es inclusivo.
function validarDescuentoMaximo(float $subtotal, float $descuento, ?float $maximoPct, string $rol = ''): void {
    if ($maximoPct === null) return;
    $d = (int)round($descuento * 100);
    if ($d <= 0) return;
    $s = (int)round($subtotal * 100);
    // Un descuento sobre subtotal 0 ya lo rechaza la regla "no mayor que el subtotal" (FR-015).
    if ($s <= 0) return;
    $m = (int)round($maximoPct * 100);
    // descuento/subtotal > máximo/100  ⇔  d × 10000 > s × m  (en centavos y centésimas de punto)
    if ($d * 10000 > $s * $m) {
        throw new DescuentoExcedidoException($subtotal, $descuento, porcentajeDescuento($subtotal, $descuento), $maximoPct, $rol);
    }
}

// 15 → "15", 7.5 → "7,5", 10.01 → "10,01". Coma decimal, sin ceros sobrantes ni separador de miles.
function formatearPorcentaje(float $valor): string {
    return rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
}
