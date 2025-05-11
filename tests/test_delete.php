<?php
try {
    require '../src/jestor.php'; // Incluye la clase
    $conn = new JESTOR('localhost', 'root', '', 'json_example');
    echo "Conexión exitosa.\n";
} catch (Exception $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit;
}

try {
    // Eliminar usuario con ID 1
    $sql = "DELETE FROM Usuarios WHERE idUsuario = :id";
    $stmt = $conn->prepare($sql);

    $datos = [
        ':id' => 7
    ];

    // Ejecutar eliminación
    $stmt->execute($datos);

    echo "Usuario eliminado correctamente.\n";
} catch (Exception $e) {
    echo "Error al eliminar: " . $e->getMessage();
}
