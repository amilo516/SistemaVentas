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

// Ordena productos del más crítico al menos crítico (specs/002-panel-stock-bajo, FR-004). Función pura:
// no filtra (eso lo hace la consulta de stock bajo) ni modifica la entrada; devuelve una lista reindexada.
// 1) Agotados (stock <= 0) primero, del stock más bajo al más alto (entre agotados el mínimo no cuenta).
// 2) El resto por proporción stock ÷ mínimo ascendente, comparada multiplicando en cruz: sin dividir,
//    así un mínimo 0 nunca divide y 1/10 empata exactamente con 2/20.
// 3) Desempate por nombre sin distinguir mayúsculas (tildes y números como texto) y, al final, por
//    id_producto (orden de alta), para que el orden sea el mismo en cada carga.
function ordenarPorCriticidad(array $productos): array {
    // Stock y mínimo en milésimas enteras (DECIMAL(12,3)): la comparación cruzada es exacta en enteros.
    $milesimas = fn($v): int => (int)round((float)$v * 1000);
    $lista = array_values($productos);
    usort($lista, function (array $a, array $b) use ($milesimas): int {
        $sa = $milesimas($a['stock']); $sb = $milesimas($b['stock']);
        $agotadoA = $sa <= 0; $agotadoB = $sb <= 0;
        if ($agotadoA !== $agotadoB) return $agotadoA ? -1 : 1;
        $c = $agotadoA
            ? $sa <=> $sb
            : $sa * $milesimas($b['stock_minimo']) <=> $sb * $milesimas($a['stock_minimo']);
        if ($c !== 0) return $c;
        $c = strcmp(mb_strtolower((string)$a['nombre']), mb_strtolower((string)$b['nombre']));
        return $c !== 0 ? $c : (int)$a['id_producto'] <=> (int)$b['id_producto'];
    });
    return $lista;
}
