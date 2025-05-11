<?php //test_update.php
try {
    require '../src/jestor.php'; // Incluye la clase
    $conn = new JESTOR('localhost', 'root', '', 'json_example');
    echo "Conexión exitosa.\n";
} catch (Exception $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit;
}

try {
    // Actualizar nombre y rol del usuario con ID 1
    $sql = "UPDATE Usuarios SET nombre = :nombre, rol = :rol WHERE idUsuario = :id";
    $stmt = $conn->prepare($sql);

    $datos = [
        ':nombre' => 'Juan Editado',
        ':rol' => 'admin',
        ':id' => 2
    ];

    // Ejecutar actualización
    $stmt->execute($datos);

    echo "Usuario actualizado correctamente.\n";
} catch (Exception $e) {
    echo "Error al actualizar: " . $e->getMessage();
}
