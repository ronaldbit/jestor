# JESTOR

JESTOR es un mini motor de base de datos que emula consultas SQL (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) sobre archivos JSON. Ideal para entornos sin bases de datos o desarrollo rápido sin dependencias.

## Características

- ✅ Consultas SQL simuladas sobre JSON
- ✅ Validación de tipos, longitudes, NOT NULL
- ✅ Soporte para `prepare` y `execute`
- ✅ Autoincremento y control de usuarios

## Ejemplo de uso

```php
require 'src/jestor.php';

$db = new JESTOR('localhost', 'carlos', '12345', 'ejemplo');

if ($db->connect_error) {
    die($db->connect_error);
}

$result = $db->query("SELECT * FROM usuarios");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
