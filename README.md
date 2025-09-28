# JESTOR - Motor SQL basado en JSON

JESTOR es un motor de base de datos PHP ligero y sin dependencias que emula consultas SQL (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) sobre archivos JSON. Es una solución ideal para proyectos pequeños, prototipos o entornos donde no se dispone de una base de datos tradicional.

## Características

- **SQL sobre JSON**: Ejecuta consultas estándar tipo SQL sobre archivos JSON estructurados.
- **Acceso basado en credenciales**: Asegura tus datos con una autenticación sencilla de usuario y contraseña.
- **Validación de datos**: Aplica restricciones básicas como `NOT NULL`, tipos de datos (`int`, `string`) y longitud.
- **Autoincremento**: Maneja automáticamente los incrementos de clave primaria para nuevos registros.
- **Sentencias Preparadas**: Soporta `prepare` y `execute` para consultas más seguras y limpias.
- **Manejo de Errores Configurable**: Elige entre informes de error silenciosos, de advertencia o basados en excepciones.

## Instalación

1.  **Incluir la clase JESTOR**:
    Coloca los directorios `src` y `data` en tu proyecto. Incluye el archivo `jestor.php` en tu script de PHP:
    ```php
    require_once 'src/jestor.php';
    ```

2.  **Prepara tu archivo de datos JSON**:
    Crea un archivo `.json` dentro del directorio `data/`. Este archivo actuará como tu base de datos. La estructura debe ser la siguiente:

    ```json
    {
      "db": {
        "users": [
          {
            "user": "tu_usuario",
            "pass": "tu_contraseña"
          }
        ]
      },
      "data": {
        "nombre_de_tu_tabla": {
          "columns": {
            "id": { "type": "int", "length": 11, "auto_increment": true, "not_null": true },
            "nombre": { "type": "string", "length": 50, "not_null": true },
            "email": { "type": "string", "length": 100, "not_null": true }
          },
          "rows": [
            { "id": 1, "nombre": "John Doe", "email": "john.doe@example.com" }
          ]
        }
      }
    }
    ```

3.  **Conéctate a la base de datos**:
    Instancia la clase `JESTOR` con tu host, credenciales y el nombre de tu archivo JSON (sin la extensión).

    ```php
    $db = new JESTOR('localhost', 'tu_usuario', 'tu_contraseña', 'tu_archivo_de_base_de_datos');

    if ($db->connect_error) {
        die("Conexión fallida: " . $db->connect_error);
    }

    echo "¡Conexión exitosa!";
    ```

## Ejemplos de Uso

### Consulta SELECT

Obtén datos de una tabla usando una sentencia `SELECT`.

```php
$resultado = $db->query("SELECT id, nombre FROM nombre_de_tu_tabla");

// Obtener filas una por una
while ($fila = $resultado->fetch_assoc()) {
    echo "ID: " . $fila['id'] . " - Nombre: " . $fila['nombre'] . "<br>";
}

// O obtener todas las filas de una vez
$todas_las_filas = $resultado->fetchAll(MYSQLI_ASSOC);
print_r($todas_las_filas);
```

### Consulta INSERT con Sentencias Preparadas

Inserta un nuevo registro en una tabla. El uso de sentencias preparadas es el enfoque recomendado.

```php
$stmt = $db->prepare("INSERT INTO nombre_de_tu_tabla (nombre, email) VALUES (:nombre, :email)");

$stmt->bindValue(':nombre', 'Jane Doe');
$stmt->bindValue(':email', 'jane.doe@example.com');

if ($stmt->execute()) {
    echo "Nuevo registro creado con éxito. Último ID insertado: " . $db->lastInsertId();
} else {
    echo "Error al insertar el registro.";
}
```

### Consulta UPDATE

Actualiza un registro existente en la tabla.

```php
$actualizado = $db->query("UPDATE nombre_de_tu_tabla SET nombre = 'Johnathan Doe' WHERE id = 1");

if ($actualizado) {
    echo "Registro actualizado con éxito.";
} else {
    echo "Error al actualizar el registro o ninguna fila afectada.";
}
```

### Consulta DELETE

Elimina un registro de la tabla.

```php
$eliminado = $db->query("DELETE FROM nombre_de_tu_tabla WHERE id = 1");

if ($eliminado) {
    echo "Registro eliminado con éxito.";
} else {
    echo "Error al eliminar el registro o ninguna fila afectada.";
}
```

## Manejo de Errores

JESTOR te permite configurar cómo se manejan los errores, de forma similar a PDO.

```php
// Establecer el modo de error para lanzar excepciones (recomendado para desarrollo)
$db->setAttribute(JESTOR::ATTR_ERRMODE, JESTOR::ERRMODE_EXCEPTION);

try {
    // Esta consulta fallará porque la tabla no existe
    $db->query("SELECT * FROM tabla_inexistente");
} catch (JESTORException $e) {
    echo "Ocurrió un error: " . $e->getMessage();
}
```

Modos de error disponibles:
- `JESTOR::ERRMODE_SILENT`: (Por defecto) Almacena el mensaje de error, que se puede recuperar con `$db->getLastError()`.
- `JESTOR::ERRMODE_WARNING`: Emite una advertencia estándar de PHP `E_USER_WARNING`.
- `JESTOR::ERRMODE_EXCEPTION`: Lanza una `JESTORException`.