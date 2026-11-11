<?php
// El detalle de las devoluciones se gestiona desde el modelo Devolucion (agregarDetalle, detalle, disponibles).
class DetalleDevolucion {
    public function __construct(private PDO $db) {}
}
