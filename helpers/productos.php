<?php
// Unidades de medida: [etiqueta, permite cantidades decimales].
function unidadesMedida(): array {
    return [
        'unidad' => ['Unidad', false], 'paquete' => ['Paquete', false], 'caja' => ['Caja', false],
        'kg' => ['Kilogramo (kg)', true], 'g' => ['Gramo (g)', true],
        'litro' => ['Litro (L)', true], 'ml' => ['Mililitro (ml)', true], 'metro' => ['Metro (m)', true],
    ];
}
function permiteDecimales(string $unidad): bool { return unidadesMedida()[$unidad][1] ?? false; }
function urlImagenProducto(?string $imagen): ?string {
    return $imagen ? APP_URL . '/assets/img/productos/' . rawurlencode($imagen) : null;
}
